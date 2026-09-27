@extends('layouts.app')

@section('content')
    <h1>{{ $project->name }}</h1>

    @if ($openLogs->isNotEmpty())
        <div class="stack" style="margin-bottom: 1rem;">
            @foreach ($openLogs as $openLog)
                @include('clock._running')
            @endforeach
        </div>
    @endif

    @if (! $project->isOpen())
        <p>Este item não aceita ponto.</p>
    @else
        <section class="panel">
            <ul class="list">
                @forelse ($subtasks as $subtask)
                    @php $running = $openLogs->firstWhere('subtask_id', $subtask->id); @endphp
                    <li>
                        @if ($running)
                            <form method="POST" action="{{ route('ponto.stop') }}" class="row-link">
                                @csrf
                                <input type="hidden" name="time_log_id" value="{{ $running->id }}">
                                <span>{{ $subtask->name }}</span>
                                <button class="btn btn-primary" type="submit">Parar</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('ponto.start') }}" class="row-link">
                                @csrf
                                <input type="hidden" name="subtask_id" value="{{ $subtask->id }}">
                                <span>{{ $subtask->name }}</span>
                                <button class="btn btn-primary" type="submit">Iniciar</button>
                            </form>
                        @endif
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
