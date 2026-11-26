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

    /** @var list<array{id: string, disk: string, path: string, mime_type: string, width: int, height: int}> */
    public array $photos = [];

    /** @param list<int> $mediaIds
     * @param  list<array{id: string, disk: string, path: string, mime_type: string, width: int, height: int}>  $photos
     */
    public function __construct(public array $mediaIds, public ?string $cvDisk, public ?string $cvPath, array $photos = [])
    {
        $this->photos = $photos;
    }

    public function handle(): void
    {
        foreach (Media::whereIn('id', $this->mediaIds)->get() as $media) {
            if (! Storage::disk($media->disk)->delete($media->getPathRelativeToRoot())) {
                throw new \RuntimeException('Unable to remove a deleted portfolio image.');
            }
            $media->delete();
        }

        foreach ($this->photos as $photo) {
            if (! Storage::disk($photo['disk'])->delete($photo['path'])) {
                throw new \RuntimeException('Unable to remove a deleted portfolio photo.');
            }
        }

        if ($this->cvDisk !== null && $this->cvPath !== null) {
            if (! Storage::disk($this->cvDisk)->delete($this->cvPath)) {
                throw new \RuntimeException('Unable to remove a deleted portfolio CV.');
            }
        }
    }
}
