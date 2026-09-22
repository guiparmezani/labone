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
 * Relatório do período. Ponto aberto fica de fora da soma.
 * O dia é o de Brasília, não o UTC.
 */
class PeriodReport
{
    public function __construct(
        public Carbon $from,
        public Carbon $to,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $fromInput = $request->string('from')->toString();
        $toInput = $request->string('to')->toString();

        $from = $fromInput !== ''
            ? Carbon::parse($fromInput, Formato::TZ)->startOfDay()->utc()
            : Carbon::now(Formato::TZ)->startOfMonth()->utc();

        $to = $toInput !== ''
            ? Carbon::parse($toInput, Formato::TZ)->endOfDay()->utc()
            : Carbon::now(Formato::TZ)->endOfMonth()->utc();

        return new self($from, $to);
    }

    public function fromDate(): string
    {
        return $this->from->timezone(Formato::TZ)->toDateString();
    }

    public function toDate(): string
    {
        return $this->to->timezone(Formato::TZ)->toDateString();
    }

    public function projects(): Collection
    {
        $duration = TimeLog::durationSql('time_logs');

        return Project::query()
            ->select('projects.*')
            ->addSelect([
                'logged_minutes' => TimeLog::query()
                    ->selectRaw("COALESCE(SUM({$duration}), 0)")
                    ->join('subtasks', 'subtasks.id', '=', 'time_logs.subtask_id')
                    ->whereColumn('subtasks.project_id', 'projects.id')
                    ->whereNotNull('time_logs.ended_at')
                    ->where('time_logs.started_at', '>=', $this->from)
                    ->where('time_logs.started_at', '<=', $this->to),
                'third_party_budget_cents' => \App\Models\Subtask::query()
                    ->selectRaw('COALESCE(SUM(budget_cents), 0)')
                    ->whereColumn('subtasks.project_id', 'projects.id')
                    ->where('kind', 'third_party'),
            ])
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    public function operators(): Collection
    {
        $duration = TimeLog::durationSql('time_logs');

        $rows = DB::table('time_logs')
            ->join('subtasks', 'subtasks.id', '=', 'time_logs.subtask_id')
            ->join('projects', 'projects.id', '=', 'subtasks.project_id')
            ->join('users', 'users.id', '=', 'time_logs.user_id')
            ->whereNotNull('time_logs.ended_at')
            ->where('time_logs.started_at', '>=', $this->from)
            ->where('time_logs.started_at', '<=', $this->to)
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

    public function finishedLogs(): Collection
    {
        return TimeLog::query()
            ->with(['user', 'subtask.project'])
            ->whereNotNull('ended_at')
            ->where('started_at', '>=', $this->from)
            ->where('started_at', '<=', $this->to)
            ->orderBy('started_at')
            ->get();
    }
}
