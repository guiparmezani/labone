<section class="clock-bar" data-started-at="{{ $openLog->started_at->toIso8601String() }}">
    <div>
        <strong>{{ $openLog->subtask->project->name }}</strong>
        <p>{{ $openLog->subtask->name }}</p>
        <p class="elapsed" aria-live="polite">{{ \App\Support\Formato::cronometro($openLog->started_at) }}</p>
        <p>Desde {{ \App\Support\Formato::hora($openLog->started_at) }}</p>
    </div>
    <form method="POST" action="{{ route('ponto.stop') }}">
        @csrf
        <button class="btn btn-primary btn-phone" type="submit">Parar</button>
    </form>
</section>
