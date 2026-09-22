@extends('layouts.app')

@section('content')
    <div class="page-head">
        <h1>Lançamentos</h1>
        <a class="btn btn-primary" href="{{ route('lancamentos.create') }}">Novo lançamento</a>
    </div>

    <form method="GET" action="{{ route('lancamentos.index') }}" class="panel stack" style="margin-bottom: 1rem;">
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
            <input type="date" name="from" value="{{ $filters['from'] }}">
        </label>
        <label class="field">
            <span>Até</span>
            <input type="date" name="to" value="{{ $filters['to'] }}">
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
                    <th>Fim</th>
                    <th>Duração</th>
                    <th>Origem</th>
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
                        <td>{{ $log->ended_at ? \App\Support\Formato::dataHora($log->ended_at) : 'Em andamento' }}</td>
                        <td>{{ $log->minutes() === null ? '—' : \App\Support\Formato::minutos($log->minutes()) }}</td>
                        <td>{{ $log->source->label() }}</td>
                        <td class="cell-end">
                            <a href="{{ route('lancamentos.edit', $log) }}">Editar</a>
                            <form method="POST" action="{{ route('lancamentos.destroy', $log) }}" style="display:inline" onsubmit="return confirm('Apagar este lançamento?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-danger" type="submit">Apagar</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8">Nenhum lançamento neste período.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
