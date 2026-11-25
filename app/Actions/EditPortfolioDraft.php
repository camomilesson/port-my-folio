<?php

namespace App\Actions;

use App\Models\Portfolio;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class EditPortfolioDraft
{
    public function handle(Portfolio $portfolio, Closure $edit): mixed
    {
        return DB::transaction(function () use ($portfolio, $edit): mixed {
            User::whereKey($portfolio->user_id)->lockForUpdate()->firstOrFail();
            $locked = Portfolio::whereKey($portfolio->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $locked);
            if ($locked->generations()->whereIn('status', ['queued', 'running'])->exists()) {
                throw ValidationException::withMessages(['portfolio' => 'Wait for generation to finish before editing this draft.']);
            }

            return $edit($locked);
        });
    }
}
