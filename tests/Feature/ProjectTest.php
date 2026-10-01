<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\SubtaskKind;
use App\Models\Project;
use App\Models\Subtask;
use App\Models\TimeLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

        $this->get('/projetos/novo')
            ->assertOk()
            ->assertSee('name="planned_hours"', false)
            ->assertDontSee('Só o administrador altera', false);

        $this->get('/projetos')
            ->assertOk()
            ->assertSee('data-abrir="novo-projeto"', false)
            ->assertSee('id="novo-projeto"', false);

        $this->followingRedirects()->from('/projetos')->post('/projetos', [
            'lightbox' => 'novo-projeto',
            'name' => '',
            'budget' => '10,00',
            'planned_hours' => '1',
        ])->assertSee('Informe o nome do projeto.')
            ->assertSee('document.getElementById("novo-projeto")?.showModal();', false);

        $this->post('/projetos', [
            'name' => 'Molde da tampa',
            'notes' => 'Cliente interno',
            'budget' => '1.234,56',
            'planned_hours' => '1,5',
        ])->assertRedirect();

        $project = Project::query()->first();
        $this->assertNotNull($project);
        $this->assertSame(123456, $project->budget_cents);

        $this->post('/projetos', [
            'name' => 'Sem orçamento',
            'budget' => '',
            'planned_hours' => '1',
        ])->assertRedirect();

        $this->assertSame(0, Project::query()->where('name', 'Sem orçamento')->value('budget_cents'));
        $this->assertSame(90, $project->planned_minutes);
        $this->assertSame($leader->id, $project->created_by);
        $this->get('/projetos')->assertSee('R$ 1.234,56')->assertSee('1h 30min');
        $this->get('/projetos/'.$project->id.'/editar')
            ->assertOk()
            ->assertSee('Só o administrador altera')
            ->assertDontSee('name="planned_hours"', false);
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
        $this->assertNotNull($subtask->timeLogs()->first());

        $this->delete('/projetos/'.$busy->id, ['apagar_lancamentos' => '1'])
            ->assertRedirect('/projetos');
        $this->assertModelMissing($busy);
        $this->assertSame(0, TimeLog::query()->where('subtask_id', $subtask->id)->count());

        $this->delete('/projetos/'.$empty->id, ['apagar_lancamentos' => '1'])->assertRedirect('/projetos');
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
        ])->assertRedirect('/projetos/'.$project->id.'/ponto');

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

        $this->get('/projetos/'.$project->id)
            ->assertOk()
            ->assertSee('Adicionar equipe terceira')
            ->assertSee('>Editar</a>', false)
            ->assertSee('/projetos/'.$project->id.'/editar', false);

        $this->get('/projetos/'.$project->id)
            ->assertOk()
            ->assertSee('placeholder="1,5"', false)
            ->assertDontSee('Se preenchido, o alarme aparece', false);

        $this->post('/projetos/'.$project->id.'/subtarefas', [
            'name' => 'Usinagem externa',
            'kind' => 'third_party',
            'budget' => '800,00',
            'planned_hours' => '1,5',
        ])->assertRedirect('/projetos/'.$project->id);

        $this->assertDatabaseHas('subtasks', [
            'name' => 'Usinagem externa',
            'kind' => 'third_party',
            'budget_cents' => 80000,
            'planned_minutes' => 90,
        ]);

        $this->get('/projetos/'.$project->id)
            ->assertSee('data-menu', false)
            ->assertSee('nova-terceira', false)
            ->assertDontSee('placeholder="Ex.: Tratamento térmico"', false);

        $project->update(['status' => 'closed', 'closed_at' => now()]);

        $this->post('/projetos/'.$project->id.'/subtarefas', [
            'name' => 'Outra',
            'kind' => 'internal',
        ])->assertSessionHasErrors('name');
    }

    public function test_nome_da_subtarefa_e_unico_no_projeto(): void
    {
        $leader = $this->actingAsRole(Role::Leader);
        $project = Project::factory()->create(['created_by' => $leader->id]);
        $other = Project::factory()->create(['created_by' => $leader->id]);
        $existing = Subtask::factory()->create([
            'project_id' => $project->id,
            'name' => 'Usinagem',
            'created_by' => $leader->id,
        ]);

        $this->post('/projetos/'.$project->id.'/subtarefas', [
            'name' => ' usinagem ',
            'kind' => 'internal',
            'lightbox' => 'nova-interna',
        ])->assertSessionHasErrors('name');

        $this->post('/projetos/'.$other->id.'/subtarefas', [
            'name' => 'Usinagem',
            'kind' => 'internal',
        ])->assertRedirect('/projetos/'.$other->id);

        $this->put('/subtarefas/'.$existing->id, [
            'name' => 'Usinagem',
            'kind' => 'internal',
        ])->assertRedirect('/projetos/'.$project->id);

        $sibling = Subtask::factory()->create([
            'project_id' => $project->id,
            'name' => 'Acabamento',
            'created_by' => $leader->id,
        ]);

        $this->put('/subtarefas/'.$sibling->id, [
            'name' => 'Usinagem',
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

    public function test_lider_importa_projeto_e_tarefas_do_csv(): void
    {
        $leader = $this->actingAsRole(Role::Leader);
        $arquivo = new UploadedFile(
            base_path('tests/fixtures/lista-os630.csv'),
            'lista.csv',
            'text/csv',
            null,
            true,
        );

        $this->get('/projetos')->assertOk()->assertSee('Importar')->assertSee('Arraste o arquivo .CSV aqui');

        $this->post('/projetos/importar', ['arquivo' => $arquivo])
            ->assertRedirect()
            ->assertSessionHas('status', 'Projeto importado com 53 tarefas.');

        $project = Project::query()->where('name', 'OS 630 Molde Tampa Stihl 4244')->first();
        $this->assertNotNull($project);
        $this->assertSame(0, $project->budget_cents);
        $this->assertSame(0, $project->planned_minutes);
        $this->assertSame($leader->id, $project->created_by);
        $this->assertSame(53, $project->subtasks()->count());
        $this->assertDatabaseHas('subtasks', [
            'project_id' => $project->id,
            'name' => 'Pos_01_CAVIDADE_FIXA',
            'kind' => 'internal',
            'budget_cents' => null,
            'planned_minutes' => null,
        ]);
        $this->assertDatabaseMissing('subtasks', [
            'project_id' => $project->id,
            'name' => 'Standard Number',
        ]);
        $this->assertDatabaseMissing('subtasks', [
            'project_id' => $project->id,
            'name' => '1629,0 X 1629,0 X 1680,0',
        ]);
    }

    public function test_importacao_ignora_tarefa_repetida_e_recusa_arquivo_sem_nome(): void
    {
        $this->actingAsRole(Role::Leader);

        $repetida = UploadedFile::fake()->createWithContent('lista.csv', implode("\n", [
            ';;Molde repetido;;;;',
            'No.;Qty.;Standard Number',
            '1;1;Usinagem',
            '2;1;usinagem',
            '3;1;',
            '4;1;Acabamento',
        ]));

        $this->post('/projetos/importar', ['arquivo' => $repetida])->assertRedirect();

        $project = Project::query()->where('name', 'Molde repetido')->first();
        $this->assertSame(
            ['Acabamento', 'Usinagem'],
            $project->subtasks()->orderBy('name')->pluck('name')->all(),
        );

        $semNome = UploadedFile::fake()->createWithContent('vazio.csv', ";;;;;;\nNo.;Qty.;Standard Number\n1;1;Usinagem\n");
        $this->from('/projetos')->post('/projetos/importar', ['arquivo' => $semNome, 'lightbox' => 'importar-projeto'])
            ->assertRedirect('/projetos')
            ->assertSessionHasErrors(['arquivo' => 'A primeira linha não tem o nome do projeto.']);
        $this->assertSame(1, Project::query()->count());
    }

    public function test_operador_nao_importa_projeto(): void
    {
        $this->actingAsRole(Role::Operator);
        $arquivo = UploadedFile::fake()->createWithContent('lista.csv', ";;Molde;;;;\n\n1;1;Peca\n");

        $this->post('/projetos/importar', ['arquivo' => $arquivo])->assertForbidden();
        $this->assertSame(0, Project::query()->count());
    }
}
