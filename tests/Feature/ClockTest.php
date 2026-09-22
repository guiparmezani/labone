<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\SubtaskKind;
use App\Models\Project;
use App\Models\Subtask;
use App\Models\TimeLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClockTest extends TestCase
{
    use RefreshDatabase;

    public function test_operador_nao_ve_orcamento_nem_horas_na_tela_de_ponto(): void
    {
        $this->actingAsRole(Role::Operator);
        $project = Project::factory()->create([
            'name' => 'Molde visível',
            'budget_cents' => 987654,
            'planned_minutes' => 123,
            'notes' => 'segredo R$ 9.876,54',
        ]);
        Subtask::factory()->create([
            'project_id' => $project->id,
            'name' => 'Polimento',
            'kind' => SubtaskKind::Internal,
        ]);
        Subtask::factory()->thirdParty()->create([
            'project_id' => $project->id,
            'name' => 'Fornecedor oculto',
        ]);

        $this->get('/projetos/'.$project->id.'/ponto')
            ->assertOk()
            ->assertSee('Molde visível')
            ->assertSee('Polimento')
            ->assertDontSee('R$ 9.876,54')
            ->assertDontSee('Fornecedor oculto')
            ->assertDontSee('2h 03min')
            ->assertDontSee('987654');
    }

    public function test_segundo_inicio_falha_e_deixa_um_ponto_aberto(): void
    {
        $operator = $this->actingAsRole(Role::Operator);
        $first = Subtask::factory()->create();
        $second = Subtask::factory()->create();

        $this->post('/ponto/iniciar', ['subtask_id' => $first->id])->assertRedirect();
        $this->post('/ponto/iniciar', ['subtask_id' => $second->id])
            ->assertSessionHasErrors('subtask_id');

        $this->assertSame(1, TimeLog::query()->where('user_id', $operator->id)->whereNull('ended_at')->count());
    }

    public function test_terceiros_e_projeto_encerrado_recusam_ponto(): void
    {
        $this->actingAsRole(Role::Operator);
        $third = Subtask::factory()->thirdParty()->create();
        $closed = Subtask::factory()->create([
            'project_id' => Project::factory()->closed(),
        ]);

        $this->post('/ponto/iniciar', ['subtask_id' => $third->id])->assertSessionHasErrors('subtask_id');
        $this->post('/ponto/iniciar', ['subtask_id' => $closed->id])->assertSessionHasErrors('subtask_id');
        $this->assertSame(0, TimeLog::query()->count());
    }

    public function test_parar_grava_o_fim_e_sair_nao_para(): void
    {
        $operator = $this->actingAsRole(Role::Operator);
        $subtask = Subtask::factory()->create();

        $this->post('/ponto/iniciar', ['subtask_id' => $subtask->id]);
        $this->post('/sair')->assertRedirect('/entrar');

        $this->assertSame(1, TimeLog::query()->where('user_id', $operator->id)->whereNull('ended_at')->count());

        $this->actingAs($operator);
        $this->post('/ponto/parar')->assertRedirect();
        $this->assertSame(0, TimeLog::query()->whereNull('ended_at')->count());
    }

    public function test_desativar_usuario_encerra_o_ponto_aberto(): void
    {
        $admin = $this->actingAsRole(Role::Admin);
        $operator = User::factory()->operator()->create();
        $subtask = Subtask::factory()->create();

        TimeLog::factory()->open()->create([
            'user_id' => $operator->id,
            'subtask_id' => $subtask->id,
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
            'started_at' => now()->subMinutes(10),
        ]);

        $this->put('/usuarios/'.$operator->id, [
            'name' => $operator->name,
            'email' => $operator->email,
            'role' => 'operator',
            'active' => '0',
        ])->assertRedirect('/usuarios');

        $log = TimeLog::query()->first();
        $this->assertNotNull($log->ended_at);
        $this->assertSame($admin->id, $log->updated_by);
    }
}
