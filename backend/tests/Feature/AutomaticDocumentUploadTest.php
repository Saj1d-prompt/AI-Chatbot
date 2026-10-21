<?php

namespace Tests\Feature;

use App\Jobs\ProcessDocumentJob;
use App\Models\KnowledgeBase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AutomaticDocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Queue::fake();
    }

    private function createKnowledgeBase(
        User $user
    ): KnowledgeBase {
        return KnowledgeBase::create([
            'user_id' => $user->id,
            'name' => 'Company Policies',
            'description' => 'Test knowledge base',
        ]);
    }

    private function uploadUrl(
        KnowledgeBase $knowledgeBase
    ): string {
        return "/api/knowledge-bases/{$knowledgeBase->id}/documents";
    }

    public function test_successful_upload_automatically_dispatches_processing_job(): void
    {
        $user = User::factory()->create();

        $knowledgeBase = $this->createKnowledgeBase($user);

        $file = UploadedFile::fake()
            ->createWithContent(
                'company-policy.txt',
                'Employees receive 20 paid annual leave days.'
            )
            ->mimeType('text/plain');

        $response = $this->actingAs($user)
            ->postJson(
                $this->uploadUrl($knowledgeBase),
                ['file' => $file]
            );

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.original_name',
                'company-policy.txt'
            )
            ->assertJsonPath('data.status', 'uploaded');

        $documentId = $response->json('data.id');

        $this->assertDatabaseHas('documents', [
            'id' => $documentId,
            'knowledge_base_id' => $knowledgeBase->id,
            'status' => 'uploaded',
        ]);

        Queue::assertPushed(
            ProcessDocumentJob::class,
            function (ProcessDocumentJob $job) use ($documentId) {
                return $job->documentId === $documentId;
            }
        );

        Queue::assertPushed(
            ProcessDocumentJob::class,
            1
        );

        $this->assertDatabaseCount('document_chunks', 0);

        Storage::disk('local')->assertExists(
            $response->json('data.file_path')
        );
    }

    public function test_unauthenticated_upload_does_not_queue_processing(): void
    {
        $user = User::factory()->create();

        $knowledgeBase = $this->createKnowledgeBase($user);

        $file = UploadedFile::fake()
            ->createWithContent(
                'policy.txt',
                'Private company policy.'
            )
            ->mimeType('text/plain');

        $this->postJson(
            $this->uploadUrl($knowledgeBase),
            ['file' => $file]
        )->assertUnauthorized();

        Queue::assertNotPushed(ProcessDocumentJob::class);

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_other_users_upload_does_not_queue_processing(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $knowledgeBase = $this->createKnowledgeBase($owner);

        $file = UploadedFile::fake()
            ->createWithContent(
                'private.txt',
                'Confidential information.'
            )
            ->mimeType('text/plain');

        $this->actingAs($otherUser)
            ->postJson(
                $this->uploadUrl($knowledgeBase),
                ['file' => $file]
            )
            ->assertNotFound();

        Queue::assertNotPushed(ProcessDocumentJob::class);

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_invalid_file_does_not_queue_processing(): void
    {
        $user = User::factory()->create();

        $knowledgeBase = $this->createKnowledgeBase($user);

        $file = UploadedFile::fake()->create(
            'document.pdf',
            100,
            'application/pdf'
        );

        $this->actingAs($user)
            ->postJson(
                $this->uploadUrl($knowledgeBase),
                ['file' => $file]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);

        Queue::assertNotPushed(ProcessDocumentJob::class);

        $this->assertDatabaseCount('documents', 0);
    }
}
