<?php

namespace App\Models;

use App\Enums\TimeLogSource;
use Database\Factories\TimeLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

#[Fillable(['user_id', 'subtask_id', 'started_at', 'ended_at', 'source', 'created_by', 'updated_by'])]
class TimeLog extends Model
{
    /** @use HasFactory<TimeLogFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'source' => TimeLogSource::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subtask(): BelongsTo
    {
        return $this->belongsTo(Subtask::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isOpen(): bool
    {
        return $this->ended_at === null;
    }

    public function minutes(): ?int
    {
        if ($this->ended_at === null) {
            return null;
        }

        return (int) $this->started_at->diffInMinutes($this->ended_at);
    }

    public static function durationSql(string $table = 'time_logs'): string
    {
        return match (DB::connection()->getDriverName()) {
            'mysql' => "TIMESTAMPDIFF(MINUTE, {$table}.started_at, {$table}.ended_at)",
            default => "CAST(ROUND((julianday({$table}.ended_at) - julianday({$table}.started_at)) * 1440) AS INTEGER)",
        };
    }
}
