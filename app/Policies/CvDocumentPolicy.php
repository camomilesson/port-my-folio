<?php

namespace App\Policies;

use App\Models\CvDocument;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CvDocumentPolicy
{
    public function view(User $user, CvDocument $cvDocument): Response
    {
        return $cvDocument->portfolio?->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }

    public function update(User $user, CvDocument $cvDocument): Response
    {
        return $this->view($user, $cvDocument);
    }

    public function delete(User $user, CvDocument $cvDocument): Response
    {
        return $this->view($user, $cvDocument);
    }
}
