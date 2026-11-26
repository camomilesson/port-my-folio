<?php

namespace App\Actions;

use App\Jobs\DeleteUnusedPortfolioPhoto;
use App\Models\Portfolio;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StorePortfolioPhoto
{
    public function __construct(private EditPortfolioDraft $editor, private ProcessGalleryImage $processor) {}

    public function handle(Portfolio $portfolio, UploadedFile $file): void
    {
        $processed = $this->processor->handle($file);
        $disk = config('portmyfolio.image_disk');
        if (config('filesystems.disks.'.$disk.'.visibility') === 'public') {
            throw ValidationException::withMessages(['photo' => 'The portfolio photo must use private storage.']);
        }
        $id = (string) Str::uuid();
        $photo = ['id' => $id, 'disk' => $disk, 'path' => 'portfolio-photos/'.$id.'.webp', 'mime_type' => 'image/webp', 'width' => $processed['width'], 'height' => $processed['height']];
        try {
            if (! Storage::disk($disk)->put($photo['path'], $processed['bytes'])) {
                throw new \RuntimeException('Unable to store the portfolio photo.');
            }
            $this->editor->handle($portfolio, function (Portfolio $locked) use ($photo): void {
                EditInlinePortfolioField::requireReady($locked);
                $home = $locked->home_content ?? [];
                $previous = $home['photo'] ?? null;
                $home['photo'] = $photo;
                $locked->update(['home_content' => $home]);
                if ($previous) {
                    DeleteUnusedPortfolioPhoto::dispatch($locked->id, $previous)->afterCommit();
                }
            });
        } catch (\Throwable $exception) {
            if ((Portfolio::find($portfolio->id)?->home_content['photo']['id'] ?? null) !== $id) {
                Storage::disk($disk)->delete($photo['path']);
            }
            throw $exception;
        }
    }
}
