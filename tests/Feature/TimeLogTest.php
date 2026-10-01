<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TimeLogSource;
use App\Models\Subtask;
use App\Models\TimeLog;
use App\Models\User;
use App\Support\Formato;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TimeLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_operador_recebe_403_em_lancamentos(): void
    {
        $this->actingAsRole(Role::Operator);

        $this->get('/lancamentos')->assertForbidden();
        $this->post('/lancamentos', [])->assertForbidden();

        $this->actingAsRole(Role::Leader);
        $this->get('/lancamentos')->assertOk();
        $this->post('/lancamentos', [])->assertForbidden();
        $this->get('/lancamentos/novo')->assertForbidden();
    }

    public function test_lider_edita_lancamento_e_nao_apaga(): void
    {
        $leader = $this->actingAsRole(Role::Leader);
        $operator = User::factory()->operator()->create();
        $subtask = Subtask::factory()->create();
        $log = TimeLog::factory()->create([
            'user_id' => $operator->id,
            'subtask_id' => $subtask->id,
            'started_at' => Carbon::now(Formato::TZ)->subHours(3)->utc(),
            'ended_at' => Carbon::now(Formato::TZ)->subHours(2)->utc(),
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
        ]);

        $this->get('/lancamentos')
            ->assertOk()
            ->assertSee('data-abrir="editar-lancamento-'.$log->id.'"', false)
            ->assertDontSee(route('lancamentos.edit', $log), false)
            ->assertSee('Apagar')
            ->assertDontSee('>Editar<', false)
            ->assertDontSee('<th>Fim</th>', false);
        $this->get('/lancamentos/'.$log->id.'/editar')
            ->assertOk()
            ->assertSee('Editar lançamento')
            ->assertSee('Duração')
            ->assertDontSee('name="ended_at"', false);

        $inicio = Carbon::now(Formato::TZ)->subHours(4)->startOfMinute();

        $this->put('/lancamentos/'.$log->id, [
            'user_id' => $operator->id,
            'subtask_id' => $subtask->id,
            'started_at' => $inicio->format('Y-m-d\TH:i'),
            'duration' => '2',
        ])->assertRedirect('/lancamentos');

        $log->refresh();
        $this->assertSame($leader->id, $log->updated_by);
        $this->assertSame(120, $log->minutes());
        $this->delete('/lancamentos/'.$log->id)->assertRedirect('/lancamentos');
        $this->assertModelMissing($log);
    }

    public function test_periodos_da_mesma_pessoa_podem_se_cruzar(): void
    {
        $admin = $this->actingAsRole(Role::Admin);
        $operator = User::factory()->operator()->create();
        $other = User::factory()->operator()->create();
        $subtask = Subtask::factory()->create(['created_by' => $admin->id]);

        $payload = [
            'user_id' => $operator->id,
            'subtask_id' => $subtask->id,
            'started_at' => '2026-09-01T08:00',
            'duration' => '2',
        ];

        $this->post('/lancamentos', $payload)->assertRedirect('/lancamentos');

        $this->post('/lancamentos', [
            ...$payload,
            'started_at' => '2026-09-01T09:00',
            'duration' => '2',
        ])->assertRedirect('/lancamentos');

        $this->post('/lancamentos', [
            ...$payload,
            'user_id' => $other->id,
            'started_at' => '2026-09-01T09:00',
            'duration' => '2',
        ])->assertRedirect('/lancamentos');

        $this->assertSame(3, TimeLog::query()->count());
        $this->assertSame(TimeLogSource::Manual, TimeLog::query()->first()->source);
    }

    public function test_duracao_zerada_e_duracao_no_futuro_sao_recusadas(): void
    {
        $this->actingAsRole(Role::Admin);
        $operator = User::factory()->create();
        $subtask = Subtask::factory()->create();
        $past = Carbon::now(Formato::TZ)->subHour()->format('Y-m-d\TH:i');

        $this->post('/lancamentos', [
            'user_id' => $operator->id,
            'subtask_id' => $subtask->id,
            'started_at' => '2026-09-01T10:00',
            'duration' => '0',
        ])->assertSessionHasErrors('duration');

        $this->post('/lancamentos', [
            'user_id' => $operator->id,
            'subtask_id' => $subtask->id,
            'started_at' => $past,
            'duration' => '5',
        ])->assertSessionHasErrors('duration');

        $this->post('/lancamentos', [
            'user_id' => $operator->id,
            'subtask_id' => $subtask->id,
            'started_at' => $past,
            'duration' => '01:30',
        ])->assertSessionHasErrors('duration');
    }

    public function test_erro_de_edicao_reabre_o_popup_daquele_lancamento(): void
    {
        $this->actingAsRole(Role::Leader);
        $operator = User::factory()->operator()->create();
        $subtask = Subtask::factory()->create();
        $log = TimeLog::factory()->create([
            'user_id' => $operator->id,
            'subtask_id' => $subtask->id,
            'started_at' => Carbon::now(Formato::TZ)->subHours(3)->utc(),
            'ended_at' => Carbon::now(Formato::TZ)->subHours(2)->utc(),
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
        ]);
        $outro = TimeLog::factory()->create([
            'user_id' => $operator->id,
            'subtask_id' => $subtask->id,
            'started_at' => Carbon::now(Formato::TZ)->subHours(5)->utc(),
            'ended_at' => Carbon::now(Formato::TZ)->subHours(4)->utc(),
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
        ]);
        $inicio = Carbon::now(Formato::TZ)->subHours(3)->startOfMinute();

        $pagina = $this->followingRedirects()->from('/lancamentos')->put('/lancamentos/'.$log->id, [
            'user_id' => $operator->id,
            'subtask_id' => $subtask->id,
            'started_at' => $inicio->format('Y-m-d\TH:i'),
            'duration' => 'abc',
            'lightbox' => 'editar-lancamento-'.$log->id,
        ]);

        $pagina->assertSee('Informe a duração em horas, como 1,5.');
        $this->assertSame(1, substr_count($pagina->getContent(), 'Informe a duração em horas, como 1,5.'));
        $pagina->assertSee('value="abc"', false);
        $pagina->assertSee('value="'.Formato::horasEntrada($outro->minutes()).'"', false);
        $pagina->assertSee('document.getElementById("editar-lancamento-'.$log->id.'")?.showModal();', false);
        $this->assertSame(60, $log->refresh()->minutes());
    }

    public function test_ponto_aberto_aparece_sem_duracao_no_periodo(): void
    {
        $this->actingAsRole(Role::Leader);
        $operator = User::factory()->create(['name' => 'Joana Torno']);
        $subtask = Subtask::factory()->create();
        TimeLog::factory()->open()->create([
            'user_id' => $operator->id,
            'subtask_id' => $subtask->id,
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
            'started_at' => Carbon::now(Formato::TZ)->startOfMonth()->addDay()->utc(),
        ]);

        $this->get('/lancamentos')->assertOk()->assertSee('Em andamento')->assertSee('Joana Torno');
    }

    public function test_abre_no_mes_atual_e_o_periodo_customizado_muda_o_titulo(): void
    {
        $this->actingAsRole(Role::Leader);
        $operator = User::factory()->create(['name' => 'Pedro Setembro']);
        $subtask = Subtask::factory()->create();
        TimeLog::factory()->create([
            'user_id' => $operator->id,
            'subtask_id' => $subtask->id,
            'created_by' => $operator->id,
            'updated_by' => $operator->id,
            'started_at' => Carbon::parse('2026-09-15 10:00:00', Formato::TZ)->utc(),
            'ended_at' => Carbon::parse('2026-09-15 12:00:00', Formato::TZ)->utc(),
        ]);

        $this->travelTo(Carbon::parse('2026-10-01 09:00:00', Formato::TZ));

        $this->get('/lancamentos')
            ->assertOk()
            ->assertSee('Outubro de 2026')
            ->assertDontSee('15/09/2026');

        $this->get('/lancamentos?mes=2026-09')
            ->assertOk()
            ->assertSee('Setembro de 2026')
            ->assertSee('15/09/2026');

        $this->get('/lancamentos?from=2026-09-10&to=2026-09-20')
            ->assertOk()
            ->assertSee('Período selecionado')
            ->assertSee('15/09/2026');
    }

    public function test_calendario_de_lancamento_e_em_portugues(): void
    {
        $this->actingAsRole(Role::Admin);

        $this->get('/lancamentos/novo')
            ->assertOk()
            ->assertDontSee('datetime-local', false)
            ->assertSee('dd/mm/aaaa hh:mm', false)
            ->assertSee('Hoje')
            ->assertSee('Limpar')
            ->assertSee('seg')
            ->assertDontSee('Today')
            ->assertDontSee('Clear');

        $this->get('/lancamentos')
            ->assertOk()
            ->assertDontSee('type="date"', false)
            ->assertSee('dd/mm/aaaa', false);
    }
}
