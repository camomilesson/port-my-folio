<?php

namespace App\Actions;

use App\Models\Generation;
use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class StartPortfolioGeneration
{
    public function handle(Portfolio $portfolio): Generation
    {
        return DB::transaction(function () use ($portfolio): Generation {
            $owner = User::whereKey($portfolio->user_id)->lockForUpdate()->firstOrFail();
            $locked = Portfolio::whereKey($portfolio->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $locked);
            $previous = $locked->generations()->latest('id')->first();
            if ($locked->generations()->where('status', 'succeeded')->exists()) {
                throw ValidationException::withMessages(['generation' => 'This portfolio has already been generated. Edit its draft instead.']);
            }
            if ($locked->generations()->whereIn('status', ['queued', 'running'])->exists()) {
                throw ValidationException::withMessages(['generation' => 'Generation is already in progress.']);
            }
            if ($previous && $previous->status === 'failed' && $previous->input_snapshot !== null) {
                $progress = $previous->progress ?? [];
                $progress['attempts'] = [];
                $progress['deadline_at'] = now()->addDay()->toIso8601String();
                $previous->update(['status' => 'queued', 'run_version' => $previous->run_version + 1, 'progress' => $progress, 'error_code' => null, 'completed_at' => null, 'lease_token' => null, 'lease_expires_at' => null, 'next_run_at' => now()]);
                GenerationState::dispatch($previous);

                return $previous;
            }
            $document = $locked->cvDocument;
            $projects = $locked->projects()->get();
            if (! $document || $projects->isEmpty() || $projects->count() > 5) {
                throw ValidationException::withMessages(['generation' => 'Add a source PDF and one to five repository-backed projects first.']);
            }
            $inputs = ['schema_version' => 1, 'focus' => $locked->focus, 'pdf' => ['id' => $document->id, 'disk' => $document->storageDisk(), 'path' => $document->storage_path],
                'projects' => $projects->map(fn ($project): array => ['id' => $project->id, 'repository_owner' => $project->repository_owner, 'repository_name' => $project->repository_name, 'author_role' => $project->author_role, 'readme_content' => $project->readme_content, 'readme_fetched_at' => $project->readme_fetched_at?->toIso8601String()])->all()];
            $generation = $locked->generations()->create(['user_id' => $owner->id, 'status' => 'queued', 'current_step' => 'checking', 'input_snapshot' => $inputs, 'progress' => ['attempts' => [], 'projects' => [], 'deadline_at' => now()->addDay()->toIso8601String()], 'usage' => [], 'provider' => 'gemini', 'model' => config('services.gemini.model'), 'prompt_version' => 'portfolio-v3', 'next_run_at' => now()]);
            GenerationState::dispatch($generation);

            return $generation;
        });
    }
}
