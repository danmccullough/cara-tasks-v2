<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

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
        // Playwright/Lighthouse servers point this at a missing file so they always use the
        // built assets, even while `composer dev` (and its public/hot file) is running.
        if ($hotFile = env('VITE_HOT_FILE')) {
            Vite::useHotFile($hotFile);
        }
    }
}
