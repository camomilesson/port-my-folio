<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\LocalDemoLoginController;
use App\Http\Controllers\Userzone\CvDocumentController;
use App\Http\Controllers\Userzone\DashboardController;
use App\Http\Controllers\Userzone\DraftContentController;
use App\Http\Controllers\Userzone\GalleryController;
use App\Http\Controllers\Userzone\PortfolioController;
use App\Http\Controllers\Userzone\PreviewController;
use App\Http\Controllers\Userzone\ProjectEditorController;
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
        Route::resource('portfolios', PortfolioController::class)->only(['create', 'store', 'show', 'destroy']);
    });
});
