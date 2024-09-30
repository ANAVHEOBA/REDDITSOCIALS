<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Facades\Socialite;
use App\Services\Socialite\RedditProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register custom Reddit Socialite provider
        Socialite::extend('reddit', function ($app) {
            $config = $app['config']['services.reddit'];
            return Socialite::buildProvider(RedditProvider::class, $config);
        });
    }
}
