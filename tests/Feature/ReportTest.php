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
        $folha = Project::factory()->create(['name' => 'Folha visível']);

        $this->actingAsRole(Role::Leader);
        $pessoa = User::factory()->create(['name' => 'Folha Pessoa']);
        TimeLog::factory()->create([
            'user_id' => $pessoa->id,
            'subtask_id' => Subtask::factory()->create(['project_id' => $folha->id])->id,
            'created_by' => $pessoa->id,
            'updated_by' => $pessoa->id,
        ]);

        $this->get('/relatorios')
            ->assertOk()
            ->assertSee('Exportar tudo')
            ->assertDontSee('Exportar horas')
            ->assertDontSee('Exportar projetos')
            ->assertSee('name="q"', false)
            ->assertSee('Aberto')
            ->assertSee('Encerrado')
            ->assertSee('/projetos/'.$folha->id.'/relatorio', false)
            ->assertSee('/relatorios/pessoas/'.$pessoa->id.'"', false);
        $this->get(route('relatorios.person', $pessoa))
            ->assertOk()
            ->assertSee('Folha Pessoa')
            ->assertSee('Imprimir')
            ->assertSee('window.print()', false)
            ->assertSee('Todo o histórico.');
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

    public function test_csv_de_pessoas_separa_cada_projeto_numa_coluna(): void
    {
        $this->actingAsRole(Role::Admin);
        $ana = User::factory()->create(['name' => 'Ana Colunas']);
        $beto = User::factory()->create(['name' => 'Beto Colunas']);
        $cadeira = Project::factory()->create(['name' => 'Cadeira']);
        $molde = Project::factory()->create(['name' => 'Molde 01']);
        $cadeiraTask = Subtask::factory()->create(['project_id' => $cadeira->id]);
        $moldeTask = Subtask::factory()->create(['project_id' => $molde->id]);

        TimeLog::factory()->create([
            'user_id' => $ana->id,
            'subtask_id' => $moldeTask->id,
            'created_by' => $ana->id,
            'updated_by' => $ana->id,
            'started_at' => Carbon::parse('2026-09-10 08:00', Formato::TZ)->utc(),
            'ended_at' => Carbon::parse('2026-09-10 09:30', Formato::TZ)->utc(),
        ]);
        TimeLog::factory()->create([
            'user_id' => $ana->id,
            'subtask_id' => $cadeiraTask->id,
            'created_by' => $ana->id,
            'updated_by' => $ana->id,
            'started_at' => Carbon::parse('2026-09-10 10:00', Formato::TZ)->utc(),
            'ended_at' => Carbon::parse('2026-09-10 10:00', Formato::TZ)->utc(),
        ]);
        TimeLog::factory()->create([
            'user_id' => $beto->id,
            'subtask_id' => $cadeiraTask->id,
            'created_by' => $beto->id,
            'updated_by' => $beto->id,
            'started_at' => Carbon::parse('2026-09-10 11:00', Formato::TZ)->utc(),
            'ended_at' => Carbon::parse('2026-09-10 12:00', Formato::TZ)->utc(),
        ]);

        $csv = $this->get('/relatorios/pessoas.csv?from=2026-09-10&to=2026-09-10')->streamedContent();
        $linhas = array_map(
            fn (string $linha) => str_getcsv($linha, ';'),
            preg_split('/\r\n|\n|\r/', ltrim($csv, "\xEF\xBB\xBF"), -1, PREG_SPLIT_NO_EMPTY),
        );

        $this->assertSame(['Nome', 'Papel', 'Horas', 'Cadeira', 'Molde 01'], $linhas[0]);
        $this->assertStringNotContainsString('Cadeira 0,00', $csv);

        $anaLinha = collect($linhas)->firstWhere(0, 'Ana Colunas');
        $betoLinha = collect($linhas)->firstWhere(0, 'Beto Colunas');
        $this->assertSame(['Ana Colunas', 'Operador', '1,50', '0,00', '1,50'], $anaLinha);
        $this->assertSame(['Beto Colunas', 'Operador', '1,00', '1,00', '0,00'], $betoLinha);
    }

    public function test_filtro_de_projeto_e_situacao_na_lista_e_no_csv(): void
    {
        $this->actingAsRole(Role::Leader);
        Project::factory()->create(['name' => 'Molde aberto', 'status' => 'open']);
        Project::factory()->closed()->create(['name' => 'Molde encerrado']);
        Project::factory()->create(['name' => 'Outro aberto', 'status' => 'open']);

        $this->get('/relatorios?q=Molde&status=open')
            ->assertOk()
            ->assertSee('Molde aberto')
            ->assertDontSee('Molde encerrado')
            ->assertDontSee('Outro aberto')
            ->assertSee('relatorios/projetos.csv?q=Molde&amp;status=open', false);

        $csv = $this->get('/relatorios/projetos.csv?q=Molde&status=closed')->streamedContent();
        $this->assertStringContainsString('Molde encerrado', $csv);
        $this->assertStringNotContainsString('Molde aberto', $csv);
    }

    public function test_folha_da_pessoa_separa_projetos_e_omite_ponto_aberto(): void
    {
        $this->actingAsRole(Role::Admin);
        $person = User::factory()->create(['name' => 'Ana Torno']);
        $project = Project::factory()->create(['name' => 'Molde da Ana']);
        $subtask = Subtask::factory()->create([
            'project_id' => $project->id,
            'name' => 'Polimento',
        ]);
        $revision = Subtask::factory()->create([
            'project_id' => $project->id,
            'name' => 'Ajuste',
            'is_revision' => true,
        ]);

        TimeLog::factory()->create([
            'user_id' => $person->id,
            'subtask_id' => $subtask->id,
            'created_by' => $person->id,
            'updated_by' => $person->id,
            'started_at' => Carbon::parse('2026-09-10 08:00', Formato::TZ)->utc(),
            'ended_at' => Carbon::parse('2026-09-10 09:30', Formato::TZ)->utc(),
        ]);
        TimeLog::factory()->create([
            'user_id' => $person->id,
            'subtask_id' => $revision->id,
            'created_by' => $person->id,
            'updated_by' => $person->id,
            'started_at' => Carbon::parse('2026-09-11 08:00', Formato::TZ)->utc(),
            'ended_at' => Carbon::parse('2026-09-11 09:00', Formato::TZ)->utc(),
        ]);
        TimeLog::factory()->open()->create([
            'user_id' => $person->id,
            'subtask_id' => $subtask->id,
            'created_by' => $person->id,
            'updated_by' => $person->id,
        ]);

        $this->get(route('relatorios.person', $person).'?from=2026-09-10&to=2026-09-10')
            ->assertOk()
            ->assertSee('Molde da Ana')
            ->assertSee('Polimento')
            ->assertSee('1h 30min')
            ->assertSee('De 10/09/2026 até 10/09/2026.')
            ->assertDontSee('Ajuste (revisão)')
            ->assertSee('user_id='.$person->id, false)
            ->assertSee('from=2026-09-10', false);

        $this->actingAsRole(Role::Operator);
        $this->get(route('relatorios.person', $person))->assertForbidden();
    }
}
