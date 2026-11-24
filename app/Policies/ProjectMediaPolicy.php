<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ProjectMediaPolicy
{
    public function view(User $user, Media $media): Response
    {
        $project = $media->model;

        return $project instanceof Project && ! $project->trashed() && $project->portfolio?->user_id === $user->id
            ? Response::allow() : Response::denyAsNotFound();
    }

    public function update(User $user, Media $media): Response
    {
        return $this->view($user, $media);
    }

    public function delete(User $user, Media $media): Response
    {
        return $this->view($user, $media);
    }
}
