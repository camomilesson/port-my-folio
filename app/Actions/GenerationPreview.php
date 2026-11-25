<?php

namespace App\Actions;

use App\Models\Generation;
use App\Models\Portfolio;

class GenerationPreview
{
    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function handle(Portfolio $portfolio, array $data, ?Generation $generation = null): array
    {
        $generation ??= $portfolio->generations()->latest('id')->first();
        $data['generation'] = self::status($generation);
        $data['generation']['revision'] = hash('sha256', json_encode([$generation?->id, $generation?->run_version, $generation?->status, $generation?->current_step, $generation?->progress['done'] ?? [], $generation?->next_run_at?->toIso8601String()]));
        $data['generation']['has_extraction'] = true;
        $data['generation']['has_profile'] = true;
        if (! $generation || $generation->status === 'succeeded' || ! isset($generation->input_snapshot['pdf'])) {
            return $data;
        }
        $checkpoints = $generation->progress['checkpoints'] ?? [];
        $data['generation']['has_extraction'] = isset($checkpoints['extracting']);
        $data['generation']['has_profile'] = isset($checkpoints['profile']);
        $data['cv_content'] = PortfolioContent::cv(new Portfolio);
        $data['home_content'] = ['schema_version' => 1, 'name' => '', 'headline' => '', 'introduction' => ''];
        if (isset($checkpoints['extracting'])) {
            $data['cv_content'] = ['schema_version' => 1] + $checkpoints['extracting'];
        }
        if (isset($checkpoints['profile'])) {
            $data['cv_content'] = ['schema_version' => 1] + $checkpoints['profile']['cv_content'];
            $data['home_content'] = ['schema_version' => 1] + $checkpoints['profile']['home_content'];
        }
        foreach ($data['projects'] as &$project) {
            $checkpoint = $checkpoints['project:'.$project['id']] ?? null;
            $project = array_replace($project, $checkpoint ?? ['title' => '', 'summary' => '', 'overview' => '', 'technical_details' => '', 'technologies' => [], 'live_url' => null]);
            $project['pending'] = $checkpoint === null;
        }
        unset($project);

        return $data;
    }

    /** @return array{status: ?string, label: string, retry_at: ?string} */
    public static function status(?Generation $generation): array
    {
        $step = $generation?->current_step;
        $done = $generation?->progress['done'] ?? [];
        $completed = count(array_filter(array_keys($done), fn (string $key): bool => str_starts_with($key, 'project:')));
        $label = match (true) {
            $generation?->status === 'failed' => 'Generation stopped. '.self::failureMessage($generation->error_code),
            $generation?->status === 'succeeded' => 'Ready',
            $step === 'checking' => 'Checking inputs',
            $step === 'extracting' => 'Reading CV',
            str_starts_with($step ?? '', 'project:') => 'Writing projects ('.$completed.'/'.count($generation->input_snapshot['projects'] ?? []).')',
            in_array($step, ['profile', 'assembling'], true) => 'Assembling portfolio',
            default => 'Not started',
        };

        return ['status' => $generation?->status, 'label' => $label, 'retry_at' => $generation?->next_run_at?->toIso8601String()];
    }

    private static function failureMessage(?string $code): string
    {
        return match ($code) {
            'provider_not_configured', 'provider_rejected' => 'Provider configuration needs attention. Contact the site owner before retrying.',
            'readme_insufficient', 'readme_too_large', 'github_missing', 'github_not_public' => 'Repository evidence needs attention. Check the README or create a new portfolio.',
            'invalid_output', 'incomplete_output' => 'The provider returned incomplete or invalid content. Retry the failed step.',
            default => 'Completed sections are saved. You can retry the remaining steps.',
        };
    }
}
