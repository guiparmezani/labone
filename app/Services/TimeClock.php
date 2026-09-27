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
    public function start(User $user, Subtask $subtask): TimeLog
    {
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
                'subtask_id' => 'Esta subtarefa já está em andamento.',
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

    public function stop(User $user, ?int $logId = null): TimeLog
    {
        $query = TimeLog::query()
            ->where('user_id', $user->id)
            ->whereNull('ended_at');

        $log = $logId === null
            ? $query->first()
            : $query->whereKey($logId)->first();

        if (! $log) {
            throw ValidationException::withMessages([
                'ponto' => 'Você não tem ponto em andamento.',
            ]);
        }

        return $this->finish($log, $user);
    }

    /**
     * O tempo já corrido fica na subtarefa antiga. A nova começa agora.
     */
    public function switchActivity(User $editor, TimeLog $log, Subtask $next): TimeLog
    {
        if (! $log->isOpen()) {
            throw ValidationException::withMessages([
                'subtask_id' => 'Este ponto já foi encerrado.',
            ]);
        }

        $next->loadMissing('project');

        if (! $next->isInternal() || ! $next->project->isOpen()) {
            throw ValidationException::withMessages([
                'subtask_id' => 'Este item não aceita ponto.',
            ]);
        }

        if ($next->id === $log->subtask_id) {
            throw ValidationException::withMessages([
                'subtask_id' => 'Esta já é a atividade em andamento.',
            ]);
        }

        $already = TimeLog::query()
            ->where('user_id', $log->user_id)
            ->where('subtask_id', $next->id)
            ->whereNull('ended_at')
            ->exists();

        if ($already) {
            throw ValidationException::withMessages([
                'subtask_id' => 'Esta pessoa já está nesta subtarefa.',
            ]);
        }

        $this->finish($log, $editor);

        return TimeLog::query()->create([
            'user_id' => $log->user_id,
            'subtask_id' => $next->id,
            'started_at' => now(),
            'ended_at' => null,
            'source' => TimeLogSource::Timer,
            'created_by' => $editor->id,
            'updated_by' => $editor->id,
        ]);
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
