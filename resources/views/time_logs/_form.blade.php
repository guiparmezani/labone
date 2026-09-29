@php
    $log = $log ?? null;
    $enviado = isset($lightbox) ? old('lightbox') === $lightbox : true;
    $pessoa = $enviado ? old('user_id', $log->user_id ?? '') : ($log->user_id ?? '');
    $tarefa = $enviado ? old('subtask_id', $log->subtask_id ?? '') : ($log->subtask_id ?? '');
    $inicio = $enviado
        ? old('started_at', $log ? \App\Support\Formato::local($log->started_at) : '')
        : ($log ? \App\Support\Formato::local($log->started_at) : '');
    $duracao = $enviado
        ? old('duration', ($log && $log->ended_at) ? \App\Support\Formato::horasEntrada($log->minutes()) : '')
        : (($log && $log->ended_at) ? \App\Support\Formato::horasEntrada($log->minutes()) : '');
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
    <span>Duração</span>
    <input type="text" name="duration" inputmode="decimal" placeholder="1,5" required value="{{ $duracao }}">
    @if ($enviado)
        @error('duration')<small class="error">{{ $message }}</small>@enderror
    @endif
</label>
