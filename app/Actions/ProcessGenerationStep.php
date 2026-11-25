<?php

namespace App\Actions;

use App\Models\Generation;
use App\Models\Portfolio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProcessGenerationStep
{
    public function __construct(private PortfolioGenerationProvider $provider, private FetchGithubReadme $readmes) {}

    public function handle(int $id, int $version): void
    {
        $claim = GenerationState::locked($id, function (Generation $generation) use ($version): ?array {
            if ($generation->run_version !== $version || $generation->status !== 'queued' || $generation->next_run_at?->isFuture()) {
                return null;
            }
            $progress = $generation->progress ?? [];
            if (isset($progress['deadline_at']) && Carbon::parse($progress['deadline_at'])->isPast()) {
                GenerationState::fail($generation, 'recovery_deadline');

                return null;
            }
            $step = $this->nextStep($generation);
            $attempts = ($progress['attempts'][$step] ?? 0) + 1;
            if ($attempts > 3) {
                GenerationState::fail($generation, 'attempts_exhausted');

                return null;
            }
            $progress['attempts'][$step] = $attempts;
            $token = (string) Str::uuid();
            $generation->update(['status' => 'running', 'current_step' => $step, 'progress' => $progress, 'started_at' => $generation->started_at ?? now(), 'lease_token' => $token, 'lease_expires_at' => now()->addMinutes(3), 'next_run_at' => null]);

            return [$generation, $token, $step];
        });
        if (! $claim) {
            return;
        }
        [$generation, $token, $step] = $claim;
        $originalModel = config('services.gemini.model');
        config(['services.gemini.model' => $generation->model]);
        try {
            $result = $this->execute($generation, $step);
            GenerationState::locked($id, function (Generation $current, Portfolio $portfolio) use ($version, $token, $step, $result): void {
                if (! $this->ownsLease($current, $version, $token)) {
                    return;
                }
                $progress = $current->progress;
                if ($result instanceof ProviderResult) {
                    $progress['checkpoints'][$step] = $result->content;
                    $usage = $current->usage ?? [];
                    $usage[$step] = ['provider' => $result->provider, 'model' => $result->model, 'prompt_version' => $result->promptVersion, 'tokens' => $result->usage];
                    $current->usage = $usage;
                    $current->provider = $result->provider;
                } elseif (is_array($result)) {
                    $current->input_snapshot = $result;
                }
                $progress['done'][$step] = true;
                $current->progress = $progress;
                if ($step === 'assembling') {
                    $this->assemble($current, $portfolio);
                    $current->fill(['status' => 'succeeded', 'current_step' => 'completed', 'completed_at' => now(), 'error_code' => null, 'lease_token' => null, 'lease_expires_at' => null, 'next_run_at' => null])->save();
                } else {
                    $current->fill(['status' => 'queued', 'current_step' => $this->nextStep($current), 'lease_token' => null, 'lease_expires_at' => null, 'next_run_at' => now()])->save();
                    GenerationState::dispatch($current);
                }
            });
        } catch (\Throwable $exception) {
            $failure = $exception instanceof ProviderFailure ? $exception : new ProviderFailure($exception instanceof ValidationException ? 'invalid_input' : 'processing_failed', 'Processing failed. Retry this attempt later.');
            GenerationState::locked($id, function (Generation $current) use ($version, $token, $step, $failure): void {
                if (! $this->ownsLease($current, $version, $token)) {
                    return;
                }
                $retryable = in_array($failure->errorCode, ['provider_retryable', 'provider_unavailable', 'github_rate_limited', 'github_unavailable', 'processing_failed'], true);
                if ($retryable && ($current->progress['attempts'][$step] ?? 0) < 3) {
                    $retryAt = max(now()->timestamp + 30, $failure->retryAt ?? 0);
                    $current->update(['status' => 'queued', 'error_code' => $failure->errorCode, 'lease_token' => null, 'lease_expires_at' => null, 'next_run_at' => Carbon::createFromTimestamp($retryAt)]);
                    GenerationState::dispatch($current);
                } else {
                    GenerationState::fail($current, $failure->errorCode);
                }
            });
        } finally {
            config(['services.gemini.model' => $originalModel]);
        }
    }

    private function ownsLease(Generation $generation, int $version, string $token): bool
    {
        return $generation->status === 'running' && $generation->run_version === $version && $generation->lease_token === $token && $generation->lease_expires_at?->isFuture();
    }

    public function nextStep(Generation $generation): string
    {
        $done = $generation->progress['done'] ?? [];
        foreach (['checking', 'extracting'] as $step) {
            if (! ($done[$step] ?? false)) {
                return $step;
            }
        }
        foreach ($generation->input_snapshot['projects'] as $project) {
            $step = 'project:'.$project['id'];
            if (! ($done[$step] ?? false)) {
                return $step;
            }
        }

        return ($done['profile'] ?? false) ? 'assembling' : 'profile';
    }

    private function execute(Generation $generation, string $step): ProviderResult|array|null
    {
        $inputs = $generation->input_snapshot;
        if ($step === 'checking') {
            foreach ($inputs['projects'] as &$project) {
                if (! $project['readme_content']) {
                    $project['readme_content'] = $this->readmes->handle('https://github.com/'.$project['repository_owner'].'/'.$project['repository_name'])['content'];
                }
                $this->readmes->validateContent($project['readme_content']);
            }
            unset($project);
            app(ValidateCvPdf::class)->handle(Storage::disk($inputs['pdf']['disk'])->get($inputs['pdf']['path']));

            return $inputs;
        }
        if ($step === 'extracting') {
            return $this->provider->extractCv(Storage::disk($inputs['pdf']['disk'])->get($inputs['pdf']['path']));
        }
        if (str_starts_with($step, 'project:')) {
            $project = collect($inputs['projects'])->firstWhere('id', (int) substr($step, 8));

            return $this->provider->generateProject(['focus' => $inputs['focus'], 'project' => $project]);
        }
        if ($step === 'profile') {
            return $this->provider->generateProfile(['focus' => $inputs['focus'], 'cv' => $generation->progress['checkpoints']['extracting'], 'projects' => collect($inputs['projects'])->map(fn (array $project): array => $generation->progress['checkpoints']['project:'.$project['id']] + ['author_role' => $project['author_role']])->all()]);
        }

        return null;
    }

    private function assemble(Generation $generation, Portfolio $portfolio): void
    {
        $checkpoints = $generation->progress['checkpoints'];
        $profile = GenerationContent::validateProfile($checkpoints['profile']);
        $portfolio->update(['home_content' => ['schema_version' => 1] + $profile['home_content'], 'cv_content' => ['schema_version' => 1] + $profile['cv_content']]);
        $portfolio->cvDocument()->whereKey($generation->input_snapshot['pdf']['id'])->update(['extracted_content' => $checkpoints['extracting'], 'status' => 'ready', 'error_code' => null]);
        foreach ($generation->input_snapshot['projects'] as $source) {
            $project = $portfolio->projects()->findOrFail($source['id']);
            $content = GenerationContent::validateProject($checkpoints['project:'.$source['id']]);
            unset($content['repository_owner'], $content['repository_name']);
            $project->update($content + ['readme_content' => $source['readme_content']]);
        }
    }
}
