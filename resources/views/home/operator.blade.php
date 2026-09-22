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
                @include('clock._running')
            @else
                <section class="panel">
                    <h2>Ponto</h2>
                    <p>Você não tem ponto em andamento.</p>
                </section>
            @endif
        </section>
    </div>
@endsection
