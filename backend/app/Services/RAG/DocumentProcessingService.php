<?php

namespace App\Services\RAG;

use App\Models\Document;
use App\Services\EmbeddingService;
use Illuminate\Support\Facades\DB;
use Throwable;

class DocumentProcessingService
{
    public function __construct(
        private TextExtractionService $textExtractionService,
        private TextChunkingService $textChunkingService,
        private EmbeddingService $embeddingService,
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

            /*
             * Generate embeddings before opening the database
             * transaction.
             *
             * Embedding generation is an external operation
             * handled by Ollama, so we should not keep a
             * database transaction open while waiting for it.
             */
            $embeddedChunks = [];

            foreach ($chunks as $chunk) {
                $embedding = $this
                    ->embeddingService
                    ->embed($chunk['content']);

                $embeddedChunks[] = [
                    'chunk_index' =>
                        $chunk['chunk_index'],

                    'content' =>
                        $chunk['content'],

                    'token_count' =>
                        $chunk['token_count'],

                    'embedding' =>
                        $embedding,
                ];
            }

            DB::transaction(function () use (
                $document,
                $embeddedChunks
            ) {
                /*
                 * Remove any previous chunks.
                 *
                 * This makes re-processing safe.
                 */
                $document
                    ->chunks()
                    ->delete();

                foreach ($embeddedChunks as $chunk) {
                    $document
                        ->chunks()
                        ->create([
                            'chunk_index' =>
                                $chunk['chunk_index'],

                            'content' =>
                                $chunk['content'],

                            'token_count' =>
                                $chunk['token_count'],

                            'embedding' =>
                                $chunk['embedding'],
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