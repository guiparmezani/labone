@extends('layouts.app')

@php
    use App\Support\Formato;
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $project->name }}</h1>
            <p class="muted">{{ $project->status->label() }} · {{ Formato::reais($project->budget_cents) }} · {{ Formato::minutos($project->planned_minutes) }} previstas · {{ Formato::minutos($project->loggedMinutes()) }} lançadas</p>
        </div>
        <div class="actions">
            <a class="btn btn-ghost" href="{{ route('projetos.edit', $project) }}">Editar</a>
            @if ($project->isOpen())
                <form method="POST" action="{{ route('projetos.close', $project) }}">@csrf<button class="btn btn-ghost" type="submit">Encerrar</button></form>
            @else
                <form method="POST" action="{{ route('projetos.reopen', $project) }}">@csrf<button class="btn btn-ghost" type="submit">Reabrir</button></form>
            @endif
            <form method="POST" action="{{ route('projetos.destroy', $project) }}" onsubmit="return confirm('Apagar este projeto?')">
                @csrf
                @method('DELETE')
                <button class="btn btn-danger" type="submit">Apagar</button>
            </form>
        </div>
    </div>

    @error('project')<p class="error">{{ $message }}</p>@enderror
    @error('subtask')<p class="error">{{ $message }}</p>@enderror
    @if ($project->notes)
        <p>{{ $project->notes }}</p>
    @endif

    <section class="panel" style="margin-bottom: 1rem;">
        <h2>Subtarefas internas</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Nome</th><th>Horas lançadas</th><th></th></tr></thead>
                <tbody>
                    @forelse ($internal as $subtask)
                        <tr>
                            <td>{{ $subtask->name }}</td>
                            <td>{{ Formato::minutos($subtask->loggedMinutes()) }}</td>
                            <td class="cell-end">
                                <a href="{{ route('subtarefas.edit', $subtask) }}">Editar</a>
                                @if ($subtask->timeLogs()->exists())
                                @else
                                    <form method="POST" action="{{ route('subtarefas.destroy', $subtask) }}" style="display:inline" onsubmit="return confirm('Apagar esta subtarefa?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-danger" type="submit">Apagar</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3">Nenhuma subtarefa interna.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel" style="margin-bottom: 1rem;">
        <h2>Equipe terceira</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Nome</th><th>Orçamento</th><th></th></tr></thead>
                <tbody>
                    @forelse ($thirdParty as $subtask)
                        <tr>
                            <td>{{ $subtask->name }}</td>
                            <td>{{ Formato::reais((int) $subtask->budget_cents) }}</td>
                            <td class="cell-end">
                                <a href="{{ route('subtarefas.edit', $subtask) }}">Editar</a>
                                @if (! $subtask->timeLogs()->exists())
                                    <form method="POST" action="{{ route('subtarefas.destroy', $subtask) }}" style="display:inline" onsubmit="return confirm('Apagar esta subtarefa?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-danger" type="submit">Apagar</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3">Nenhuma subtarefa de equipe terceira.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($project->isOpen())
        <section class="panel panel-narrow">
            <h2>Nova subtarefa</h2>
            <form method="POST" action="{{ route('subtarefas.store', $project) }}" class="stack">
                @csrf
                <label class="field">
                    <span>Nome</span>
                    <input type="text" name="name" value="{{ old('name') }}" required maxlength="160">
                    @error('name')<small class="error">{{ $message }}</small>@enderror
                </label>
                <label class="field">
                    <span>Tipo</span>
                    <select name="kind" required>
                        <option value="internal" @selected(old('kind', 'internal') === 'internal')>Interna</option>
                        <option value="third_party" @selected(old('kind') === 'third_party')>Equipe terceira</option>
                    </select>
                    @error('kind')<small class="error">{{ $message }}</small>@enderror
                </label>
                <label class="field">
                    <span>Orçamento da equipe terceira (R$)</span>
                    <input type="text" name="budget" inputmode="decimal" value="{{ old('budget') }}">
                    @error('budget_cents')<small class="error">{{ $message }}</small>@enderror
                </label>
                <button class="btn btn-primary" type="submit">Adicionar</button>
            </form>
        </section>
    @endif
@endsection
