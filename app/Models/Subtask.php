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
use Illuminate\Support\Facades\DB;

#[Fillable(['project_id', 'name', 'kind', 'budget_cents', 'planned_minutes', 'realized_cents', 'alert_percentage', 'alert_enabled', 'created_by'])]
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
            'planned_minutes' => 'integer',
            'realized_cents' => 'integer',
            'alert_percentage' => 'integer',
            'alert_enabled' => 'boolean',
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
        if (array_key_exists('logged_minutes', $this->attributes)) {
            return (int) $this->attributes['logged_minutes'];
        }

        $duration = TimeLog::durationSql('time_logs');

        return (int) $this->timeLogs()->whereNotNull('ended_at')->sum(DB::raw($duration));
    }

    /**
     * Alarme desta subtarefa, só com o interruptor ligado e horas previstas acima de zero.
     * Ponto aberto não entra.
     */
    public function alertReached(): bool
    {
        if (! $this->alert_enabled || $this->planned_minutes === null || $this->planned_minutes <= 0 || $this->alert_percentage === null) {
            return false;
        }

        return $this->loggedMinutes() * 100 >= $this->planned_minutes * $this->alert_percentage;
    }

    /**
     * @param  Builder<Subtask>  $query
     * @return Builder<Subtask>
     */
    public function scopeReached(Builder $query): Builder
    {
        $duration = TimeLog::durationSql('tl');

        return $query
            ->select('subtasks.*')
            ->join('projects', 'projects.id', '=', 'subtasks.project_id')
            ->where('subtasks.alert_enabled', true)
            ->where('subtasks.planned_minutes', '>', 0)
            ->whereNotNull('subtasks.alert_percentage')
            ->whereRaw(
                '(select coalesce(sum('.$duration.'), 0) from time_logs as tl where tl.subtask_id = subtasks.id and tl.ended_at is not null) * 100 >= subtasks.planned_minutes * subtasks.alert_percentage'
            )
            ->orderBy('projects.name')
            ->orderBy('subtasks.name');
    }
}
