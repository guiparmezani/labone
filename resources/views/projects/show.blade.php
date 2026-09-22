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
            <a class="btn btn-ghost" href="{{ route('projetos.copy', $project) }}">Copiar</a>
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
        @if ($project->isOpen())
            <form method="POST" action="{{ route('subtarefas.store', $project) }}" class="inline-form cols-2">
                @csrf
                <input type="hidden" name="kind" value="internal">
                <label class="field">
                    <span>Nome</span>
                    <input type="text" name="name" value="{{ old('kind') === 'internal' ? old('name') : '' }}" required maxlength="160" placeholder="Ex.: Usinagem">
                </label>
                <button class="btn btn-primary" type="submit">Adicionar subtarefa</button>
                @error('name')
                    @if (old('kind') === 'internal')<small class="error">{{ $message }}</small>@endif
                @enderror
            </form>
        @endif
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Tempo previsto</th>
                        <th>Tempo realizado</th>
                        <th>Valor previsto</th>
                        <th>Valor realizado</th>
                        <th>Alarme</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($internal as $subtask)
                        <tr>
                            <td>{{ $subtask->name }}</td>
                            <td>{{ $subtask->planned_minutes !== null ? Formato::minutos($subtask->planned_minutes) : '—' }}</td>
                            <td>{{ Formato::minutos($subtask->loggedMinutes()) }}</td>
                            <td>{{ $subtask->budget_cents !== null ? Formato::reais($subtask->budget_cents) : '—' }}</td>
                            <td>{{ $subtask->realized_cents !== null ? Formato::reais($subtask->realized_cents) : '—' }}</td>
                            <td>
                                @if ($subtask->alert_enabled && $subtask->alert_percentage)
                                    {{ $subtask->alert_percentage }}%@if ($subtask->alertReached()) · Atingido @endif
                                @else
                                    —
                                @endif
                            </td>
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
                        <tr><td colspan="7">Nenhuma subtarefa interna.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel" style="margin-bottom: 1rem;">
        <h2>Equipe terceira</h2>
        <p class="muted">Sem ponto. O valor previsto é o combinado com a equipe de fora. O valor realizado é digitado.</p>
        @if ($project->isOpen())
            <form method="POST" action="{{ route('subtarefas.store', $project) }}" class="inline-form">
                @csrf
                <input type="hidden" name="kind" value="third_party">
                <label class="field">
                    <span>Nome</span>
                    <input type="text" name="name" value="{{ old('kind') === 'third_party' ? old('name') : '' }}" required maxlength="160" placeholder="Ex.: Tratamento térmico">
                </label>
                <label class="field">
                    <span>Valor previsto (R$)</span>
                    <input type="text" name="budget" inputmode="decimal" value="{{ old('kind') === 'third_party' ? old('budget') : '' }}" required placeholder="1.800,00">
                </label>
                <button class="btn btn-primary" type="submit">Adicionar equipe terceira</button>
                @if (old('kind') === 'third_party')
                    @error('name')<small class="error">{{ $message }}</small>@enderror
                    @error('budget_cents')<small class="error">{{ $message }}</small>@enderror
                @endif
            </form>
        @endif
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Tempo previsto</th>
                        <th>Valor previsto</th>
                        <th>Valor realizado</th>
                        <th>Alarme</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($thirdParty as $subtask)
                        <tr>
                            <td>{{ $subtask->name }}</td>
                            <td>{{ $subtask->planned_minutes !== null ? Formato::minutos($subtask->planned_minutes) : '—' }}</td>
                            <td>{{ $subtask->budget_cents !== null ? Formato::reais($subtask->budget_cents) : '—' }}</td>
                            <td>{{ $subtask->realized_cents !== null ? Formato::reais($subtask->realized_cents) : '—' }}</td>
                            <td>
                                @if ($subtask->alert_enabled && $subtask->alert_percentage)
                                    {{ $subtask->alert_percentage }}%
                                @else
                                    —
                                @endif
                            </td>
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
                        <tr><td colspan="6">Nenhuma subtarefa de equipe terceira.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

@endsection
