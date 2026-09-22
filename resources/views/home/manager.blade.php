@extends('layouts.app')

@section('content')
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
                            <p>Desde {{ \App\Support\Formato::hora($log->started_at) }} · <span class="elapsed">—</span></p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
    @if ($openLogs->isNotEmpty())
        <script>
            function paintElapsed() {
                var now = Date.now();
                document.querySelectorAll('[data-started-at]').forEach(function (row) {
                    var start = Date.parse(row.getAttribute('data-started-at'));
                    var minutes = Math.max(0, Math.floor((now - start) / 60000));
                    var target = row.querySelector('.elapsed');
                    if (target) {
                        target.textContent = Math.floor(minutes / 60) + 'h ' + String(minutes % 60).padStart(2, '0') + 'min';
                    }
                });
            }
            paintElapsed();
            setInterval(paintElapsed, 1000);
        </script>
    @endif
@endsection
