<?php

namespace App\Jobs;

use App\Models\Portfolio;
use App\Models\Project;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteUnusedProjectMedia implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $projectId) {}

    public function handle(): void
    {
        $project = Project::withTrashed()->find($this->projectId);
        if (! $project) {
            return;
        }
        DB::transaction(function () use ($project): void {
            $portfolio = Portfolio::whereKey($project->portfolio_id)->lockForUpdate()->first();
            if (! $portfolio) {
                return;
            }
            $project = Project::withTrashed()->find($project->id);
            if (! $project) {
                return;
            }
            $publishedProjects = collect($portfolio->published_snapshot['projects'] ?? []);
            $referencedImages = $publishedProjects->flatMap(fn (array $item): array => $item['gallery'] ?? [])->pluck('id')->map(fn ($id): int => (int) $id)->all();
            foreach ($project->getMedia('gallery') as $media) {
                if (in_array($media->id, $referencedImages, true)) {
                    continue;
                }
                if (! $project->trashed() && ! $media->getCustomProperty('draft_removed', false)) {
                    continue;
                }
                if (! Storage::disk($media->disk)->delete($media->getPathRelativeToRoot())) {
                    throw new \RuntimeException('Unable to remove an unused gallery image.');
                }
                $media->delete();
            }
            if ($project->trashed() && ! $publishedProjects->contains(fn (array $item): bool => ($item['id'] ?? null) === $project->id)) {
                $project->forceDelete();
            }
        });
    }
}
