<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Enums\SubtaskKind;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use App\Models\Subtask;
use App\Support\Formato;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        $project->load(['subtasks' => fn ($query) => $query->withLoggedMinutes()->with('revisionOf')->orderBy('name')]);

        return view('projects.show', [
            'project' => $project,
            'internal' => $project->subtasks->where('kind', \App\Enums\SubtaskKind::Internal),
            'thirdParty' => $project->subtasks->where('kind', \App\Enums\SubtaskKind::ThirdParty),
        ]);
    }

    public function report(Project $project): View
    {
        $this->authorize('view', $project);

        return view('projects.report', $this->sheet($project));
    }

    public function export(Project $project): StreamedResponse
    {
        $this->authorize('view', $project);
        $sheet = $this->sheet($project);

        return $this->download($this->exportFilename($project->name), function ($out) use ($project, $sheet) {
            fputcsv($out, ['Grupo', 'Tarefa', 'Equipe terceira', 'Tempo previsto', 'Tempo realizado', 'Valor previsto', 'Valor realizado', 'Revisões', 'Notas'], ';');

            foreach ($sheet['internal'] as $subtask) {
                fputcsv($out, [
                    'Equipe interna',
                    $subtask->name,
                    '',
                    $this->horasCsv($subtask->planned_minutes),
                    Formato::horasCsv($subtask->consumedMinutes()),
                    $this->dinheiroCsv($subtask->budget_cents),
                    $this->dinheiroCsv($subtask->realized_cents),
                    '',
                    (string) ($subtask->revision_notes ?? ''),
                ], ';');
            }

            foreach ($sheet['thirdParty'] as $subtask) {
                fputcsv($out, [
                    'Equipe terceira',
                    $subtask->name,
                    '',
                    $this->horasCsv($subtask->planned_minutes),
                    '',
                    $this->dinheiroCsv($subtask->budget_cents),
                    $this->dinheiroCsv($subtask->realized_cents),
                    (string) $sheet['revisions']->where('revision_of_subtask_id', $subtask->id)->count(),
                    (string) ($subtask->revision_notes ?? ''),
                ], ';');
            }

            foreach ($sheet['revisions'] as $revision) {
                fputcsv($out, [
                    'Revisão',
                    $revision->name,
                    $revision->revisionOf->name ?? '',
                    $this->horasCsv($revision->planned_minutes),
                    Formato::horasCsv($revision->consumedMinutes()),
                    $this->dinheiroCsv($revision->budget_cents),
                    $this->dinheiroCsv($revision->realized_cents),
                    '',
                    (string) ($revision->revision_notes ?? ''),
                ], ';');
            }

            $previstoSubtarefas = (int) $project->subtasks->sum('budget_cents');
            $realizado = (int) $project->subtasks->sum('realized_cents');

            fputcsv($out, ['Totais', 'Horas previstas', Formato::horasCsv($project->planned_minutes).' do projeto + '.Formato::horasCsv($project->plannedMinutesFromSubtasks()).' das tarefas', Formato::horasCsv($project->plannedMinutesTotal()), '', '', '', '', ''], ';');
            fputcsv($out, ['Totais', 'Horas realizadas', 'Inclui o ponto em andamento', '', Formato::horasCsv($project->consumedMinutes()), '', '', '', ''], ';');
            fputcsv($out, ['Totais', 'Valor previsto', Formato::decimal($project->budget_cents).' do projeto + '.Formato::decimal($previstoSubtarefas).' das tarefas', '', '', Formato::decimal($project->budget_cents + $previstoSubtarefas), '', '', ''], ';');
            fputcsv($out, ['Totais', 'Valor realizado', 'Soma do que foi digitado nas tarefas', '', '', '', Formato::decimal($realizado), '', ''], ';');
        });
    }

    /**
     * @return array{project: Project, internal: \Illuminate\Support\Collection, thirdParty: \Illuminate\Support\Collection, revisions: \Illuminate\Support\Collection}
     */
    private function sheet(Project $project): array
    {
        $consumed = \App\Models\TimeLog::consumedMinutesSql('subtasks.id');

        $project->load(['subtasks' => function ($query) use ($consumed) {
            $query->withLoggedMinutes()
                ->addSelect(DB::raw($consumed.' as consumed_minutes'))
                ->with('revisionOf')
                ->orderBy('name');
        }]);

        $revisions = $project->subtasks->where('is_revision', true)->values();
        $work = $project->subtasks->reject(fn ($subtask) => $subtask->is_revision);

        return [
            'project' => $project,
            'internal' => $work->where('kind', SubtaskKind::Internal)->values(),
            'thirdParty' => $work->where('kind', SubtaskKind::ThirdParty)->values(),
            'revisions' => $revisions,
        ];
    }

    private function horasCsv(?int $minutes): string
    {
        return $minutes === null ? '' : Formato::horasCsv($minutes);
    }

    private function dinheiroCsv(?int $cents): string
    {
        return $cents === null ? '' : Formato::decimal($cents);
    }

    private function exportFilename(string $label): string
    {
        $clean = preg_replace('/[\\\\\\/:*?"<>|\x00-\x1F]+/u', ' ', $label) ?? '';
        $clean = trim(preg_replace('/\s+/u', ' ', $clean) ?? '');

        if ($clean === '' || $clean === '.') {
            return 'relatorio.csv';
        }

        return $clean.'.csv';
    }

    private function download(string $filename, callable $write): StreamedResponse
    {
        return response()->streamDownload(function () use ($write) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            $write($out);
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
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

    public function copyForm(Project $project): View
    {
        $this->authorize('create', Project::class);

        return view('projects.copy', ['project' => $project]);
    }

    public function copy(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('create', Project::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:160'],
        ], [
            'name.required' => 'Informe o nome do projeto.',
            'name.min' => 'O nome precisa ter pelo menos 2 caracteres.',
            'name.max' => 'O nome pode ter no máximo 160 caracteres.',
        ]);

        $copy = DB::transaction(function () use ($project, $data, $request) {
            $project->load('subtasks');

            $new = Project::query()->create([
                'name' => $data['name'],
                'notes' => $project->notes,
                'status' => ProjectStatus::Open,
                'budget_cents' => $project->budget_cents,
                'planned_minutes' => $project->planned_minutes,
                'created_by' => $request->user()->id,
            ]);

            $map = [];

            foreach ($project->subtasks as $subtask) {
                $created = Subtask::query()->create([
                    'project_id' => $new->id,
                    'name' => $subtask->name,
                    'kind' => $subtask->kind,
                    'budget_cents' => $subtask->budget_cents,
                    'planned_minutes' => $subtask->planned_minutes,
                    'realized_cents' => null,
                    'alert_percentage' => $subtask->alert_percentage,
                    'alert_enabled' => $subtask->alert_enabled,
                    'is_revision' => $subtask->is_revision,
                    'revision_notes' => $subtask->revision_notes,
                    'created_by' => $request->user()->id,
                ]);
                $map[$subtask->id] = $created->id;
            }

            foreach ($project->subtasks as $subtask) {
                $linked = $subtask->revision_of_subtask_id;

                if ($linked && isset($map[$linked], $map[$subtask->id])) {
                    Subtask::query()->whereKey($map[$subtask->id])->update([
                        'revision_of_subtask_id' => $map[$linked],
                    ]);
                }
            }

            return $new;
        });

        return redirect()->route('projetos.show', $copy)->with('status', 'Projeto copiado.');
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
