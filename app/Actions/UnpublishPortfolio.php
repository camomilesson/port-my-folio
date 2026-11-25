<?php

namespace App\Actions;

use App\Jobs\DeleteUnusedProjectMedia;
use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UnpublishPortfolio
{
    public function handle(Portfolio $portfolio): void
    {
        DB::transaction(function () use ($portfolio): void {
            $owner = User::whereKey($portfolio->user_id)->lockForUpdate()->firstOrFail();
            $locked = Portfolio::whereKey($portfolio->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $locked);
            $locked->update(['published_snapshot' => null, 'published_at' => null]);
            if ($owner->default_portfolio_id === $locked->id) {
                $owner->forceFill(['default_portfolio_id' => null])->save();
            }
            foreach ($locked->projects()->withTrashed()->pluck('id') as $projectId) {
                DeleteUnusedProjectMedia::dispatch($projectId)->afterCommit();
            }
        });
    }
}
