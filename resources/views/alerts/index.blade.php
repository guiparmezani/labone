@extends('layouts.app')

@php
    use App\Support\Formato;
@endphp

@section('content')
    <div class="page-head">
        <h1>Alertas</h1>
    </div>

    <section class="panel" style="margin-bottom: 1rem;">
        <h2>Jornada</h2>
        @php
            $porData = $gaps
                ->sortByDesc(fn ($gap) => ($gap->startedAt ?? $gap->since)->getTimestamp())
                ->groupBy(fn ($gap) => $gap->since->format('Y-m-d'))
                ->sortKeysDesc();
        @endphp
        @forelse ($porData as $doDia)
            <h3 class="alert-day">{{ Formato::data($doDia->first()->since) }}</h3>
            <ul class="list">
                @foreach ($doDia as $gap)
                    <li>
                        <span>
                            {{ $gap->user->name }} não iniciou uma tarefa desde {{ Formato::hora($gap->since) }}.
                            @if ($gap->startedAt)
                                Iniciou às {{ Formato::hora($gap->startedAt) }}.
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        @empty
            <p class="muted">Ninguém parado na jornada.</p>
        @endforelse
        @if ($podeCarregar)
            <form class="alert-more" method="POST" action="{{ route('alertas.carregar') }}">
                @csrf
                <input type="hidden" name="anteriores" value="{{ $anteriores + 5 }}">
                <button class="btn btn-ghost" type="submit">Carregar dias anteriores</button>
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
