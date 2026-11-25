<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Fillable(['portfolio_id', 'slug', 'repository_owner', 'repository_name', 'readme_content', 'readme_fetched_at', 'title', 'summary', 'overview', 'technical_details', 'technologies', 'live_url', 'position'])]
class Project extends Model implements HasMedia
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    use InteractsWithMedia;
    use SoftDeletes;

    protected $attributes = ['author_role' => ''];

    protected function casts(): array
    {
        return ['readme_fetched_at' => 'datetime', 'technologies' => 'array', 'position' => 'integer'];
    }

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }

    public function repositoryUrl(): string
    {
        return 'https://github.com/'.rawurlencode($this->repository_owner).'/'.rawurlencode($this->repository_name);
    }

    /** @return Collection<int, Media> */
    public function activeGallery(): Collection
    {
        return $this->getMedia('gallery')->reject(fn ($media): bool => (bool) $media->getCustomProperty('draft_removed', false))->sortBy('order_column')->values();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('gallery')->useDisk(config('portmyfolio.image_disk'));
    }
}
