<?php

namespace App\Actions;

use App\Jobs\DeletePortfolioFiles;
use App\Models\Portfolio;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class DeletePortfolio
{
    public function handle(Portfolio $portfolio): void
    {
        DB::transaction(function () use ($portfolio): void {
            User::whereKey($portfolio->user_id)->lockForUpdate()->firstOrFail();
            $locked = Portfolio::whereKey($portfolio->id)->lockForUpdate()->firstOrFail();

            Gate::authorize('delete', $locked);

            $projectIds = $locked->projects()->withTrashed()->pluck('id');
            $mediaIds = Media::where('model_type', (new Project)->getMorphClass())->whereIn('model_id', $projectIds)->pluck('id')->all();
            $photos = array_values(array_filter([$locked->home_content['photo'] ?? null, $locked->published_snapshot['photo'] ?? null]));
            $cvPath = $locked->cvDocument?->storage_path;
            $cvDisk = $locked->cvDocument?->storageDisk();
            foreach ($locked->generations()->lockForUpdate()->get() as $generation) {
                $terminal = in_array($generation->status, ['queued', 'running'], true);
                $generation->update(['input_snapshot' => null, 'progress' => null, 'lease_token' => null, 'lease_expires_at' => null, 'next_run_at' => null, 'run_version' => $generation->run_version + 1] + ($terminal ? ['status' => 'failed', 'error_code' => 'cancelled', 'completed_at' => now()] : []));
            }
            $locked->delete();

            if ($mediaIds !== [] || $cvPath !== null || $photos !== []) {
                DeletePortfolioFiles::dispatch($mediaIds, $cvDisk, $cvPath, $photos)->afterCommit();
            }
        });
    }
}
