<label class="field">
    <span>Nome</span>
    <input type="text" name="name" value="{{ old('name', $project->name ?? '') }}" required maxlength="160">
    @error('name')<small class="error">{{ $message }}</small>@enderror
</label>
<label class="field">
    <span>Observações</span>
    <textarea name="notes" maxlength="2000">{{ old('notes', $project->notes ?? '') }}</textarea>
    @error('notes')<small class="error">{{ $message }}</small>@enderror
</label>
<label class="field">
    <span>Orçamento (R$)</span>
    <input type="text" name="budget" inputmode="decimal" value="{{ old('budget', isset($project->budget_cents) ? \App\Support\Formato::reaisEntrada($project->budget_cents) : '') }}" required>
    @error('budget')<small class="error">{{ $message }}</small>@enderror
    @error('budget_cents')<small class="error">{{ $message }}</small>@enderror
</label>
<label class="field">
    <span>Horas previstas</span>
    <input type="text" name="planned_hours" inputmode="decimal" value="{{ old('planned_hours', isset($project->planned_minutes) ? \App\Support\Formato::horasEntrada($project->planned_minutes) : '') }}" required>
    @error('planned_hours')<small class="error">{{ $message }}</small>@enderror
    @error('planned_minutes')<small class="error">{{ $message }}</small>@enderror
</label>
