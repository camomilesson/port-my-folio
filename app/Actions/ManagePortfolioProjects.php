<?php

namespace App\Actions;

use App\Jobs\DeleteUnusedProjectMedia;
use App\Models\Portfolio;
use App\Models\Project;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManagePortfolioProjects
{
    public function __construct(private EditPortfolioDraft $editor) {}

    /** @param array<string, mixed> $data */
    public function save(Portfolio $portfolio, ?Project $project, array $data): Project
    {
        return $this->editor->handle($portfolio, function (Portfolio $locked) use ($project, $data): Project {
            if ($project) {
                $project = $locked->projects()->findOrFail($project->id);
                Gate::authorize('update', $project);
            } else {
                if ($locked->projects()->count() >= 5) {
                    throw ValidationException::withMessages(['repository_url' => 'A portfolio can contain at most five projects. Remove one before adding another.']);
                }
                [$owner, $name] = explode('/', trim(substr($data['repository_url'], strlen('https://github.com/')), '/'));
                $owner = Str::lower($owner);
                $name = Str::lower(preg_replace('/\.git$/i', '', $name));
                if ($name === '' || in_array($name, ['.', '..'], true)) {
                    throw ValidationException::withMessages(['repository_url' => 'Enter a valid GitHub repository name.']);
                }
                $existing = $locked->projects()->withTrashed()->whereRaw('LOWER(repository_owner) = ?', [$owner])->whereRaw('LOWER(repository_name) = ?', [$name])->first();
                if ($existing && ! $existing->trashed()) {
                    throw ValidationException::withMessages(['repository_url' => 'This repository is already in your portfolio.']);
                }
                if ($existing) {
                    $existing->restore();
                    $project = $existing;
                } else {
                    $project = $locked->projects()->make(['repository_owner' => $owner, 'repository_name' => $name, 'slug' => (Str::slug(Str::limit($name, 48, '')) ?: 'project').'-'.Str::lower(Str::random(8))]);
                }
                $project->position = ($locked->projects()->max('position') ?? -1) + 1;
            }
            $project->fill(collect($data)->only(['title', 'summary', 'overview', 'technical_details', 'technologies', 'live_url'])->all());
            $project->save();

            return $project;
        });
    }

    public function remove(Portfolio $portfolio, Project $project): void
    {
        $this->editor->handle($portfolio, function (Portfolio $locked) use ($project): void {
            $project = $locked->projects()->findOrFail($project->id);
            Gate::authorize('delete', $project);
            $project->delete();
            DeleteUnusedProjectMedia::dispatch($project->id)->afterCommit();
        });
    }

    public function move(Portfolio $portfolio, Project $project, string $direction): void
    {
        $this->editor->handle($portfolio, function (Portfolio $locked) use ($project, $direction): void {
            $projects = $locked->projects()->get();
            $index = $projects->search(fn (Project $item): bool => $item->id === $project->id);
            abort_if($index === false, 404);
            $target = $direction === 'up' ? $index - 1 : $index + 1;
            if ($target >= 0 && $target < $projects->count()) {
                $other = $projects[$target];
                $projects[$target] = $projects[$index];
                $projects[$index] = $other;
            }
            foreach ($projects as $position => $item) {
                $item->update(['position' => $position]);
            }
        });
    }
}
