<?php

use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\ConversationMessageController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'AI Chatbot API is running.',
    ]);
});

/*
|--------------------------------------------------------------------------
| Standalone AI Chat
|--------------------------------------------------------------------------
|
| Temporary endpoint used for provider testing.
|
*/

Route::post(
    '/chat',
    [ChatController::class, 'store']
);

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

Route::post(
    '/conversations/{conversation}/messages',
    [ConversationMessageController::class, 'store']
);