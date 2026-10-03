<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\KnowledgeBase;
use App\Models\User;
use App\Services\RAG\DocumentProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentProcessingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_extracts_chunks_and_saves_document_chunks(): void
    {
        Storage::fake('local');

        config([
            'rag.chunk_size' => 5,
            'rag.chunk_overlap' => 2,
        ]);

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
        } catch (\RuntimeException) {
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
}