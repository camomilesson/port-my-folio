<?php

namespace App\Jobs;

use App\Models\Portfolio;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteUnusedPortfolioPhoto implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @param array{id: string, disk: string, path: string, mime_type: string, width: int, height: int} $photo */
    public function __construct(public int $portfolioId, public array $photo) {}

    public function handle(): void
    {
        DB::transaction(function (): void {
            $portfolio = Portfolio::whereKey($this->portfolioId)->lockForUpdate()->first();
            if (($portfolio?->home_content['photo']['id'] ?? null) === $this->photo['id'] || ($portfolio?->published_snapshot['photo']['id'] ?? null) === $this->photo['id']) {
                return;
            }
            if (! Storage::disk($this->photo['disk'])->delete($this->photo['path'])) {
                throw new \RuntimeException('Unable to remove an unused portfolio photo.');
            }
        });
    }
}
