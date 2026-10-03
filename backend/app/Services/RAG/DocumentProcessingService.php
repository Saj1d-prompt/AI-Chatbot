<?php

namespace App\Services\RAG;

use App\Models\Document;
use Illuminate\Support\Facades\DB;
use Throwable;

class DocumentProcessingService
{
    public function __construct(
        private TextExtractionService $textExtractionService,
        private TextChunkingService $textChunkingService,
    ) {
    }

    public function process(Document $document): Document
    {
        $document->update([
            'status' => 'processing',
            'error_message' => null,
        ]);

        try {
            $text = $this
                ->textExtractionService
                ->extract($document);

            $chunks = $this
                ->textChunkingService
                ->chunk($text);

            if (empty($chunks)) {
                throw new \RuntimeException(
                    'No document chunks could be created.'
                );
            }

            DB::transaction(function () use (
                $document,
                $chunks
            ) {
                /*
                 * Remove any previous chunks.
                 *
                 * This makes re-processing safe.
                 */
                $document
                    ->chunks()
                    ->delete();

                foreach ($chunks as $chunk) {
                    $document
                        ->chunks()
                        ->create([
                            'chunk_index' =>
                                $chunk['chunk_index'],

                            'content' =>
                                $chunk['content'],

                            'token_count' =>
                                $chunk['token_count'],

                            /*
                             * Embeddings come later.
                             */
                            'embedding' => null,
                        ]);
                }

                $document->update([
                    'status' => 'ready',
                    'error_message' => null,
                ]);
            });

            return $document->fresh();
        } catch (Throwable $exception) {
            $document->update([
                'status' => 'failed',

                'error_message' =>
                    $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}