<?php

namespace Tests\Feature;

use App\Jobs\ProcessDocumentJob;
use App\Models\Conversation;
use App\Models\Document;
use App\Models\KnowledgeBase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegularDocumentRouteIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Queue::fake();
    }

    private function createInternalKnowledgeBase(
        User $user
    ): KnowledgeBase {
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'title' => 'Private Chat',
        ]);

        return KnowledgeBase::create([
            'user_id' => $user->id,
            'conversation_id' => $conversation->id,
            'name' => 'Private Chat Attachments',
        ]);
    }

    private function createRegularKnowledgeBase(
        User $user
    ): KnowledgeBase {
        return KnowledgeBase::create([
            'user_id' => $user->id,
            'name' => 'Regular Knowledge Base',
        ]);
    }

    private function makeTextFile(): UploadedFile
    {
        return UploadedFile::fake()
            ->createWithContent(
                'company-policy.txt',
                'Employees receive 20 paid leave days.'
            )
            ->mimeType('text/plain');
    }

    public function test_internal_document_list_cannot_be_accessed_through_regular_route(): void
    {
        $user = User::factory()->create();

        $internal = $this->createInternalKnowledgeBase(
            $user
        );

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/knowledge-bases/{$internal->id}/documents"
            );

        $response->assertNotFound();
    }

    public function test_internal_knowledge_base_cannot_receive_regular_document_upload(): void
    {
        $user = User::factory()->create();

        $internal = $this->createInternalKnowledgeBase(
            $user
        );

        $response = $this
            ->actingAs($user)
            ->postJson(
                "/api/knowledge-bases/{$internal->id}/documents",
                [
                    'file' => $this->makeTextFile(),
                ]
            );

        $response->assertNotFound();

        $this->assertDatabaseCount('documents', 0);

        Queue::assertNotPushed(
            ProcessDocumentJob::class
        );
    }

    public function test_internal_document_cannot_be_processed_through_regular_route(): void
    {
        $user = User::factory()->create();

        $internal = $this->createInternalKnowledgeBase(
            $user
        );

        $document = Document::create([
            'knowledge_base_id' => $internal->id,
            'original_name' => 'private.txt',
            'stored_name' => 'private.txt',
            'mime_type' => 'text/plain',
            'file_path' => 'documents/private.txt',
            'file_size' => 100,
            'status' => 'uploaded',
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(
                "/api/knowledge-bases/{$internal->id}/documents/{$document->id}/process"
            );

        $response->assertNotFound();

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'status' => 'uploaded',
        ]);

        Queue::assertNotPushed(
            ProcessDocumentJob::class
        );
    }

    public function test_regular_document_list_still_works(): void
    {
        $user = User::factory()->create();

        $regular = $this->createRegularKnowledgeBase(
            $user
        );

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/knowledge-bases/{$regular->id}/documents"
            );

        $response
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_regular_document_upload_still_works(): void
    {
        $user = User::factory()->create();

        $regular = $this->createRegularKnowledgeBase(
            $user
        );

        $response = $this
            ->actingAs($user)
            ->postJson(
                "/api/knowledge-bases/{$regular->id}/documents",
                [
                    'file' => $this->makeTextFile(),
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.knowledge_base_id',
                $regular->id
            )
            ->assertJsonPath(
                'data.status',
                'uploaded'
            );

        $this->assertDatabaseHas('documents', [
            'knowledge_base_id' => $regular->id,
            'original_name' => 'company-policy.txt',
            'status' => 'uploaded',
        ]);

        Queue::assertPushed(
            ProcessDocumentJob::class,
            1
        );
    }
}
