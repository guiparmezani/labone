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

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_operador_nao_abre_a_lista_de_projetos(): void
    {
        $this->actingAsRole(Role::Operator);

        $this->get('/projetos')->assertRedirect('/');
        $this->post('/projetos', [
            'name' => 'Molde',
            'budget' => '10,00',
            'planned_hours' => '1',
        ])->assertForbidden();
    }

    public function test_lider_cria_projeto_com_reais_e_horas(): void
    {
        $leader = $this->actingAsRole(Role::Leader);

        $this->post('/projetos', [
            'name' => 'Molde da tampa',
            'notes' => 'Cliente interno',
            'budget' => '1.234,56',
            'planned_hours' => '1,5',
        ])->assertRedirect();

        $project = Project::query()->first();
        $this->assertNotNull($project);
        $this->assertSame(123456, $project->budget_cents);
        $this->assertSame(90, $project->planned_minutes);
        $this->assertSame($leader->id, $project->created_by);
        $this->get('/projetos')->assertSee('R$ 1.234,56')->assertSee('1h 30min');
    }

    public function test_encerra_e_reabre_sem_apagar_historico(): void
    {
        $this->actingAsRole(Role::Admin);
        $project = Project::factory()->create();

        $this->post('/projetos/'.$project->id.'/encerrar')->assertRedirect();
        $this->assertNotNull($project->refresh()->closed_at);

        $this->post('/projetos/'.$project->id.'/reabrir')->assertRedirect();
        $project->refresh();
        $this->assertNull($project->closed_at);
        $this->assertTrue($project->isOpen());
    }

    public function test_apaga_projeto_vazio_e_recusa_quando_ha_lancamento(): void
    {
        $user = $this->actingAsRole(Role::Leader);
        $empty = Project::factory()->create(['created_by' => $user->id]);
        $busy = Project::factory()->create(['created_by' => $user->id]);
        $subtask = Subtask::factory()->create([
            'project_id' => $busy->id,
            'created_by' => $user->id,
        ]);
        TimeLog::factory()->create([
            'user_id' => $user->id,
            'subtask_id' => $subtask->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->delete('/projetos/'.$busy->id)
            ->assertRedirect()
            ->assertSessionHasErrors('project');
        $this->assertModelExists($busy);

        $this->delete('/projetos/'.$empty->id)->assertRedirect('/projetos');
        $this->assertModelMissing($empty);
    }

    public function test_operador_so_cria_subtarefa_interna(): void
    {
        $operator = $this->actingAsRole(Role::Operator);
        $project = Project::factory()->create();

        $this->post('/projetos/'.$project->id.'/subtarefas', [
            'name' => 'Acabamento',
            'kind' => 'third_party',
            'budget' => '500,00',
        ])->assertRedirect('/');

        $subtask = Subtask::query()->first();
        $this->assertSame('Acabamento', $subtask->name);
        $this->assertSame(SubtaskKind::Internal, $subtask->kind);
        $this->assertNull($subtask->budget_cents);
        $this->assertSame($operator->id, $subtask->created_by);
    }

    public function test_lider_cria_subtarefa_de_terceiros_e_projeto_encerrado_recusa(): void
    {
        $leader = $this->actingAsRole(Role::Leader);
        $project = Project::factory()->create(['created_by' => $leader->id]);

        $this->post('/projetos/'.$project->id.'/subtarefas', [
            'name' => 'Usinagem externa',
            'kind' => 'third_party',
            'budget' => '800,00',
        ])->assertRedirect('/projetos/'.$project->id);

        $this->assertDatabaseHas('subtasks', [
            'name' => 'Usinagem externa',
            'kind' => 'third_party',
            'budget_cents' => 80000,
        ]);

        $project->update(['status' => 'closed', 'closed_at' => now()]);

        $this->post('/projetos/'.$project->id.'/subtarefas', [
            'name' => 'Outra',
            'kind' => 'internal',
        ])->assertSessionHasErrors('name');
    }

    public function test_nao_apaga_subtarefa_com_lancamento(): void
    {
        $user = $this->actingAsRole(Role::Admin);
        $subtask = Subtask::factory()->create(['created_by' => $user->id]);
        TimeLog::factory()->create([
            'user_id' => $user->id,
            'subtask_id' => $subtask->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->delete('/subtarefas/'.$subtask->id)->assertSessionHasErrors('subtask');
        $this->assertModelExists($subtask);
    }
}
