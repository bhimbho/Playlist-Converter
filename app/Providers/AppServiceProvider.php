<?php

namespace App\Providers;

use App\Services\Contracts\SpotifyServiceInterface;
use App\Services\Contracts\YouTubeServiceInterface;
use App\Services\SpotifyService;
use App\Services\YouTubeService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SpotifyServiceInterface::class, SpotifyService::class);
        $this->app->bind(YouTubeServiceInterface::class, YouTubeService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
