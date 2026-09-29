<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Project;
use App\Models\Subtask;
use App\Models\TimeLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_operador_ve_projetos_e_o_proprio_ponto_sem_totais(): void
    {
        $operator = $this->actingAsRole(Role::Operator);
        $other = User::factory()->operator()->create(['name' => 'Outra Pessoa']);
        $project = Project::factory()->create([
            'name' => 'Molde visível',
            'budget_cents' => 987654,
            'planned_minutes' => 123,
        ]);
        $subtask = Subtask::factory()->create([
            'project_id' => $project->id,
            'name' => 'Polimento',
        ]);
        TimeLog::factory()->open()->create([
            'user_id' => $operator->id,
            'subtask_id' => $subtask->id,
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
        ]);
        TimeLog::factory()->open()->create([
            'user_id' => $other->id,
            'subtask_id' => Subtask::factory()->create()->id,
            'created_by' => $other->id,
            'updated_by' => $other->id,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Molde visível')
            ->assertSee('Desde')
            ->assertSee('Parar')
            ->assertSee('data-started-at', false)
            ->assertSee('elapsed', false)
            ->assertDontSee('Outra Pessoa')
            ->assertDontSee('R$ 9.876,54')
            ->assertDontSee('2h 03min');
    }

    public function test_lider_ve_orcamento_e_todos_os_pontos_abertos(): void
    {
        $this->actingAsRole(Role::Leader);
        Project::factory()->create([
            'name' => 'Molde visível',
            'budget_cents' => 987654,
        ]);
        $operator = User::factory()->create(['name' => 'Joana Torno']);
        TimeLog::factory()->open()->create([
            'user_id' => $operator->id,
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('R$ 9.876,54')
            ->assertSee('Joana Torno')
            ->assertSee('Iniciar ponto')
            ->assertSee('data-started-at')
            ->assertSee('Parar')
            ->assertDontSee('Trocar');
    }

    public function test_lider_inicia_pelo_mesmo_ponto_e_para_o_proprio_relogio(): void
    {
        $leader = $this->actingAsRole(Role::Leader);
        $project = Project::factory()->create(['name' => 'Molde do lider']);
        $subtask = Subtask::factory()->create([
            'project_id' => $project->id,
            'name' => 'Bancada',
        ]);

        $this->get(route('projetos.show', $project))
            ->assertOk()
            ->assertSee(route('projetos.ponto', $project), false);

        $this->get(route('projetos.ponto', $project))
            ->assertOk()
            ->assertSee('Bancada')
            ->assertSee('Iniciar');

        $this->get('/')->assertOk()->assertSee('Iniciar ponto');

        $this->post('/ponto/iniciar', ['subtask_id' => $subtask->id])->assertRedirect();

        $this->get('/')
            ->assertOk()
            ->assertSee('Bancada')
            ->assertSee('Parar');

        $this->assertSame(1, TimeLog::query()->where('user_id', $leader->id)->whereNull('ended_at')->count());
    }
}
