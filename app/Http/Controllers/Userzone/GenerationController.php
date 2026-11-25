<?php

namespace App\Http\Controllers\Userzone;

use App\Actions\StartPortfolioGeneration;
use App\Http\Controllers\Controller;
use App\Models\Portfolio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class GenerationController extends Controller
{
    public function confirm(Portfolio $portfolio): View
    {
        Gate::authorize('update', $portfolio);
        $portfolio->load('cvDocument', 'projects');
        $quota = StartPortfolioGeneration::quota($portfolio->user);
        $generation = $portfolio->generations()->latest('id')->first();

        return view('userzone.portfolios.generate', compact('portfolio', 'quota', 'generation'));
    }

    public function store(Request $request, Portfolio $portfolio, StartPortfolioGeneration $start): RedirectResponse
    {
        Gate::authorize('update', $portfolio);
        $request->validate(['confirm' => ['accepted']]);
        $start->handle($portfolio);

        return to_route('userzone.generation.confirm', $portfolio)->with('status', 'Generation queued. You can leave this page and return later.');
    }

    public function status(Portfolio $portfolio): JsonResponse
    {
        Gate::authorize('view', $portfolio);
        $generation = $portfolio->generations()->latest('id')->first();
        $done = $generation?->progress['done'] ?? [];
        $completedProjects = count(array_filter(array_keys($done), fn (string $step): bool => str_starts_with($step, 'project:')));
        $step = $generation?->current_step;
        $label = match (true) {
            $generation?->status === 'failed' => 'Generation stopped'.(str_starts_with($step ?? '', 'project:') ? ' while writing project '.(collect($generation->input_snapshot['projects'] ?? [])->firstWhere('id', (int) substr($step, 8))['repository_name'] ?? 'content') : '').'. '.$this->failureMessage($generation->error_code),
            $generation?->status === 'succeeded' => 'Ready',
            $step === 'checking' => 'Checking inputs',
            $step === 'extracting' => 'Reading CV',
            str_starts_with($step ?? '', 'project:') => 'Writing projects ('.$completedProjects.'/'.count($generation->input_snapshot['projects'] ?? []).')',
            in_array($step, ['profile', 'assembling'], true) => 'Assembling portfolio',
            default => 'Not started',
        };

        return response()->json(['status' => $generation?->status, 'label' => $label, 'retry_at' => $generation?->next_run_at?->toIso8601String()])->header('Cache-Control', 'private, no-store');
    }

    private function failureMessage(?string $code): string
    {
        return match ($code) {
            'provider_not_configured', 'provider_rejected' => 'Provider configuration needs attention. Contact the site owner before retrying.',
            'readme_insufficient', 'readme_too_large', 'github_missing', 'github_not_public' => 'Repository evidence is unavailable or insufficient. Fix the README and retry, or create a new portfolio.',
            'invalid_output', 'incomplete_output' => 'The provider returned incomplete or invalid content. Retry the failed step.',
            default => 'The attempt could not finish. Completed steps are saved; you can retry.',
        };
    }
}
