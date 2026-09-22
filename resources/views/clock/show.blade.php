@extends('layouts.app')

@section('content')
    <h1>{{ $project->name }}</h1>

    @if ($openLog)
        @include('clock._running')
        <p>Você já tem um ponto em andamento. Pare esse ponto antes de iniciar outro.</p>
    @elseif (! $project->isOpen())
        <p>Este item não aceita ponto.</p>
    @else
        <section class="panel">
            <ul class="list">
                @forelse ($subtasks as $subtask)
                    <li>
                        <form method="POST" action="{{ route('ponto.start') }}" class="row-link">
                            @csrf
                            <input type="hidden" name="subtask_id" value="{{ $subtask->id }}">
                            <span>{{ $subtask->name }}</span>
                            <button class="btn btn-primary" type="submit">Iniciar</button>
                        </form>
                    </li>
                @empty
                    <li><p>Nenhuma subtarefa interna.</p></li>
                @endforelse
            </ul>
            @error('subtask_id')<p class="error">{{ $message }}</p>@enderror
        </section>
    @endif

    @if ($project->isOpen())
        <section class="panel" style="margin-top: 1rem;">
            <h2>Nova subtarefa</h2>
            <form method="POST" action="{{ route('subtarefas.store', $project) }}" class="stack">
                @csrf
                <label class="field">
                    <span>Nome</span>
                    <input type="text" name="name" value="{{ old('name') }}" required maxlength="160">
                    @error('name')<small class="error">{{ $message }}</small>@enderror
                </label>
                <button class="btn btn-primary" type="submit">Adicionar</button>
            </form>
        </section>
    @endif
@endsection
