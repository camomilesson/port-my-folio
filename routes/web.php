<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\LocalDemoLoginController;
use App\Http\Controllers\Userzone\DashboardController;
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
});
