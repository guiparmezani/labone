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

class ShiftAndLaborTest extends TestCase
{
    use RefreshDatabase;

    public function test_lider_grava_valor_hora_e_os_dois_turnos(): void
    {
        $this->actingAsRole(Role::Leader);

        $pagina = $this->get('/usuarios')
            ->assertOk()
            ->assertSee('Valor hora (R$)')
            ->assertSee('Adicionar jornada')
            ->assertSee('>Jornada</p>', false)
            ->assertSee('Jornada 1')
            ->assertSee('Jornada 2');
        $this->assertMatchesRegularExpression('/data-jornada\s+hidden/', $pagina->getContent());

        $this->post('/usuarios', [
            'name' => 'Pedro Turno',
            'email' => 'pedro@oficina.test',
            'password' => 'senha-segura',
            'role' => 'operator',
            'active' => '1',
            'hourly_rate' => '10,00',
            'shift_start' => '07:00',
            'shift_end' => '12:00',
            'shift_afternoon_start' => '13:00',
            'shift_afternoon_end' => '17:00',
        ])->assertRedirect('/usuarios');

        $pedro = User::query()->where('name', 'Pedro Turno')->first();
        $this->assertNotNull($pedro);
        $this->assertSame(1000, $pedro->hourly_rate_cents);
        $this->assertSame('07:00', $pedro->shift_start);
        $this->assertSame('12:00', $pedro->shift_end);
        $this->assertSame('13:00', $pedro->shift_afternoon_start);
        $this->assertSame('17:00', $pedro->shift_afternoon_end);

        $this->get('/usuarios')
            ->assertOk()
            ->assertSee('Jornada 1')
            ->assertSee('Jornada 2')
            ->assertSee('value="07:00"', false)
            ->assertSee('value="13:00"', false);
    }

    public function test_jornada_incompleta_segundo_turno_e_valor_hora_ruim_falham(): void
    {
        $this->actingAsRole(Role::Admin);

        $base = [
            'name' => 'Ana Jornada',
            'email' => 'ana-jornada@oficina.test',
            'password' => 'senha-segura',
            'role' => 'operator',
            'active' => '1',
        ];

        $this->followingRedirects()->from('/usuarios')->post('/usuarios', [
            ...$base,
            'lightbox' => 'novo-usuario',
            'shift_start' => '07:00',
        ])->assertSee('Informe o início e o fim da jornada.');

        $this->followingRedirects()->from('/usuarios')->post('/usuarios', [
            ...$base,
            'lightbox' => 'novo-usuario',
            'shift_start' => '07:00',
            'shift_end' => '12:00',
            'shift_afternoon_start' => '11:00',
            'shift_afternoon_end' => '17:00',
        ])->assertSee('A segunda jornada começa depois do fim da primeira.');

        $this->followingRedirects()->from('/usuarios')->post('/usuarios', [
            ...$base,
            'lightbox' => 'novo-usuario',
            'hourly_rate' => 'abc',
        ])->assertSee('Informe o valor hora em reais.');

        $this->assertNull(User::query()->where('name', 'Ana Jornada')->first());
    }

    public function test_alerta_de_quinze_minutos_so_em_dia_util_dentro_da_jornada(): void
    {
        $this->actingAsRole(Role::Leader);
        $pedro = User::factory()->operator()->create([
            'name' => 'Pedro Parado',
            'shift_start' => '07:00',
            'shift_end' => '17:00',
            'created_at' => Carbon::parse('2026-10-01 06:00:00', Formato::TZ),
        ]);
        User::factory()->operator()->create(['name' => 'Sem Jornada']);

        $this->travelTo(Carbon::parse('2026-10-01 07:14:00', Formato::TZ));
        $this->get(route('alertas.index'))
            ->assertOk()
            ->assertDontSee('Pedro Parado')
            ->assertSee('Ninguém parado na jornada.');

        $this->travelTo(Carbon::parse('2026-10-01 07:15:00', Formato::TZ));
        $this->get(route('alertas.index'))
            ->assertOk()
            ->assertSee('01/10/2026')
            ->assertSee('Pedro Parado não iniciou uma tarefa desde 07:00.')
            ->assertDontSee('Iniciou às')
            ->assertDontSee('Sem Jornada')
            ->assertDontSee('Carregar dias anteriores')
            ->assertDontSee('nav-count', false);

        $this->travelTo(Carbon::parse('2026-10-03 10:00:00', Formato::TZ));
        $this->get(route('alertas.index'))
            ->assertOk()
            ->assertDontSee('Pedro Parado')
            ->assertDontSee('01/10/2026')
            ->assertSee('Carregar dias anteriores');

        $sabado = $this->followingRedirects()->post(route('alertas.carregar'), ['anteriores' => 5])
            ->assertOk()
            ->assertSee('Pedro Parado')
            ->assertSee('02/10/2026')
            ->assertSee('01/10/2026')
            ->assertDontSee('03/10/2026')
            ->assertDontSee('Carregar dias anteriores');
        $html = $sabado->getContent();
        $this->assertNotFalse($html);
        $this->assertLessThan(strpos($html, '01/10/2026'), strpos($html, '02/10/2026'));

        $tarde = User::factory()->operator()->create([
            'name' => 'Carlos Tarde',
            'shift_start' => '13:00',
            'shift_end' => '17:00',
        ]);
        $this->travelTo(Carbon::parse('2026-10-01 09:00:00', Formato::TZ));
        $this->get(route('alertas.index'))
            ->assertOk()
            ->assertDontSee('Carlos Tarde');

        $this->assertNotNull($tarde);
    }

    public function test_carregar_dias_anteriores_traz_cinco_por_vez(): void
    {
        $this->actingAsRole(Role::Leader);
        User::factory()->operator()->create([
            'name' => 'Pedro Historico',
            'shift_start' => '07:00',
            'shift_end' => '17:00',
            'created_at' => Carbon::parse('2026-09-01 06:00:00', Formato::TZ),
        ]);

        $this->travelTo(Carbon::parse('2026-10-01 07:15:00', Formato::TZ));

        $hoje = $this->get(route('alertas.index'))->assertOk()->assertSee('01/10/2026')->assertSee('value="5"', false);
        $this->assertSame(1, substr_count((string) $hoje->getContent(), 'alert-day'));

        $cinco = $this->followingRedirects()->post(route('alertas.carregar'), ['anteriores' => 5])->assertOk()->assertSee('value="10"', false);
        $this->assertSame(6, substr_count((string) $cinco->getContent(), 'alert-day'));
        $this->assertLessThan(strpos((string) $cinco->getContent(), '25/09/2026'), strpos((string) $cinco->getContent(), '30/09/2026'));

        $recarregado = $this->get(route('alertas.index', ['anteriores' => 5]))->assertOk();
        $this->assertSame(1, substr_count((string) $recarregado->getContent(), 'alert-day'));

        $dez = $this->followingRedirects()->post(route('alertas.carregar'), ['anteriores' => 10])->assertOk();
        $this->assertSame(11, substr_count((string) $dez->getContent(), 'alert-day'));
    }

    public function test_iniciar_o_ponto_fecha_a_linha_e_outro_buraco_abre_outra(): void
    {
        $lider = $this->actingAsRole(Role::Leader);
        $pedro = User::factory()->operator()->create([
            'name' => 'Pedro Volta',
            'shift_start' => '07:00',
            'shift_end' => '17:00',
            'created_at' => Carbon::parse('2026-10-01 06:00:00', Formato::TZ),
        ]);
        $tarefa = Subtask::factory()->create();

        $this->travelTo(Carbon::parse('2026-10-01 07:20:00', Formato::TZ));
        TimeLog::factory()->open()->create([
            'user_id' => $pedro->id,
            'subtask_id' => $tarefa->id,
            'created_by' => $lider->id,
            'updated_by' => $lider->id,
            'started_at' => Carbon::parse('2026-10-01 07:20:00', Formato::TZ)->utc(),
        ]);

        $this->get(route('alertas.index'))
            ->assertOk()
            ->assertSee('Pedro Volta não iniciou uma tarefa desde 07:00.')
            ->assertSee('Iniciou às 07:20.');

        TimeLog::query()->where('user_id', $pedro->id)->update([
            'ended_at' => Carbon::parse('2026-10-01 07:30:00', Formato::TZ)->utc(),
        ]);
        $this->travelTo(Carbon::parse('2026-10-01 07:50:00', Formato::TZ));

        $html = $this->get(route('alertas.index'))->assertOk()->getContent();
        $this->assertNotFalse($html);
        $this->assertSame(2, substr_count($html, 'Pedro Volta não iniciou uma tarefa desde'));
        $this->assertStringContainsString('desde 07:00.', $html);
        $this->assertStringContainsString('Iniciou às 07:20.', $html);
        $this->assertStringContainsString('desde 07:30.', $html);
        $this->assertLessThan(strpos($html, 'desde 07:00.'), strpos($html, 'desde 07:30.'));
    }

    public function test_almoco_entre_os_dois_turnos_nao_dispara(): void
    {
        $lider = $this->actingAsRole(Role::Leader);
        $pedro = User::factory()->operator()->create([
            'name' => 'Pedro Almoco',
            'created_at' => Carbon::parse('2026-10-01 06:00:00', Formato::TZ),
            'shift_start' => '07:00',
            'shift_end' => '12:00',
            'shift_afternoon_start' => '13:00',
            'shift_afternoon_end' => '17:00',
        ]);
        $tarefa = Subtask::factory()->create();

        TimeLog::factory()->create([
            'user_id' => $pedro->id,
            'subtask_id' => $tarefa->id,
            'created_by' => $lider->id,
            'updated_by' => $lider->id,
            'started_at' => Carbon::parse('2026-10-01 07:00:00', Formato::TZ)->utc(),
            'ended_at' => Carbon::parse('2026-10-01 12:00:00', Formato::TZ)->utc(),
        ]);

        $this->travelTo(Carbon::parse('2026-10-01 12:20:00', Formato::TZ));
        $this->get(route('alertas.index'))
            ->assertOk()
            ->assertDontSee('Pedro Almoco');

        $this->travelTo(Carbon::parse('2026-10-01 13:15:00', Formato::TZ));
        $this->get(route('alertas.index'))
            ->assertOk()
            ->assertSee('Pedro Almoco não iniciou uma tarefa desde 13:00.');
    }

    public function test_valor_realizado_soma_material_e_mao_de_obra(): void
    {
        $admin = $this->actingAsRole(Role::Admin);
        $pedro = User::factory()->operator()->create([
            'name' => 'Pedro Hora',
            'hourly_rate_cents' => 1000,
        ]);
        $project = Project::factory()->create(['name' => 'Molde placa']);
        $tarefa = Subtask::factory()->create([
            'project_id' => $project->id,
            'name' => 'Placa 1',
            'realized_cents' => 100000,
        ]);
        $fim = now();
        TimeLog::factory()->create([
            'user_id' => $pedro->id,
            'subtask_id' => $tarefa->id,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'started_at' => $fim->copy()->subHours(100),
            'ended_at' => $fim,
        ]);

        $this->get(route('projetos.show', $project))
            ->assertOk()
            ->assertSee('R$ 1.000,00 + R$ 1.000,00');

        $this->get(route('projetos.relatorio', $project))
            ->assertOk()
            ->assertSee('R$ 1.000,00 + R$ 1.000,00')
            ->assertSee('Digitado nas tarefas + horas pelo valor hora');

        $csv = $this->get(route('projetos.relatorio.csv', $project))->assertOk()->streamedContent();
        $this->assertStringContainsString('1000,00 + 1000,00', $csv);
    }

    public function test_ponto_aberto_entra_na_mao_de_obra_e_sem_valor_hora_fica_so_o_digitado(): void
    {
        $admin = $this->actingAsRole(Role::Admin);
        $comValor = User::factory()->operator()->create(['hourly_rate_cents' => 1000]);
        $semValor = User::factory()->operator()->create(['hourly_rate_cents' => null]);
        $project = Project::factory()->create();
        $aberta = Subtask::factory()->create([
            'project_id' => $project->id,
            'name' => 'Em curso',
            'realized_cents' => null,
        ]);
        $digitada = Subtask::factory()->create([
            'project_id' => $project->id,
            'name' => 'So material',
            'realized_cents' => 100000,
        ]);

        TimeLog::factory()->open()->create([
            'user_id' => $comValor->id,
            'subtask_id' => $aberta->id,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'started_at' => now()->subHour(),
        ]);
        TimeLog::factory()->create([
            'user_id' => $semValor->id,
            'subtask_id' => $digitada->id,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'started_at' => now()->subHours(10),
            'ended_at' => now(),
        ]);

        $this->assertGreaterThan(0, $aberta->laborCents());

        $this->get(route('projetos.show', $project))
            ->assertOk()
            ->assertSee($aberta->realizedLabel())
            ->assertSee('R$ 1.000,00')
            ->assertDontSee('R$ 1.000,00 +');
    }

    public function test_o_numero_do_menu_e_so_o_que_esta_pessoa_ainda_nao_abriu(): void
    {
        $marina = User::factory()->leader()->create(['name' => 'Marina Contagem']);
        $outro = User::factory()->leader()->create(['name' => 'Outro Lider']);
        User::factory()->operator()->create([
            'name' => 'Pedro Parado',
            'shift_start' => '07:00',
            'shift_end' => '17:00',
            'created_at' => Carbon::parse('2026-10-01 06:00:00', Formato::TZ),
        ]);

        $this->travelTo(Carbon::parse('2026-10-01 07:15:00', Formato::TZ));

        $this->actingAs($marina)->get(route('inicio'))
            ->assertOk()
            ->assertSee('Alertas')
            ->assertSee('(1)', false);

        $this->actingAs($outro)->get(route('inicio'))
            ->assertOk()
            ->assertSee('(1)', false);

        $this->actingAs($marina)->get(route('alertas.index'))
            ->assertOk()
            ->assertDontSee('nav-count', false);

        $this->actingAs($marina)->get(route('inicio'))
            ->assertOk()
            ->assertDontSee('(1)', false);

        $this->actingAs($outro)->get(route('inicio'))
            ->assertOk()
            ->assertSee('(1)', false);

        User::factory()->operator()->create([
            'name' => 'Ana Nova',
            'shift_start' => '07:00',
            'shift_end' => '17:00',
        ]);
        $this->travelTo(Carbon::parse('2026-10-01 07:40:00', Formato::TZ));

        $this->actingAs($marina)->get(route('inicio'))
            ->assertOk()
            ->assertSee('(1)', false);

        $this->actingAs($outro)->get(route('inicio'))
            ->assertOk()
            ->assertSee('(2)', false);
    }

    public function test_abrir_alertas_zera_o_numero_mesmo_com_dias_fora_da_tela(): void
    {
        $marina = User::factory()->leader()->create(['name' => 'Marina Contagem']);
        User::factory()->operator()->create([
            'name' => 'Pedro Parado',
            'shift_start' => '07:00',
            'shift_end' => '17:00',
            'created_at' => Carbon::parse('2026-09-28 06:00:00', Formato::TZ),
        ]);

        $this->travelTo(Carbon::parse('2026-10-01 07:15:00', Formato::TZ));

        $this->actingAs($marina)->get(route('inicio'))
            ->assertOk()
            ->assertSee('nav-count', false);

        $this->actingAs($marina)->get(route('alertas.index'))
            ->assertOk()
            ->assertSee('01/10/2026')
            ->assertDontSee('30/09/2026')
            ->assertDontSee('nav-count', false);

        $this->actingAs($marina)->get(route('inicio'))
            ->assertOk()
            ->assertDontSee('nav-count', false);
    }
}
