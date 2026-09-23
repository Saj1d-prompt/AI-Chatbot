<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\ConversationMessageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Health
|--------------------------------------------------------------------------
*/

Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'API is healthy.',
    ]);
});

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    Route::post(
        '/register',
        [AuthController::class, 'register']
    )->middleware('throttle:register');

    Route::post(
        '/login',
        [AuthController::class, 'login']
    )->middleware('throttle:login');

    Route::middleware('auth:sanctum')
        ->group(function () {
            Route::get(
                '/user',
                [AuthController::class, 'user']
            );

            Route::post(
                '/logout',
                [AuthController::class, 'logout']
            );
        });
});

/*
|--------------------------------------------------------------------------
| Authenticated Conversation API
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')
    ->group(function () {
        /*
        |--------------------------------------------------------------------------
        | Conversations
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/conversations',
            [ConversationController::class, 'index']
        );

        Route::post(
            '/conversations',
            [ConversationController::class, 'store']
        );

        Route::get(
            '/conversations/{conversation}',
            [ConversationController::class, 'show']
        );

        Route::patch(
            '/conversations/{conversation}',
            [ConversationController::class, 'update']
        );

        Route::delete(
            '/conversations/{conversation}',
            [ConversationController::class, 'destroy']
        );

        /*
        |--------------------------------------------------------------------------
        | Conversation Messages
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/conversations/{conversation}/messages',
            [ConversationMessageController::class, 'index']
        );

        /*
        |--------------------------------------------------------------------------
        | AI Generation
        |--------------------------------------------------------------------------
        |
        | These endpoints call the AI provider, so they use the dedicated
        | AI rate limiter to help prevent abuse and unnecessary usage.
        |
        */

        Route::post(
            '/conversations/{conversation}/messages',
            [ConversationMessageController::class, 'store']
        )->middleware('throttle:ai');

        Route::post(
            '/conversations/{conversation}/regenerate',
            [ConversationMessageController::class, 'regenerate']
        )->middleware('throttle:ai');
    });