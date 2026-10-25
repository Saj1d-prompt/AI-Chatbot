<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationAttachmentStatusController extends Controller
{
    /**
     * List documents uploaded directly into a conversation.
     *
     * Only the conversation owner may access this endpoint.
     */
    public function index(
        Request $request,
        $conversation
    ): JsonResponse {
        /*
         * Resolve the conversation through the
         * authenticated user's relationship.
         */
        $conversationModel = $request
            ->user()
            ->conversations()
            ->whereKey($conversation)
            ->firstOrFail();

        /*
         * A conversation may not have any attachments.
         * Return an empty list in that case.
         */
        $knowledgeBase = $conversationModel
            ->attachmentKnowledgeBase()
            ->first();

        if ($knowledgeBase === null) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        /*
         * Read only documents from this conversation's
         * dedicated internal knowledge base.
         */
        $documents = $knowledgeBase
            ->documents()
            ->withCount('chunks')
            ->orderBy('id')
            ->get()
            ->map(function ($document) {
                return [
                    'id' => $document->id,

                    'original_name' =>
                        $document->original_name,

                    'file_size' =>
                        $document->file_size,

                    'status' =>
                        $document->status,

                    'chunks_count' =>
                        $document->chunks_count,

                    'error_message' =>
                        $document->status === 'failed'
                            ? ($document->error_message ?? null)
                            : null,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $documents,
        ]);
    }
}
