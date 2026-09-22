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
    }

    public function test_periodos_da_mesma_pessoa_nao_se_cruzam_e_pessoas_diferentes_podem(): void
    {
        $leader = $this->actingAsRole(Role::Leader);
        $operator = User::factory()->operator()->create();
        $other = User::factory()->operator()->create();
        $subtask = Subtask::factory()->create(['created_by' => $leader->id]);

        $payload = [
            'user_id' => $operator->id,
            'subtask_id' => $subtask->id,
            'started_at' => '2026-09-01T08:00',
            'ended_at' => '2026-09-01T10:00',
        ];

        $this->post('/lancamentos', $payload)->assertRedirect('/lancamentos');

        $this->post('/lancamentos', [
            ...$payload,
            'started_at' => '2026-09-01T09:00',
            'ended_at' => '2026-09-01T11:00',
        ])->assertSessionHasErrors('started_at');

        $this->post('/lancamentos', [
            ...$payload,
            'user_id' => $other->id,
            'started_at' => '2026-09-01T09:00',
            'ended_at' => '2026-09-01T11:00',
        ])->assertRedirect('/lancamentos');

        $this->assertSame(2, TimeLog::query()->count());
        $this->assertSame(TimeLogSource::Manual, TimeLog::query()->first()->source);
    }

    public function test_fim_antes_do_inicio_e_fim_no_futuro_sao_recusados(): void
    {
        $this->actingAsRole(Role::Admin);
        $operator = User::factory()->create();
        $subtask = Subtask::factory()->create();
        $future = Carbon::now(Formato::TZ)->addDay()->format('Y-m-d\TH:i');
        $past = Carbon::now(Formato::TZ)->subDay()->format('Y-m-d\TH:i');

        $this->post('/lancamentos', [
            'user_id' => $operator->id,
            'subtask_id' => $subtask->id,
            'started_at' => '2026-09-01T10:00',
            'ended_at' => '2026-09-01T09:00',
        ])->assertSessionHasErrors('ended_at');

        $this->post('/lancamentos', [
            'user_id' => $operator->id,
            'subtask_id' => $subtask->id,
            'started_at' => $past,
            'ended_at' => $future,
        ])->assertSessionHasErrors('ended_at');
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
}
