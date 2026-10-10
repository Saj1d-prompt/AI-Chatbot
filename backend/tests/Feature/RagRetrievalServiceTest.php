<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\KnowledgeBase;
use App\Models\User;
use App\Services\EmbeddingService;
use App\Services\RAG\RagRetrievalService;
use App\Services\VectorSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class RagRetrievalServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_embeds_question_and_returns_relevant_chunks(): void
    {
        $knowledgeBase = $this->createKnowledgeBase();

        $document = $this->createDocument(
            $knowledgeBase,
            'Laravel Guide'
        );

        $chunk = $document->chunks()->create([
            'chunk_index' => 0,
            'content' => 'Laravel Sanctum provides SPA authentication.',
            'embedding' => array_fill(0, 768, 0.1),
            'token_count' => 7,
        ]);

        $embeddingService = $this->mock(
            EmbeddingService::class
        );

        $embeddingService
            ->shouldReceive('embed')
            ->once()
            ->with('How does Laravel authentication work?')
            ->andReturn(
                array_fill(0, 768, 0.1)
            );

        $vectorSearchService = $this->mock(
            VectorSearchService::class
        );

        $vectorSearchService
            ->shouldReceive('search')
            ->once()
            ->with(
                array_fill(0, 768, 0.1),
                $knowledgeBase->id,
                5,
                0.0
            )
            ->andReturn(
                new EloquentCollection([$chunk])
            );

        $service = app(
            RagRetrievalService::class
        );

        $results = $service->retrieve(
            question: 'How does Laravel authentication work?',
            knowledgeBaseId: $knowledgeBase->id,
        );

        $this->assertCount(1, $results);

        $this->assertSame(
            $chunk->id,
            $results->first()->id
        );

        $this->assertSame(
            'Laravel Guide',
            $results->first()->document->original_name
        );
    }

    public function test_it_returns_empty_collection_when_no_chunks_match(): void
    {
        $knowledgeBase = $this->createKnowledgeBase();

        $embeddingService = $this->mock(
            EmbeddingService::class
        );

        $embeddingService
            ->shouldReceive('embed')
            ->once()
            ->with('A question with no matching documents')
            ->andReturn(
                array_fill(0, 768, 0.1)
            );

        $vectorSearchService = $this->mock(
            VectorSearchService::class
        );

        $vectorSearchService
            ->shouldReceive('search')
            ->once()
            ->andReturn(
                new EloquentCollection()
            );

        $service = app(
            RagRetrievalService::class
        );

        $results = $service->retrieve(
            question: 'A question with no matching documents',
            knowledgeBaseId: $knowledgeBase->id,
        );

        $this->assertInstanceOf(
            Collection::class,
            $results
        );

        $this->assertCount(0, $results);
    }

    public function test_it_rejects_an_empty_question(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Question cannot be empty.'
        );

        $service = app(
            RagRetrievalService::class
        );

        $service->retrieve(
            question: '   ',
            knowledgeBaseId: 1,
        );
    }

    public function test_it_rejects_an_invalid_knowledge_base_id(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Knowledge base ID must be valid.'
        );

        $service = app(
            RagRetrievalService::class
        );

        $service->retrieve(
            question: 'What is in my documents?',
            knowledgeBaseId: 0,
        );
    }

    public function test_it_rejects_an_invalid_result_limit(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Retrieval limit must be at least 1.'
        );

        $service = app(
            RagRetrievalService::class
        );

        $service->retrieve(
            question: 'What is in my documents?',
            knowledgeBaseId: 1,
            limit: 0,
        );
    }

    public function test_it_rejects_an_invalid_similarity_threshold(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Minimum similarity score must be between -1 and 1.'
        );

        $service = app(
            RagRetrievalService::class
        );

        $service->retrieve(
            question: 'What is in my documents?',
            knowledgeBaseId: 1,
            minimumScore: 1.5,
        );
    }

    private function createKnowledgeBase(): KnowledgeBase
    {
        $user = User::factory()->create();

        return KnowledgeBase::create([
            'user_id' => $user->id,
            'name' => 'RAG Retrieval Test',
        ]);
    }

    private function createDocument(
        KnowledgeBase $knowledgeBase,
        string $name
    ): Document {
        return Document::create([
            'knowledge_base_id' => $knowledgeBase->id,
            'original_name' => $name,
            'stored_name' => 'test-document.txt',
            'mime_type' => 'text/plain',
            'file_path' => 'rag/test-document.txt',
            'file_size' => 100,
            'status' => 'ready',
        ]);
    }
}