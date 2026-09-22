<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->managesProjects();
    }

    public function view(User $user, Project $project): bool
    {
        return $user->managesProjects();
    }

    public function create(User $user): bool
    {
        return $user->managesProjects();
    }

    public function update(User $user, Project $project): bool
    {
        return $user->managesProjects();
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->managesProjects();
    }
}
