<?php

namespace App\Services;

use App\Enums\TimeLogSource;
use App\Models\Subtask;
use App\Models\TimeLog;
use App\Models\User;
use App\Support\Formato;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Lançamento manual. A origem do ponto iniciado pelo operador não muda na edição.
 */
class TimeLogWriter
{
    /**
     * @param  array{user_id: int, subtask_id: int, started_at: string, ended_at: string}  $input
     */
    public function create(User $editor, array $input): TimeLog
    {
        $subtask = $this->internalSubtask((int) $input['subtask_id'], requireOpenProject: true);
        $start = Formato::interpretarLocal($input['started_at']);
        $end = Formato::interpretarLocal($input['ended_at']);
        $this->assertInterval($start, $end);
        $this->assertNoOverlap((int) $input['user_id'], $start, $end);

        return TimeLog::query()->create([
            'user_id' => $input['user_id'],
            'subtask_id' => $subtask->id,
            'started_at' => $start,
            'ended_at' => $end,
            'source' => TimeLogSource::Manual,
            'created_by' => $editor->id,
            'updated_by' => $editor->id,
        ]);
    }

    /**
     * @param  array{user_id: int, subtask_id: int, started_at: string, ended_at: string}  $input
     */
    public function update(User $editor, TimeLog $log, array $input): TimeLog
    {
        $subtask = $this->internalSubtask((int) $input['subtask_id'], requireOpenProject: false);
        $start = Formato::interpretarLocal($input['started_at']);
        $end = Formato::interpretarLocal($input['ended_at']);
        $this->assertInterval($start, $end);
        $this->assertNoOverlap((int) $input['user_id'], $start, $end, $log->id);

        $log->fill([
            'user_id' => $input['user_id'],
            'subtask_id' => $subtask->id,
            'started_at' => $start,
            'ended_at' => $end,
            'updated_by' => $editor->id,
        ])->save();

        return $log;
    }

    public function assertNoOverlap(int $userId, Carbon $start, Carbon $end, ?int $ignoreId = null): void
    {
        $conflict = TimeLog::query()
            ->where('user_id', $userId)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('started_at', '<', $end)
            ->where(function ($query) use ($start) {
                $query->whereNull('ended_at')->orWhere('ended_at', '>', $start);
            })
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'started_at' => 'Este período cruza outro lançamento desta pessoa.',
            ]);
        }
    }

    private function assertInterval(Carbon $start, Carbon $end): void
    {
        if (! $end->greaterThan($start)) {
            throw ValidationException::withMessages([
                'ended_at' => 'O fim precisa ser depois do início.',
            ]);
        }

        if ($end->greaterThan(now())) {
            throw ValidationException::withMessages([
                'ended_at' => 'O fim não pode ser no futuro.',
            ]);
        }
    }

    private function internalSubtask(int $id, bool $requireOpenProject): Subtask
    {
        $subtask = Subtask::query()->with('project')->find($id);

        if (! $subtask || ! $subtask->isInternal() || ($requireOpenProject && ! $subtask->project->isOpen())) {
            throw ValidationException::withMessages([
                'subtask_id' => 'Este item não aceita ponto.',
            ]);
        }

        return $subtask;
    }
}
