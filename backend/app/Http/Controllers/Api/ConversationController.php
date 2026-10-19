<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Conversation\StoreConversationRequest;
use App\Http\Requests\Conversation\UpdateConversationRequest;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ConversationController extends Controller
{
    /**
     * List conversations owned by the user.
     */
    public function index(Request $request): JsonResponse
    {
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
     * Create a new conversation.
     */
    public function store(
        StoreConversationRequest $request
    ): JsonResponse {
        $conversation = $request
            ->user()
            ->conversations()
            ->create([
                'title' => $request->validated('title'),
            ]);

        return response()->json([
            'success' => true,
            'conversation' => $conversation,
        ], 201);
    }

    /**
     * Show a conversation and its attached
     * knowledge base IDs.
     */
    public function show(
        Request $request,
        $conversation
    ): JsonResponse {
        $conversationModel = $this->findOwnedConversation(
            $request,
            $conversation
        );

        $conversationModel->loadCount('messages');

        $knowledgeBaseIds = $conversationModel
            ->knowledgeBases()
            ->pluck('knowledge_bases.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return response()->json([
            'success' => true,
            'conversation' => $conversationModel,
            'knowledge_base_ids' => $knowledgeBaseIds,
        ]);
    }

    /**
     * Rename a conversation.
     */
    public function update(
        UpdateConversationRequest $request,
        $conversation
    ): JsonResponse {
        $conversationModel = $this->findOwnedConversation(
            $request,
            $conversation
        );

        $conversationModel->update([
            'title' => trim(
                $request->validated('title')
            ),
        ]);

        $conversationModel->refresh();

        return response()->json([
            'success' => true,
            'conversation' => $conversationModel,
        ]);
    }

    /**
     * Delete a conversation.
     */
    public function destroy(
        Request $request,
        $conversation
    ): JsonResponse {
        $conversationModel = $this->findOwnedConversation(
            $request,
            $conversation
        );

        $conversationModel->delete();

        return response()->json([
            'success' => true,
            'message' => 'Conversation deleted successfully.',
        ]);
    }

    /**
     * Replace the knowledge bases attached
     * to a conversation.
     *
     * Passing an empty array removes all attachments.
     */
    public function updateKnowledgeBases(
        Request $request,
        $conversation
    ): JsonResponse {
        $conversationModel = $this->findOwnedConversation(
            $request,
            $conversation
        );

        $validated = $request->validate([
            'knowledge_base_ids' => [
                'present',
                'array',
                'max:20',
            ],
            'knowledge_base_ids.*' => [
                'integer',
                'min:1',
                'distinct',
            ],
        ]);

        $requestedIds = array_map(
            'intval',
            $validated['knowledge_base_ids']
        );

        /*
         * Check every requested knowledge base
         * against the authenticated user's ownership.
         */
        $ownedIds = $request
            ->user()
            ->knowledgeBases()
            ->whereIn('id', $requestedIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($ownedIds) !== count($requestedIds)) {
            throw ValidationException::withMessages([
                'knowledge_base_ids' => [
                    'One or more selected knowledge bases are unavailable.',
                ],
            ]);
        }

        /*
         * Sync replaces the existing attachments.
         * It also accepts [] to detach everything.
         */
        $conversationModel
            ->knowledgeBases()
            ->sync($requestedIds);

        $attachedIds = $conversationModel
            ->knowledgeBases()
            ->pluck('knowledge_bases.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return response()->json([
            'success' => true,
            'message' => 'Conversation knowledge bases updated.',
            'knowledge_base_ids' => $attachedIds,
        ]);
    }

    /**
     * Resolve conversations only through
     * the authenticated user's ownership.
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
