<?php

namespace App\Services\RAG;

use App\Models\DocumentChunk;
use App\Services\EmbeddingService;
use App\Services\VectorSearchService;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class RagRetrievalService
{
    public function __construct(
        private EmbeddingService $embeddingService,
        private VectorSearchService $vectorSearchService,
    ) {
    }

    /**
     * Retrieve the most relevant document chunks
     * for a question within a knowledge base.
     *
     * @return Collection<int, DocumentChunk>
     */
    public function retrieve(
        string $question,
        int $knowledgeBaseId,
        int $limit = 5,
        float $minimumScore = 0.0
    ): Collection {
        $question = trim($question);

        if ($question === '') {
            throw new InvalidArgumentException(
                'Question cannot be empty.'
            );
        }

        if ($knowledgeBaseId < 1) {
            throw new InvalidArgumentException(
                'Knowledge base ID must be valid.'
            );
        }

        if ($limit < 1) {
            throw new InvalidArgumentException(
                'Retrieval limit must be at least 1.'
            );
        }

        if (
            $minimumScore < -1.0 ||
            $minimumScore > 1.0
        ) {
            throw new InvalidArgumentException(
                'Minimum similarity score must be between -1 and 1.'
            );
        }

        /*
         * Convert the user's question into an embedding.
         */
        $questionEmbedding = $this
            ->embeddingService
            ->embed($question);

        /*
         * Find the most similar chunks belonging
         * to the requested knowledge base.
         */
        $chunks = $this
            ->vectorSearchService
            ->search(
                queryEmbedding: $questionEmbedding,
                knowledgeBaseId: $knowledgeBaseId,
                limit: $limit,
                minimumScore: $minimumScore,
            );

        /*
         * Load document details so the caller can
         * display source information later.
         */
        $chunks->load('document');

        return $chunks;
    }
}