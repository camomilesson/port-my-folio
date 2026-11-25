<?php

namespace App\Providers;

use App\Actions\GeminiPortfolioProvider;
use App\Actions\PortfolioGenerationProvider;
use App\Policies\ProjectMediaPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PortfolioGenerationProvider::class, GeminiPortfolioProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Media::class, ProjectMediaPolicy::class);
    }
}
