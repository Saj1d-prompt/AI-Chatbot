<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ConversationAttachmentDeleteController extends Controller
{

    public function destroy(
        Request $request,
        $conversation,
        $document
    ): JsonResponse {
        /*
         * 1. Verify conversation ownership.
         *
         * Another user's conversation returns 404.
         */
        $conversationModel = $request
            ->user()
            ->conversations()
            ->whereKey($conversation)
            ->firstOrFail();

        /*
         * 2. Find the conversation's internal
         * attachment knowledge base.
         *
         * Regular knowledge bases are excluded.
         */
        $knowledgeBase = $conversationModel
            ->attachmentKnowledgeBase()
            ->firstOrFail();

        /*
         * 3. Perform deletion inside a database
         * transaction.
         */
        DB::transaction(function () use (
            $knowledgeBase,
            $document
        ) {
            $attachment = $knowledgeBase
                ->documents()
                ->whereKey($document)
                ->lockForUpdate()
                ->firstOrFail();

            if (!in_array(
                $attachment->status,
                ['ready', 'failed'],
                true
            )) {
                abort(
                    409,
                    'Wait until the attachment finishes processing before removing it.'
                );
            }

            /*
             * Save the physical path before
             * deleting the database record.
             */
            $filePath = $attachment->file_path;

            /*
             * 4. Delete the document record.
             *
             * Database foreign key cascades remove:
             *
             * - document_chunks
             * - message_sources referencing those chunks
             *
             * This ensures future retrieval cannot
             * find this document's embeddings.
             */
            $attachment->delete();

            /*
             * 5. Delete the physical TXT file from
             * Laravel's private local storage.
             *
             * A missing file does not prevent
             * cleanup of an existing DB record.
             *
             * If storage deletion explicitly fails,
             * throw an exception to roll back the
             * database transaction.
             */
            $disk = Storage::disk('local');

            if (
                $disk->exists($filePath) &&
                !$disk->delete($filePath)
            ) {
                throw new RuntimeException(
                    'The attachment file could not be removed from storage.'
                );
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Attachment removed successfully.',
        ]);
    }
}
