<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/login', [GoogleAuthController::class, 'redirect'])->middleware('guest')->name('login');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->middleware('guest')->name('auth.google.callback');
Route::post('/logout', [GoogleAuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::view('/dashboard', 'dashboard')->middleware('auth')->name('dashboard');
