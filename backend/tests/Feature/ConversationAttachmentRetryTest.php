<?php

namespace Tests\Feature;

use App\Jobs\ProcessDocumentJob;
use App\Models\Conversation;
use App\Models\Document;
use App\Models\KnowledgeBase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ConversationAttachmentRetryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    private function createConversation(
        User $user
    ): Conversation {
        return Conversation::create([
            'user_id' => $user->id,
            'title' => 'Attachment Retry Test',
        ]);
    }

    private function createInternalKnowledgeBase(
        User $user,
        Conversation $conversation
    ): KnowledgeBase {
        return KnowledgeBase::create([
            'user_id' => $user->id,
            'conversation_id' => $conversation->id,
            'name' => 'Chat Attachments',
        ]);
    }

    private function createDocument(
        KnowledgeBase $knowledgeBase,
        string $status = 'failed'
    ): Document {
        $document = Document::create([
            'knowledge_base_id' =>
                $knowledgeBase->id,
            'original_name' => 'policy.txt',
            'stored_name' => 'policy.txt',
            'mime_type' => 'text/plain',
            'file_path' => 'documents/policy.txt',
            'file_size' => 100,
            'status' => $status,
        ]);

        if ($status === 'failed') {
            $document->forceFill([
                'error_message' =>
                    'Previous embedding attempt failed.',
            ])->save();
        }

        return $document;
    }

    private function retryUrl(
        Conversation $conversation,
        Document $document
    ): string {
        return
            "/api/conversations/{$conversation->id}" .
            "/attachments/{$document->id}/retry";
    }

    public function test_unauthenticated_user_cannot_retry_attachment(): void
    {
        $owner = User::factory()->create();

        $conversation = $this->createConversation(
            $owner
        );

        $knowledgeBase = $this
            ->createInternalKnowledgeBase(
                $owner,
                $conversation
            );

        $document = $this->createDocument(
            $knowledgeBase
        );

        $this->postJson(
            $this->retryUrl(
                $conversation,
                $document
            )
        )->assertUnauthorized();

        Queue::assertNotPushed(
            ProcessDocumentJob::class
        );
    }

    public function test_user_cannot_retry_another_users_attachment(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $conversation = $this->createConversation(
            $owner
        );

        $knowledgeBase = $this
            ->createInternalKnowledgeBase(
                $owner,
                $conversation
            );

        $document = $this->createDocument(
            $knowledgeBase
        );

        $this->actingAs($otherUser)
            ->postJson(
                $this->retryUrl(
                    $conversation,
                    $document
                )
            )
            ->assertNotFound();

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'status' => 'failed',
        ]);

        Queue::assertNotPushed(
            ProcessDocumentJob::class
        );
    }

    public function test_user_cannot_retry_another_conversations_attachment(): void
    {
        $user = User::factory()->create();

        $firstConversation =
            $this->createConversation($user);

        $secondConversation =
            $this->createConversation($user);

        $firstKnowledgeBase =
            $this->createInternalKnowledgeBase(
                $user,
                $firstConversation
            );

        $document = $this->createDocument(
            $firstKnowledgeBase
        );

        $this->actingAs($user)
            ->postJson(
                $this->retryUrl(
                    $secondConversation,
                    $document
                )
            )
            ->assertNotFound();

        Queue::assertNotPushed(
            ProcessDocumentJob::class
        );
    }

    public function test_regular_knowledge_base_document_cannot_be_retried_as_chat_attachment(): void
    {
        $user = User::factory()->create();

        $conversation = $this->createConversation(
            $user
        );

        $regularKnowledgeBase = KnowledgeBase::create([
            'user_id' => $user->id,
            'name' => 'Regular Documents',
        ]);

        $document = $this->createDocument(
            $regularKnowledgeBase
        );

        $this->actingAs($user)
            ->postJson(
                $this->retryUrl(
                    $conversation,
                    $document
                )
            )
            ->assertNotFound();

        Queue::assertNotPushed(
            ProcessDocumentJob::class
        );
    }

    public function test_failed_attachment_can_be_retried(): void
    {
        $user = User::factory()->create();

        $conversation = $this->createConversation(
            $user
        );

        $knowledgeBase =
            $this->createInternalKnowledgeBase(
                $user,
                $conversation
            );

        $document = $this->createDocument(
            $knowledgeBase,
            'failed'
        );

        $response = $this->actingAs($user)
            ->postJson(
                $this->retryUrl(
                    $conversation,
                    $document
                )
            );

        $response
            ->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.id',
                $document->id
            )
            ->assertJsonPath(
                'data.status',
                'uploaded'
            );

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'status' => 'uploaded',
            'error_message' => null,
        ]);

        $this->assertDatabaseCount(
            'documents',
            1
        );

        Queue::assertPushed(
            ProcessDocumentJob::class,
            function (ProcessDocumentJob $job) use (
                $document
            ) {
                return $job->documentId ===
                    $document->id;
            }
        );

        Queue::assertPushed(
            ProcessDocumentJob::class,
            1
        );
    }

    public function test_ready_attachment_cannot_be_retried(): void
    {
        $this->assertRetryRejectedForStatus(
            'ready'
        );
    }

    public function test_uploaded_attachment_cannot_be_retried(): void
    {
        $this->assertRetryRejectedForStatus(
            'uploaded'
        );
    }

    public function test_processing_attachment_cannot_be_retried(): void
    {
        $this->assertRetryRejectedForStatus(
            'processing'
        );
    }

    private function assertRetryRejectedForStatus(
        string $status
    ): void {
        $user = User::factory()->create();

        $conversation = $this->createConversation(
            $user
        );

        $knowledgeBase =
            $this->createInternalKnowledgeBase(
                $user,
                $conversation
            );

        $document = $this->createDocument(
            $knowledgeBase,
            $status
        );

        $response = $this->actingAs($user)
            ->postJson(
                $this->retryUrl(
                    $conversation,
                    $document
                )
            );

        $response
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'status' => $status,
        ]);

        Queue::assertNotPushed(
            ProcessDocumentJob::class
        );
    }
}
