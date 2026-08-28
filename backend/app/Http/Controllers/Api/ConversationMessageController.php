<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Conversation\ListMessagesRequest;
use App\Http\Requests\Conversation\SendMessageRequest;
use App\Models\Conversation;
use App\Services\Conversations\ConversationChatService;
use DomainException;
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
     * Return paginated conversation messages.
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

        $beforeId =
            $validated['before_id'] ?? null;

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

        $hasMore =
            $messages->count() > $limit;

        if ($hasMore) {
            $messages =
                $messages->take($limit);
        }

        $messages = $messages
            ->reverse()
            ->values();

        $oldestMessage =
            $messages->first();

        return response()->json([
            'success' => true,

            'messages' => $messages,

            'pagination' => [
                'has_more' => $hasMore,

                'next_before_id' => (
                    $hasMore &&
                    $oldestMessage
                        ? $oldestMessage->id
                        : null
                ),

                'limit' => $limit,
            ],
        ]);
    }

    /**
     * Store a new user message and generate an AI response.
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

            $conversation->refresh();

            return response()->json([
                'success' => true,

                'conversation' =>
                    $conversation,

                'user_message' =>
                    $result['user_message'],

                'assistant_message' =>
                    $result[
                        'assistant_message'
                    ],
            ], 201);
        } catch (Throwable $exception) {
            Log::error(
                'Conversation AI request failed.',
                [
                    'conversation_id' =>
                        $conversation->id,

                    'exception' =>
                        $exception::class,

                    'message' =>
                        $exception->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,

                'message' =>
                    'The AI service is temporarily unavailable. You can retry the response.',
            ], 502);
        }
    }

    /**
     * Regenerate or retry the latest assistant response.
     */
    public function regenerate(
        Conversation $conversation
    ): JsonResponse {
        try {
            $result = $this
                ->conversationChatService
                ->regenerateLatestResponse(
                    $conversation
                );

            $conversation->refresh();

            return response()->json([
                'success' => true,

                'mode' => $result['mode'],

                'conversation' =>
                    $conversation,

                'user_message' =>
                    $result['user_message'],

                'assistant_message' =>
                    $result[
                        'assistant_message'
                    ],
            ]);
        } catch (DomainException $exception) {
            return response()->json([
                'success' => false,
                'message' =>
                    $exception->getMessage(),
            ], 422);
        } catch (Throwable $exception) {
            Log::error(
                'AI response regeneration failed.',
                [
                    'conversation_id' =>
                        $conversation->id,

                    'exception' =>
                        $exception::class,

                    'message' =>
                        $exception->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,

                'message' =>
                    'The AI response could not be generated. Please try again.',
            ], 502);
        }
    }
}