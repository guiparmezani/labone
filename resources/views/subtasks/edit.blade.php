@extends('layouts.app')

@php
    use App\Support\Formato;
@endphp

@section('content')
    <section class="panel">
        <h1>Editar subtarefa</h1>
        <p class="muted">Tempo realizado: {{ Formato::minutos($subtask->loggedMinutes()) }}. O ponto em andamento não entra nessa conta. O valor realizado é digitado, não calculado.</p>
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
            <label class="field">
                <span>Tempo previsto</span>
                <input type="text" name="planned_hours" inputmode="decimal" value="{{ old('planned_hours', $subtask->planned_minutes !== null ? Formato::horasEntrada($subtask->planned_minutes) : '') }}" placeholder="1,5">
                @error('planned_minutes')<small class="error">{{ $message }}</small>@enderror
            </label>
            <label class="field">
                <span>Valor previsto (R$)</span>
                <input type="text" name="budget" inputmode="decimal" value="{{ old('budget', $subtask->budget_cents !== null ? Formato::reaisEntrada($subtask->budget_cents) : '') }}" placeholder="1.800,00">
                @error('budget_cents')<small class="error">{{ $message }}</small>@enderror
            </label>
            <label class="field">
                <span>Valor realizado (R$)</span>
                <input type="text" name="realized" inputmode="decimal" value="{{ old('realized', $subtask->realized_cents !== null ? Formato::reaisEntrada($subtask->realized_cents) : '') }}" placeholder="900,00">
                @error('realized_cents')<small class="error">{{ $message }}</small>@enderror
            </label>
            <label class="check">
                <input type="checkbox" name="alert_enabled" value="1" @checked(session()->hasOldInput() ? (bool) old('alert_enabled') : $subtask->alert_enabled)>
                <span>Alarme ligado</span>
            </label>
            <label class="field">
                <span>Alarme (%)</span>
                <input type="number" name="alert_percentage" min="1" max="100" step="1" value="{{ old('alert_percentage', $subtask->alert_percentage) }}">
                @error('alert_percentage')<small class="error">{{ $message }}</small>@enderror
            </label>
            <p class="muted">Ligado, o alarme aparece no início quando as horas lançadas chegam nessa porcentagem do tempo previsto.</p>
            <div class="actions">
                <button class="btn btn-primary" type="submit">Salvar</button>
                <a class="btn btn-ghost" href="{{ route('projetos.show', $subtask->project_id) }}">Cancelar</a>
            </div>
        </form>
    </section>
@endsection
