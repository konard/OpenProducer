<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\AiClientInterface;
use App\Services\GeminiClient;
use App\Services\OpenAiCompatibleClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(AiClientInterface::class, function ($app) {
            // Priority-based provider detection
            $providers = $this->getAvailableProviders();

            foreach ($providers as $provider => $config) {
                Log::info('AI Service Provider Selected', [
                    'provider' => $provider,
                    'model' => $config['model'],
                    'priority' => $config['priority'],
                ]);

                return $config['client']();
            }

            // Emergency fallback
            Log::warning('No AI providers available, using fallback');
            return new OpenAiCompatibleClient();
        });
    }

    /**
     * Get available AI providers with priority order
     */
    private function getAvailableProviders(): array
    {
        $providers = [];

        // Priority 1: Gemini (free, fast, modern)
        $geminiKey = config('bot.gemini.api_key');
        if (!empty($geminiKey)) {
            $providers['GEMINI'] = [
                'client' => fn () => new GeminiClient(),
                'model' => config('bot.gemini.model', 'gemini-2.5-flash'),
                'priority' => 1,
                'key_length' => strlen($geminiKey),
            ];
        }

        // Priority 2: OpenAI (reliable, high quality)
        $openaiKey = config('bot.openai.api_key');
        if (!empty($openaiKey) && config('bot.openai.provider') === 'OPENAI') {
            $providers['OPENAI'] = [
                'client' => fn () => new OpenAiCompatibleClient(),
                'model' => config('bot.openai.model', 'gpt-4o-mini'),
                'priority' => 2,
                'key_length' => strlen($openaiKey),
            ];
        }

        // Priority 3: ZAI (fallback, paid)
        $zaiKey = config('bot.openai.api_key');
        if (!empty($zaiKey) && config('bot.openai.provider') === 'ZAI') {
            $providers['ZAI'] = [
                'client' => fn () => new OpenAiCompatibleClient(),
                'model' => config('bot.openai.model', 'glm-4.6'),
                'priority' => 3,
                'key_length' => strlen($zaiKey),
            ];
        }

        // Sort by priority (lower number = higher priority)
        uasort($providers, function ($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });

        return $providers;
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
