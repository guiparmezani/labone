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

    public function projects(?int $projectId = null, ?string $search = null, ?string $status = null): Collection
    {
        $duration = TimeLog::durationSql('time_logs');
        $search = trim((string) $search);

        return Project::query()
            ->select('projects.*')
            ->when($projectId, fn ($query) => $query->where('projects.id', $projectId))
            ->when($status, fn ($query) => $query->where('projects.status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
                $query->whereRaw('projects.name like ? escape ?', [$like, '\\']);
            })
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
                'projects.id as project_id',
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
                    'id' => (int) $line->project_id,
                    'name' => $line->project_name,
                    'minutes' => (int) $line->minutes,
                ])->values(),
            ];
        })->values();
    }

    /**
     * Horas encerradas de uma pessoa, agrupadas por projeto e subtarefa.
     *
     * @return object{minutes: int, projects: Collection<int, object>}
     */
    public function personSheet(int $userId): object
    {
        $duration = TimeLog::durationSql('time_logs');

        $rows = DB::table('time_logs')
            ->join('subtasks', 'subtasks.id', '=', 'time_logs.subtask_id')
            ->join('projects', 'projects.id', '=', 'subtasks.project_id')
            ->where('time_logs.user_id', $userId)
            ->whereNotNull('time_logs.ended_at')
            ->when($this->from, fn ($query) => $query->where('time_logs.started_at', '>=', $this->from))
            ->when($this->to, fn ($query) => $query->where('time_logs.started_at', '<=', $this->to))
            ->groupBy('projects.id', 'projects.name', 'subtasks.id', 'subtasks.name', 'subtasks.is_revision')
            ->orderBy('projects.name')
            ->orderBy('subtasks.name')
            ->select([
                'projects.id as project_id',
                'projects.name as project_name',
                'subtasks.name as subtask_name',
                'subtasks.is_revision as is_revision',
                DB::raw("SUM({$duration}) as minutes"),
            ])
            ->get();

        $projects = $rows->groupBy('project_id')->map(function (Collection $lines) {
            $first = $lines->first();

            return (object) [
                'name' => $first->project_name,
                'minutes' => (int) $lines->sum('minutes'),
                'subtasks' => $lines->map(fn ($line) => (object) [
                    'name' => $line->subtask_name,
                    'is_revision' => (bool) $line->is_revision,
                    'minutes' => (int) $line->minutes,
                ])->values(),
            ];
        })->values();

        return (object) [
            'minutes' => (int) $rows->sum('minutes'),
            'projects' => $projects,
        ];
    }

    public function periodLabel(): string
    {
        if ($this->from === null && $this->to === null) {
            return 'Todo o histórico.';
        }

        if ($this->from !== null && $this->to !== null) {
            return 'De '.Formato::data($this->from).' até '.Formato::data($this->to).'.';
        }

        if ($this->from !== null) {
            return 'A partir de '.Formato::data($this->from).'.';
        }

        return 'Até '.Formato::data($this->to).'.';
    }

}
