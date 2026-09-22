@extends('layouts.app')

@section('content')
    <section class="panel panel-narrow">
        <h1>Copiar projeto</h1>
        <p class="muted">Copia as subtarefas, os valores previstos e os alarmes de {{ $project->name }}. Horas lançadas e o valor realizado ficam de fora. O novo projeto abre em branco.</p>
        <form method="POST" action="{{ route('projetos.copy.store', $project) }}" class="stack">
            @csrf
            <label class="field">
                <span>Nome</span>
                <input type="text" name="name" value="{{ old('name', $project->name.' (cópia)') }}" required maxlength="160">
                @error('name')<small class="error">{{ $message }}</small>@enderror
            </label>
            <div class="actions">
                <button class="btn btn-primary" type="submit">Copiar</button>
                <a class="btn btn-ghost" href="{{ route('projetos.show', $project) }}">Cancelar</a>
            </div>
        </form>
    </section>
@endsection
