<?php

namespace App\Actions;

use App\Models\Portfolio;

class PortfolioPageData
{
    public function __construct(private BuildPortfolioSnapshot $builder) {}

    /** @return array<string, mixed> */
    public function preview(Portfolio $portfolio): array
    {
        $generation = $portfolio->generations()->latest('id')->first();
        if ($generation?->status === 'succeeded') {
            $portfolio = $portfolio->fresh();
        }
        $data = $this->builder->draft($portfolio);
        $data = app(GenerationPreview::class)->handle($portfolio, $data, $generation);
        $data['preview'] = true;
        $data['links'] = ['home' => route('userzone.preview.home', $portfolio), 'cv' => route('userzone.preview.cv', $portfolio), 'editor' => route('userzone.portfolios.show', $portfolio),
            'pdf' => $data['original_pdf'] ? route('userzone.cv-document.show', $portfolio) : null];
        foreach ($data['projects'] as &$project) {
            $project['url'] = route('userzone.preview.project', [$portfolio, $project['id']]);
            $project['repository_url'] = $this->repositoryUrl($project);
            foreach ($project['gallery'] as &$image) {
                $image['url'] = route('userzone.gallery.show', [$portfolio, $project['id'], $image['id']]);
            }
            unset($image);
        }
        unset($project);

        return $data;
    }

    /** @param array<string, mixed> $snapshot
     * @return array<string, mixed>
     */
    public function published(array $snapshot, string $username, string $slug): array
    {
        $data = $snapshot;
        $data['preview'] = false;
        $data['links'] = ['home' => route('portfolio.home', [$username, $slug]), 'cv' => route('portfolio.cv', [$username, $slug]), 'pdf' => route('portfolio.pdf', [$username, $slug])];
        foreach ($data['projects'] as &$project) {
            $project['url'] = route('portfolio.project', [$username, $slug, $project['slug']]);
            $project['repository_url'] = $this->repositoryUrl($project);
            foreach ($project['gallery'] as &$image) {
                $image['url'] = route('portfolio.image', [$username, $slug, $project['slug'], $image['id']]);
            }
            unset($image);
        }
        unset($project);

        return $data;
    }

    /** @param array<string, mixed> $project */
    private function repositoryUrl(array $project): string
    {
        return 'https://github.com/'.rawurlencode($project['repository_owner']).'/'.rawurlencode($project['repository_name']);
    }
}
