
<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\ConversationMessageController;
use App\Http\Controllers\Api\ConversationAttachmentController;
use App\Http\Controllers\Api\ConversationAttachmentStatusController;
use App\Http\Controllers\Api\ConversationAttachmentRetryController;
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

    /*
    |--------------------------------------------------------------------------
    | Conversation Knowledge Bases
    |--------------------------------------------------------------------------
    |
    | Manage reusable knowledge bases manually selected
    | for a conversation.
    |
    | Internal chat attachment knowledge bases remain
    | attached automatically and cannot be manually selected.
    |
    */

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
    | Regular Knowledge Base Documents
    |--------------------------------------------------------------------------
    |
    | These routes are available only for regular,
    | reusable knowledge bases.
    |
    | Internal conversation attachment knowledge bases
    | cannot be accessed through these endpoints.
    |
    */

    Route::middleware([
        EnsureRegularKnowledgeBase::class,
    ])->group(function () {

        /*
         * List documents.
         */
        Route::get(
            '/knowledge-bases/{knowledgeBase}/documents',
            [DocumentController::class, 'index']
        );

        /*
         * Upload a document.
         */
        Route::post(
            '/knowledge-bases/{knowledgeBase}/documents',
            [DocumentController::class, 'store']
        );

        /*
         * Manually process a document when required.
         */
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
    | These endpoints manage documents uploaded directly
    | through the chat paperclip button.
    |
    | Each conversation has its own internal knowledge base.
    |
    | All operations require authentication, and the
    | corresponding controllers enforce conversation
    | ownership and document isolation.
    |
    */

    /*
     * GET:
     * Retrieve conversation attachments and their
     * processing statuses.
     */
    Route::get(
        '/conversations/{conversation}/attachments',
        [ConversationAttachmentStatusController::class, 'index']
    );

    /*
     * POST:
     * Upload a TXT document into a conversation.
     *
     * Automatically creates/reuses the internal
     * knowledge base and queues document processing.
     */
    Route::post(
        '/conversations/{conversation}/attachments',
        [ConversationAttachmentController::class, 'store']
    )->middleware('throttle:ai');

    /*
     * POST:
     * Retry processing a failed chat attachment.
     *
     * Only documents with a failed status can
     * be retried through this endpoint.
     */
    Route::post(
        '/conversations/{conversation}/attachments/{document}/retry',
        [ConversationAttachmentRetryController::class, 'retry']
    )->middleware('throttle:ai');

});
