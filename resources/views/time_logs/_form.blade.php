@php
    $log = $log ?? null;
    $enviado = isset($lightbox) ? old('lightbox') === $lightbox : true;
    $pessoa = $enviado ? old('user_id', $log->user_id ?? '') : ($log->user_id ?? '');
    $tarefa = $enviado ? old('subtask_id', $log->subtask_id ?? '') : ($log->subtask_id ?? '');
    $inicio = $enviado
        ? old('started_at', $log ? \App\Support\Formato::local($log->started_at) : '')
        : ($log ? \App\Support\Formato::local($log->started_at) : '');
    $fim = $enviado
        ? old('ended_at', $log ? \App\Support\Formato::local($log->ended_at) : '')
        : ($log ? \App\Support\Formato::local($log->ended_at) : '');
    $total = ($log && $log->ended_at) ? \App\Support\Formato::minutos($log->minutes()) : ($log ? 'Em andamento' : 'Calculado ao salvar');
@endphp
<label class="field">
    <span>Pessoa</span>
    <select name="user_id" required>
        @foreach ($users as $person)
            <option value="{{ $person->id }}" @selected((string) $pessoa === (string) $person->id)>{{ $person->name }}</option>
        @endforeach
    </select>
    @if ($enviado)
        @error('user_id')<small class="error">{{ $message }}</small>@enderror
    @endif
</label>
<label class="field">
    <span>Tarefa</span>
    <select name="subtask_id" required>
        @foreach ($subtasks as $subtask)
            <option value="{{ $subtask->id }}" @selected((string) $tarefa === (string) $subtask->id)>
                {{ $subtask->project->name }} — {{ $subtask->name }}
            </option>
        @endforeach
    </select>
    @if ($enviado)
        @error('subtask_id')<small class="error">{{ $message }}</small>@enderror
    @endif
</label>
<div class="stack" data-time-interval
    data-original-start="{{ $log?->started_at?->copy()->timezone(\App\Support\Formato::TZ)->format('Y-m-d\TH:i:s') }}"
    data-original-end="{{ $log?->ended_at?->copy()->timezone(\App\Support\Formato::TZ)->format('Y-m-d\TH:i:s') }}">
    <label class="field">
        <span>Início</span>
        @include('partials.calendario', [
            'nome' => 'started_at',
            'modo' => 'datetime',
            'obrigatorio' => true,
            'valor' => $inicio,
        ])
        @if ($enviado)
            @error('started_at')<small class="error">{{ $message }}</small>@enderror
        @endif
    </label>
    <label class="field">
        <span>Fim</span>
        @include('partials.calendario', [
            'nome' => 'ended_at',
            'modo' => 'datetime',
            'obrigatorio' => true,
            'valor' => $fim,
        ])
        @if ($enviado)
            @error('ended_at')<small class="error">{{ $message }}</small>@enderror
        @endif
    </label>
    <label class="field">
        <span>Total de horas</span>
        <input type="text" readonly data-duration value="{{ $total }}">
        <small class="muted">Calculado pelo início e fim, no horário de Brasília. Para trabalhos de um dia para o outro, ajuste também a data do fim.</small>
        @if ($enviado)
            @error('duration')<small class="error">{{ $message }}</small>@enderror
        @endif
    </label>
</div>

@once
    @push('scripts')
        <script src="{{ asset('js/lancamentos.js') }}?v={{ filemtime(public_path('js/lancamentos.js')) }}"></script>
    @endpush
@endonce
