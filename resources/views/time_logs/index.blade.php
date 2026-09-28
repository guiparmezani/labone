@extends('layouts.app')

@section('content')
    <div class="page-head">
        <h1>Lançamentos</h1>
        @if (auth()->user()->isAdmin())
            <a class="btn btn-primary" href="{{ route('lancamentos.create') }}">Novo lançamento</a>
        @endif
    </div>

    <form method="GET" action="{{ route('lancamentos.index') }}" class="panel filter-bar">
        <label class="field">
            <span>Projeto</span>
            <select name="project_id">
                <option value="">Todos</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}" @selected((string) $filters['project_id'] === (string) $project->id)>{{ $project->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="field">
            <span>Pessoa</span>
            <select name="user_id">
                <option value="">Todas</option>
                @foreach ($users as $person)
                    <option value="{{ $person->id }}" @selected((string) $filters['user_id'] === (string) $person->id)>{{ $person->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="field">
            <span>De</span>
            @include('partials.calendario', ['nome' => 'from', 'valor' => $filters['from']])
        </label>
        <label class="field">
            <span>Até</span>
            @include('partials.calendario', ['nome' => 'to', 'valor' => $filters['to']])
        </label>
        <button class="btn btn-ghost" type="submit">Filtrar</button>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Pessoa</th>
                    <th>Projeto</th>
                    <th>Subtarefa</th>
                    <th>Início</th>
                    <th>Duração</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td>{{ $log->user->name }}</td>
                        <td>{{ $log->subtask->project->name }}</td>
                        <td>{{ $log->subtask->name }}</td>
                        <td>{{ \App\Support\Formato::dataHora($log->started_at) }}</td>
                        <td>{{ $log->minutes() === null ? 'Em andamento' : \App\Support\Formato::minutos($log->minutes()) }}</td>
                        <td class="cell-end">
                            @can('update', $log)
                                <a href="{{ route('lancamentos.edit', $log) }}">Editar</a>
                            @endcan
                            @can('delete', $log)
                                <form method="POST" action="{{ route('lancamentos.destroy', $log) }}" style="display:inline" onsubmit="return confirm('Apagar este lançamento?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger" type="submit">Apagar</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">Nenhum lançamento neste período.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
