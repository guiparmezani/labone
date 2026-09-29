@extends('layouts.app')

@php
    use App\Enums\Role;
    use App\Support\Formato;
@endphp

@section('content')
    <div class="page-head">
        <h1>Relatórios</h1>
    </div>

    <p class="muted">A lista mostra todo o histórico. Pontos em andamento não entram na soma. Orçamento e horas previstas são do projeto inteiro.</p>

    <section class="report-block">
        <h2>Por projeto</h2>
        <form method="GET" action="{{ route('relatorios.index') }}" class="panel filter-bar filter-bar-status">
            <label class="field">
                <span>Projeto</span>
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Nome do projeto">
            </label>
            <label class="field">
                <span>Situação</span>
                <select name="status">
                    <option value="">Todas</option>
                    @foreach (\App\Enums\ProjectStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </label>
            <button class="btn btn-ghost" type="submit">Filtrar</button>
        </form>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Projeto</th>
                        <th>Situação</th>
                        <th>Orçamento</th>
                        <th>Horas previstas</th>
                        <th>Horas lançadas</th>
                        <th>Orçamento de terceiros</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($projects as $project)
                        <tr class="is-clickable" tabindex="0" data-folha="{{ route('projetos.relatorio', $project) }}">
                            <td>{{ $project->name }}</td>
                            <td>{{ $project->status->label() }}</td>
                            <td>{{ Formato::reais($project->budget_cents) }}</td>
                            <td>{{ Formato::minutos($project->plannedMinutesTotal()) }}</td>
                            <td>{{ Formato::minutos($project->loggedMinutes()) }}</td>
                            <td>{{ Formato::reais($project->thirdPartyBudgetCents()) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6">Nenhum projeto.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <a class="btn btn-primary" href="{{ route('relatorios.projects', array_filter(['q' => $filters['q'], 'status' => $filters['status']])) }}">Exportar tudo</a>
    </section>

    <section class="report-block">
        <h2>Por operador</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Papel</th>
                        <th>Horas</th>
                        <th>Por projeto</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($operators as $operator)
                        <tr class="is-clickable" tabindex="0" data-folha="{{ route('relatorios.person', $operator->id) }}">
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
                        <tr><td colspan="4">Nenhuma hora lançada.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <a class="btn btn-primary" href="{{ route('relatorios.people') }}">Exportar tudo</a>
    </section>

    <script>
        (function () {
            document.querySelectorAll('[data-folha]').forEach(function (linha) {
                linha.addEventListener('click', function () { window.location = linha.dataset.folha; });
                linha.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        window.location = linha.dataset.folha;
                    }
                });
            });
        })();
    </script>
@endsection
