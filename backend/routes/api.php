<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\ConversationMessageController;
use App\Http\Controllers\Api\ConversationAttachmentController;
use App\Http\Controllers\Api\KnowledgeBaseController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Middleware\EnsureRegularKnowledgeBase;
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

    Route::middleware('auth:sanctum')->group(function () {

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
| Authenticated API
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

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

    Route::put(
        '/conversations/{conversation}/knowledge-bases',
        [ConversationController::class, 'updateKnowledgeBases']
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
    )->middleware('throttle:ai');

    Route::post(
        '/conversations/{conversation}/regenerate',
        [ConversationMessageController::class, 'regenerate']
    )->middleware('throttle:ai');

    /*
    |--------------------------------------------------------------------------
    | Knowledge Bases
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/knowledge-bases',
        [KnowledgeBaseController::class, 'index']
    );

    Route::post(
        '/knowledge-bases',
        [KnowledgeBaseController::class, 'store']
    );

    Route::get(
        '/knowledge-bases/{knowledgeBase}',
        [KnowledgeBaseController::class, 'show']
    );

    Route::patch(
        '/knowledge-bases/{knowledgeBase}',
        [KnowledgeBaseController::class, 'update']
    );

    Route::delete(
        '/knowledge-bases/{knowledgeBase}',
        [KnowledgeBaseController::class, 'destroy']
    );

    /*
    |--------------------------------------------------------------------------
    | Knowledge Base Documents
    |--------------------------------------------------------------------------
    |
    | These endpoints are only available for regular,
    | reusable knowledge bases.
    |
    | Internal chat attachment knowledge bases must
    | not be accessed through these routes.
    |
    */

    Route::middleware([
        EnsureRegularKnowledgeBase::class,
    ])->group(function () {

        Route::get(
            '/knowledge-bases/{knowledgeBase}/documents',
            [DocumentController::class, 'index']
        );

        Route::post(
            '/knowledge-bases/{knowledgeBase}/documents',
            [DocumentController::class, 'store']
        );

        Route::post(
            '/knowledge-bases/{knowledgeBase}/documents/{document}/process',
            [DocumentController::class, 'process']
        )->middleware('throttle:ai');

    });

    /*
    |--------------------------------------------------------------------------
    | Conversation Attachments
    |--------------------------------------------------------------------------
    |
    | Upload files directly within a conversation.
    |
    | This endpoint automatically creates or reuses
    | the conversation's internal knowledge base,
    | stores the attachment and dispatches the
    | document processing job.
    |
    | The regular knowledge base middleware does
    | not apply to this endpoint.
    |
    */

    Route::post(
        '/conversations/{conversation}/attachments',
        [ConversationAttachmentController::class, 'store']
    )->middleware('throttle:ai');

});
