<?php

namespace App\Http\Controllers;

use App\Enums\SubtaskKind;
use App\Models\Project;
use App\Models\Subtask;
use App\Services\TimeClock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClockController extends Controller
{
    public function show(Request $request, Project $project, TimeClock $clock): View
    {
        $openLogs = $clock->openLogs($request->user());
        $openLogs->load('subtask.project');

        return view('clock.show', [
            'project' => $project,
            'subtasks' => $project->subtasks()
                ->where('kind', SubtaskKind::Internal)
                ->orderBy('name')
                ->get(['id', 'project_id', 'name', 'kind']),
            'openLogs' => $openLogs,
        ]);
    }

    public function start(Request $request, TimeClock $clock): RedirectResponse
    {
        $data = $request->validate([
            'subtask_id' => ['required', 'integer', 'exists:subtasks,id'],
        ], [
            'subtask_id.required' => 'Escolha a subtarefa.',
        ]);

        $clock->start($request->user(), Subtask::query()->findOrFail($data['subtask_id']));

        return back()->with('status', 'Ponto iniciado.');
    }

    public function stop(Request $request, TimeClock $clock): RedirectResponse
    {
        $data = $request->validate([
            'time_log_id' => ['nullable', 'integer'],
        ]);

        $clock->stop($request->user(), $data['time_log_id'] ?? null);

        return back()->with('status', 'Ponto encerrado.');
    }

    public function switchActivity(Request $request, \App\Models\TimeLog $timeLog, TimeClock $clock): RedirectResponse
    {
        abort_unless($request->user()?->managesProjects(), 403);

        $data = $request->validate([
            'subtask_id' => ['required', 'integer', 'exists:subtasks,id'],
        ], [
            'subtask_id.required' => 'Escolha a nova atividade.',
        ]);

        $clock->switchActivity($request->user(), $timeLog, Subtask::query()->findOrFail($data['subtask_id']));

        return back()->with('status', 'Atividade trocada. O tempo já corrido ficou na atividade anterior.');
    }
}
