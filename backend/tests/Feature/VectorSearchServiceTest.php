<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\KnowledgeBase;
use App\Models\User;
use App\Services\VectorSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VectorSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_calculates_cosine_similarity(): void
    {
        $service = app(VectorSearchService::class);

        $score = $service->cosineSimilarity(
            [1, 0, 0],
            [1, 0, 0]
        );

        $this->assertEqualsWithDelta(
            1.0,
            $score,
            0.000001
        );
    }

    public function test_orthogonal_vectors_have_zero_similarity(): void
    {
        $service = app(VectorSearchService::class);

        $score = $service->cosineSimilarity(
            [1, 0],
            [0, 1]
        );

        $this->assertEqualsWithDelta(
            0.0,
            $score,
            0.000001
        );
    }

    public function test_it_rejects_vectors_with_different_dimensions(): void
    {
        $service = app(VectorSearchService::class);

        $this->expectException(
            \InvalidArgumentException::class
        );

        $service->cosineSimilarity(
            [1, 0],
            [1, 0, 0]
        );
    }

    public function test_it_returns_chunks_sorted_by_similarity(): void
    {
        $user = User::factory()->create();

        $knowledgeBase = KnowledgeBase::create([
            'user_id' => $user->id,
            'name' => 'Test Knowledge Base',
            'description' => 'Vector search testing.',
        ]);

        $document = Document::create([
            'knowledge_base_id' => $knowledgeBase->id,
            'original_name' => 'test.txt',
            'stored_name' => 'test.txt',
            'mime_type' => 'text/plain',
            'file_path' => 'documents/test.txt',
            'file_size' => 100,
            'status' => 'processed',
        ]);

        $mostRelevant = DocumentChunk::create([
            'document_id' => $document->id,
            'chunk_index' => 0,
            'content' => 'Laravel authentication uses Sanctum.',
            'embedding' => [1, 0, 0],
            'token_count' => 5,
        ]);

        DocumentChunk::create([
            'document_id' => $document->id,
            'chunk_index' => 1,
            'content' => 'PHP is used for server-side development.',
            'embedding' => [0, 1, 0],
            'token_count' => 7,
        ]);

        DocumentChunk::create([
            'document_id' => $document->id,
            'chunk_index' => 2,
            'content' => 'Laravel Sanctum provides SPA authentication.',
            'embedding' => [0.9, 0.1, 0],
            'token_count' => 6,
        ]);

        $service = app(VectorSearchService::class);

        $results = $service->search(
            queryEmbedding: [1, 0, 0],
            knowledgeBaseId: $knowledgeBase->id,
            limit: 2
        );

        $this->assertCount(2, $results);

        $this->assertSame(
            $mostRelevant->id,
            $results->first()->id
        );

        $this->assertGreaterThan(
            $results->last()->similarity_score,
            $results->first()->similarity_score
        );
    }

    public function test_it_only_searches_the_requested_knowledge_base(): void
    {
        $user = User::factory()->create();

        $firstKnowledgeBase = KnowledgeBase::create([
            'user_id' => $user->id,
            'name' => 'First Knowledge Base',
            'description' => 'First KB.',
        ]);

        $secondKnowledgeBase = KnowledgeBase::create([
            'user_id' => $user->id,
            'name' => 'Second Knowledge Base',
            'description' => 'Second KB.',
        ]);

        $firstDocument = Document::create([
            'knowledge_base_id' => $firstKnowledgeBase->id,
            'original_name' => 'first.txt',
            'stored_name' => 'first.txt',
            'mime_type' => 'text/plain',
            'file_path' => 'documents/first.txt',
            'file_size' => 100,
            'status' => 'processed',
        ]);

        $secondDocument = Document::create([
            'knowledge_base_id' => $secondKnowledgeBase->id,
            'original_name' => 'second.txt',
            'stored_name' => 'second.txt',
            'mime_type' => 'text/plain',
            'file_path' => 'documents/second.txt',
            'file_size' => 100,
            'status' => 'processed',
        ]);

        DocumentChunk::create([
            'document_id' => $firstDocument->id,
            'chunk_index' => 0,
            'content' => 'First knowledge base content.',
            'embedding' => [1, 0, 0],
            'token_count' => 5,
        ]);

        DocumentChunk::create([
            'document_id' => $secondDocument->id,
            'chunk_index' => 0,
            'content' => 'Second knowledge base content.',
            'embedding' => [1, 0, 0],
            'token_count' => 5,
        ]);

        $service = app(VectorSearchService::class);

        $results = $service->search(
            queryEmbedding: [1, 0, 0],
            knowledgeBaseId: $firstKnowledgeBase->id,
            limit: 10
        );

        $this->assertCount(1, $results);

        $this->assertSame(
            'First knowledge base content.',
            $results->first()->content
        );
    }

    public function test_it_respects_the_result_limit(): void
    {
        $user = User::factory()->create();

        $knowledgeBase = KnowledgeBase::create([
            'user_id' => $user->id,
            'name' => 'Limit Test KB',
            'description' => 'Testing limits.',
        ]);

        $document = Document::create([
            'knowledge_base_id' => $knowledgeBase->id,
            'original_name' => 'test.txt',
            'stored_name' => 'test.txt',
            'mime_type' => 'text/plain',
            'file_path' => 'documents/test.txt',
            'file_size' => 100,
            'status' => 'processed',
        ]);

        for ($i = 0; $i < 5; $i++) {
            DocumentChunk::create([
                'document_id' => $document->id,
                'chunk_index' => $i,
                'content' => "Chunk {$i}",
                'embedding' => [1, 0, 0],
                'token_count' => 2,
            ]);
        }

        $service = app(VectorSearchService::class);

        $results = $service->search(
            queryEmbedding: [1, 0, 0],
            knowledgeBaseId: $knowledgeBase->id,
            limit: 3
        );

        $this->assertCount(3, $results);
    }
}