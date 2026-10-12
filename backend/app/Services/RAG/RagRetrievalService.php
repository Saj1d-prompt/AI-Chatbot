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
    ) {}

    /**
     * Retrieve relevant chunks from one knowledge base.
     *
     * @return EloquentCollection<int, DocumentChunk>
     */
    public function retrieve(
        string $question,
        int $knowledgeBaseId,
        int $limit = 5,
        float $minimumScore = 0.0
    ): EloquentCollection {
        if ($knowledgeBaseId < 1) {
            throw new InvalidArgumentException(
                'Knowledge base ID must be valid.'
            );
        }

        return $this->retrieveFromKnowledgeBases(
            question: $question,
            knowledgeBaseIds: [$knowledgeBaseId],
            limit: $limit,
            minimumScore: $minimumScore,
        );
    }

    /**
     * Retrieve and globally rank chunks across multiple knowledge bases.
     *
     * The question embedding is generated once and reused for every
     * knowledge base.
     *
     * @param array<int, int> $knowledgeBaseIds
     * @return EloquentCollection<int, DocumentChunk>
     */
    public function retrieveFromKnowledgeBases(
        string $question,
        array $knowledgeBaseIds,
        int $limit = 5,
        float $minimumScore = 0.0
    ): EloquentCollection {
        $question = trim($question);

        if ($question === '') {
            throw new InvalidArgumentException(
                'Question cannot be empty.'
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

        $knowledgeBaseIds = array_values(
            array_unique(
                array_map('intval', $knowledgeBaseIds)
            )
        );

        foreach ($knowledgeBaseIds as $knowledgeBaseId) {
            if ($knowledgeBaseId < 1) {
                throw new InvalidArgumentException(
                    'Knowledge base ID must be valid.'
                );
            }
        }

        if ($knowledgeBaseIds === []) {
            return new EloquentCollection();
        }

        // Generate the question embedding only once.
        $questionEmbedding = $this->embeddingService->embed(
            $question
        );

        $allChunks = collect();

        foreach ($knowledgeBaseIds as $knowledgeBaseId) {
            $chunks = $this->vectorSearchService->search(
                queryEmbedding: $questionEmbedding,
                knowledgeBaseId: $knowledgeBaseId,
                limit: $limit,
                minimumScore: $minimumScore,
            );

            $allChunks = $allChunks->concat($chunks);
        }

        // Merge candidates and rank them across all knowledge bases.
        $rankedChunks = $allChunks
            ->sortByDesc('similarity_score')
            ->take($limit)
            ->values();

        $results = new EloquentCollection(
            $rankedChunks->all()
        );

        if ($results->isNotEmpty()) {
            $results->load('document');
        }

        return $results;
    }
}