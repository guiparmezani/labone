<?php

namespace App\Http\Controllers;

use App\Enums\SubtaskKind;
use App\Http\Requests\StoreSubtaskRequest;
use App\Http\Requests\UpdateSubtaskRequest;
use App\Models\Project;
use App\Models\Subtask;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SubtaskController extends Controller
{
    public function store(StoreSubtaskRequest $request, Project $project): RedirectResponse
    {
        if (! $project->isOpen()) {
            return back()->withErrors([
                'name' => 'Projeto encerrado não recebe subtarefa.',
            ])->withInput();
        }

        $user = $request->user();
        $kind = $user->isOperator()
            ? SubtaskKind::Internal
            : SubtaskKind::from($request->string('kind')->toString());

        Subtask::query()->create([
            'project_id' => $project->id,
            'name' => $request->string('name')->toString(),
            'kind' => $kind,
            'budget_cents' => $user->isOperator() || $kind !== SubtaskKind::ThirdParty
                ? null
                : $request->integer('budget_cents'),
            'planned_minutes' => null,
            'realized_cents' => null,
            'alert_percentage' => null,
            'alert_enabled' => false,
            'created_by' => $user->id,
        ]);

        $back = $user->isOperator()
            ? route('projetos.ponto', $project)
            : route('projetos.show', $project);

        return redirect($back)->with('status', 'Subtarefa criada.');
    }

    public function edit(Subtask $subtask): View
    {
        $this->authorize('update', $subtask);

        return view('subtasks.edit', ['subtask' => $subtask]);
    }

    public function update(UpdateSubtaskRequest $request, Subtask $subtask): RedirectResponse
    {
        $kind = SubtaskKind::from($request->string('kind')->toString());
        $hasLogs = $subtask->timeLogs()->exists();

        if ($hasLogs && $kind !== $subtask->kind) {
            return back()->withErrors([
                'kind' => 'Esta subtarefa já tem lançamentos.',
            ])->withInput();
        }

        $subtask->update([
            'name' => $request->string('name')->toString(),
            'kind' => $kind,
            'budget_cents' => $request->input('budget_cents'),
            'planned_minutes' => $request->input('planned_minutes'),
            'realized_cents' => $request->input('realized_cents'),
            'alert_percentage' => $request->input('alert_percentage'),
            'alert_enabled' => $request->boolean('alert_enabled'),
        ]);

        return redirect()->route('projetos.show', $subtask->project_id)->with('status', 'Subtarefa atualizada.');
    }

    public function destroy(Subtask $subtask): RedirectResponse
    {
        $this->authorize('delete', $subtask);

        if ($subtask->timeLogs()->exists()) {
            return back()->withErrors([
                'subtask' => 'Este item tem lançamentos. Encerre o projeto em vez de apagar.',
            ]);
        }

        $projectId = $subtask->project_id;
        $subtask->delete();

        return redirect()->route('projetos.show', $projectId)->with('status', 'Subtarefa apagada.');
    }
}
