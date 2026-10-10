<?php

namespace App\Services\RAG;

use App\Models\DocumentChunk;
use App\Services\EmbeddingService;
use App\Services\VectorSearchService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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
     * @return EloquentCollection<int, DocumentChunk>
     */
    public function retrieve(
        string $question,
        int $knowledgeBaseId,
        int $limit = 5,
        float $minimumScore = 0.0
    ): EloquentCollection {
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

        $questionEmbedding = $this
            ->embeddingService
            ->embed($question);

        $chunks = $this
            ->vectorSearchService
            ->search(
                queryEmbedding: $questionEmbedding,
                knowledgeBaseId: $knowledgeBaseId,
                limit: $limit,
                minimumScore: $minimumScore,
            );

        /*
         * VectorSearchService returns a support Collection.
         * Convert its DocumentChunk models into an Eloquent
         * collection before loading document relationships.
         */
        $chunks = new EloquentCollection(
            $chunks->all()
        );

        if ($chunks->isNotEmpty()) {
            $chunks->load('document');
        }

        return $chunks;
    }
}