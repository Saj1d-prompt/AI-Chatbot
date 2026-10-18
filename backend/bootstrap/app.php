
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Throwable;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * Always return JSON for API exceptions,
         * including unauthenticated requests.
         *
         * This prevents Laravel from trying to
         * redirect API clients to a named login route.
         */
        $exceptions->shouldRenderJsonWhen(
            function (Request $request, Throwable $exception): bool {
                return $request->is('api/*')
                    || $request->expectsJson();
            }
        );
    })
    ->create();
