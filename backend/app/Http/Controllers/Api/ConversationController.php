<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Conversation\StoreConversationRequest;
use App\Http\Requests\Conversation\UpdateConversationRequest;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;

class ConversationController extends Controller
{
    /**
     * Return conversations ordered by recent activity.
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
     * Create a conversation.
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
     * Rename a conversation.
     */
    public function update(
        UpdateConversationRequest $request,
        Conversation $conversation
    ): JsonResponse {
        $conversation->update([
            'title' => trim(
                $request->validated('title')
            ),
        ]);

        $conversation->refresh();

        return response()->json([
            'success' => true,
            'conversation' => $conversation,
        ]);
    }

    /**
     * Delete a conversation.
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