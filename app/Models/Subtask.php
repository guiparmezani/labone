<?php

namespace App\Models;

use App\Enums\SubtaskKind;
use Database\Factories\SubtaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['project_id', 'name', 'kind', 'budget_cents', 'created_by'])]
class Subtask extends Model
{
    /** @use HasFactory<SubtaskFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => SubtaskKind::class,
            'budget_cents' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function timeLogs(): HasMany
    {
        return $this->hasMany(TimeLog::class);
    }

    public function isInternal(): bool
    {
        return $this->kind === SubtaskKind::Internal;
    }

    /**
     * @param  Builder<Subtask>  $query
     * @return Builder<Subtask>
     */
    public function scopeWithLoggedMinutes(Builder $query): Builder
    {
        $duration = TimeLog::durationSql('time_logs');

        return $query->select('subtasks.*')->addSelect([
            'logged_minutes' => TimeLog::query()
                ->selectRaw("COALESCE(SUM({$duration}), 0)")
                ->whereColumn('time_logs.subtask_id', 'subtasks.id')
                ->whereNotNull('time_logs.ended_at'),
        ]);
    }

    public function loggedMinutes(): int
    {
        return (int) ($this->attributes['logged_minutes'] ?? $this->timeLogs()->whereNotNull('ended_at')->count());
    }
}
