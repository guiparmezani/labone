@extends('layouts.app')

@section('content')
    <div class="page-head">
        <h1>Projetos</h1>
        <a class="btn btn-primary" href="{{ route('projetos.create') }}">Novo projeto</a>
    </div>

    @error('project')
        <p class="error">{{ $message }}</p>
    @enderror

    <nav class="filters">
        <a href="{{ route('projetos.index', ['status' => 'open']) }}" @class(['is-current' => $status === 'open'])>Abertos</a>
        <a href="{{ route('projetos.index', ['status' => 'closed']) }}" @class(['is-current' => $status === 'closed'])>Encerrados</a>
        <a href="{{ route('projetos.index', ['status' => 'all']) }}" @class(['is-current' => $status === 'all'])>Todos</a>
    </nav>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Situação</th>
                    <th>Orçamento</th>
                    <th>Horas previstas</th>
                    <th>Horas lançadas</th>
                    <th>Terceiros</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($projects as $project)
                    <tr>
                        <td><a href="{{ route('projetos.show', $project) }}">{{ $project->name }}</a></td>
                        <td>{{ $project->status->label() }}</td>
                        <td>{{ \App\Support\Formato::reais($project->budget_cents) }}</td>
                        <td>{{ \App\Support\Formato::minutos($project->planned_minutes) }}</td>
                        <td>{{ \App\Support\Formato::minutos($project->loggedMinutes()) }}</td>
                        <td>{{ \App\Support\Formato::reais($project->thirdPartyBudgetCents()) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">Nenhum projeto nesta lista.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
