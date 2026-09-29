<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Models\Project;
use App\Models\User;
use App\Services\PeriodReport;
use App\Support\Formato;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeManager($request);
        $report = PeriodReport::allTime();
        $filters = $this->projectFilters($request);

        return view('reports.index', [
            'projects' => $report->projects(null, $filters['q'], $filters['status']),
            'operators' => $report->operators(),
            'filters' => $filters,
        ]);
    }

    public function person(Request $request, User $user): View
    {
        $this->authorizeManager($request);
        $report = PeriodReport::fromRequest($request);

        return view('reports.person', [
            'user' => $user,
            'sheet' => $report->personSheet($user->id),
            'period' => $report->periodLabel(),
            'from' => $request->string('from')->toString(),
            'to' => $request->string('to')->toString(),
        ]);
    }

    public function projects(Request $request): StreamedResponse
    {
        $this->authorizeManager($request);
        $report = PeriodReport::fromRequest($request);
        $filters = $this->projectFilters($request);
        $projectId = $request->integer('project_id') ?: null;
        $filename = 'projetos.csv';

        if ($projectId) {
            $name = Project::query()->whereKey($projectId)->value('name');

            if (is_string($name) && $name !== '') {
                $filename = $this->exportFilename($name, $filename);
            }
        }

        return $this->download($filename, function ($out) use ($report, $projectId, $filters) {
            fputcsv($out, ['Projeto', 'Situação', 'Orçamento', 'Horas previstas', 'Horas lançadas', 'Orçamento de terceiros'], ';');

            foreach ($report->projects($projectId, $filters['q'], $filters['status']) as $project) {
                fputcsv($out, [
                    $project->name,
                    $project->status->label(),
                    Formato::decimal($project->budget_cents),
                    Formato::horasCsv($project->plannedMinutesTotal()),
                    Formato::horasCsv($project->loggedMinutes()),
                    Formato::decimal($project->thirdPartyBudgetCents()),
                ], ';');
            }
        });
    }

    public function people(Request $request): StreamedResponse
    {
        $this->authorizeManager($request);
        $report = PeriodReport::fromRequest($request);
        $userId = $request->integer('user_id') ?: null;
        $filename = 'pessoas.csv';

        if ($userId) {
            $name = User::query()->whereKey($userId)->value('name');

            if (is_string($name) && $name !== '') {
                $filename = $this->exportFilename($name, $filename);
            }
        }

        return $this->download($filename, function ($out) use ($report, $userId) {
            $operators = $report->operators($userId);
            $projects = $operators
                ->flatMap(fn ($operator) => $operator->projects)
                ->unique('id')
                ->sortBy([['name', 'asc'], ['id', 'asc']])
                ->values();

            fputcsv($out, ['Nome', 'Papel', 'Horas', ...$projects->pluck('name')->all()], ';');

            foreach ($operators as $operator) {
                $hours = $operator->projects->mapWithKeys(fn ($line) => [$line->id => $line->minutes]);

                fputcsv($out, [
                    $operator->name,
                    Role::from($operator->role)->label(),
                    Formato::horasCsv($operator->minutes),
                    ...$projects->map(fn ($project) => Formato::horasCsv((int) ($hours[$project->id] ?? 0)))->all(),
                ], ';');
            }
        });
    }

    private function exportFilename(string $label, string $fallback): string
    {
        $clean = preg_replace('/[\\\\\\/:*?"<>|\x00-\x1F]+/u', ' ', $label) ?? '';
        $clean = trim(preg_replace('/\s+/u', ' ', $clean) ?? '');

        if ($clean === '' || $clean === '.') {
            return $fallback;
        }

        return $clean.'.csv';
    }

    /**
     * @return array{q: string, status: string}
     */
    private function projectFilters(Request $request): array
    {
        $status = $request->string('status')->toString();

        return [
            'q' => trim($request->string('q')->toString()),
            'status' => in_array($status, [ProjectStatus::Open->value, ProjectStatus::Closed->value], true) ? $status : '',
        ];
    }

    private function authorizeManager(Request $request): void
    {
        abort_unless($request->user()?->managesProjects(), 403, 'Você não tem acesso a esta página.');
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
}
