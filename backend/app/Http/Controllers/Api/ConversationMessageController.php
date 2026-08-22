<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Conversation\ListMessagesRequest;
use App\Http\Requests\Conversation\SendMessageRequest;
use App\Models\Conversation;
use App\Services\Conversations\ConversationChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class ConversationMessageController extends Controller
{
    public function __construct(
        private readonly ConversationChatService $conversationChatService
    ) {
    }

    /**
     * Return a paginated batch of conversation messages.
     *
     * Newest messages are loaded initially.
     * Older messages can then be requested using before_id.
     */
    public function index(
        ListMessagesRequest $request,
        Conversation $conversation
    ): JsonResponse {
        $validated = $request->validated();

        $limit = (int) (
            $validated['limit']
            ?? config('chat.message_page_size', 20)
        );

        $beforeId = $validated['before_id'] ?? null;

        /*
        |--------------------------------------------------------------------------
        | Fetch One Extra Message
        |--------------------------------------------------------------------------
        |
        | If the client asks for 20, we fetch 21.
        |
        | 21 found:
        | → there are older messages
        |
        | <= 20 found:
        | → we've reached the beginning
        |
        */

        $query = $conversation
            ->messages()
            ->select([
                'id',
                'conversation_id',
                'role',
                'content',
                'token_usage',
                'created_at',
                'updated_at',
            ]);

        if ($beforeId !== null) {
            $query->where(
                'id',
                '<',
                $beforeId
            );
        }

        $messages = $query
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();

        $hasMore = $messages->count() > $limit;

        if ($hasMore) {
            $messages = $messages->take($limit);
        }

        /*
        |--------------------------------------------------------------------------
        | Restore Chronological Order
        |--------------------------------------------------------------------------
        |
        | SQL fetched:
        |
        | 100
        | 99
        | 98
        |
        | UI receives:
        |
        | 98
        | 99
        | 100
        |
        */

        $messages = $messages
            ->reverse()
            ->values();

        $oldestMessage = $messages->first();

        return response()->json([
            'success' => true,

            'messages' => $messages,

            'pagination' => [
                'has_more' => $hasMore,

                'next_before_id' => (
                    $hasMore && $oldestMessage
                        ? $oldestMessage->id
                        : null
                ),

                'limit' => $limit,
            ],
        ]);
    }

    /**
     * Send a message inside a persisted conversation.
     */
    public function store(
        SendMessageRequest $request,
        Conversation $conversation
    ): JsonResponse {
        try {
            $result = $this
                ->conversationChatService
                ->sendMessage(
                    $conversation,
                    $request->validated('message')
                );

            /*
            |--------------------------------------------------------------------------
            | Reload Conversation Metadata
            |--------------------------------------------------------------------------
            |
            | The title or updated_at timestamp may have changed.
            |
            */

            $conversation->refresh();

            return response()->json([
                'success' => true,

                'conversation' => $conversation,

                'user_message' => $result[
                    'user_message'
                ],

                'assistant_message' => $result[
                    'assistant_message'
                ],
            ], 201);
        } catch (Throwable $exception) {
            Log::error(
                'Conversation AI request failed.',
                [
                    'conversation_id' => $conversation->id,

                    'exception' => $exception::class,

                    'message' => $exception->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,

                'message' =>
                    'The AI service is temporarily unavailable. Please try again.',
            ], 502);
        }
    }
}