<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Conversation\ListMessagesRequest;
use App\Http\Requests\Conversation\SendMessageRequest;
use App\Models\Conversation;
use App\Services\Conversations\ConversationChatService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ConversationMessageController extends Controller
{
    public function __construct(
        private readonly ConversationChatService $conversationChatService
    ) {
    }

    /**
     * Return paginated messages from an
     * authenticated user's conversation.
     */
    public function index(
        ListMessagesRequest $request,
        $conversation
    ): JsonResponse {
        $conversationModel =
            $this->findOwnedConversation(
                $request,
                $conversation
            );

        $validated =
            $request->validated();

        $limit = (int) (
            $validated['limit']
            ?? config(
                'chat.message_page_size',
                20
            )
        );

        $beforeId =
            $validated['before_id']
            ?? null;

        $query =
            $conversationModel
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
            $messages->count() >
            $limit;

        if ($hasMore) {
            $messages =
                $messages->take(
                    $limit
                );
        }

        $messages =
            $messages
                ->reverse()
                ->values();

        $oldestMessage =
            $messages->first();

        return response()->json([
            'success' => true,

            'messages' =>
                $messages,

            'pagination' => [
                'has_more' =>
                    $hasMore,

                'next_before_id' =>
                    $hasMore &&
                    $oldestMessage
                        ? $oldestMessage
                            ->id
                        : null,

                'limit' =>
                    $limit,
            ],
        ]);
    }

    /**
     * Store a message and generate an AI
     * response inside an owned conversation.
     */
    public function store(
        SendMessageRequest $request,
        $conversation
    ): JsonResponse {
        $conversationModel =
            $this->findOwnedConversation(
                $request,
                $conversation
            );

        try {
            $result =
                $this
                    ->conversationChatService
                    ->sendMessage(
                        $conversationModel,
                        $request->validated(
                            'message'
                        )
                    );

            $conversationModel
                ->refresh();

            return response()->json([
                'success' => true,

                'conversation' =>
                    $conversationModel,

                'user_message' =>
                    $result[
                        'user_message'
                    ],

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
                        $conversationModel
                            ->id,

                    'user_id' =>
                        $request
                            ->user()
                            ->id,

                    'exception' =>
                        $exception::class,

                    'message' =>
                        $exception
                            ->getMessage(),
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
     * Regenerate or retry the latest response
     * only inside the authenticated user's
     * conversation.
     */
    public function regenerate(
        Request $request,
        $conversation
    ): JsonResponse {
        $conversationModel =
            $this->findOwnedConversation(
                $request,
                $conversation
            );

        try {
            $result =
                $this
                    ->conversationChatService
                    ->regenerateLatestResponse(
                        $conversationModel
                    );

            $conversationModel
                ->refresh();

            return response()->json([
                'success' => true,

                'mode' =>
                    $result['mode'],

                'conversation' =>
                    $conversationModel,

                'user_message' =>
                    $result[
                        'user_message'
                    ],

                'assistant_message' =>
                    $result[
                        'assistant_message'
                    ],
            ]);
        } catch (DomainException $exception) {
            return response()->json([
                'success' => false,

                'message' =>
                    $exception
                        ->getMessage(),
            ], 422);
        } catch (Throwable $exception) {
            Log::error(
                'AI response regeneration failed.',
                [
                    'conversation_id' =>
                        $conversationModel
                            ->id,

                    'user_id' =>
                        $request
                            ->user()
                            ->id,

                    'exception' =>
                        $exception::class,

                    'message' =>
                        $exception
                            ->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,

                'message' =>
                    'The AI response could not be generated. Please try again.',
            ], 502);
        }
    }

    /**
     * Resolve a conversation only through
     * the authenticated user's relationship.
     *
     * A conversation belonging to another
     * user therefore behaves as not found.
     */
    private function findOwnedConversation(
        Request $request,
        $conversationId
    ): Conversation {
        return $request
            ->user()
            ->conversations()
            ->whereKey($conversationId)
            ->firstOrFail();
    }
}