@extends('layouts.app')

@section('content')
    @isset($reachedAlerts)
        <section class="panel" style="margin-bottom: 1rem;">
            <h1>Alertas atingidos</h1>
            <ul class="list">
                @forelse ($reachedAlerts as $alert)
                    <li>
                        <a class="row-link" href="{{ route('projetos.show', $alert->project) }}">
                            <span>{{ $alert->project->name }} — {{ $alert->name }} · {{ $alert->alert_percentage }}%</span>
                            <span class="muted">
                                {{ \App\Support\Formato::minutos($alert->loggedMinutes()) }}
                                de {{ \App\Support\Formato::minutos($alert->planned_minutes) }} previstas
                            </span>
                        </a>
                    </li>
                @empty
                    <li><p class="muted">Nenhum alerta atingido.</p></li>
                @endforelse
            </ul>
        </section>
    @endisset
    <div class="split">
        <section class="panel">
            <h1>Projetos em andamento</h1>
            <ul class="list">
                @forelse ($projects as $project)
                    <li>
                        <a class="row-link" href="{{ route('projetos.show', $project) }}">
                            <span>{{ $project->name }}</span>
                            <span class="muted">
                                {{ \App\Support\Formato::reais($project->budget_cents) }}
                                · {{ \App\Support\Formato::minutos($project->planned_minutes) }}
                                · {{ \App\Support\Formato::minutos($project->loggedMinutes()) }}
                                · terceiros {{ \App\Support\Formato::reais($project->thirdPartyBudgetCents()) }}
                            </span>
                        </a>
                    </li>
                @empty
                    <li><p class="muted">Nenhum projeto aberto.</p></li>
                @endforelse
            </ul>
        </section>
        <section class="panel">
            <h2>Pontos em andamento</h2>
            @if ($openLogs->isEmpty())
                <p>Nenhum ponto em andamento.</p>
            @else
                <ul class="list" id="open-logs">
                    @foreach ($openLogs as $log)
                        <li data-started-at="{{ $log->started_at->toIso8601String() }}">
                            <p><strong>{{ $log->user->name }}</strong></p>
                            <p>{{ $log->subtask->project->name }} — {{ $log->subtask->name }}</p>
                            <p class="elapsed">{{ \App\Support\Formato::cronometro($log->started_at) }}</p>
                            <p>Desde {{ \App\Support\Formato::hora($log->started_at) }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
@endsection
