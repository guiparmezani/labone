@extends('layouts.app')

@php
    use App\Support\Formato;

    $previstoSubtarefas = (int) $project->subtasks->sum('budget_cents');
    $realizado = (int) $project->subtasks->sum('realized_cents');
    $revisions = $project->subtasks->where('is_revision', true);
@endphp

@section('content')
    <div class="page-head no-print">
        <div>
            <h1>Relatório · {{ $project->name }}</h1>
            <p class="muted">Para guardar como referência de um trabalho parecido.</p>
        </div>
        <div class="actions">
            <a class="btn btn-ghost" href="{{ route('projetos.show', $project) }}">Voltar</a>
        </div>
    </div>

    <section class="panel">
        <h1 class="print-title">{{ $project->name }}</h1>
        <p>{{ $project->status->label() }}</p>
        @if ($project->notes)
            <p>{{ $project->notes }}</p>
        @endif

        <h2>Equipe interna</h2>
        <table>
            <thead>
                <tr>
                    <th>Tarefa</th>
                    <th>Tempo previsto</th>
                    <th>Tempo realizado</th>
                    <th>Valor previsto</th>
                    <th>Valor realizado</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($internal as $subtask)
                    <tr>
                        <td>
                            {{ $subtask->name }}
                            @if ($subtask->revision_notes)
                                <br><span class="muted">{{ $subtask->revision_notes }}</span>
                            @endif
                        </td>
                        <td>{{ $subtask->planned_minutes !== null ? Formato::minutos($subtask->planned_minutes) : '—' }}</td>
                        <td>{{ Formato::minutos($subtask->consumedMinutes()) }}</td>
                        <td>{{ $subtask->budget_cents !== null ? Formato::reais($subtask->budget_cents) : '—' }}</td>
                        <td>{{ $subtask->realized_cents !== null ? Formato::reais($subtask->realized_cents) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">Nenhuma tarefa.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h2>Equipe terceira</h2>
        <table>
            <thead>
                <tr>
                    <th>Tarefa</th>
                    <th>Tempo previsto</th>
                    <th>Valor previsto</th>
                    <th>Valor realizado</th>
                    <th>Revisões</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($thirdParty as $subtask)
                    <tr>
                        <td>
                            {{ $subtask->name }}
                            @if ($subtask->revision_notes)
                                <br><span class="muted">{{ $subtask->revision_notes }}</span>
                            @endif
                        </td>
                        <td>{{ $subtask->planned_minutes !== null ? Formato::minutos($subtask->planned_minutes) : '—' }}</td>
                        <td>{{ $subtask->budget_cents !== null ? Formato::reais($subtask->budget_cents) : '—' }}</td>
                        <td>{{ $subtask->realized_cents !== null ? Formato::reais($subtask->realized_cents) : '—' }}</td>
                        <td>{{ $revisions->where('revision_of_subtask_id', $subtask->id)->count() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">Nenhuma equipe terceira.</td></tr>
                @endforelse
            </tbody>
        </table>

        @if ($revisions->isNotEmpty())
            <h2>Revisões</h2>
            <table>
                <thead>
                    <tr>
                        <th>Tarefa</th>
                        <th>Equipe terceira</th>
                        <th>Tempo previsto</th>
                        <th>Tempo realizado</th>
                        <th>Valor previsto</th>
                        <th>Valor realizado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($revisions as $revision)
                        <tr>
                            <td>
                                {{ $revision->name }}
                                @if ($revision->revision_notes)
                                    <br><span class="muted">{{ $revision->revision_notes }}</span>
                                @endif
                            </td>
                            <td>{{ $revision->revisionOf->name ?? '—' }}</td>
                            <td>{{ $revision->planned_minutes !== null ? Formato::minutos($revision->planned_minutes) : '—' }}</td>
                            <td>{{ Formato::minutos($revision->consumedMinutes()) }}</td>
                            <td>{{ $revision->budget_cents !== null ? Formato::reais($revision->budget_cents) : '—' }}</td>
                            <td>{{ $revision->realized_cents !== null ? Formato::reais($revision->realized_cents) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div class="sheet-foot">
            <div>
                <p class="muted">Horas previstas</p>
                <strong>{{ Formato::minutos($project->plannedMinutesTotal()) }}</strong>
                <p class="muted">{{ Formato::minutos($project->planned_minutes) }} do projeto + {{ Formato::minutos($project->plannedMinutesFromSubtasks()) }} das tarefas</p>
            </div>
            <div>
                <p class="muted">Horas realizadas</p>
                <strong>{{ Formato::minutos($project->consumedMinutes()) }}</strong>
                <p class="muted">Inclui o ponto em andamento</p>
            </div>
            <div>
                <p class="muted">Valor previsto</p>
                <strong>{{ Formato::reais($project->budget_cents + $previstoSubtarefas) }}</strong>
                <p class="muted">{{ Formato::reais($project->budget_cents) }} do projeto + {{ Formato::reais($previstoSubtarefas) }} das tarefas</p>
            </div>
            <div>
                <p class="muted">Valor realizado</p>
                <strong>{{ Formato::reais($realizado) }}</strong>
                <p class="muted">Soma do que foi digitado nas tarefas</p>
            </div>
        </div>

        <div class="sheet-download no-print actions">
            <a class="btn btn-primary" href="{{ route('projetos.relatorio.csv', $project) }}">Exportar</a>
            <button class="btn btn-ghost" type="button" onclick="window.print()">Imprimir</button>
        </div>
    </section>
@endsection
