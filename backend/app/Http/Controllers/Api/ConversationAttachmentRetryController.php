<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessDocumentJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConversationAttachmentRetryController extends Controller
{
    /**
     * Retry processing a failed chat attachment.
     *
     * Only the conversation owner may retry.
     * The document must belong to that conversation's
     * private attachment knowledge base.
     */
    public function retry(
        Request $request,
        $conversation,
        $document
    ): JsonResponse {
        /*
         * Step 1:
         * Verify conversation ownership.
         */
        $conversationModel = $request
            ->user()
            ->conversations()
            ->whereKey($conversation)
            ->firstOrFail();

        /*
         * Step 2:
         * Resolve the internal attachment storage.
         *
         * Regular knowledge bases cannot be used here.
         */
        $knowledgeBase = $conversationModel
            ->attachmentKnowledgeBase()
            ->firstOrFail();

        /*
         * Step 3:
         * Lock the document while updating its status
         * and scheduling another processing attempt.
         *
         * This prevents two simultaneous retry requests
         * from queueing the same document twice.
         */
        return DB::transaction(function () use (
            $knowledgeBase,
            $document
        ): JsonResponse {
            $attachment = $knowledgeBase
                ->documents()
                ->whereKey($document)
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * Only failed documents can be retried.
             *
             * Ready, uploaded and processing documents
             * must not be scheduled again.
             */
            if ($attachment->status !== 'failed') {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'Only failed attachments can be retried.',
                ], 409);
            }

            /*
             * Reset the failed state.
             *
             * The existing ProcessDocumentJob already
             * knows how to process uploaded documents.
             */
            $attachment->forceFill([
                'status' => 'uploaded',
                'error_message' => null,
            ])->save();

            /*
             * Reuse the originally uploaded physical
             * file instead of creating another document.
             *
             * This project uses the database queue.
             * The job insert participates in the
             * database transaction.
             */
            ProcessDocumentJob::dispatch(
                $attachment->id
            );

            return response()->json([
                'success' => true,
                'message' =>
                    'Attachment processing has been queued again.',
                'data' => [
                    'id' => $attachment->id,
                    'original_name' =>
                        $attachment->original_name,
                    'status' => $attachment->status,
                ],
            ], 202);
        });
    }
}
