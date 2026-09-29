<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => Role::class,
            'active' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isLeader(): bool
    {
        return $this->role === Role::Leader;
    }

    public function isOperator(): bool
    {
        return $this->role === Role::Operator;
    }

    public function timeLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TimeLog::class);
    }

    public function managesProjects(): bool
    {
        return $this->isAdmin() || $this->isLeader();
    }

    public static function nameTaken(string $name, ?int $ignoreId = null): bool
    {
        $needle = mb_strtolower(trim($name));

        return self::query()
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->pluck('name')
            ->contains(fn (string $existing) => mb_strtolower(trim($existing)) === $needle);
    }

    /**
     * Papéis que esta pessoa pode atribuir ao cadastrar ou editar um usuário.
     *
     * @return list<Role>
     */
    public function assignableRoles(): array
    {
        if ($this->isAdmin()) {
            return Role::cases();
        }

        return [Role::Leader, Role::Operator];
    }
}
