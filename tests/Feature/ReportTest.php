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

    public function test_lider_abre_relatorio_e_operador_nao(): void
    {
        $this->actingAsRole(Role::Leader);
        $this->get('/relatorios')
            ->assertOk()
            ->assertSee('Exportar tudo')
            ->assertDontSee('Exportar horas')
            ->assertDontSee('Exportar projetos')
            ->assertSee('Sem datas, o relatório cobre todo o histórico.');
        $this->get('/relatorios/pessoas.csv')->assertOk();
        $this->get('/relatorios/projetos.csv')->assertOk();

        $this->actingAsRole(Role::Operator);
        $this->get('/relatorios')->assertForbidden();
        $this->get('/relatorios/pessoas.csv')->assertForbidden();
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

        $this->get('/relatorios')
            ->assertOk()
            ->assertSee('Noite Clara')
            ->assertSee('Pontos em andamento não entram na soma.');

        $noDia = $this->get('/relatorios/pessoas.csv?from=2026-09-01&to=2026-09-01')->streamedContent();
        $this->assertStringContainsString('Noite Clara', $noDia);

        $noDiaSeguinte = $this->get('/relatorios/pessoas.csv?from=2026-09-02&to=2026-09-02')->streamedContent();
        $this->assertStringNotContainsString('Noite Clara', $noDiaSeguinte);
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

        $csv = $this->get('/relatorios/pessoas.csv?from=2026-09-10&to=2026-09-10&user_id='.$operator->id)->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString(';', $csv);
        $this->assertStringContainsString('1,50', $csv);
        $this->assertStringContainsString('Dia Inteiro', $csv);
        $this->assertStringNotContainsString('Ainda Aberto', $csv);

        $projetos = $this->get('/relatorios/projetos.csv?from=2026-09-10&to=2026-09-10&project_id='.$subtask->project_id)->streamedContent();
        $this->assertStringContainsString('1,50', $projetos);
        $this->assertStringNotContainsString('Ainda Aberto', $projetos);
    }

    public function test_lightbox_nomeia_o_arquivo_pelo_projeto_ou_pela_pessoa(): void
    {
        $this->actingAsRole(Role::Admin);
        $project = Project::factory()->create(['name' => 'Molde/tampa']);
        $person = User::factory()->create(['name' => 'João da Silva']);

        $projeto = $this->get('/relatorios/projetos.csv?project_id='.$project->id);
        $pessoa = $this->get('/relatorios/pessoas.csv?user_id='.$person->id);
        $tudo = $this->get('/relatorios/projetos.csv');

        $this->assertStringContainsString('Molde tampa.csv', urldecode((string) $projeto->headers->get('content-disposition')));
        $this->assertStringContainsString('João da Silva.csv', urldecode((string) $pessoa->headers->get('content-disposition')));
        $this->assertStringContainsString('projetos.csv', (string) $tudo->headers->get('content-disposition'));
    }
}
