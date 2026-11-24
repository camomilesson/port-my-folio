<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class DeletePortfolioFiles implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @param list<int> $mediaIds */
    public function __construct(public array $mediaIds, public ?string $cvDisk, public ?string $cvPath) {}

    public function handle(): void
    {
        foreach (Media::whereIn('id', $this->mediaIds)->get() as $media) {
            if (! Storage::disk($media->disk)->delete($media->getPathRelativeToRoot())) {
                throw new \RuntimeException('Unable to remove a deleted portfolio image.');
            }
            $media->delete();
        }

        if ($this->cvDisk !== null && $this->cvPath !== null) {
            if (! Storage::disk($this->cvDisk)->delete($this->cvPath)) {
                throw new \RuntimeException('Unable to remove a deleted portfolio CV.');
            }
        }
    }
}
