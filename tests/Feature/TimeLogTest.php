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

        $this->get('/lancamentos')->assertOk()->assertSee('Editar')->assertSee('Apagar')->assertDontSee('<th>Fim</th>', false);
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
            'duration' => '02:00',
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
            'duration' => '02:00',
        ];

        $this->post('/lancamentos', $payload)->assertRedirect('/lancamentos');

        $this->post('/lancamentos', [
            ...$payload,
            'started_at' => '2026-09-01T09:00',
            'duration' => '02:00',
        ])->assertRedirect('/lancamentos');

        $this->post('/lancamentos', [
            ...$payload,
            'user_id' => $other->id,
            'started_at' => '2026-09-01T09:00',
            'duration' => '02:00',
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
            'duration' => '00:00',
        ])->assertSessionHasErrors('duration');

        $this->post('/lancamentos', [
            'user_id' => $operator->id,
            'subtask_id' => $subtask->id,
            'started_at' => $past,
            'duration' => '05:00',
        ])->assertSessionHasErrors('duration');

        $this->post('/lancamentos', [
            'user_id' => $operator->id,
            'subtask_id' => $subtask->id,
            'started_at' => $past,
            'duration' => '1,5',
        ])->assertSessionHasErrors('duration');
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
