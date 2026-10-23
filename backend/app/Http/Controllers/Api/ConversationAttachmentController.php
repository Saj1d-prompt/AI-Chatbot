<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Document\StoreDocumentRequest;
use App\Jobs\ProcessDocumentJob;
use App\Models\KnowledgeBase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ConversationAttachmentController extends Controller
{
    /**
     * Upload a document directly into a conversation.
     *
     * An internal knowledge base is automatically
     * created and attached to the conversation.
     */
    public function store(
        StoreDocumentRequest $request,
        $conversation
    ): JsonResponse {
        /*
         * Verify that the authenticated user
         * owns the requested conversation.
         */
        $conversationModel = $request
            ->user()
            ->conversations()
            ->whereKey($conversation)
            ->firstOrFail();

        $file = $request->file('file');

        $storedName = $file->hashName();

        /*
         * Separate physical storage by user
         * and conversation.
         */
        $directory =
            'rag/users/' .
            $request->user()->id .
            '/conversations/' .
            $conversationModel->id .
            '/documents';

        $path = $file->storeAs(
            $directory,
            $storedName,
            'local'
        );

        if ($path === false) {
            return response()->json([
                'success' => false,
                'message' =>
                    'The attachment could not be stored.',
            ], 500);
        }

        try {
            /*
             * Keep knowledge base creation,
             * conversation attachment, document
             * creation and database queue dispatch
             * in one database transaction.
             *
             * This assumes the configured database
             * queue uses the same database connection.
             */
            $document = DB::transaction(
                function () use (
                    $request,
                    $conversationModel,
                    $file,
                    $path,
                    $storedName
                ) {
                    /*
                     * Reuse the same internal knowledge
                     * base for subsequent uploads.
                     */
                    $knowledgeBase = KnowledgeBase::firstOrCreate(
                        [
                            'conversation_id' =>
                                $conversationModel->id,
                        ],
                        [
                            'user_id' =>
                                $request->user()->id,

                            'name' =>
                                'Chat Attachments - Conversation ' .
                                $conversationModel->id,

                            'description' =>
                                'Internal documents uploaded to this conversation.',
                        ]
                    );

                    /*
                     * Make this internal knowledge
                     * base available to the existing
                     * RAG retrieval service.
                     *
                     * Keep other attachments intact.
                     */
                    $conversationModel
                        ->knowledgeBases()
                        ->syncWithoutDetaching([
                            $knowledgeBase->id,
                        ]);

                    /*
                     * Create the document record.
                     */
                    $document = $knowledgeBase
                        ->documents()
                        ->create([
                            'original_name' =>
                                $file->getClientOriginalName(),

                            'stored_name' => $storedName,

                            'mime_type' => $file->getMimeType(),

                            'file_path' => $path,

                            'file_size' => $file->getSize(),

                            'status' => 'uploaded',
                        ]);

                    /*
                     * With the database queue,
                     * the job becomes available
                     * after the transaction commits.
                     */
                    ProcessDocumentJob::dispatch(
                        $document->id
                    );

                    return $document;
                }
            );
        } catch (Throwable $exception) {
            /*
             * If the database transaction fails,
             * remove the uploaded physical file.
             */
            Storage::disk('local')->delete($path);

            throw $exception;
        }

        return response()->json([
            'success' => true,
            'message' =>
                'Attachment uploaded and queued for processing.',
            'data' => [
                'id' => $document->id,

                'conversation_id' =>
                    $conversationModel->id,

                'knowledge_base_id' =>
                    $document->knowledge_base_id,

                'original_name' =>
                    $document->original_name,

                'file_size' =>
                    $document->file_size,

                'status' => $document->status,
            ],
        ], 201);
    }
}
