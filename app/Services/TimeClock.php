<?php

namespace App\Services;

use App\Enums\TimeLogSource;
use App\Models\Subtask;
use App\Models\TimeLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * O relógio do servidor grava o ponto. O navegador não manda hora.
 * A mesma pessoa pode ter vários pontos abertos, um por subtarefa.
 */
class TimeClock
{
    public function start(User $user, Subtask $subtask, ?User $editor = null): TimeLog
    {
        $editor ??= $user;
        $subtask->loadMissing('project');

        if (! $subtask->isInternal() || ! $subtask->project->isOpen()) {
            throw ValidationException::withMessages([
                'subtask_id' => 'Este item não aceita ponto.',
            ]);
        }

        $already = TimeLog::query()
            ->where('user_id', $user->id)
            ->where('subtask_id', $subtask->id)
            ->whereNull('ended_at')
            ->exists();

        if ($already) {
            throw ValidationException::withMessages([
                'subtask_id' => 'Esta tarefa já está em andamento.',
            ]);
        }

        return TimeLog::query()->create([
            'user_id' => $user->id,
            'subtask_id' => $subtask->id,
            'started_at' => now(),
            'ended_at' => null,
            'source' => TimeLogSource::Timer,
            'created_by' => $editor->id,
            'updated_by' => $editor->id,
        ]);
    }

    public function stop(User $actor, ?int $logId = null): TimeLog
    {
        $log = $this->openLogFor($actor, $logId);

        if (! $log) {
            throw ValidationException::withMessages([
                'ponto' => 'Você não tem ponto em andamento.',
            ]);
        }

        return $this->finish($log, $actor);
    }

    public function closeOpen(User $subject, User $editor): void
    {
        $this->openLogs($subject)->each(fn (TimeLog $log) => $this->finish($log, $editor));
    }

    public function openLog(User $user): ?TimeLog
    {
        return $this->openLogs($user)->first();
    }

    /**
     * @return Collection<int, TimeLog>
     */
    public function openLogs(User $user): Collection
    {
        return TimeLog::query()
            ->where('user_id', $user->id)
            ->whereNull('ended_at')
            ->orderBy('started_at')
            ->get();
    }

    private function openLogFor(User $actor, ?int $logId): ?TimeLog
    {
        if ($logId === null) {
            return TimeLog::query()
                ->where('user_id', $actor->id)
                ->whereNull('ended_at')
                ->first();
        }

        $log = TimeLog::query()->whereKey($logId)->whereNull('ended_at')->first();

        if ($log === null) {
            return null;
        }

        if ($log->user_id !== $actor->id && ! $actor->managesProjects()) {
            return null;
        }

        return $log;
    }

    private function finish(TimeLog $log, User $editor): TimeLog
    {
        $end = Carbon::now();

        if (! $end->greaterThan($log->started_at)) {
            $end = $log->started_at->copy()->addSecond();
        }

        $log->forceFill([
            'ended_at' => $end,
            'updated_by' => $editor->id,
        ])->save();

        return $log;
    }
}
