<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\DB;

#[Fillable(['name', 'notes', 'status', 'budget_cents', 'planned_minutes', 'closed_at', 'created_by'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'budget_cents' => 'integer',
            'planned_minutes' => 'integer',
            'closed_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(Subtask::class);
    }

    public function timeLogs(): HasManyThrough
    {
        return $this->hasManyThrough(TimeLog::class, Subtask::class);
    }

    public function isOpen(): bool
    {
        return $this->status === ProjectStatus::Open;
    }

    /**
     * Totais para a lista. Orçamento de terceiros é a soma atual, horas lançadas ignoram ponto aberto.
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeWithTotals(Builder $query): Builder
    {
        $duration = TimeLog::durationSql('time_logs');

        return $query->select('projects.*')->addSelect([
            'third_party_budget_cents' => Subtask::query()
                ->selectRaw('COALESCE(SUM(budget_cents), 0)')
                ->whereColumn('subtasks.project_id', 'projects.id')
                ->where('kind', 'third_party'),
            'logged_minutes' => TimeLog::query()
                ->selectRaw("COALESCE(SUM({$duration}), 0)")
                ->join('subtasks', 'subtasks.id', '=', 'time_logs.subtask_id')
                ->whereColumn('subtasks.project_id', 'projects.id')
                ->whereNotNull('time_logs.ended_at'),
        ]);
    }

    public function thirdPartyBudgetCents(): int
    {
        if (array_key_exists('third_party_budget_cents', $this->attributes)) {
            return (int) $this->attributes['third_party_budget_cents'];
        }

        return (int) $this->subtasks()->where('kind', 'third_party')->sum('budget_cents');
    }

    public function loggedMinutes(): int
    {
        if (array_key_exists('logged_minutes', $this->attributes)) {
            return (int) $this->attributes['logged_minutes'];
        }

        $duration = TimeLog::durationSql('time_logs');

        return (int) DB::table('time_logs')
            ->join('subtasks', 'subtasks.id', '=', 'time_logs.subtask_id')
            ->where('subtasks.project_id', $this->id)
            ->whereNotNull('time_logs.ended_at')
            ->selectRaw("COALESCE(SUM({$duration}), 0) as total")
            ->value('total');
    }
}
