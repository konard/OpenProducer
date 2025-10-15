<?php

namespace App\Providers;

use App\Services\AiClientInterface;
use App\Services\OpenAiCompatibleClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind AI Client interface to implementation
        $this->app->bind(AiClientInterface::class, OpenAiCompatibleClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
