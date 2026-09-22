<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if ($request->user()->isOperator()) {
            return redirect()->route('inicio');
        }

        $this->authorize('viewAny', Project::class);

        $status = $request->string('status')->toString() ?: 'open';
        $query = Project::query()->withTotals()->orderBy('name');

        if ($status === 'closed') {
            $query->where('status', ProjectStatus::Closed);
        } elseif ($status !== 'all') {
            $status = 'open';
            $query->where('status', ProjectStatus::Open);
        }

        return view('projects.index', [
            'projects' => $query->get(),
            'status' => $status,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Project::class);

        return view('projects.create');
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = Project::query()->create([
            ...$request->safe()->only(['name', 'notes', 'budget_cents', 'planned_minutes']),
            'status' => ProjectStatus::Open,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('projetos.show', $project)->with('status', 'Projeto criado.');
    }

    public function show(Project $project): View
    {
        $this->authorize('view', $project);

        $project->load(['subtasks' => fn ($query) => $query->withLoggedMinutes()->orderBy('name')]);

        return view('projects.show', [
            'project' => $project,
            'internal' => $project->subtasks->where('kind', \App\Enums\SubtaskKind::Internal),
            'thirdParty' => $project->subtasks->where('kind', \App\Enums\SubtaskKind::ThirdParty),
        ]);
    }

    public function edit(Project $project): View
    {
        $this->authorize('update', $project);

        return view('projects.edit', ['project' => $project]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $project->update($request->safe()->only(['name', 'notes', 'budget_cents', 'planned_minutes']));

        return redirect()->route('projetos.show', $project)->with('status', 'Projeto atualizado.');
    }

    public function close(Project $project): RedirectResponse
    {
        $this->authorize('update', $project);
        $project->update([
            'status' => ProjectStatus::Closed,
            'closed_at' => now(),
        ]);

        return back()->with('status', 'Projeto encerrado.');
    }

    public function reopen(Project $project): RedirectResponse
    {
        $this->authorize('update', $project);
        $project->update([
            'status' => ProjectStatus::Open,
            'closed_at' => null,
        ]);

        return back()->with('status', 'Projeto reaberto.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        if ($project->timeLogs()->exists()) {
            return back()->withErrors([
                'project' => 'Este item tem lançamentos. Encerre o projeto em vez de apagar.',
            ]);
        }

        $project->delete();

        return redirect()->route('projetos.index')->with('status', 'Projeto apagado.');
    }
}
