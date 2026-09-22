<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= 'password',
            'role' => Role::Operator,
            'active' => true,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => Role::Admin]);
    }

    public function leader(): static
    {
        return $this->state(fn () => ['role' => Role::Leader]);
    }

    public function operator(): static
    {
        return $this->state(fn () => ['role' => Role::Operator]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
