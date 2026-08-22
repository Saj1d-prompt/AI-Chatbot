<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Conversation\StoreConversationRequest;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;

class ConversationController extends Controller
{
    /**
     * Return all conversations ordered by recent activity.
     *
     * Later, after authentication is implemented, this query will be scoped
     * to the currently authenticated user.
     */
    public function index(): JsonResponse
    {
        $conversations = Conversation::query()
            ->withCount('messages')
            ->orderByDesc('updated_at')
            ->get();

        return response()->json([
            'success' => true,
            'conversations' => $conversations,
        ]);
    }

    /**
     * Create a new conversation.
     */
    public function store(
        StoreConversationRequest $request
    ): JsonResponse {
        $conversation = Conversation::create([
            'user_id' => null,
            'title' => $request->validated('title'),
        ]);

        return response()->json([
            'success' => true,
            'conversation' => $conversation,
        ], 201);
    }

    /**
     * Return conversation metadata.
     *
     * Messages are intentionally NOT returned here.
     * They have their own paginated endpoint.
     */
    public function show(
        Conversation $conversation
    ): JsonResponse {
        $conversation->loadCount('messages');

        return response()->json([
            'success' => true,
            'conversation' => $conversation,
        ]);
    }

    /**
     * Delete the conversation.
     *
     * The messages are automatically deleted through the database's
     * cascade-on-delete foreign key.
     */
    public function destroy(
        Conversation $conversation
    ): JsonResponse {
        $conversation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Conversation deleted successfully.',
        ]);
    }
}