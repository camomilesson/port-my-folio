<?php

namespace App\Actions;

use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SetDefaultPortfolio
{
    public function handle(Portfolio $portfolio): void
    {
        DB::transaction(function () use ($portfolio): void {
            $owner = User::whereKey($portfolio->user_id)->lockForUpdate()->firstOrFail();
            $locked = Portfolio::whereKey($portfolio->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $locked);
            if (! $locked->isPublished()) {
                throw ValidationException::withMessages(['publication' => 'Publish this portfolio before making it your default.']);
            }
            $owner->forceFill(['default_portfolio_id' => $locked->id])->save();
        });
    }
}
