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
        if ($data['photo']) {
            $data['photo']['url'] = route('userzone.photo.show', [$portfolio, $data['photo']['id']]);
        }
        $data['photo_upload_url'] = route('userzone.photo.store', $portfolio);
        $data['preview'] = true;
        $data['editable'] = $generation?->status === 'succeeded';
        $data['inline'] = ['home' => route('userzone.inline.page', [$portfolio, 'home']), 'cv' => route('userzone.inline.page', [$portfolio, 'cv'])];
        $data['links'] = ['home' => route('userzone.preview.home', $portfolio), 'cv' => route('userzone.preview.cv', $portfolio), 'editor' => route('userzone.portfolios.show', $portfolio),
            'pdf' => $data['original_pdf'] ? route('userzone.cv-document.show', $portfolio) : null];
        foreach ($data['projects'] as &$project) {
            $project['inline_url'] = route('userzone.inline.project', [$portfolio, $project['id']]);
            $project['upload_url'] = route('userzone.inline.images', [$portfolio, $project['id']]);
            $project['url'] = route('userzone.preview.project', [$portfolio, $project['id']]);
            $project['repository_url'] = $this->repositoryUrl($project);
            foreach ($project['gallery'] as &$image) {
                $image['inline_url'] = route('userzone.inline.image', [$portfolio, $project['id'], $image['id']]);
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
        if ($data['photo'] ?? null) {
            $data['photo']['url'] = route('portfolio.photo', [$username, $slug, $data['photo']['id']]);
        }
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
