<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProjectPolicy
{
    public function view(User $user, Project $project): Response
    {
        return ! $project->trashed() && $project->portfolio?->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }

    public function update(User $user, Project $project): Response
    {
        return $this->view($user, $project);
    }

    public function delete(User $user, Project $project): Response
    {
        return $this->view($user, $project);
    }
}
