<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // AI Client binding is now handled by AiServiceProvider with priority-based selection
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
