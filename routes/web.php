<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\LocalDemoLoginController;
use App\Http\Controllers\PublicPortfolioController;
use App\Http\Controllers\Userzone\AppearanceController;
use App\Http\Controllers\Userzone\CvDocumentController;
use App\Http\Controllers\Userzone\DashboardController;
use App\Http\Controllers\Userzone\DraftContentController;
use App\Http\Controllers\Userzone\GalleryController;
use App\Http\Controllers\Userzone\GenerationController;
use App\Http\Controllers\Userzone\InlinePortfolioController;
use App\Http\Controllers\Userzone\PortfolioController;
use App\Http\Controllers\Userzone\PreviewController;
use App\Http\Controllers\Userzone\ProjectEditorController;
use App\Http\Controllers\Userzone\PublicationController;
use App\Http\Controllers\Userzone\UsernameController;
use App\Http\Controllers\WelcomeController;
use App\Http\Middleware\EnsureUsernameIsSet;
use Illuminate\Support\Facades\Route;

Route::get('/', WelcomeController::class)->name('home');

Route::get('/login', [GoogleAuthController::class, 'redirect'])->middleware('guest')->name('login');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->middleware('guest')->name('auth.google.callback');
Route::post('/logout', [GoogleAuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::post('/demo/login', LocalDemoLoginController::class)->middleware(['guest', 'throttle:6,1'])->name('demo.login');
Route::middleware('auth')->group(function () {
    Route::get('/onboarding/username', [UsernameController::class, 'edit'])->name('userzone.username.edit');
    Route::post('/onboarding/username', [UsernameController::class, 'store'])->name('userzone.username.store');
});
Route::middleware(['auth', EnsureUsernameIsSet::class])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::prefix('user')->name('userzone.')->group(function () {
        Route::prefix('portfolios/{portfolio}')->group(function () {
            Route::patch('appearance', AppearanceController::class)->name('appearance.update');
            Route::post('photo', [InlinePortfolioController::class, 'photo'])->name('photo.store');
            Route::get('photo/{photoId}', [InlinePortfolioController::class, 'showPhoto'])->whereUuid('photoId')->name('photo.show');
            Route::patch('inline/{scope}', [InlinePortfolioController::class, 'page'])->whereIn('scope', ['home', 'cv'])->name('inline.page');
            Route::patch('projects/{project}/inline', [InlinePortfolioController::class, 'project'])->name('inline.project');
            Route::post('projects/{project}/images', [InlinePortfolioController::class, 'images'])->name('inline.images');
            Route::patch('projects/{project}/images/{media}', [InlinePortfolioController::class, 'image'])->name('inline.image');
            Route::get('generate', [GenerationController::class, 'confirm'])->name('generation.confirm');
            Route::post('generate', [GenerationController::class, 'store'])->middleware('throttle:6,1')->name('generation.store');
            Route::get('generation-status', [GenerationController::class, 'status'])->name('generation.status');
            Route::get('publish', [PublicationController::class, 'confirm'])->name('publication.confirm');
            Route::post('publish', [PublicationController::class, 'publish'])->name('publication.publish');
            Route::delete('publish', [PublicationController::class, 'unpublish'])->name('publication.unpublish');
            Route::post('make-default', [PublicationController::class, 'makeDefault'])->name('publication.default');
            Route::get('edit/home', [DraftContentController::class, 'home'])->name('content.home');
            Route::put('edit/home', [DraftContentController::class, 'updateHome'])->name('content.home.update');
            Route::get('edit/cv', [DraftContentController::class, 'cv'])->name('content.cv');
            Route::put('edit/cv', [DraftContentController::class, 'updateCv'])->name('content.cv.update');
            Route::post('source-cv', [CvDocumentController::class, 'store'])->name('cv-document.store');
            Route::get('source-cv', [CvDocumentController::class, 'show'])->name('cv-document.show');
            Route::get('preview', [PreviewController::class, 'home'])->name('preview.home');
            Route::get('preview/cv', [PreviewController::class, 'cv'])->name('preview.cv');
            Route::get('preview/projects/{project}', [PreviewController::class, 'project'])->name('preview.project');
            Route::get('projects/create', [ProjectEditorController::class, 'create'])->name('projects.create');
            Route::post('projects', [ProjectEditorController::class, 'store'])->name('projects.store');
            Route::get('projects/{project}/edit', [ProjectEditorController::class, 'edit'])->name('projects.edit');
            Route::put('projects/{project}', [ProjectEditorController::class, 'update'])->name('projects.update');
            Route::delete('projects/{project}', [ProjectEditorController::class, 'destroy'])->name('projects.destroy');
            Route::post('projects/{project}/move', [ProjectEditorController::class, 'move'])->name('projects.move');
            Route::post('projects/{project}/gallery', [GalleryController::class, 'store'])->name('gallery.store');
            Route::get('projects/{project}/gallery/{media}', [GalleryController::class, 'show'])->name('gallery.show');
            Route::put('projects/{project}/gallery/{media}', [GalleryController::class, 'update'])->name('gallery.update');
            Route::delete('projects/{project}/gallery/{media}', [GalleryController::class, 'destroy'])->name('gallery.destroy');
            Route::post('projects/{project}/gallery/{media}/move', [GalleryController::class, 'move'])->name('gallery.move');
        });
        Route::post('generate-portfolio', [PortfolioController::class, 'generate'])->middleware('throttle:6,1')->name('portfolios.generate');
        Route::resource('portfolios', PortfolioController::class)->only(['create', 'store', 'show', 'destroy']);
    });
});

Route::get('/{username}/{portfolioSlug}/photo/{photoId}', [PublicPortfolioController::class, 'photo'])->whereUuid('photoId')->name('portfolio.photo');
Route::get('/{username}/{portfolioSlug}/cv/download', [PublicPortfolioController::class, 'pdf'])->name('portfolio.pdf');
Route::get('/{username}/{portfolioSlug}/projects/{projectSlug}/images/{mediaId}', [PublicPortfolioController::class, 'image'])->whereNumber('mediaId')->name('portfolio.image');
Route::get('/{username}/{portfolioSlug}/projects/{projectSlug}', [PublicPortfolioController::class, 'project'])->name('portfolio.project');
Route::get('/{username}/{portfolioSlug}/cv', [PublicPortfolioController::class, 'cv'])->name('portfolio.cv');
Route::get('/{username}/{portfolioSlug}', [PublicPortfolioController::class, 'home'])->name('portfolio.home');
Route::get('/{username}', [PublicPortfolioController::class, 'default'])->name('portfolio.default');
