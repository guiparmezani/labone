@php
    $enviado = isset($lightbox) ? old('lightbox') === $lightbox : true;
    $nome = $enviado ? old('name', $project->name ?? '') : ($project->name ?? '');
    $notas = $enviado ? old('notes', $project->notes ?? '') : ($project->notes ?? '');
    $orcamento = $enviado
        ? old('budget', isset($project->budget_cents) ? \App\Support\Formato::reaisEntrada($project->budget_cents) : '')
        : (isset($project->budget_cents) ? \App\Support\Formato::reaisEntrada($project->budget_cents) : '');
    $horas = $enviado
        ? old('planned_hours', isset($project->planned_minutes) ? \App\Support\Formato::horasEntrada($project->planned_minutes) : '')
        : (isset($project->planned_minutes) ? \App\Support\Formato::horasEntrada($project->planned_minutes) : '');
@endphp
<label class="field">
    <span>Nome</span>
    <input type="text" name="name" value="{{ $nome }}" required maxlength="160">
    @if ($enviado)
        @error('name')<small class="error">{{ $message }}</small>@enderror
    @endif
</label>
<label class="field">
    <span>Observações</span>
    <textarea name="notes" maxlength="2000">{{ $notas }}</textarea>
    @if ($enviado)
        @error('notes')<small class="error">{{ $message }}</small>@enderror
    @endif
</label>
<label class="field">
    <span>Orçamento (R$)</span>
    <input type="text" name="budget" inputmode="decimal" value="{{ $orcamento }}">
    @if ($enviado)
        @error('budget')<small class="error">{{ $message }}</small>@enderror
        @error('budget_cents')<small class="error">{{ $message }}</small>@enderror
    @endif
</label>
@if (! isset($project) || ! $project->exists || auth()->user()->isAdmin())
    <label class="field">
        <span>Horas previstas</span>
        <input type="text" name="planned_hours" inputmode="decimal" value="{{ $horas }}" required>
        @if ($enviado)
            @error('planned_hours')<small class="error">{{ $message }}</small>@enderror
            @error('planned_minutes')<small class="error">{{ $message }}</small>@enderror
        @endif
    </label>
    <p class="muted">Estas horas são do projeto. As horas previstas de cada tarefa somam por cima.</p>
@else
    <p>Horas previstas do projeto: {{ \App\Support\Formato::minutos($project->planned_minutes) }}. Só o administrador altera. As tarefas somam por cima.</p>
@endif
