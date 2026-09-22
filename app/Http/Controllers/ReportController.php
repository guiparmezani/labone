<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Services\PeriodReport;
use App\Support\Formato;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);
        $report = PeriodReport::fromRequest($request);

        return view('reports.index', [
            'report' => $report,
            'projects' => $report->projects(),
            'operators' => $report->operators(),
        ]);
    }

    public function hours(Request $request): StreamedResponse
    {
        $this->authorizeAdmin($request);
        $report = PeriodReport::fromRequest($request);

        return $this->download('horas.csv', function ($out) use ($report) {
            fputcsv($out, ['Data início', 'Hora início', 'Data fim', 'Hora fim', 'Horas', 'Operador', 'Projeto', 'Subtarefa'], ';');

            foreach ($report->finishedLogs() as $log) {
                fputcsv($out, [
                    Formato::data($log->started_at),
                    Formato::hora($log->started_at),
                    Formato::data($log->ended_at),
                    Formato::hora($log->ended_at),
                    Formato::horasCsv((int) $log->minutes()),
                    $log->user->name,
                    $log->subtask->project->name,
                    $log->subtask->name,
                ], ';');
            }
        });
    }

    public function projects(Request $request): StreamedResponse
    {
        $this->authorizeAdmin($request);
        $report = PeriodReport::fromRequest($request);

        return $this->download('projetos.csv', function ($out) use ($report) {
            fputcsv($out, ['Projeto', 'Situação', 'Orçamento', 'Horas previstas', 'Horas lançadas no período', 'Orçamento de terceiros'], ';');

            foreach ($report->projects() as $project) {
                fputcsv($out, [
                    $project->name,
                    $project->status->label(),
                    Formato::decimal($project->budget_cents),
                    Formato::horasCsv($project->planned_minutes),
                    Formato::horasCsv($project->loggedMinutes()),
                    Formato::decimal($project->thirdPartyBudgetCents()),
                ], ';');
            }
        });
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Você não tem acesso a esta página.');
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
