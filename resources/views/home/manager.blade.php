@extends('layouts.app')

@section('content')
    @if ($myOpenLogs->isNotEmpty())
        <div class="stack home-clocks">
            @foreach ($myOpenLogs as $openLog)
                @include('clock._running')
            @endforeach
        </div>
    @endif
    <div class="home-columns">
        <div class="stack">
            <section class="panel">
                <h1>Projetos em andamento</h1>
                <ul class="list">
                    @forelse ($projects as $project)
                        <li>
                            <a class="row-link" href="{{ route('projetos.show', $project) }}">
                                <span>{{ $project->name }}</span>
                                <span class="muted">
                                    {{ \App\Support\Formato::reais($project->budget_cents) }}
                                    · {{ \App\Support\Formato::minutos($project->plannedMinutesTotal()) }}
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
            @isset($reachedAlerts)
                <section class="panel">
                    <h2>Alertas atingidos</h2>
                    <ul class="list">
                        @forelse ($reachedAlerts as $alert)
                            <li>
                                <a class="row-link" href="{{ route('projetos.show', $alert->project) }}">
                                    <span>{{ $alert->project->name }} — {{ $alert->name }} · {{ $alert->alert_percentage }}%</span>
                                    <span class="muted">
                                        {{ \App\Support\Formato::minutos($alert->consumedMinutes()) }}
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
            <section class="panel">
                <h2>Iniciar ponto</h2>
                @if ($switchSubtasks->isEmpty())
                    <p class="muted">Nenhuma subtarefa interna em projeto aberto.</p>
                @else
                    <form method="POST" action="{{ route('ponto.start') }}" class="stack">
                        @csrf
                        <label class="field">
                            <span>Atividade</span>
                            <select name="subtask_id" required>
                                <option value="">Escolha a subtarefa</option>
                                @foreach ($switchSubtasks as $subtask)
                                    <option value="{{ $subtask->id }}" @selected((string) old('subtask_id') === (string) $subtask->id)>
                                        {{ $subtask->project->name }} — {{ $subtask->name }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        <button class="btn btn-primary" type="submit">Iniciar</button>
                        @error('subtask_id')<p class="error">{{ $message }}</p>@enderror
                    </form>
                @endif
            </section>
        </div>
        <div class="stack">
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
                                <form method="POST" action="{{ route('ponto.switch', $log) }}" class="stack">
                                    @csrf
                                    <label class="field">
                                        <span>Trocar atividade</span>
                                        <select name="subtask_id" required>
                                            @foreach ($switchSubtasks as $subtask)
                                                <option value="{{ $subtask->id }}" @selected($subtask->id === $log->subtask_id)>
                                                    {{ $subtask->project->name }} — {{ $subtask->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <button class="btn btn-ghost" type="submit">Trocar</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>
@endsection
