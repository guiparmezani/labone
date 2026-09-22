@extends('layouts.app')

@section('content')
    <section class="panel panel-narrow">
        <h1>Editar subtarefa</h1>
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
                <span>Orçamento da equipe terceira (R$)</span>
                <input type="text" name="budget" inputmode="decimal" value="{{ old('budget', $subtask->budget_cents !== null ? \App\Support\Formato::reaisEntrada($subtask->budget_cents) : '') }}">
                @error('budget_cents')<small class="error">{{ $message }}</small>@enderror
            </label>
            <div class="actions">
                <button class="btn btn-primary" type="submit">Salvar</button>
                <a class="btn btn-ghost" href="{{ route('projetos.show', $subtask->project_id) }}">Cancelar</a>
            </div>
        </form>
    </section>
@endsection
