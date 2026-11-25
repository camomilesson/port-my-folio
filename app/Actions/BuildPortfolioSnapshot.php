<?php

namespace App\Actions;

use App\Models\Portfolio;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BuildPortfolioSnapshot
{
    /** @return array<string, mixed> */
    public function draft(Portfolio $portfolio): array
    {
        $portfolio->loadMissing('projects.media', 'cvDocument');
        $cv = PortfolioContent::cv($portfolio);
        $projects = [];
        foreach ($portfolio->projects as $project) {
            $gallery = [];
            foreach ($project->activeGallery() as $media) {
                $gallery[] = ['id' => $media->id, 'disk' => $media->disk, 'path' => $media->getPathRelativeToRoot(), 'mime_type' => $media->mime_type,
                    'width' => $media->getCustomProperty('width'), 'height' => $media->getCustomProperty('height'),
                    'caption' => $media->getCustomProperty('caption'), 'alt_text' => $media->getCustomProperty('decorative', false) ? '' : $media->getCustomProperty('alt_text', ''),
                    'decorative' => (bool) $media->getCustomProperty('decorative', false)];
            }
            $projects[] = ['id' => $project->id, 'slug' => $project->slug, 'repository_owner' => $project->repository_owner, 'repository_name' => $project->repository_name,
                'title' => $project->title, 'summary' => $project->summary, 'overview' => $project->overview, 'technical_details' => $project->technical_details ?? '',
                'technologies' => $project->technologies ?? [], 'live_url' => $project->live_url, 'gallery' => $gallery];
        }
        $document = $portfolio->cvDocument;

        return ['schema_version' => 1, 'preset' => $portfolio->preset, 'accent_color' => $portfolio->accent_color,
            'home_content' => ['schema_version' => 1, 'name' => $cv['name'], 'headline' => $cv['headline'], 'introduction' => $portfolio->home_content['introduction'] ?? null],
            'cv_content' => $cv, 'projects' => $projects,
            'photo' => $portfolio->home_content['photo'] ?? null,
            'original_pdf' => $document ? ['disk' => $document->storageDisk(), 'path' => $document->storage_path, 'mime_type' => 'application/pdf'] : null];
    }

    /** @return array<string, mixed> */
    public function build(Portfolio $portfolio): array
    {
        $snapshot = $this->draft($portfolio);
        $cvRules = PortfolioContent::cvRules();
        $cvRules['name'] = ['nullable', 'string', 'max:120'];
        $cvRules['headline'] = ['nullable', 'string', 'max:120'];
        $cv = $snapshot['cv_content'];
        unset($cv['schema_version']);
        $validator = Validator::make($cv, $cvRules);
        $validator->after(fn ($validator) => PortfolioContent::validateDates($validator, $cv));
        if ($validator->fails()) {
            throw ValidationException::withMessages(['publication' => 'Some saved CV content is invalid. Review the CV editor before publishing.']);
        }
        $snapshot['cv_content'] = ['schema_version' => 1] + $validator->validated();
        Validator::make($snapshot, [
            'preset' => ['required', 'in:minimal'], 'accent_color' => ['required', 'regex:/^#[a-fA-F0-9]{6}$/'],
            'home_content.introduction' => ['nullable', 'string', 'max:3000'],
            'original_pdf' => ['required', 'array:disk,path,mime_type'],
            'projects' => ['required', 'array', 'list', 'min:1', 'max:5'],
            'projects.*.id' => ['required', 'integer', 'distinct'],
            'projects.*.slug' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'distinct'],
            'projects.*.repository_owner' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9-]+$/'],
            'projects.*.repository_name' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_.-]+$/'],
            'projects.*.title' => ['nullable', 'string', 'max:120'], 'projects.*.summary' => ['nullable', 'string', 'max:1000'],
            'projects.*.technical_details' => ['nullable', 'string', 'max:10000'],
            'projects.*.overview' => ['nullable', 'string', 'max:10000'], 'projects.*.live_url' => ['nullable', 'url:http,https', 'max:2048'],
            'projects.*.technologies' => ['present', 'array', 'list', 'max:30'], 'projects.*.technologies.*' => ['string', 'max:80'],
            'projects.*.gallery' => ['present', 'array', 'list', 'max:10'],
            'projects.*.gallery.*.id' => ['required', 'integer', 'distinct'],
            'projects.*.gallery.*.mime_type' => ['required', 'in:image/jpeg,image/png,image/webp'],
            'projects.*.gallery.*.caption' => ['nullable', 'string', 'max:500'],
            'projects.*.gallery.*.alt_text' => ['nullable', 'string', 'max:500'],
        ])->validate();
        foreach ($snapshot['projects'] as $project) {
            foreach ($project['gallery'] as $image) {
                if (! $image['decorative'] && trim($image['alt_text']) === '') {
                    throw ValidationException::withMessages(['publication' => 'Give each non-decorative image a description before publishing.']);
                }
                $this->requireFile($image);
            }
        }
        if ($snapshot['photo']) {
            $this->requireFile($snapshot['photo']);
        }
        $this->requireFile($snapshot['original_pdf']);
        $snapshot['published_at'] = now()->toIso8601String();

        return $snapshot;
    }

    /** @param array<string, mixed> $file */
    private function requireFile(array $file): void
    {
        if (! array_key_exists($file['disk'], config('filesystems.disks')) || ! Storage::disk($file['disk'])->exists($file['path'])) {
            throw ValidationException::withMessages(['publication' => 'A source file is unavailable. Please check your PDF and gallery files before publishing.']);
        }
    }
}
