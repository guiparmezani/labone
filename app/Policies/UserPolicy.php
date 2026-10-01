<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->managesProjects();
    }

    public function view(User $user, User $model): bool
    {
        return $user->managesProjects();
    }

    public function create(User $user): bool
    {
        return $user->managesProjects();
    }

    public function update(User $user, User $model): bool
    {
        if (! $user->managesProjects()) {
            return false;
        }

        return ! $model->isAdmin() || $user->isAdmin();
    }

    public function delete(User $user, User $model): bool
    {
        return $user->isAdmin() && $user->isNot($model);
    }
}
