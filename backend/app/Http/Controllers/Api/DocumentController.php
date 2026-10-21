<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Document\StoreDocumentRequest;
use App\Jobs\ProcessDocumentJob;
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
     * Upload a TXT document and automatically
     * dispatch its background processing job.
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

        $document = null;

        try {
            /*
             * Save document information.
             *
             * It remains uploaded until the queue worker
             * starts the processing service.
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
             * Automatically dispatch processing.
             *
             * With QUEUE_CONNECTION=database,
             * Laravel stores the job in the jobs table.
             *
             * The worker will process it separately.
             */
            ProcessDocumentJob::dispatch($document->id);

        } catch (Throwable $exception) {
            /*
             * If saving or queue dispatch fails,
             * remove the incomplete upload.
             */
            if ($document !== null) {
                $document->delete();
            }

            Storage::disk('local')->delete($path);

            throw $exception;
        }

        return response()->json([
            'success' => true,
            'message' =>
                'Document uploaded and queued for processing.',
            'data' => $document,
        ], 201);
    }

    /**
     * Keep the existing manual processing endpoint.
     *
     * It can still be used to retry failed documents.
     */
    public function process(
        Request $request,
        DocumentProcessingService $processingService,
        $knowledgeBase,
        $document
    ): JsonResponse {
        $knowledgeBase = $this->findOwnedKnowledgeBase(
            $request,
            $knowledgeBase
        );

        $document = $knowledgeBase
            ->documents()
            ->whereKey($document)
            ->firstOrFail();

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
     * Resolve knowledge bases through the
     * authenticated user's ownership.
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
