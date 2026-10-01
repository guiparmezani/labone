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
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TimeLogController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', TimeLog::class);

        [$from, $to, $mes] = $this->range($request);

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
            ...$this->formData(),
            'filters' => [
                'project_id' => $request->integer('project_id') ?: '',
                'user_id' => $request->integer('user_id') ?: '',
                'mes' => $mes?->format('Y-m') ?? '',
                'from' => $from->timezone(Formato::TZ)->toDateString(),
                'to' => $to->timezone(Formato::TZ)->toDateString(),
            ],
            'periodo' => $mes ? $this->nomeDoMes($mes) : 'Período selecionado',
            'meses' => $this->meses($mes),
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
     * @return array{0: Carbon, 1: Carbon, 2: ?Carbon}
     */
    private function range(Request $request): array
    {
        $mesPedido = $this->mesValido($request->string('mes')->toString());
        $fromInput = $request->string('from')->toString();
        $toInput = $request->string('to')->toString();

        if ($fromInput === '' && $toInput === '') {
            $mes = $mesPedido ?? Carbon::now(Formato::TZ)->startOfMonth();

            return [$this->inicioUtc($mes), $this->fimUtc($mes), $mes];
        }

        $from = Carbon::parse($fromInput !== '' ? $fromInput : $toInput, Formato::TZ)->startOfDay();
        $to = Carbon::parse($toInput !== '' ? $toInput : $fromInput, Formato::TZ)->endOfDay();

        if ($mesPedido !== null && $this->cabeNoMes($from, $to, $mesPedido)) {
            return [$from->utc(), $to->utc(), $mesPedido];
        }

        return [$from->utc(), $to->utc(), $this->mesCheio($from, $to)];
    }

    private function mesValido(string $mes): ?Carbon
    {
        if (preg_match('/^\d{4}-\d{2}$/', $mes) !== 1) {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d', $mes.'-01', Formato::TZ)->startOfMonth();
    }

    private function cabeNoMes(Carbon $from, Carbon $to, Carbon $mes): bool
    {
        return $from->toDateString() === $mes->toDateString()
            && $to->toDateString() === $mes->copy()->endOfMonth()->toDateString();
    }

    private function mesCheio(Carbon $from, Carbon $to): ?Carbon
    {
        $inicio = $from->copy()->startOfMonth();

        if (! $this->cabeNoMes($from, $to, $inicio)) {
            return null;
        }

        return $inicio;
    }

    private function inicioUtc(Carbon $mes): Carbon
    {
        return $mes->copy()->timezone(Formato::TZ)->startOfMonth()->utc();
    }

    private function fimUtc(Carbon $mes): Carbon
    {
        return $mes->copy()->timezone(Formato::TZ)->endOfMonth()->utc();
    }

    private function nomeDoMes(Carbon $mes): string
    {
        $nome = $mes->copy()->locale('pt_BR')->translatedFormat('F');

        return mb_strtoupper(mb_substr($nome, 0, 1)).mb_substr($nome, 1).' de '.$mes->year;
    }

    /**
     * @return list<Carbon>
     */
    private function meses(?Carbon $selecionado): array
    {
        $agora = Carbon::now(Formato::TZ)->startOfMonth();
        $primeiro = TimeLog::query()->orderBy('started_at')->value('started_at');
        $comeco = $primeiro
            ? Carbon::parse($primeiro)->timezone(Formato::TZ)->startOfMonth()
            : $agora->copy();

        if ($selecionado !== null && $selecionado->lt($comeco)) {
            $comeco = $selecionado->copy()->startOfMonth();
        }

        $fim = $agora->copy();

        if ($selecionado !== null && $selecionado->gt($fim)) {
            $fim = $selecionado->copy()->startOfMonth();
        }

        $lista = [];
        $cursor = $fim->copy();

        while ($cursor->greaterThanOrEqualTo($comeco)) {
            $lista[] = $cursor->copy();
            $cursor->subMonth();
        }

        return $lista;
    }

    /**
     * @return array{user_id: int, subtask_id: int, started_at: string, duration_minutes: int}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'subtask_id' => ['required', 'integer', 'exists:subtasks,id'],
            'started_at' => ['required', 'date'],
            'duration' => ['required', 'string'],
        ], [
            'user_id.required' => 'Escolha a pessoa.',
            'subtask_id.required' => 'Escolha a tarefa.',
            'started_at.required' => 'Informe o início.',
            'duration.required' => 'Informe a duração.',
        ]);

        $minutes = Formato::minutosDeHoras($data['duration']);

        if ($minutes === null) {
            throw ValidationException::withMessages([
                'duration' => 'Informe a duração em horas, como 1,5.',
            ]);
        }

        return [
            'user_id' => (int) $data['user_id'],
            'subtask_id' => (int) $data['subtask_id'],
            'started_at' => $data['started_at'],
            'duration_minutes' => $minutes,
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
