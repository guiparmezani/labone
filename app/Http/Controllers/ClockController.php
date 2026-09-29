<?php

namespace App\Http\Controllers;

use App\Enums\SubtaskKind;
use App\Models\Project;
use App\Models\Subtask;
use App\Models\User;
use App\Services\TimeClock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
                ->get(['id', 'project_id', 'name', 'kind', 'is_revision']),
            'openLogs' => $openLogs,
        ]);
    }

    public function start(Request $request, TimeClock $clock): RedirectResponse
    {
        $data = $request->validate([
            'subtask_id' => ['required', 'integer', 'exists:subtasks,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ], [
            'subtask_id.required' => 'Escolha a tarefa.',
            'user_id.exists' => 'Escolha a pessoa.',
        ]);

        $actor = $request->user();
        $subject = $actor;

        if ($actor->managesProjects() && ! empty($data['user_id'])) {
            $subject = User::query()->whereKey($data['user_id'])->where('active', true)->first();

            if (! $subject) {
                throw ValidationException::withMessages([
                    'user_id' => 'Escolha uma pessoa com conta ativa.',
                ]);
            }
        }

        $clock->start($subject, Subtask::query()->findOrFail($data['subtask_id']), $actor);

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
}
