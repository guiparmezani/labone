@extends('layouts.app')

@php
    use App\Enums\Role;
    use App\Support\Formato;
    $query = ['from' => $report->fromDate(), 'to' => $report->toDate()];
@endphp

@section('content')
    <div class="page-head">
        <h1>Relatórios</h1>
        <div class="actions">
            <a class="btn btn-ghost" href="{{ route('relatorios.hours', $query) }}">Exportar horas</a>
            <a class="btn btn-ghost" href="{{ route('relatorios.projects', $query) }}">Exportar projetos</a>
        </div>
    </div>

    <form method="GET" action="{{ route('relatorios.index') }}" class="panel stack" style="margin-bottom: 1rem;">
        <label class="field">
            <span>De</span>
            <input type="date" name="from" value="{{ $report->fromDate() }}">
        </label>
        <label class="field">
            <span>Até</span>
            <input type="date" name="to" value="{{ $report->toDate() }}">
        </label>
        <button class="btn btn-primary" type="submit">Atualizar</button>
    </form>

    <p class="muted">Pontos em andamento não entram na soma. Orçamento e horas previstas são do projeto inteiro, não do período.</p>

    <h2>Por projeto</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Projeto</th>
                    <th>Situação</th>
                    <th>Orçamento</th>
                    <th>Horas previstas</th>
                    <th>Horas lançadas no período</th>
                    <th>Orçamento de terceiros</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($projects as $project)
                    <tr>
                        <td>{{ $project->name }}</td>
                        <td>{{ $project->status->label() }}</td>
                        <td>{{ Formato::reais($project->budget_cents) }}</td>
                        <td>{{ Formato::minutos($project->planned_minutes) }}</td>
                        <td>{{ Formato::minutos($project->loggedMinutes()) }}</td>
                        <td>{{ Formato::reais($project->thirdPartyBudgetCents()) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">Nenhum projeto.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <h2>Por operador</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Papel</th>
                    <th>Horas no período</th>
                    <th>Por projeto</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($operators as $operator)
                    <tr>
                        <td>{{ $operator->name }}</td>
                        <td>{{ Role::from($operator->role)->label() }}</td>
                        <td>{{ Formato::minutos($operator->minutes) }}</td>
                        <td>
                            @foreach ($operator->projects as $line)
                                {{ $line->name }} {{ Formato::minutos($line->minutes) }}@if (! $loop->last), @endif
                            @endforeach
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4">Nenhuma hora lançada neste período.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
