<?php

namespace App\Policies;

use App\Models\Subtask;
use App\Models\User;

class SubtaskPolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Subtask $subtask): bool
    {
        return $user->managesProjects();
    }

    public function delete(User $user, Subtask $subtask): bool
    {
        return $user->managesProjects();
    }
}
