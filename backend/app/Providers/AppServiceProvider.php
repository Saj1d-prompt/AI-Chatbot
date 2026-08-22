<?php

namespace App\Providers;

use App\Contracts\AIProviderInterface;
use App\Services\AI\Providers\CloudflareAIProvider;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register application services.
     */
    public function register(): void
    {
        $this->app->bind(
            AIProviderInterface::class,
            function ($app) {
                $provider = config('ai.default');

                return match ($provider) {
                    'cloudflare' => $app->make(
                        CloudflareAIProvider::class
                    ),

                    default => throw new InvalidArgumentException(
                        "Unsupported AI provider: {$provider}"
                    ),
                };
            }
        );
    }

    /**
     * Bootstrap application services.
     */
    public function boot(): void
    {
        //
    }
}