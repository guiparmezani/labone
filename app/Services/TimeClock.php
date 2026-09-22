<?php

namespace App\Services;

use App\Enums\TimeLogSource;
use App\Models\Subtask;
use App\Models\TimeLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * O relógio do servidor grava o ponto. O navegador não manda hora.
 */
class TimeClock
{
    public function start(User $user, Subtask $subtask): TimeLog
    {
        if ($this->openLog($user)) {
            throw ValidationException::withMessages([
                'subtask_id' => 'Você já tem um ponto em andamento. Pare esse ponto antes de iniciar outro.',
            ]);
        }

        $subtask->loadMissing('project');

        if (! $subtask->isInternal() || ! $subtask->project->isOpen()) {
            throw ValidationException::withMessages([
                'subtask_id' => 'Este item não aceita ponto.',
            ]);
        }

        return TimeLog::query()->create([
            'user_id' => $user->id,
            'subtask_id' => $subtask->id,
            'started_at' => now(),
            'ended_at' => null,
            'source' => TimeLogSource::Timer,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    public function stop(User $user): TimeLog
    {
        $log = $this->openLog($user);

        if (! $log) {
            throw ValidationException::withMessages([
                'ponto' => 'Você não tem ponto em andamento.',
            ]);
        }

        return $this->finish($log, $user);
    }

    public function closeOpen(User $subject, User $editor): void
    {
        $log = $this->openLog($subject);

        if ($log) {
            $this->finish($log, $editor);
        }
    }

    public function openLog(User $user): ?TimeLog
    {
        return TimeLog::query()
            ->where('user_id', $user->id)
            ->whereNull('ended_at')
            ->first();
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
