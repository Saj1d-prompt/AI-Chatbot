<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\RAG\DocumentProcessingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessDocumentJob implements ShouldQueue
{
    use Queueable;

    /**
     * Process a document only once per queued job.
     *
     * The processing service already records failures
     * in the documents table.
     */
    public int $tries = 1;

    /**
     * Maximum processing time in seconds.
     *
     * The queue connection's retry_after setting must
     * be higher than this timeout.
     */
    public int $timeout = 300;

    /**
     * Store only the document ID in the queue payload.
     */
    public function __construct(
        public int $documentId
    ) {
    }

    /**
     * Execute document processing.
     */
    public function handle(
        DocumentProcessingService $processingService
    ): void {
        /*
         * Retrieve the document from the database.
         *
         * The document might have been deleted
         * before the worker processes this job.
         */
        $document = Document::query()
            ->find($this->documentId);

        if ($document === null) {
            return;
        }

        /*
         * Skip documents that are already ready
         * or currently marked as processing.
         *
         * Failed documents may be retried by
         * dispatching a new job.
         */
        if (!in_array(
            $document->status,
            ['uploaded', 'failed'],
            true
        )) {
            return;
        }

        /*
         * Reuse the existing RAG processing service.
         *
         * It performs:
         * 1. Text extraction
         * 2. Text chunking
         * 3. Embedding generation
         * 4. Database storage
         * 5. Document status updates
         */
        $processingService->process($document);
    }
}
