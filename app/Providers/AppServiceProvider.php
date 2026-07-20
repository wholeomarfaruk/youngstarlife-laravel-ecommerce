<?php

namespace App\Providers;

use App\Services\AnthropicOrderExtractionService;
use App\Services\GeminiOrderExtractionService;
use App\Services\OpenAiOrderExtractionService;
use App\Services\OpenRouterOrderExtractionService;
use App\Services\OrderExtractionServiceInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(OrderExtractionServiceInterface::class, function () {
            return match (config('services.ai.provider')) {
                'openai' => new OpenAiOrderExtractionService(),
                'gemini' => new GeminiOrderExtractionService(),
                'openrouter' => new OpenRouterOrderExtractionService(),
                default => new AnthropicOrderExtractionService(),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Some local Windows PHP installs point openssl.cnf at a path that doesn't
        // exist, which breaks the EC key signing web-push needs for VAPID auth.
        // Only kicks in when that's actually the case; no-op everywhere else
        // (e.g. production Linux, where the default openssl.cnf is valid).
        if (! getenv('OPENSSL_CONF') && ! file_exists('C:\\Program Files\\Common Files\\SSL\\openssl.cnf')) {
            $fallback = 'C:/php-8.3.22/extras/ssl/openssl.cnf';

            if (file_exists($fallback)) {
                putenv("OPENSSL_CONF={$fallback}");
            }
        }
    }
}
