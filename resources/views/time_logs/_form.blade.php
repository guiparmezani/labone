@php
    $log = $log ?? null;
@endphp
<label class="field">
    <span>Pessoa</span>
    <select name="user_id" required>
        @foreach ($users as $person)
            <option value="{{ $person->id }}" @selected((string) old('user_id', $log->user_id ?? '') === (string) $person->id)>{{ $person->name }}</option>
        @endforeach
    </select>
    @error('user_id')<small class="error">{{ $message }}</small>@enderror
</label>
<label class="field">
    <span>Subtarefa interna</span>
    <select name="subtask_id" required>
        @foreach ($subtasks as $subtask)
            <option value="{{ $subtask->id }}" @selected((string) old('subtask_id', $log->subtask_id ?? '') === (string) $subtask->id)>
                {{ $subtask->project->name }} — {{ $subtask->name }}
            </option>
        @endforeach
    </select>
    @error('subtask_id')<small class="error">{{ $message }}</small>@enderror
</label>
<label class="field">
    <span>Início</span>
    <input type="datetime-local" name="started_at" required value="{{ old('started_at', $log ? \App\Support\Formato::local($log->started_at) : '') }}">
    @error('started_at')<small class="error">{{ $message }}</small>@enderror
</label>
<label class="field">
    <span>Fim</span>
    <input type="datetime-local" name="ended_at" required value="{{ old('ended_at', $log && $log->ended_at ? \App\Support\Formato::local($log->ended_at) : '') }}">
    @error('ended_at')<small class="error">{{ $message }}</small>@enderror
</label>
