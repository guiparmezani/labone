<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\TimeLog;
use App\Models\User;
use App\Services\TimeLogWriter;
use App\Support\Formato;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class TimeLogController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', TimeLog::class);

        [$from, $to] = $this->range($request);

        $logs = TimeLog::query()
            ->with(['user', 'subtask.project'])
            ->when($request->integer('project_id'), function ($query, $projectId) {
                $query->whereHas('subtask', fn ($subtask) => $subtask->where('project_id', $projectId));
            })
            ->when($request->integer('user_id'), fn ($query, $userId) => $query->where('user_id', $userId))
            ->where('started_at', '>=', $from)
            ->where('started_at', '<=', $to)
            ->orderByDesc('started_at')
            ->get();

        return view('time_logs.index', [
            'logs' => $logs,
            'projects' => Project::query()->orderBy('name')->get(),
            'users' => User::query()->orderBy('name')->get(),
            'filters' => [
                'project_id' => $request->integer('project_id') ?: '',
                'user_id' => $request->integer('user_id') ?: '',
                'from' => $from->timezone(Formato::TZ)->toDateString(),
                'to' => $to->timezone(Formato::TZ)->toDateString(),
            ],
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', TimeLog::class);

        return view('time_logs.create', $this->formData());
    }

    public function store(Request $request, TimeLogWriter $writer): RedirectResponse
    {
        $this->authorize('create', TimeLog::class);
        $writer->create($request->user(), $this->validated($request));

        return redirect()->route('lancamentos.index')->with('status', 'Lançamento criado.');
    }

    public function edit(TimeLog $timeLog): View
    {
        $this->authorize('update', $timeLog);

        return view('time_logs.edit', [
            'log' => $timeLog,
            ...$this->formData(),
        ]);
    }

    public function update(Request $request, TimeLog $timeLog, TimeLogWriter $writer): RedirectResponse
    {
        $this->authorize('update', $timeLog);
        $writer->update($request->user(), $timeLog, $this->validated($request));

        return redirect()->route('lancamentos.index')->with('status', 'Lançamento atualizado.');
    }

    public function destroy(TimeLog $timeLog): RedirectResponse
    {
        $this->authorize('delete', $timeLog);
        $timeLog->delete();

        return redirect()->route('lancamentos.index')->with('status', 'Lançamento apagado.');
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(Request $request): array
    {
        $fromInput = $request->string('from')->toString();
        $toInput = $request->string('to')->toString();

        $from = $fromInput !== ''
            ? Carbon::parse($fromInput, Formato::TZ)->startOfDay()->utc()
            : Carbon::now(Formato::TZ)->startOfMonth()->utc();

        $to = $toInput !== ''
            ? Carbon::parse($toInput, Formato::TZ)->endOfDay()->utc()
            : Carbon::now(Formato::TZ)->endOfMonth()->utc();

        return [$from, $to];
    }

    /**
     * @return array{user_id: int, subtask_id: int, started_at: string, ended_at: string}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'subtask_id' => ['required', 'integer', 'exists:subtasks,id'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date'],
        ], [
            'user_id.required' => 'Escolha a pessoa.',
            'subtask_id.required' => 'Escolha a subtarefa.',
            'started_at.required' => 'Informe o início.',
            'ended_at.required' => 'Informe o fim.',
        ]);

        return [
            'user_id' => (int) $data['user_id'],
            'subtask_id' => (int) $data['subtask_id'],
            'started_at' => $data['started_at'],
            'ended_at' => $data['ended_at'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'users' => User::query()->orderBy('name')->get(),
            'subtasks' => \App\Models\Subtask::query()
                ->with('project')
                ->where('kind', 'internal')
                ->orderBy('name')
                ->get(),
        ];
    }
}
