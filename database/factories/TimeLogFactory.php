<?php

namespace Database\Factories;

use App\Enums\TimeLogSource;
use App\Models\Subtask;
use App\Models\TimeLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeLog>
 */
class TimeLogFactory extends Factory
{
    protected $model = TimeLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = User::factory();

        return [
            'user_id' => $user,
            'subtask_id' => Subtask::factory(),
            'started_at' => now()->subHours(2),
            'ended_at' => now()->subHour(),
            'source' => TimeLogSource::Manual,
            'created_by' => $user,
            'updated_by' => $user,
        ];
    }

    public function open(): static
    {
        return $this->state(fn () => [
            'ended_at' => null,
            'source' => TimeLogSource::Timer,
            'started_at' => now()->subMinutes(15),
        ]);
    }
}
