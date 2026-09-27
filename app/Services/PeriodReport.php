<?php

namespace App\Services;

use App\Models\Project;
use App\Models\TimeLog;
use App\Support\Formato;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Relatório de horas encerradas. Sem datas, cobre todo o histórico.
 * O dia informado é o de Brasília, não o UTC. Ponto aberto fica de fora.
 */
class PeriodReport
{
    public function __construct(
        public ?Carbon $from,
        public ?Carbon $to,
    ) {}

    public static function allTime(): self
    {
        return new self(null, null);
    }

    public static function fromRequest(Request $request): self
    {
        $fromInput = $request->string('from')->toString();
        $toInput = $request->string('to')->toString();

        $from = $fromInput !== ''
            ? Carbon::parse($fromInput, Formato::TZ)->startOfDay()->utc()
            : null;

        $to = $toInput !== ''
            ? Carbon::parse($toInput, Formato::TZ)->endOfDay()->utc()
            : null;

        return new self($from, $to);
    }

    public function projects(?int $projectId = null): Collection
    {
        $duration = TimeLog::durationSql('time_logs');

        return Project::query()
            ->select('projects.*')
            ->when($projectId, fn ($query) => $query->where('projects.id', $projectId))
            ->addSelect([
                'logged_minutes' => TimeLog::query()
                    ->selectRaw("COALESCE(SUM({$duration}), 0)")
                    ->join('subtasks', 'subtasks.id', '=', 'time_logs.subtask_id')
                    ->whereColumn('subtasks.project_id', 'projects.id')
                    ->whereNotNull('time_logs.ended_at')
                    ->when($this->from, fn ($query) => $query->where('time_logs.started_at', '>=', $this->from))
                    ->when($this->to, fn ($query) => $query->where('time_logs.started_at', '<=', $this->to)),
                'third_party_budget_cents' => \App\Models\Subtask::query()
                    ->selectRaw('COALESCE(SUM(budget_cents), 0)')
                    ->whereColumn('subtasks.project_id', 'projects.id')
                    ->where('kind', 'third_party'),
                'subtask_planned_minutes' => \App\Models\Subtask::query()
                    ->selectRaw('COALESCE(SUM(planned_minutes), 0)')
                    ->whereColumn('subtasks.project_id', 'projects.id'),
            ])
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    public function operators(?int $userId = null): Collection
    {
        $duration = TimeLog::durationSql('time_logs');

        $rows = DB::table('time_logs')
            ->join('subtasks', 'subtasks.id', '=', 'time_logs.subtask_id')
            ->join('projects', 'projects.id', '=', 'subtasks.project_id')
            ->join('users', 'users.id', '=', 'time_logs.user_id')
            ->whereNotNull('time_logs.ended_at')
            ->when($this->from, fn ($query) => $query->where('time_logs.started_at', '>=', $this->from))
            ->when($this->to, fn ($query) => $query->where('time_logs.started_at', '<=', $this->to))
            ->when($userId, fn ($query) => $query->where('users.id', $userId))
            ->groupBy('users.id', 'users.name', 'users.role', 'projects.id', 'projects.name')
            ->orderBy('users.name')
            ->orderBy('projects.name')
            ->select([
                'users.id as user_id',
                'users.name as user_name',
                'users.role as role',
                'projects.name as project_name',
                DB::raw("SUM({$duration}) as minutes"),
            ])
            ->get();

        return $rows->groupBy('user_id')->map(function (Collection $lines) {
            $first = $lines->first();

            return (object) [
                'id' => (int) $first->user_id,
                'name' => $first->user_name,
                'role' => $first->role,
                'minutes' => (int) $lines->sum('minutes'),
                'projects' => $lines->map(fn ($line) => (object) [
                    'name' => $line->project_name,
                    'minutes' => (int) $line->minutes,
                ])->values(),
            ];
        })->values();
    }

}
