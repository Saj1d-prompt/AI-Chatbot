<?php

namespace Tests\Feature;

use App\Jobs\ProcessDocumentJob;
use App\Models\Document;
use App\Models\KnowledgeBase;
use App\Models\User;
use App\Services\EmbeddingService;
use App\Services\RAG\DocumentProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ProcessDocumentJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        config([
            'rag.chunk_size' => 300,
            'rag.chunk_overlap' => 50,
        ]);
    }

    private function createDocument(
        string $status = 'uploaded',
        bool $createFile = true
    ): Document {
        $user = User::factory()->create();

        $knowledgeBase = KnowledgeBase::create([
            'user_id' => $user->id,
            'name' => 'Company Policies',
            'description' => 'Company policy documents',
        ]);

        $path = 'rag/test-documents/policy-' .
            $knowledgeBase->id .
            '.txt';

        if ($createFile) {
            Storage::disk('local')->put(
                $path,
                'Employees receive 20 paid annual leave days.'
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

    public function test_document_processing_job_can_be_dispatched(): void
    {
        Queue::fake();

        $document = $this->createDocument();

        ProcessDocumentJob::dispatch($document->id);

        Queue::assertPushed(
            ProcessDocumentJob::class,
            function (ProcessDocumentJob $job) use ($document) {
                return $job->documentId === $document->id;
            }
        );

        Queue::assertPushed(
            ProcessDocumentJob::class,
            1
        );

        /*
         * Queue::fake() prevents execution.
         * The document should not be processed yet.
         */
        $this->assertSame(
            'uploaded',
            $document->fresh()->status
        );

        $this->assertSame(
            0,
            $document->chunks()->count()
        );
    }

    public function test_job_processes_uploaded_document_successfully(): void
    {
        $document = $this->createDocument();

        $embeddingService = $this->mock(
            EmbeddingService::class
        );

        $embeddingService
            ->shouldReceive('embed')
            ->once()
            ->with(
                'Employees receive 20 paid annual leave days.'
            )
            ->andReturn(
                array_fill(0, 768, 0.1)
            );

        $job = new ProcessDocumentJob(
            $document->id
        );

        $job->handle(
            app(DocumentProcessingService::class)
        );

        $document->refresh();

        $this->assertSame(
            'ready',
            $document->status
        );

        $this->assertNull(
            $document->error_message
        );

        $this->assertSame(
            1,
            $document->chunks()->count()
        );

        $this->assertDatabaseHas(
            'document_chunks',
            [
                'document_id' => $document->id,
                'chunk_index' => 0,
                'content' =>
                    'Employees receive 20 paid annual leave days.',
            ]
        );

        $chunk = $document->chunks()->first();

        $this->assertNotNull($chunk);

        $this->assertIsArray(
            $chunk->embedding
        );

        $this->assertCount(
            768,
            $chunk->embedding
        );
    }

    public function test_job_skips_already_ready_document(): void
    {
        $document = $this->createDocument('ready');

        $processingService = $this->mock(
            DocumentProcessingService::class
        );

        $processingService
            ->shouldNotReceive('process');

        $job = new ProcessDocumentJob(
            $document->id
        );

        $job->handle($processingService);

        $this->assertSame(
            'ready',
            $document->fresh()->status
        );
    }

    public function test_job_skips_document_already_processing(): void
    {
        $document = $this->createDocument('processing');

        $processingService = $this->mock(
            DocumentProcessingService::class
        );

        $processingService
            ->shouldNotReceive('process');

        $job = new ProcessDocumentJob(
            $document->id
        );

        $job->handle($processingService);

        $this->assertSame(
            'processing',
            $document->fresh()->status
        );
    }

    public function test_job_handles_deleted_document_gracefully(): void
    {
        $document = $this->createDocument();

        $documentId = $document->id;

        $document->delete();

        $processingService = $this->mock(
            DocumentProcessingService::class
        );

        $processingService
            ->shouldNotReceive('process');

        $job = new ProcessDocumentJob(
            $documentId
        );

        $job->handle($processingService);

        $this->assertDatabaseMissing(
            'documents',
            [
                'id' => $documentId,
            ]
        );
    }

    public function test_job_marks_document_failed_when_processing_fails(): void
    {
        /*
         * Create a database record without its
         * physical file to trigger extraction failure.
         */
        $document = $this->createDocument(
            'uploaded',
            false
        );

        $job = new ProcessDocumentJob(
            $document->id
        );

        try {
            $job->handle(
                app(DocumentProcessingService::class)
            );

            $this->fail(
                'Expected document processing to throw an exception.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'The document file could not be found.',
                $exception->getMessage()
            );
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

        $this->assertSame(
            0,
            $document->chunks()->count()
        );
    }
}
