<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Conversation\StoreConversationRequest;
use App\Http\Requests\Conversation\UpdateConversationRequest;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    /**
     * Return only conversations belonging
     * to the authenticated user.
     */
    public function index(
        Request $request
    ): JsonResponse {
        $conversations = $request
            ->user()
            ->conversations()
            ->withCount('messages')
            ->orderByDesc('updated_at')
            ->get();

        return response()->json([
            'success' => true,
            'conversations' => $conversations,
        ]);
    }

    /**
     * Create a conversation owned by
     * the authenticated user.
     */
    public function store(
        StoreConversationRequest $request
    ): JsonResponse {
        $conversation = $request
            ->user()
            ->conversations()
            ->create([
                'title' =>
                    $request->validated(
                        'title'
                    ),
            ]);

        return response()->json([
            'success' => true,
            'conversation' =>
                $conversation,
        ], 201);
    }

    /**
     * Return one conversation only if
     * it belongs to the authenticated user.
     */
    public function show(
        Request $request,
        $conversation
    ): JsonResponse {
        $conversationModel =
            $this->findOwnedConversation(
                $request,
                $conversation
            );

        $conversationModel
            ->loadCount('messages');

        return response()->json([
            'success' => true,
            'conversation' =>
                $conversationModel,
        ]);
    }

    /**
     * Rename an owned conversation.
     */
    public function update(
        UpdateConversationRequest $request,
        $conversation
    ): JsonResponse {
        $conversationModel =
            $this->findOwnedConversation(
                $request,
                $conversation
            );

        $conversationModel->update([
            'title' => trim(
                $request->validated(
                    'title'
                )
            ),
        ]);

        $conversationModel->refresh();

        return response()->json([
            'success' => true,
            'conversation' =>
                $conversationModel,
        ]);
    }

    /**
     * Delete an owned conversation.
     */
    public function destroy(
        Request $request,
        $conversation
    ): JsonResponse {
        $conversationModel =
            $this->findOwnedConversation(
                $request,
                $conversation
            );

        $conversationModel->delete();

        return response()->json([
            'success' => true,
            'message' =>
                'Conversation deleted successfully.',
        ]);
    }

    /**
     * Resolve a conversation through the
     * authenticated user's relationship.
     *
     * This prevents one user from accessing
     * another user's conversation simply by
     * changing the conversation ID.
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