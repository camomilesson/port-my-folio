<?php

namespace App\Http\Controllers;

use App\Actions\PortfolioPageData;
use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicPortfolioController extends Controller
{
    public function default(string $username): RedirectResponse
    {
        $user = User::where('username', $username)->with('defaultPortfolio')->firstOrFail();
        $portfolio = $user->defaultPortfolio;
        abort_unless($portfolio && $portfolio->user_id === $user->id && $portfolio->isPublished(), 404);

        return to_route('portfolio.home', [$username, $portfolio->slug])->header('Cache-Control', 'no-store, max-age=0');
    }

    public function home(string $username, string $portfolioSlug, PortfolioPageData $pages): Response
    {
        $snapshot = $this->snapshot($username, $portfolioSlug);

        return response()->view('portfolios.home', ['page' => $pages->published($snapshot, $username, $portfolioSlug)])->header('Cache-Control', 'no-store, max-age=0');
    }

    public function cv(string $username, string $portfolioSlug, PortfolioPageData $pages): Response
    {
        $snapshot = $this->snapshot($username, $portfolioSlug);

        return response()->view('portfolios.cv', ['page' => $pages->published($snapshot, $username, $portfolioSlug)])->header('Cache-Control', 'no-store, max-age=0');
    }

    public function project(string $username, string $portfolioSlug, string $projectSlug, PortfolioPageData $pages): Response
    {
        $snapshot = $this->snapshot($username, $portfolioSlug);
        $page = $pages->published($snapshot, $username, $portfolioSlug);
        $project = collect($page['projects'])->firstWhere('slug', $projectSlug);
        abort_unless($project, 404);

        return response()->view('portfolios.project', compact('page', 'project'))->header('Cache-Control', 'no-store, max-age=0');
    }

    public function pdf(string $username, string $portfolioSlug): StreamedResponse
    {
        $snapshot = $this->snapshot($username, $portfolioSlug);
        $pdf = $snapshot['original_pdf'];
        abort_unless(Storage::disk($pdf['disk'])->exists($pdf['path']), 404);

        return Storage::disk($pdf['disk'])->download($pdf['path'], 'cv.pdf', ['Content-Type' => 'application/pdf', 'Cache-Control' => 'no-store, max-age=0', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function image(string $username, string $portfolioSlug, string $projectSlug, string $mediaId): StreamedResponse
    {
        $snapshot = $this->snapshot($username, $portfolioSlug);
        $project = collect($snapshot['projects'])->firstWhere('slug', $projectSlug);
        abort_unless($project, 404);
        $image = collect($project['gallery'])->firstWhere('id', (int) $mediaId);
        abort_unless($image && Storage::disk($image['disk'])->exists($image['path']), 404);

        return Storage::disk($image['disk'])->response($image['path'], 'image.'.($image['mime_type'] === 'image/jpeg' ? 'jpg' : substr($image['mime_type'], 6)), ['Content-Type' => $image['mime_type'], 'Cache-Control' => 'no-store, max-age=0', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** @return array<string, mixed> */
    private function snapshot(string $username, string $portfolioSlug): array
    {
        $portfolio = Portfolio::where('slug', $portfolioSlug)->whereHas('user', fn ($query) => $query->where('username', $username))->whereNotNull('published_at')->whereNotNull('published_snapshot')->firstOrFail();
        $snapshot = $portfolio->published_snapshot;
        abort_unless(($snapshot['schema_version'] ?? null) === 1 && isset($snapshot['original_pdf'], $snapshot['cv_content'], $snapshot['home_content'], $snapshot['projects']), 404);

        return $snapshot;
    }
}
