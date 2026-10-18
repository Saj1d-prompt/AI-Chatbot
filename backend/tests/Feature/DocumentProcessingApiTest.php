<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\KnowledgeBase;
use App\Models\User;
use App\Services\EmbeddingService;
use App\Services\RAG\DocumentProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentProcessingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function createKnowledgeBase(
        User $user
    ): KnowledgeBase {
        return KnowledgeBase::create([
            'user_id' => $user->id,
            'name' => 'Test Knowledge Base',
            'description' => 'Test documents',
        ]);
    }

    private function createDocument(
        KnowledgeBase $knowledgeBase,
        string $status = 'uploaded',
        bool $createFile = true
    ): Document {
        $path = 'rag/test-' .
            $knowledgeBase->id .
            '-' .
            uniqid() .
            '.txt';

        if ($createFile) {
            Storage::disk('local')->put(
                $path,
                'Employees receive twenty days of annual leave.'
            );
        }

        return Document::create([
            'knowledge_base_id' => $knowledgeBase->id,
            'original_name' => 'policy.txt',
            'stored_name' => 'policy.txt',
            'mime_type' => 'text/plain',
            'file_path' => $path,
            'file_size' => 100,
            'status' => $status,
        ]);
    }

    private function processUrl(
        KnowledgeBase $knowledgeBase,
        Document $document
    ): string {
        return "/api/knowledge-bases/{$knowledgeBase->id}" .
            "/documents/{$document->id}/process";
    }

    public function test_unauthenticated_user_cannot_process_document(): void
    {
        $owner = User::factory()->create();

        $knowledgeBase = $this->createKnowledgeBase($owner);
        $document = $this->createDocument($knowledgeBase);

        $this->postJson(
            $this->processUrl($knowledgeBase, $document)
        )->assertUnauthorized();

        $this->assertSame(
            'uploaded',
            $document->fresh()->status
        );
    }

    public function test_user_cannot_process_another_users_document(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $knowledgeBase = $this->createKnowledgeBase($owner);
        $document = $this->createDocument($knowledgeBase);

        $this->actingAs($otherUser)
            ->postJson(
                $this->processUrl($knowledgeBase, $document)
            )
            ->assertNotFound();

        $this->assertSame(
            'uploaded',
            $document->fresh()->status
        );
    }

    public function test_document_must_belong_to_requested_knowledge_base(): void
    {
        $user = User::factory()->create();

        $firstKnowledgeBase = $this->createKnowledgeBase($user);
        $secondKnowledgeBase = $this->createKnowledgeBase($user);

        $document = $this->createDocument(
            $secondKnowledgeBase
        );

        $this->actingAs($user)
            ->postJson(
                $this->processUrl(
                    $firstKnowledgeBase,
                    $document
                )
            )
            ->assertNotFound();

        $this->assertSame(
            'uploaded',
            $document->fresh()->status
        );
    }

    public function test_owner_can_process_uploaded_document(): void
    {
        $user = User::factory()->create();

        $knowledgeBase = $this->createKnowledgeBase($user);
        $document = $this->createDocument($knowledgeBase);

        config([
            'rag.chunk_size' => 300,
            'rag.chunk_overlap' => 50,
        ]);

        $embeddingService = $this->mock(
            EmbeddingService::class
        );

        $embeddingService
            ->shouldReceive('embed')
            ->once()
            ->with(
                'Employees receive twenty days of annual leave.'
            )
            ->andReturn(
                array_fill(0, 768, 0.1)
            );

        $response = $this->actingAs($user)
            ->postJson(
                $this->processUrl($knowledgeBase, $document)
            );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $document->id)
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.chunks_count', 1);

        $document->refresh();

        $this->assertSame('ready', $document->status);
        $this->assertNull($document->error_message);
        $this->assertSame(1, $document->chunks()->count());

        $this->assertDatabaseHas('document_chunks', [
            'document_id' => $document->id,
            'chunk_index' => 0,
            'content' =>
                'Employees receive twenty days of annual leave.',
        ]);

        $chunk = $document->chunks()->first();

        $this->assertNotNull($chunk);
        $this->assertIsArray($chunk->embedding);
        $this->assertCount(768, $chunk->embedding);
    }

    public function test_ready_document_cannot_be_processed_again(): void
    {
        $user = User::factory()->create();

        $knowledgeBase = $this->createKnowledgeBase($user);

        $document = $this->createDocument(
            $knowledgeBase,
            'ready'
        );

        $processingService = $this->mock(
            DocumentProcessingService::class
        );

        $processingService
            ->shouldNotReceive('process');

        $response = $this->actingAs($user)
            ->postJson(
                $this->processUrl($knowledgeBase, $document)
            );

        $response
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        $this->assertSame(
            'ready',
            $document->fresh()->status
        );
    }

    public function test_processing_document_cannot_be_started_again(): void
    {
        $user = User::factory()->create();

        $knowledgeBase = $this->createKnowledgeBase($user);

        $document = $this->createDocument(
            $knowledgeBase,
            'processing'
        );

        $processingService = $this->mock(
            DocumentProcessingService::class
        );

        $processingService
            ->shouldNotReceive('process');

        $response = $this->actingAs($user)
            ->postJson(
                $this->processUrl($knowledgeBase, $document)
            );

        $response
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        $this->assertSame(
            'processing',
            $document->fresh()->status
        );
    }

    public function test_processing_failure_marks_document_as_failed(): void
    {
        $user = User::factory()->create();

        $knowledgeBase = $this->createKnowledgeBase($user);

        /*
         * Do not create the physical file.
         * Text extraction must fail.
         */
        $document = $this->createDocument(
            $knowledgeBase,
            'uploaded',
            false
        );

        $response = $this->actingAs($user)
            ->postJson(
                $this->processUrl($knowledgeBase, $document)
            );

        $response
            ->assertStatus(500)
            ->assertJsonPath('success', false);

        $document->refresh();

        $this->assertSame(
            'failed',
            $document->status
        );

        $this->assertSame(
            'The document file could not be found.',
            $document->error_message
        );

        $this->assertSame(
            0,
            $document->chunks()->count()
        );
    }

    public function test_previously_failed_document_can_be_reprocessed(): void
    {
        $user = User::factory()->create();

        $knowledgeBase = $this->createKnowledgeBase($user);

        $document = $this->createDocument(
            $knowledgeBase,
            'failed'
        );

        $document->update([
            'error_message' => 'Previous processing failure.',
        ]);

        config([
            'rag.chunk_size' => 300,
            'rag.chunk_overlap' => 50,
        ]);

        $embeddingService = $this->mock(
            EmbeddingService::class
        );

        $embeddingService
            ->shouldReceive('embed')
            ->once()
            ->andReturn(
                array_fill(0, 768, 0.2)
            );

        $response = $this->actingAs($user)
            ->postJson(
                $this->processUrl($knowledgeBase, $document)
            );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.chunks_count', 1);

        $document->refresh();

        $this->assertSame('ready', $document->status);
        $this->assertNull($document->error_message);
        $this->assertSame(1, $document->chunks()->count());
    }
}
