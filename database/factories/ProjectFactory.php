<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'notes' => null,
            'status' => ProjectStatus::Open,
            'budget_cents' => 150000,
            'planned_minutes' => 480,
            'closed_at' => null,
            'created_by' => User::factory(),
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => ProjectStatus::Closed,
            'closed_at' => now(),
        ]);
    }
}
