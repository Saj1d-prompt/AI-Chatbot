<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\KnowledgeBase;
use App\Models\User;
use App\Services\EmbeddingService;
use App\Services\RAG\DocumentProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class DocumentProcessingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_extracts_chunks_generates_embeddings_and_saves_document_chunks(): void
    {
        Storage::fake('local');

        config([
            'rag.chunk_size' => 5,
            'rag.chunk_overlap' => 2,
        ]);

        /*
         * Do not call the real Ollama server during tests.
         *
         * Replace EmbeddingService with a deterministic
         * fake that returns a 768-dimensional vector.
         */
        $this->app->instance(
            EmbeddingService::class,
            new class {
                /**
                 * @return array<int, float>
                 */
                public function embed(
                    string $text
                ): array {
                    return array_fill(
                        0,
                        768,
                        0.1
                    );
                }
            }
        );

        $user = User::factory()->create();

        $knowledgeBase = KnowledgeBase::create([
            'user_id' => $user->id,
            'name' => 'Test Knowledge',
        ]);

        $path =
            'rag/users/' .
            $user->id .
            '/knowledge-bases/' .
            $knowledgeBase->id .
            '/documents/policy.txt';

        Storage::disk('local')->put(
            $path,
            'one two three four five six seven eight nine ten'
        );

        $document = Document::create([
            'knowledge_base_id' =>
                $knowledgeBase->id,

            'original_name' =>
                'policy.txt',

            'stored_name' =>
                'policy.txt',

            'mime_type' =>
                'text/plain',

            'file_path' =>
                $path,

            'file_size' =>
                100,

            'status' =>
                'uploaded',
        ]);

        $service = app(
            DocumentProcessingService::class
        );

        $service->process(
            $document
        );

        $document->refresh();

        $this->assertSame(
            'ready',
            $document->status
        );

        $this->assertNull(
            $document->error_message
        );

        $this->assertCount(
            3,
            $document->chunks
        );

        /*
         * Verify first chunk content.
         */
        $this->assertDatabaseHas(
            'document_chunks',
            [
                'document_id' =>
                    $document->id,

                'chunk_index' =>
                    0,

                'content' =>
                    'one two three four five',
            ]
        );

        /*
         * Verify second chunk content.
         */
        $this->assertDatabaseHas(
            'document_chunks',
            [
                'document_id' =>
                    $document->id,

                'chunk_index' =>
                    1,

                'content' =>
                    'four five six seven eight',
            ]
        );

        /*
         * Verify third chunk content.
         */
        $this->assertDatabaseHas(
            'document_chunks',
            [
                'document_id' =>
                    $document->id,

                'chunk_index' =>
                    2,

                'content' =>
                    'seven eight nine ten',
            ]
        );

        /*
         * Verify every chunk received an embedding.
         */
        foreach ($document->chunks as $chunk) {
            $this->assertIsArray(
                $chunk->embedding
            );

            $this->assertCount(
                768,
                $chunk->embedding
            );

            $this->assertNotEmpty(
                $chunk->embedding
            );
        }
    }

    public function test_processing_failure_marks_document_as_failed(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();

        $knowledgeBase = KnowledgeBase::create([
            'user_id' => $user->id,
            'name' => 'Test Knowledge',
        ]);

        $document = Document::create([
            'knowledge_base_id' =>
                $knowledgeBase->id,

            'original_name' =>
                'missing.txt',

            'stored_name' =>
                'missing.txt',

            'mime_type' =>
                'text/plain',

            'file_path' =>
                'rag/missing.txt',

            'file_size' =>
                100,

            'status' =>
                'uploaded',
        ]);

        $service = app(
            DocumentProcessingService::class
        );

        try {
            $service->process(
                $document
            );
        } catch (RuntimeException) {
            // Expected failure.
        }

        $document->refresh();

        $this->assertSame(
            'failed',
            $document->status
        );

        $this->assertSame(
            'The document file could not be found.',
            $document->error_message
        );

        $this->assertDatabaseCount(
            'document_chunks',
            0
        );
    }

    public function test_embedding_failure_marks_document_as_failed(): void
    {
        Storage::fake('local');

        config([
            'rag.chunk_size' => 5,
            'rag.chunk_overlap' => 2,
        ]);

        /*
         * Simulate an Ollama/embedding failure.
         */
        $this->app->instance(
            EmbeddingService::class,
            new class {
                public function embed(
                    string $text
                ): array {
                    throw new RuntimeException(
                        'Embedding generation failed.'
                    );
                }
            }
        );

        $user = User::factory()->create();

        $knowledgeBase = KnowledgeBase::create([
            'user_id' => $user->id,
            'name' => 'Test Knowledge',
        ]);

        $path =
            'rag/users/' .
            $user->id .
            '/knowledge-bases/' .
            $knowledgeBase->id .
            '/documents/policy.txt';

        Storage::disk('local')->put(
            $path,
            'one two three four five six seven eight'
        );

        $document = Document::create([
            'knowledge_base_id' =>
                $knowledgeBase->id,

            'original_name' =>
                'policy.txt',

            'stored_name' =>
                'policy.txt',

            'mime_type' =>
                'text/plain',

            'file_path' =>
                $path,

            'file_size' =>
                100,

            'status' =>
                'uploaded',
        ]);

        $service = app(
            DocumentProcessingService::class
        );

        try {
            $service->process(
                $document
            );
        } catch (RuntimeException) {
            // Expected embedding failure.
        }

        $document->refresh();

        $this->assertSame(
            'failed',
            $document->status
        );

        $this->assertSame(
            'Embedding generation failed.',
            $document->error_message
        );

        $this->assertDatabaseCount(
            'document_chunks',
            0
        );
    }
}