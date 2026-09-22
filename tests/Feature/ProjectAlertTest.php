<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Project;
use App\Models\Subtask;
use App\Models\TimeLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_liga_alarme_da_subtarefa_e_ve_no_inicio(): void
    {
        $admin = $this->actingAsRole(Role::Admin);
        $project = Project::factory()->create(['name' => 'Molde alerta']);
        $hit = Subtask::factory()->create([
            'project_id' => $project->id,
            'name' => 'Usinagem',
            'planned_minutes' => 100,
        ]);
        $quiet = Subtask::factory()->create([
            'project_id' => $project->id,
            'name' => 'Acabamento',
            'planned_minutes' => 100,
            'alert_percentage' => 80,
            'alert_enabled' => false,
        ]);
        $this->logMinutes($admin, $hit, 80);
        $this->logMinutes($admin, $quiet, 80);

        $this->put(route('subtarefas.update', $hit), [
            'name' => 'Usinagem',
            'kind' => 'internal',
            'planned_hours' => '1,67',
            'budget' => '1.500,00',
            'realized' => '400,00',
            'alert_enabled' => '1',
            'alert_percentage' => 80,
        ])->assertRedirect(route('projetos.show', $project));

        $hit->refresh();
        $this->assertSame(100, $hit->planned_minutes);
        $this->assertSame(150000, $hit->budget_cents);
        $this->assertSame(40000, $hit->realized_cents);
        $this->assertTrue($hit->alert_enabled);

        $this->get(route('projetos.show', $project))
            ->assertOk()
            ->assertSee('80%')
            ->assertSee('Atingido')
            ->assertSee('R$ 1.500,00')
            ->assertSee('R$ 400,00');

        $this->get(route('inicio'))
            ->assertOk()
            ->assertSee('Alertas atingidos')
            ->assertSee('Molde alerta — Usinagem · 80%')
            ->assertDontSee('Acabamento · 80%');
    }

    public function test_alarme_desligado_ponto_aberto_e_sem_meta_nao_atingem(): void
    {
        $admin = $this->actingAsRole(Role::Admin);
        $project = Project::factory()->create(['name' => 'Molde']);
        $off = Subtask::factory()->create([
            'project_id' => $project->id,
            'name' => 'Desligado',
            'planned_minutes' => 100,
            'alert_percentage' => 50,
            'alert_enabled' => false,
        ]);
        $this->logMinutes($admin, $off, 80);

        $openOnly = Subtask::factory()->create([
            'project_id' => $project->id,
            'name' => 'So aberto',
            'planned_minutes' => 100,
            'alert_percentage' => 1,
            'alert_enabled' => true,
        ]);
        TimeLog::factory()->open()->create([
            'user_id' => $admin->id,
            'subtask_id' => $openOnly->id,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'started_at' => now()->subHours(5),
        ]);

        Subtask::factory()->create([
            'project_id' => $project->id,
            'name' => 'Sem meta',
            'planned_minutes' => null,
            'alert_percentage' => 1,
            'alert_enabled' => true,
        ]);

        $this->get(route('inicio'))
            ->assertOk()
            ->assertSee('Nenhum alerta atingido.');
    }

    public function test_lider_nao_ve_o_painel_e_operador_nao_grava_alarme(): void
    {
        $project = Project::factory()->create();
        $leader = User::factory()->leader()->create();
        $operator = User::factory()->operator()->create();

        $this->actingAs($leader)
            ->get(route('inicio'))
            ->assertOk()
            ->assertDontSee('Alertas atingidos');

        $this->actingAs($operator)
            ->post(route('subtarefas.store', $project), [
                'name' => 'Emergencia',
                'planned_hours' => '8',
                'budget' => '100,00',
                'realized' => '50,00',
                'alert_enabled' => '1',
                'alert_percentage' => 80,
            ])->assertRedirect(route('projetos.ponto', $project));

        $subtask = Subtask::query()->where('name', 'Emergencia')->first();
        $this->assertNotNull($subtask);
        $this->assertNull($subtask->planned_minutes);
        $this->assertNull($subtask->budget_cents);
        $this->assertNull($subtask->realized_cents);
        $this->assertFalse($subtask->alert_enabled);
        $this->assertNull($subtask->alert_percentage);

        $this->actingAs($operator)
            ->get(route('inicio'))
            ->assertDontSee('Alertas atingidos');
    }

    public function test_alarme_ligado_sem_porcentagem_e_recusado(): void
    {
        $this->actingAsRole(Role::Admin);
        $subtask = Subtask::factory()->create(['name' => 'Usinagem']);

        $this->from(route('subtarefas.edit', $subtask))
            ->put(route('subtarefas.update', $subtask), [
                'name' => 'Usinagem',
                'kind' => 'internal',
                'alert_enabled' => '1',
            ])
            ->assertRedirect(route('subtarefas.edit', $subtask))
            ->assertSessionHasErrors('alert_percentage');
    }

    public function test_copia_do_projeto_leva_plano_e_deixa_realizado(): void
    {
        $user = $this->actingAsRole(Role::Leader);
        $project = Project::factory()->create([
            'name' => 'Casa 01',
            'notes' => 'Padrão',
            'created_by' => $user->id,
        ]);
        $subtask = Subtask::factory()->create([
            'project_id' => $project->id,
            'name' => 'Pintura',
            'budget_cents' => 180000,
            'planned_minutes' => 120,
            'realized_cents' => 50000,
            'alert_percentage' => 80,
            'alert_enabled' => true,
            'created_by' => $user->id,
        ]);
        $this->logMinutes($user, $subtask, 30);

        $this->post(route('projetos.copy.store', $project), [
            'name' => 'Casa 02',
        ])->assertRedirect();

        $copy = Project::query()->where('name', 'Casa 02')->first();
        $this->assertNotNull($copy);
        $this->assertTrue($copy->isOpen());
        $this->assertSame('Padrão', $copy->notes);
        $this->assertSame(0, $copy->timeLogs()->count());

        $line = $copy->subtasks()->first();
        $this->assertSame('Pintura', $line->name);
        $this->assertSame(180000, $line->budget_cents);
        $this->assertSame(120, $line->planned_minutes);
        $this->assertNull($line->realized_cents);
        $this->assertSame(80, $line->alert_percentage);
        $this->assertTrue($line->alert_enabled);
        $this->assertSame(0, $line->timeLogs()->count());
    }

    private function logMinutes(User $user, Subtask $subtask, int $minutes): void
    {
        $start = now()->subDays(2)->startOfHour();

        TimeLog::factory()->create([
            'user_id' => $user->id,
            'subtask_id' => $subtask->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'started_at' => $start,
            'ended_at' => $start->copy()->addMinutes($minutes),
        ]);
    }
}
