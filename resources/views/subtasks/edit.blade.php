@extends('layouts.app')

@php
    use App\Support\Formato;
@endphp

@section('content')
    <section class="panel">
        <h1>Editar subtarefa</h1>
        <p class="muted">Tempo realizado: {{ Formato::minutos($subtask->loggedMinutes()) }} já parados, {{ Formato::minutos($subtask->consumedMinutes()) }} com o ponto aberto. O valor realizado é digitado, não calculado.</p>
        <form method="POST" action="{{ route('subtarefas.update', $subtask) }}" class="stack">
            @csrf
            @method('PUT')
            <label class="field">
                <span>Nome</span>
                <input type="text" name="name" value="{{ old('name', $subtask->name) }}" required maxlength="160">
                @error('name')<small class="error">{{ $message }}</small>@enderror
            </label>
            <label class="field">
                <span>Tipo</span>
                <select name="kind" required>
                    @foreach (\App\Enums\SubtaskKind::cases() as $kind)
                        <option value="{{ $kind->value }}" @selected(old('kind', $subtask->kind->value) === $kind->value)>{{ $kind->label() }}</option>
                    @endforeach
                </select>
                @error('kind')<small class="error">{{ $message }}</small>@enderror
            </label>
            @if (auth()->user()->isAdmin())
                <label class="field">
                    <span>Tempo previsto</span>
                    <input type="text" name="planned_hours" inputmode="numeric" value="{{ old('planned_hours', $subtask->planned_minutes !== null ? Formato::duracaoEntrada($subtask->planned_minutes) : '') }}" placeholder="hh:mm">
                    @error('planned_minutes')<small class="error">{{ $message }}</small>@enderror
                </label>
            @else
                <p>Tempo previsto: {{ $subtask->planned_minutes !== null ? Formato::minutos($subtask->planned_minutes) : '—' }}. Só o administrador altera. Essas horas somam às horas previstas do projeto.</p>
            @endif
            @if (auth()->user()->isAdmin() || $subtask->kind !== \App\Enums\SubtaskKind::ThirdParty)
                <label class="field">
                    <span>Valor previsto (R$)</span>
                    <input type="text" name="budget" inputmode="decimal" value="{{ old('budget', $subtask->budget_cents !== null ? Formato::reaisEntrada($subtask->budget_cents) : '') }}" placeholder="1.800,00">
                    @error('budget_cents')<small class="error">{{ $message }}</small>@enderror
                </label>
            @else
                <p>Valor previsto da equipe terceira: {{ $subtask->budget_cents !== null ? Formato::reais($subtask->budget_cents) : '—' }}. Só o administrador altera.</p>
            @endif
            <label class="field">
                <span>Valor realizado (R$)</span>
                <input type="text" name="realized" inputmode="decimal" value="{{ old('realized', $subtask->realized_cents !== null ? Formato::reaisEntrada($subtask->realized_cents) : '') }}" placeholder="900,00">
                @error('realized_cents')<small class="error">{{ $message }}</small>@enderror
            </label>
            <label class="field">
                <span>Alarme (%)</span>
                <input type="number" name="alert_percentage" min="1" max="100" step="1" value="{{ old('alert_percentage', $subtask->alert_enabled ? $subtask->alert_percentage : '') }}">
                @error('alert_percentage')<small class="error">{{ $message }}</small>@enderror
            </label>
            <label class="check">
                <input class="revision-toggle" type="checkbox" name="is_revision" value="1" @checked(session()->hasOldInput() ? (bool) old('is_revision') : $subtask->is_revision)>
                <span>Revisão</span>
            </label>
            <div class="revision-fields">
                <label class="field">
                    <span>Descrição da revisão</span>
                    <textarea name="revision_notes" maxlength="2000">{{ old('revision_notes', $subtask->revision_notes) }}</textarea>
                    @error('revision_notes')<small class="error">{{ $message }}</small>@enderror
                </label>
                <label class="field">
                    <span>Equipe terceira relacionada</span>
                    <select name="revision_of_subtask_id">
                        <option value="">Nenhuma</option>
                        @foreach ($thirdParties as $third)
                            <option value="{{ $third->id }}" @selected((string) old('revision_of_subtask_id', $subtask->revision_of_subtask_id) === (string) $third->id)>{{ $third->name }}</option>
                        @endforeach
                    </select>
                    @error('revision_of_subtask_id')<small class="error">{{ $message }}</small>@enderror
                </label>
            </div>
            <div class="actions">
                <button class="btn btn-primary" type="submit">Salvar</button>
                <a class="btn btn-ghost" href="{{ route('projetos.show', $subtask->project_id) }}">Cancelar</a>
            </div>
        </form>
    </section>
@endsection
