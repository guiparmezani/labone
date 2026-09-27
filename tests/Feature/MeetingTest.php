<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Project;
use App\Models\Subtask;
use App\Models\TimeLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingTest extends TestCase
{
    use RefreshDatabase;

    public function test_horas_previstas_do_projeto_somam_as_subtarefas(): void
    {
        $this->actingAsRole(Role::Leader);
        $project = Project::factory()->create([
            'name' => 'Molde soma',
            'planned_minutes' => 60,
        ]);
        Subtask::factory()->create([
            'project_id' => $project->id,
            'planned_minutes' => 30,
        ]);

        $this->get(route('projetos.show', $project))
            ->assertOk()
            ->assertSee('1h 30min previstas')
            ->assertSee('1h 00min do projeto + 0h 30min das subtarefas');
    }

    public function test_lider_nao_altera_horas_previstas_nem_orcamento_de_terceiros(): void
    {
        $this->actingAsRole(Role::Leader);
        $project = Project::factory()->create([
            'name' => 'Molde trava',
            'planned_minutes' => 120,
            'budget_cents' => 100000,
        ]);
        $third = Subtask::factory()->thirdParty()->create([
            'project_id' => $project->id,
            'name' => 'Pintura externa',
            'budget_cents' => 80000,
            'planned_minutes' => 90,
        ]);

        $this->put(route('projetos.update', $project), [
            'name' => 'Molde trava',
            'budget' => '1.000,00',
            'planned_hours' => '9',
        ])->assertRedirect();

        $this->put(route('subtarefas.update', $third), [
            'name' => 'Pintura externa',
            'kind' => 'third_party',
            'budget' => '1,00',
            'planned_hours' => '1',
        ])->assertRedirect();

        $project->refresh();
        $third->refresh();
        $this->assertSame(120, $project->planned_minutes);
        $this->assertSame(80000, $third->budget_cents);
        $this->assertSame(90, $third->planned_minutes);
    }

    public function test_troca_de_atividade_deixa_o_tempo_corrido_na_subtarefa_anterior(): void
    {
        $this->actingAsRole(Role::Leader);
        $operator = User::factory()->operator()->create();
        $project = Project::factory()->create();
        $current = Subtask::factory()->create(['project_id' => $project->id, 'name' => 'Usinagem']);
        $next = Subtask::factory()->create(['project_id' => $project->id, 'name' => 'Acabamento']);
        $open = TimeLog::factory()->open()->create([
            'user_id' => $operator->id,
            'subtask_id' => $current->id,
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
            'started_at' => now()->subMinutes(25),
        ]);

        $this->post(route('ponto.switch', $open), [
            'subtask_id' => $next->id,
        ])->assertRedirect();

        $open->refresh();
        $this->assertNotNull($open->ended_at);
        $this->assertSame($current->id, $open->subtask_id);
        $this->assertGreaterThanOrEqual(24, $open->started_at->diffInMinutes($open->ended_at));

        $fresh = TimeLog::query()->whereNull('ended_at')->first();
        $this->assertNotNull($fresh);
        $this->assertSame($operator->id, $fresh->user_id);
        $this->assertSame($next->id, $fresh->subtask_id);
    }

    public function test_revisao_aponta_equipe_terceira_e_a_copia_reaponta(): void
    {
        $user = $this->actingAsRole(Role::Leader);
        $project = Project::factory()->create(['name' => 'Casa 10', 'created_by' => $user->id]);
        $crew = Subtask::factory()->thirdParty()->create([
            'project_id' => $project->id,
            'name' => 'Forno externo',
            'created_by' => $user->id,
        ]);
        $line = Subtask::factory()->create([
            'project_id' => $project->id,
            'name' => 'Retrabalho',
            'created_by' => $user->id,
        ]);

        $this->put(route('subtarefas.update', $line), [
            'name' => 'Retrabalho',
            'kind' => 'internal',
            'is_revision' => '1',
            'revision_notes' => 'Peça veio fora de medida',
            'revision_of_subtask_id' => $crew->id,
        ])->assertRedirect();

        $line->refresh();
        $this->assertTrue($line->is_revision);
        $this->assertSame('Peça veio fora de medida', $line->revision_notes);
        $this->assertSame($crew->id, $line->revision_of_subtask_id);

        $this->post(route('projetos.copy.store', $project), ['name' => 'Casa 11'])->assertRedirect();

        $copy = Project::query()->where('name', 'Casa 11')->first();
        $copiedLine = $copy->subtasks()->where('name', 'Retrabalho')->first();
        $copiedCrew = $copy->subtasks()->where('name', 'Forno externo')->first();
        $this->assertTrue($copiedLine->is_revision);
        $this->assertSame($copiedCrew->id, $copiedLine->revision_of_subtask_id);
        $this->assertNotSame($crew->id, $copiedLine->revision_of_subtask_id);
    }

    public function test_relatorio_do_projeto_mostra_o_rodape_e_o_operador_nao_abre(): void
    {
        $this->actingAsRole(Role::Admin);
        $project = Project::factory()->create([
            'name' => 'Molde folha',
            'planned_minutes' => 60,
            'budget_cents' => 100000,
        ]);
        Subtask::factory()->create([
            'project_id' => $project->id,
            'name' => 'Usinagem',
            'planned_minutes' => 30,
            'budget_cents' => 20000,
            'realized_cents' => 5000,
        ]);
        TimeLog::factory()->open()->create([
            'subtask_id' => $project->subtasks()->first()->id,
            'started_at' => now()->subMinutes(40),
        ]);

        Subtask::factory()->create([
            'project_id' => $project->id,
            'name' => 'Retrabalho da folha',
            'is_revision' => true,
            'revision_notes' => 'Medida errada',
        ]);

        $html = $this->get(route('projetos.relatorio', $project))
            ->assertOk()
            ->assertSee('Exportar')
            ->assertDontSee('Imprimir')
            ->assertDontSee('window.print')
            ->assertSee('Horas previstas')
            ->assertSee('1h 30min')
            ->assertSee('Inclui o ponto em andamento')
            ->assertSee('Valor previsto')
            ->assertSee('R$ 1.200,00')
            ->assertSee('Valor realizado')
            ->assertSee('R$ 50,00')
            ->getContent();

        $arquivo = $this->get(route('projetos.relatorio.csv', $project));
        $csv = $arquivo->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString(';', $csv);
        $this->assertStringContainsString('Molde folha.csv', urldecode((string) $arquivo->headers->get('content-disposition')));
        $this->assertStringContainsString('Usinagem', $csv);
        $this->assertStringContainsString('Retrabalho da folha', $csv);
        $this->assertStringContainsString('Medida errada', $csv);
        $this->assertStringContainsString('1,50', $csv);

        $interna = str($html)->between('<h2>Equipe interna</h2>', '<h2>Equipe terceira</h2>')->value();
        $this->assertStringContainsString('Usinagem', $interna);
        $this->assertStringNotContainsString('Retrabalho da folha', $interna);
        $this->assertStringContainsString('Retrabalho da folha', str($html)->after('<h2>Revisões</h2>')->value());

        $this->actingAsRole(Role::Operator);
        $this->get(route('projetos.relatorio', $project))->assertForbidden();
        $this->get(route('projetos.relatorio.csv', $project))->assertForbidden();
    }

    public function test_operador_nao_troca_atividade(): void
    {
        $operator = $this->actingAsRole(Role::Operator);
        $open = TimeLog::factory()->open()->create([
            'user_id' => $operator->id,
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
        ]);

        $this->post(route('ponto.switch', $open), [
            'subtask_id' => Subtask::factory()->create()->id,
        ])->assertForbidden();
    }
}
