<?php

namespace App\Models;

use App\Enums\SubtaskKind;
use App\Support\Formato;
use Database\Factories\SubtaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[Fillable(['project_id', 'name', 'kind', 'budget_cents', 'planned_minutes', 'realized_cents', 'alert_percentage', 'alert_enabled', 'is_revision', 'revision_notes', 'revision_of_subtask_id', 'created_by'])]
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
            'is_revision' => 'boolean',
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

    public function revisionOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'revision_of_subtask_id');
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
     * Nome como aparece ao escolher a subtarefa para um ponto.
     */
    public function clockLabel(): string
    {
        return $this->is_revision ? $this->name.' (revisão)' : $this->name;
    }

    public static function nameTaken(int $projectId, string $name, ?int $ignoreId = null): bool
    {
        $needle = mb_strtolower(trim($name));

        return self::query()
            ->where('project_id', $projectId)
            ->when($ignoreId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->pluck('name')
            ->contains(fn (string $existing) => mb_strtolower(trim($existing)) === $needle);
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

    /**
     * @param  Builder<Subtask>  $query
     * @return Builder<Subtask>
     */
    public function scopeWithLaborCents(Builder $query): Builder
    {
        return $query->addSelect(DB::raw(TimeLog::laborCentsSql().' as labor_cents'));
    }

    public function laborCents(): int
    {
        if (array_key_exists('labor_cents', $this->attributes)) {
            return (int) $this->attributes['labor_cents'];
        }

        return (int) DB::scalar('select '.TimeLog::laborCentsSql((string) (int) $this->id));
    }

    public function realizedTotalCents(): int
    {
        return (int) ($this->realized_cents ?? 0) + $this->laborCents();
    }

    /**
     * Material digitado, mão de obra, ou os dois lado a lado.
     */
    public function realizedLabel(): string
    {
        $material = $this->realized_cents;
        $mao = $this->laborCents();
        $temMaterial = $material !== null && $material > 0;
        $temMao = $mao > 0;

        if ($temMaterial && $temMao) {
            return Formato::reais($material).' + '.Formato::reais($mao);
        }

        if ($temMaterial) {
            return Formato::reais($material);
        }

        if ($temMao) {
            return Formato::reais($mao);
        }

        return '—';
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
     * Horas já paradas mais o que ainda está correndo nesta subtarefa.
     */
    public function consumedMinutes(): int
    {
        if (array_key_exists('consumed_minutes', $this->attributes)) {
            return (int) $this->attributes['consumed_minutes'];
        }

        return (int) $this->timeLogs()->rawValue(
            'coalesce(sum(case when ended_at is null then '.TimeLog::openDurationSql('time_logs').' else '.TimeLog::durationSql('time_logs').' end), 0)'
        );
    }

    /**
     * Alarme desta subtarefa, só com o interruptor ligado e horas previstas acima de zero.
     * O ponto aberto entra na conta.
     */
    public function alertReached(): bool
    {
        if (! $this->alert_enabled || $this->planned_minutes === null || $this->planned_minutes <= 0 || $this->alert_percentage === null) {
            return false;
        }

        return $this->consumedMinutes() * 100 >= $this->planned_minutes * $this->alert_percentage;
    }

    /**
     * @param  Builder<Subtask>  $query
     * @return Builder<Subtask>
     */
    public function scopeReached(Builder $query): Builder
    {
        $consumed = TimeLog::consumedMinutesSql('subtasks.id');

        return $query
            ->select('subtasks.*')
            ->addSelect(DB::raw($consumed.' as consumed_minutes'))
            ->join('projects', 'projects.id', '=', 'subtasks.project_id')
            ->where('subtasks.alert_enabled', true)
            ->where('subtasks.planned_minutes', '>', 0)
            ->whereNotNull('subtasks.alert_percentage')
            ->whereRaw($consumed.' * 100 >= subtasks.planned_minutes * subtasks.alert_percentage')
            ->orderBy('projects.name')
            ->orderBy('subtasks.name');
    }
}
