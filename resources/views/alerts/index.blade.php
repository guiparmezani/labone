@extends('layouts.app')

@php
    use App\Support\Formato;
@endphp

@section('content')
    <div class="page-head">
        <h1>Alertas</h1>
    </div>

    <section class="panel shift-alerts">
        <h2>Jornada</h2>
        @php
            $porData = $gaps
                ->groupBy(fn ($gap) => $gap->since->format('Y-m-d'))
                ->sortKeysDesc();
        @endphp
        @forelse ($porData as $doDia)
            <h3 class="alert-day">{{ Formato::data($doDia->first()->since) }}</h3>
            <ul class="shift-alert-list">
                @foreach ($doDia as $gap)
                    <li @class(['shift-alert', 'is-ongoing' => $gap->isOngoing()])>
                        <p class="shift-alert-message"><strong>{{ $gap->user->name }}</strong> {{ $gap->isOngoing() ? 'está' : 'ficou' }} <strong>{{ Formato::duracaoCurta($gap->minutes()) }}</strong> sem iniciar uma tarefa</p>
                        <p class="shift-alert-since">Desde <time datetime="{{ $gap->since->toIso8601String() }}">{{ Formato::hora($gap->since) }}</time></p>
                    </li>
                @endforeach
            </ul>
        @empty
            <p class="muted">Nenhum alerta de jornada nos últimos 30 dias.</p>
        @endforelse
        @if ($podeCarregar)
            <form class="alert-more" method="POST" action="{{ route('alertas.carregar') }}">
                @csrf
                <input type="hidden" name="anteriores" value="{{ $anteriores + 5 }}">
                <button class="btn btn-ghost" type="submit">Carregar mais</button>
            </form>
        @endif
    </section>

    <section class="panel">
        <h2>Alertas atingidos</h2>
        <ul class="list">
            @forelse ($reached as $alert)
                <li>
                    <a class="row-link" href="{{ route('projetos.show', $alert->project) }}">
                        <span>{{ $alert->project->name }} — {{ $alert->name }} · {{ $alert->alert_percentage }}%</span>
                        <span class="muted">
                            {{ Formato::minutos($alert->consumedMinutes()) }}
                            de {{ Formato::minutos($alert->planned_minutes) }} previstas
                        </span>
                    </a>
                </li>
            @empty
                <li><p class="muted">Nenhum alerta atingido.</p></li>
            @endforelse
        </ul>
    </section>
@endsection
