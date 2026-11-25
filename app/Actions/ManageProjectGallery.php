<?php

namespace App\Actions;

use App\Jobs\DeleteUnusedProjectMedia;
use App\Models\Portfolio;
use App\Models\Project;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ManageProjectGallery
{
    public function __construct(private EditPortfolioDraft $editor, private ProcessGalleryImage $processor) {}

    /** @param array<string, mixed> $data */
    public function store(Portfolio $portfolio, Project $project, UploadedFile $file, array $data): void
    {
        $processed = $this->processor->handle($file);
        $media = null;
        $temporary = tempnam(sys_get_temp_dir(), 'portfolio-image-');
        file_put_contents($temporary, $processed['bytes']);
        $fileName = Str::uuid().'.webp';
        try {
            $this->editor->handle($portfolio, function (Portfolio $locked) use ($project, $processed, $data, $temporary, $fileName, &$media): void {
                $project = $locked->projects()->findOrFail($project->id);
                if ($project->activeGallery()->count() >= 10) {
                    throw ValidationException::withMessages(['image' => 'A project can contain at most ten gallery images.']);
                }
                if (config('filesystems.disks.'.config('portmyfolio.image_disk').'.visibility') === 'public') {
                    throw ValidationException::withMessages(['image' => 'The gallery must use a private storage disk.']);
                }
                try {
                    $media = $project->addMedia($temporary)->usingFileName($fileName)
                        ->withCustomProperties($this->metadata($data) + ['draft_removed' => false, 'width' => $processed['width'], 'height' => $processed['height']])
                        ->toMediaCollection('gallery');
                } catch (\Throwable $exception) {
                    $media = $project->media()->where('file_name', $fileName)->first();
                    throw $exception;
                }
            });
        } catch (\Throwable $exception) {
            if ($media) {
                $media->delete();
            }
            throw $exception;
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    /** @param array<string, mixed> $data */
    public function update(Portfolio $portfolio, Project $project, Media $media, array $data): void
    {
        $this->editor->handle($portfolio, function (Portfolio $locked) use ($project, $media, $data): void {
            $project = $locked->projects()->findOrFail($project->id);
            $media = $project->activeGallery()->firstWhere('id', $media->id);
            abort_unless($media, 404);
            foreach ($this->metadata($data) as $key => $value) {
                $media->setCustomProperty($key, $value);
            }
            $media->save();
        });
    }

    public function remove(Portfolio $portfolio, Project $project, Media $media): void
    {
        $this->editor->handle($portfolio, function (Portfolio $locked) use ($project, $media): void {
            $project = $locked->projects()->findOrFail($project->id);
            $media = $project->activeGallery()->firstWhere('id', $media->id);
            abort_unless($media, 404);
            $media->setCustomProperty('draft_removed', true)->save();
            DeleteUnusedProjectMedia::dispatch($project->id)->afterCommit();
        });
    }

    public function move(Portfolio $portfolio, Project $project, Media $media, string $direction): void
    {
        $this->editor->handle($portfolio, function (Portfolio $locked) use ($project, $media, $direction): void {
            $project = $locked->projects()->findOrFail($project->id);
            $gallery = $project->activeGallery()->values();
            $index = $gallery->search(fn (Media $item): bool => $item->id === $media->id);
            abort_if($index === false, 404);
            $target = $direction === 'up' ? $index - 1 : $index + 1;
            if ($target >= 0 && $target < $gallery->count()) {
                $other = $gallery[$target];
                $gallery[$target] = $gallery[$index];
                $gallery[$index] = $other;
            }
            foreach ($gallery as $position => $item) {
                $item->order_column = $position + 1;
                $item->save();
            }
        });
    }

    /** @param array<string, mixed> $data
     * @return array{caption: ?string, decorative: bool, alt_text: string}
     */
    private function metadata(array $data): array
    {
        $decorative = (bool) $data['decorative'];

        return ['caption' => $data['caption'] ?? null, 'decorative' => $decorative, 'alt_text' => $decorative ? '' : $data['alt_text']];
    }
}
