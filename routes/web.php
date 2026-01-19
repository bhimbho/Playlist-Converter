<?php

use App\Http\Controllers\Api\YouTubeOAuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [LoginController::class, 'index'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.validate');

use App\Http\Controllers\Api\SpotifyOAuthController;

// OAuth callbacks (must be accessible without auth for redirects)
Route::get('/youtube/callback', [YouTubeOAuthController::class, 'callback'])
    ->name('youtube.callback');
Route::get('/spotify/callback', [SpotifyOAuthController::class, 'callback'])
    ->name('spotify.callback');

Route::group(['middleware' => 'auth'], function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/history', [HomeController::class, 'history'])->name('history');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/youtube/authorize', [YouTubeOAuthController::class, 'authorize'])
        ->name('youtube.authorize');
    Route::get('/spotify/authorize', [SpotifyOAuthController::class, 'authorize'])
        ->name('spotify.authorize');
});