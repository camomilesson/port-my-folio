<?php

namespace App\Models;

use Database\Factories\PortfolioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['user_id', 'title', 'slug', 'focus', 'preset', 'accent_color', 'home_content', 'cv_content', 'published_snapshot', 'published_at'])]
class Portfolio extends Model
{
    /** @use HasFactory<PortfolioFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['home_content' => 'array', 'cv_content' => 'array', 'published_snapshot' => 'array', 'published_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class)->orderBy('position')->orderBy('id');
    }

    public function cvDocument(): HasOne
    {
        return $this->hasOne(CvDocument::class);
    }

    public function generations(): HasMany
    {
        return $this->hasMany(Generation::class);
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_snapshot !== null;
    }
}
