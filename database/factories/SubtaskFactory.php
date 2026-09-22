<?php

namespace Database\Factories;

use App\Enums\SubtaskKind;
use App\Models\Project;
use App\Models\Subtask;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subtask>
 */
class SubtaskFactory extends Factory
{
    protected $model = Subtask::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => fake()->words(2, true),
            'kind' => SubtaskKind::Internal,
            'budget_cents' => null,
            'created_by' => User::factory(),
        ];
    }

    public function thirdParty(): static
    {
        return $this->state(fn () => [
            'kind' => SubtaskKind::ThirdParty,
            'budget_cents' => 80000,
        ]);
    }
}
