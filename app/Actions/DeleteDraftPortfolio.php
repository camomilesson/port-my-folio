<?php

namespace App\Actions;

use App\Jobs\DeletePortfolioFiles;
use App\Models\Portfolio;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class DeleteDraftPortfolio
{
    public function handle(Portfolio $portfolio): void
    {
        DB::transaction(function () use ($portfolio): void {
            User::whereKey($portfolio->user_id)->lockForUpdate()->firstOrFail();
            $locked = Portfolio::whereKey($portfolio->id)->lockForUpdate()->firstOrFail();

            if ($locked->published_snapshot !== null || $locked->published_at !== null || $locked->generations()->whereIn('status', ['queued', 'running'])->exists()) {
                throw ValidationException::withMessages(['portfolio' => 'Only unpublished portfolios without active generation can be deleted.']);
            }

            $projectIds = $locked->projects()->withTrashed()->pluck('id');
            $mediaIds = Media::where('model_type', (new Project)->getMorphClass())->whereIn('model_id', $projectIds)->pluck('id')->all();
            $cvPath = $locked->cvDocument?->storage_path;
            $locked->generations()->update(['input_snapshot' => null, 'progress' => null]);
            $locked->delete();

            if ($mediaIds !== [] || $cvPath !== null) {
                DeletePortfolioFiles::dispatch($mediaIds, $cvPath ? config('portmyfolio.cv_disk') : null, $cvPath)->afterCommit();
            }
        });
    }
}
