<?php

namespace App\Services;

use App\Models\DocumentChunk;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class VectorSearchService
{
    /**
     * Search document chunks using cosine similarity.
     *
     * @return Collection<int, DocumentChunk>
     */
    public function search(
        array $queryEmbedding,
        int $knowledgeBaseId,
        int $limit = 5,
        float $minimumScore = 0.0
    ): Collection {
        if ($queryEmbedding === []) {
            throw new InvalidArgumentException(
                'Query embedding cannot be empty.'
            );
        }

        if ($limit < 1) {
            throw new InvalidArgumentException(
                'Search limit must be at least 1.'
            );
        }

        $chunks = DocumentChunk::query()
            ->whereHas('document', function ($query) use ($knowledgeBaseId) {
                $query->where(
                    'knowledge_base_id',
                    $knowledgeBaseId
                );
            })
            ->whereNotNull('embedding')
            ->get();

        return $chunks
            ->map(function (DocumentChunk $chunk) use ($queryEmbedding) {
                $embedding = $this->normalizeEmbedding(
                    $chunk->embedding
                );

                $score = $this->cosineSimilarity(
                    $queryEmbedding,
                    $embedding
                );

                $chunk->similarity_score = $score;

                return $chunk;
            })
            ->filter(
                fn (DocumentChunk $chunk): bool =>
                    $chunk->similarity_score >= $minimumScore
            )
            ->sortByDesc('similarity_score')
            ->take($limit)
            ->values();
    }

    /**
     * Calculate cosine similarity between two vectors.
     */
    public function cosineSimilarity(
        array $a,
        array $b
    ): float {
        if ($a === [] || $b === []) {
            throw new InvalidArgumentException(
                'Vectors cannot be empty.'
            );
        }

        if (count($a) !== count($b)) {
            throw new InvalidArgumentException(
                'Vectors must have the same dimensions.'
            );
        }

        $dotProduct = 0.0;
        $magnitudeA = 0.0;
        $magnitudeB = 0.0;

        foreach ($a as $index => $valueA) {
            $valueA = (float) $valueA;
            $valueB = (float) $b[$index];

            $dotProduct += $valueA * $valueB;

            $magnitudeA += $valueA * $valueA;
            $magnitudeB += $valueB * $valueB;
        }

        if ($magnitudeA === 0.0 || $magnitudeB === 0.0) {
            return 0.0;
        }

        return $dotProduct /
            (
                sqrt($magnitudeA) *
                sqrt($magnitudeB)
            );
    }

    /**
     * Convert the embedding into a usable PHP array.
     */
    private function normalizeEmbedding(
        mixed $embedding
    ): array {
        if (is_string($embedding)) {
            $decoded = json_decode(
                $embedding,
                true
            );

            if (!is_array($decoded)) {
                throw new InvalidArgumentException(
                    'Invalid JSON embedding.'
                );
            }

            $embedding = $decoded;
        }

        if (!is_array($embedding)) {
            throw new InvalidArgumentException(
                'Embedding must be an array.'
            );
        }

        return array_map(
            static fn ($value): float => (float) $value,
            $embedding
        );
    }
}