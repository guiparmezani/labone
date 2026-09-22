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
        $open = $clock->openLog($request->user());
        $open?->load('subtask.project');

        return view('clock.show', [
            'project' => $project,
            'subtasks' => $project->subtasks()
                ->where('kind', SubtaskKind::Internal)
                ->orderBy('name')
                ->get(['id', 'project_id', 'name', 'kind']),
            'openLog' => $open,
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
        $clock->stop($request->user());

        return back()->with('status', 'Ponto encerrado.');
    }
}
