<?php

namespace App\Policies;

use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PortfolioPolicy
{
    public function create(User $user): bool
    {
        return $user->username !== null;
    }

    public function view(User $user, Portfolio $portfolio): Response
    {
        return $portfolio->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }

    public function update(User $user, Portfolio $portfolio): Response
    {
        return $this->view($user, $portfolio);
    }

    public function delete(User $user, Portfolio $portfolio): Response
    {
        return $this->view($user, $portfolio);
    }
}
