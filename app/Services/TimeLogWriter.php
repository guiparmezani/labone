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
     * @param  array{user_id: int, subtask_id: int, started_at: string, ended_at?: string, duration_minutes?: int}  $input
     */
    public function create(User $editor, array $input): TimeLog
    {
        $subtask = $this->internalSubtask((int) $input['subtask_id'], requireOpenProject: true);
        [$start, $end] = $this->interval($input);

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
     * @param  array{user_id: int, subtask_id: int, started_at: string, ended_at?: string, duration_minutes?: int}  $input
     */
    public function update(User $editor, TimeLog $log, array $input): TimeLog
    {
        $subtask = $this->internalSubtask((int) $input['subtask_id'], requireOpenProject: false);
        [$start, $end] = $this->interval($input, $log);

        $log->fill([
            'user_id' => $input['user_id'],
            'subtask_id' => $subtask->id,
            'started_at' => $start,
            'ended_at' => $end,
            'updated_by' => $editor->id,
        ])->save();

        return $log;
    }

    /**
     * @param  array{started_at: string, ended_at?: string, duration_minutes?: int}  $input
     * @return array{Carbon, Carbon}
     */
    private function interval(array $input, ?TimeLog $log = null): array
    {
        $start = $this->localTime($input['started_at'], $log?->started_at);
        $hasEnd = isset($input['ended_at']);
        $end = $hasEnd
            ? $this->localTime($input['ended_at'], $log?->ended_at)
            : $start->copy()->addMinutes($input['duration_minutes']);
        $field = $hasEnd ? 'ended_at' : 'duration';

        if (! $end->greaterThan($start)) {
            throw ValidationException::withMessages([
                $field => $hasEnd ? 'O fim precisa ser depois do início.' : 'A duração precisa ser maior que zero.',
            ]);
        }

        if ($end->greaterThan(now())) {
            throw ValidationException::withMessages([
                $field => $hasEnd ? 'O fim não pode passar do momento atual.' : 'A duração não pode passar do momento atual.',
            ]);
        }

        return [$start, $end];
    }

    private function localTime(string $value, ?Carbon $original): Carbon
    {
        if ($original !== null && Formato::local($original) === $value) {
            return $original->copy();
        }

        return Formato::interpretarLocal($value);
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
