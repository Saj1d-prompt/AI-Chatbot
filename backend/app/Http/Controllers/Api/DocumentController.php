<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Document\StoreDocumentRequest;
use App\Models\KnowledgeBase;
use App\Services\RAG\DocumentProcessingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DocumentController extends Controller
{
    /**
     * List documents belonging to an owned knowledge base.
     */
    public function index(
        Request $request,
        $knowledgeBase
    ): JsonResponse {
        $knowledgeBase = $this->findOwnedKnowledgeBase(
            $request,
            $knowledgeBase
        );

        $documents = $knowledgeBase
            ->documents()
            ->select([
                'id',
                'knowledge_base_id',
                'original_name',
                'file_size',
                'mime_type',
                'status',
                'error_message',
                'created_at',
                'updated_at',
            ])
            ->withCount('chunks')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $documents,
        ]);
    }

    /**
     * Upload a TXT document.
     */
    public function store(
        StoreDocumentRequest $request,
        $knowledgeBase
    ): JsonResponse {
        $knowledgeBase = $this->findOwnedKnowledgeBase(
            $request,
            $knowledgeBase
        );

        $file = $request->file('file');

        $storedName = $file->hashName();

        $directory =
            'rag/users/' .
            $request->user()->id .
            '/knowledge-bases/' .
            $knowledgeBase->id .
            '/documents';

        $path = $file->storeAs(
            $directory,
            $storedName,
            'local'
        );

        if ($path === false) {
            return response()->json([
                'success' => false,
                'message' => 'The document could not be stored.',
            ], 500);
        }

        try {
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
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }

        return response()->json([
            'success' => true,
            'message' => 'Document uploaded successfully.',
            'data' => $document,
        ], 201);
    }

    /**
     * Process an uploaded document using the RAG pipeline.
     */
    public function process(
        Request $request,
        DocumentProcessingService $processingService,
        $knowledgeBase,
        $document
    ): JsonResponse {
        /*
         * Verify ownership of the knowledge base.
         */
        $knowledgeBase = $this->findOwnedKnowledgeBase(
            $request,
            $knowledgeBase
        );

        /*
         * Find the document only within this knowledge base.
         * This prevents processing another user's documents.
         */
        $document = $knowledgeBase
            ->documents()
            ->whereKey($document)
            ->firstOrFail();

        /*
         * Prevent unnecessary reprocessing.
         */
        if (in_array(
            $document->status,
            ['ready', 'completed', 'processing'],
            true
        )) {
            return response()->json([
                'success' => false,
                'message' =>
                    'This document is already processed or processing.',
            ], 409);
        }

        /*
         * Only uploaded or previously failed documents
         * may be submitted for processing.
         */
        if (!in_array(
            $document->status,
            ['uploaded', 'failed'],
            true
        )) {
            return response()->json([
                'success' => false,
                'message' =>
                    'This document cannot be processed in its current state.',
            ], 409);
        }

        try {
            /*
             * The existing service:
             * - extracts text
             * - creates chunks
             * - generates embeddings
             * - saves chunks
             * - marks document ready
             */
            $processedDocument = $processingService
                ->process($document);

            $processedDocument->loadCount('chunks');

            return response()->json([
                'success' => true,
                'message' => 'Document processed successfully.',
                'data' => [
                    'id' => $processedDocument->id,
                    'knowledge_base_id' =>
                        $processedDocument->knowledge_base_id,
                    'original_name' =>
                        $processedDocument->original_name,
                    'status' => $processedDocument->status,
                    'chunks_count' =>
                        $processedDocument->chunks_count,
                    'error_message' =>
                        $processedDocument->error_message,
                ],
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' =>
                    'Document processing failed. Check the document status for details.',
            ], 500);
        }
    }

    /**
     * Resolve a knowledge base belonging to the current user.
     */
    private function findOwnedKnowledgeBase(
        Request $request,
        $knowledgeBaseId
    ): KnowledgeBase {
        return $request
            ->user()
            ->knowledgeBases()
            ->whereKey($knowledgeBaseId)
            ->firstOrFail();
    }
}
