<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Project;
use App\Models\Subtask;
use App\Models\TimeLog;
use App\Models\User;
use App\Support\Formato;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_lider_e_operador_nao_abrem_relatorio_nem_csv(): void
    {
        $this->actingAsRole(Role::Leader);
        $this->get('/relatorios')->assertForbidden();
        $this->get('/relatorios/horas.csv')->assertForbidden();
        $this->get('/relatorios/projetos.csv')->assertForbidden();

        $this->actingAsRole(Role::Operator);
        $this->get('/relatorios')->assertForbidden();
        $this->get('/relatorios/horas.csv')->assertForbidden();
    }

    public function test_dia_23h30_em_brasilia_cai_nessa_data_local(): void
    {
        $this->actingAsRole(Role::Admin);
        $operator = User::factory()->create(['name' => 'Noite Clara']);
        $project = Project::factory()->create(['name' => 'Turno da noite']);
        $subtask = Subtask::factory()->create([
            'project_id' => $project->id,
            'name' => 'Fechamento',
        ]);

        TimeLog::factory()->create([
            'user_id' => $operator->id,
            'subtask_id' => $subtask->id,
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
            'started_at' => Carbon::parse('2026-09-01 23:30', Formato::TZ)->utc(),
            'ended_at' => Carbon::parse('2026-09-02 00:30', Formato::TZ)->utc(),
        ]);

        $this->get('/relatorios?from=2026-09-01&to=2026-09-01')
            ->assertOk()
            ->assertSee('Noite Clara')
            ->assertSee('Pontos em andamento não entram na soma.');

        $this->get('/relatorios?from=2026-09-02&to=2026-09-02')
            ->assertOk()
            ->assertDontSee('Noite Clara');
    }

    public function test_csv_tem_bom_ponto_e_virgula_e_omite_ponto_aberto(): void
    {
        $this->actingAsRole(Role::Admin);
        $operator = User::factory()->create(['name' => 'Dia Inteiro']);
        $openUser = User::factory()->create(['name' => 'Ainda Aberto']);
        $subtask = Subtask::factory()->create();

        TimeLog::factory()->create([
            'user_id' => $operator->id,
            'subtask_id' => $subtask->id,
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
            'started_at' => Carbon::parse('2026-09-10 08:00', Formato::TZ)->utc(),
            'ended_at' => Carbon::parse('2026-09-10 09:30', Formato::TZ)->utc(),
        ]);
        TimeLog::factory()->open()->create([
            'user_id' => $openUser->id,
            'subtask_id' => $subtask->id,
            'created_by' => $openUser->id,
            'updated_by' => $openUser->id,
            'started_at' => Carbon::parse('2026-09-10 14:00', Formato::TZ)->utc(),
        ]);

        $csv = $this->get('/relatorios/horas.csv?from=2026-09-10&to=2026-09-10')->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString(';', $csv);
        $this->assertStringContainsString('1,50', $csv);
        $this->assertStringContainsString('Dia Inteiro', $csv);
        $this->assertStringNotContainsString('Ainda Aberto', $csv);
        $this->assertStringContainsString('10/09/2026', $csv);
    }
}
