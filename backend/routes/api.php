<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatController;
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
    );

    Route::post(
        '/login',
        [AuthController::class, 'login']
    );

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
| Temporary AI Provider Test Endpoint
|--------------------------------------------------------------------------
|
| This endpoint currently remains available for development/provider
| testing. The real frontend uses the conversation endpoints below.
|
*/

Route::post(
    '/chat',
    [ChatController::class, 'store']
);

/*
|--------------------------------------------------------------------------
| Authenticated Conversation API
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')
    ->group(function () {
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

        Route::get(
            '/conversations/{conversation}/messages',
            [ConversationMessageController::class, 'index']
        );

        Route::post(
            '/conversations/{conversation}/messages',
            [ConversationMessageController::class, 'store']
        );

        Route::post(
            '/conversations/{conversation}/regenerate',
            [ConversationMessageController::class, 'regenerate']
        );
    });