@extends('layouts.app')

@section('content')
    <div class="split">
        <section class="panel">
            <h1>Projetos em andamento</h1>
            <ul class="list">
                @forelse ($projects as $project)
                    <li>
                        <a class="row-link" href="{{ route('projetos.ponto', $project) }}">
                            <span>{{ $project->name }}</span>
                        </a>
                    </li>
                @empty
                    <li><p class="muted">Nenhum projeto aberto.</p></li>
                @endforelse
            </ul>
        </section>
        <section>
            @if ($openLog)
                <div class="clock-bar">
                    <div>
                        <strong>{{ $openLog->subtask->project->name }}</strong>
                        <p>{{ $openLog->subtask->name }}</p>
                        <p>Desde {{ \App\Support\Formato::hora($openLog->started_at) }}</p>
                    </div>
                    <form method="POST" action="{{ route('ponto.stop') }}">
                        @csrf
                        <button class="btn btn-primary btn-phone" type="submit">Parar</button>
                    </form>
                </div>
            @else
                <section class="panel">
                    <h2>Ponto</h2>
                    <p>Você não tem ponto em andamento.</p>
                </section>
            @endif
        </section>
    </div>
@endsection
