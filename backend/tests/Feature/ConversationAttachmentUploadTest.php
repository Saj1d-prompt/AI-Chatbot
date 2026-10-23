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

class ConversationAttachmentUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Queue::fake();
    }

    private function createConversation(
        User $user
    ): Conversation {
        return Conversation::create([
            'user_id' => $user->id,
            'title' => 'Attachment Test Conversation',
        ]);
    }

    private function uploadUrl(
        Conversation $conversation
    ): string {
        return "/api/conversations/{$conversation->id}/attachments";
    }

    private function makeTextFile(
        string $name = 'policy.txt'
    ): UploadedFile {
        return UploadedFile::fake()
            ->createWithContent(
                $name,
                'Employees receive 20 paid annual leave days.'
            )
            ->mimeType('text/plain');
    }

    public function test_unauthenticated_user_cannot_upload_chat_attachment(): void
    {
        $owner = User::factory()->create();

        $conversation = $this->createConversation($owner);

        $response = $this->postJson(
            $this->uploadUrl($conversation),
            [
                'file' => $this->makeTextFile(),
            ]
        );

        $response->assertUnauthorized();

        $this->assertDatabaseCount('documents', 0);
        $this->assertDatabaseCount('knowledge_bases', 0);

        Queue::assertNotPushed(ProcessDocumentJob::class);
    }

    public function test_user_cannot_upload_to_another_users_conversation(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $conversation = $this->createConversation($owner);

        $response = $this
            ->actingAs($otherUser)
            ->postJson(
                $this->uploadUrl($conversation),
                [
                    'file' => $this->makeTextFile(),
                ]
            );

        $response->assertNotFound();

        $this->assertDatabaseCount('documents', 0);
        $this->assertDatabaseCount('knowledge_bases', 0);

        Queue::assertNotPushed(ProcessDocumentJob::class);
    }

    public function test_upload_creates_internal_knowledge_base_and_dispatches_job(): void
    {
        $user = User::factory()->create();

        $conversation = $this->createConversation($user);

        $response = $this
            ->actingAs($user)
            ->postJson(
                $this->uploadUrl($conversation),
                [
                    'file' => $this->makeTextFile(
                        'company-policy.txt'
                    ),
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.conversation_id',
                $conversation->id
            )
            ->assertJsonPath(
                'data.original_name',
                'company-policy.txt'
            )
            ->assertJsonPath(
                'data.status',
                'uploaded'
            );

        $documentId = $response->json('data.id');

        $document = Document::findOrFail($documentId);

        $knowledgeBase = $conversation
            ->attachmentKnowledgeBase()
            ->first();

        $this->assertNotNull($knowledgeBase);

        $this->assertSame(
            $user->id,
            $knowledgeBase->user_id
        );

        $this->assertSame(
            $conversation->id,
            $knowledgeBase->conversation_id
        );

        $this->assertSame(
            $knowledgeBase->id,
            $document->knowledge_base_id
        );

        $this->assertDatabaseHas(
            'conversation_knowledge_base',
            [
                'conversation_id' =>
                    $conversation->id,

                'knowledge_base_id' =>
                    $knowledgeBase->id,
            ]
        );

        Storage::disk('local')->assertExists(
            $document->file_path
        );

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

        $this->assertSame(
            0,
            $document->chunks()->count()
        );
    }

    public function test_subsequent_uploads_reuse_the_same_internal_knowledge_base(): void
    {
        $user = User::factory()->create();

        $conversation = $this->createConversation($user);

        $firstResponse = $this
            ->actingAs($user)
            ->postJson(
                $this->uploadUrl($conversation),
                [
                    'file' => $this->makeTextFile(
                        'first.txt'
                    ),
                ]
            );

        $secondResponse = $this
            ->actingAs($user)
            ->postJson(
                $this->uploadUrl($conversation),
                [
                    'file' => $this->makeTextFile(
                        'second.txt'
                    ),
                ]
            );

        $firstResponse->assertCreated();
        $secondResponse->assertCreated();

        $this->assertSame(
            $firstResponse->json('data.knowledge_base_id'),
            $secondResponse->json('data.knowledge_base_id')
        );

        $this->assertDatabaseCount(
            'knowledge_bases',
            1
        );

        $this->assertDatabaseCount(
            'documents',
            2
        );

        $this->assertDatabaseCount(
            'conversation_knowledge_base',
            1
        );

        Queue::assertPushed(
            ProcessDocumentJob::class,
            2
        );
    }

    public function test_different_conversations_receive_separate_attachment_knowledge_bases(): void
    {
        $user = User::factory()->create();

        $firstConversation = $this->createConversation(
            $user
        );

        $secondConversation = $this->createConversation(
            $user
        );

        $firstResponse = $this
            ->actingAs($user)
            ->postJson(
                $this->uploadUrl($firstConversation),
                [
                    'file' => $this->makeTextFile(
                        'first-chat.txt'
                    ),
                ]
            );

        $secondResponse = $this
            ->actingAs($user)
            ->postJson(
                $this->uploadUrl($secondConversation),
                [
                    'file' => $this->makeTextFile(
                        'second-chat.txt'
                    ),
                ]
            );

        $firstResponse->assertCreated();
        $secondResponse->assertCreated();

        $firstKnowledgeBaseId = $firstResponse->json(
            'data.knowledge_base_id'
        );

        $secondKnowledgeBaseId = $secondResponse->json(
            'data.knowledge_base_id'
        );

        $this->assertNotEquals(
            $firstKnowledgeBaseId,
            $secondKnowledgeBaseId
        );

        $this->assertDatabaseHas(
            'knowledge_bases',
            [
                'id' => $firstKnowledgeBaseId,
                'conversation_id' =>
                    $firstConversation->id,
            ]
        );

        $this->assertDatabaseHas(
            'knowledge_bases',
            [
                'id' => $secondKnowledgeBaseId,
                'conversation_id' =>
                    $secondConversation->id,
            ]
        );

        $this->assertDatabaseCount(
            'conversation_knowledge_base',
            2
        );

        Queue::assertPushed(
            ProcessDocumentJob::class,
            2
        );
    }

    public function test_invalid_file_does_not_create_attachment_records(): void
    {
        $user = User::factory()->create();

        $conversation = $this->createConversation($user);

        $file = UploadedFile::fake()->create(
            'report.pdf',
            100,
            'application/pdf'
        );

        $response = $this
            ->actingAs($user)
            ->postJson(
                $this->uploadUrl($conversation),
                [
                    'file' => $file,
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'file',
            ]);

        $this->assertDatabaseCount(
            'knowledge_bases',
            0
        );

        $this->assertDatabaseCount(
            'documents',
            0
        );

        Queue::assertNotPushed(
            ProcessDocumentJob::class
        );
    }
}
