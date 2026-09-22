<?php

namespace App\Policies;

use App\Models\TimeLog;
use App\Models\User;

class TimeLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->managesProjects();
    }

    public function create(User $user): bool
    {
        return $user->managesProjects();
    }

    public function update(User $user, TimeLog $timeLog): bool
    {
        return $user->managesProjects();
    }

    public function delete(User $user, TimeLog $timeLog): bool
    {
        return $user->managesProjects();
    }
}
