<?php

use App\Http\Controllers\Api\PlaylistConverterController;
use App\Http\Controllers\Api\YouTubeOAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/playlists/convert', [PlaylistConverterController::class, 'convert'])
        ->name('api.playlists.convert');

    Route::get('/youtube/authorize', [YouTubeOAuthController::class, 'authorize'])
        ->name('api.youtube.authorize');
    
    Route::get('/youtube/callback', [YouTubeOAuthController::class, 'callback'])
        ->name('api.youtube.callback');
    
    Route::get('/spotify/callback', [\App\Http\Controllers\Api\SpotifyOAuthController::class, 'callback'])
        ->name('api.spotify.callback');
});

