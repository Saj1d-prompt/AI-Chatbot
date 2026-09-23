<?php

namespace App\Providers;

use App\Contracts\AIProviderInterface;
use App\Services\AI\Providers\CloudflareAIProvider;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

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
        RateLimiter::for(
            'login',
            function (Request $request) {
                $email = Str::lower(
                    (string) $request->input(
                        'email'
                    )
                );

                return Limit::perMinute(5)
                    ->by(
                        $email . '|' .
                        $request->ip()
                    );
            }
        );

        RateLimiter::for(
            'register',
            function (Request $request) {
                return Limit::perMinute(3)
                    ->by(
                        $request->ip()
                    );
            }
        );

        RateLimiter::for(
            'ai',
            function (Request $request) {
                $key = $request->user()
                    ? 'user:' .
                        $request->user()->id
                    : 'ip:' .
                        $request->ip();

                return Limit::perMinute(20)
                    ->by($key);
            }
        );
    }
}