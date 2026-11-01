<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\KnowledgeBase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ConversationAttachmentDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function createConversation(
        User $user
    ): Conversation {
        return Conversation::create([
            'user_id' => $user->id,
            'title' => 'Attachment Deletion Test',
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
        string $status = 'ready',
        string $name = 'policy.txt'
    ): Document {
        $path =
            "rag/tests/{$knowledgeBase->id}/{$name}";

        Storage::disk('local')->put(
            $path,
            'Nexus test attachment content.'
        );

        return Document::create([
            'knowledge_base_id' =>
                $knowledgeBase->id,
            'original_name' => $name,
            'stored_name' => $name,
            'mime_type' => 'text/plain',
            'file_path' => $path,
            'file_size' => 30,
            'status' => $status,
        ]);
    }

    private function deleteUrl(
        Conversation $conversation,
        Document $document
    ): string {
        return
            "/api/conversations/{$conversation->id}" .
            "/attachments/{$document->id}";
    }

    public function test_unauthenticated_user_cannot_delete_attachment(): void
    {
        $owner = User::factory()->create();

        $conversation =
            $this->createConversation($owner);

        $knowledgeBase =
            $this->createInternalKnowledgeBase(
                $owner,
                $conversation
            );

        $document =
            $this->createDocument($knowledgeBase);

        $this->deleteJson(
            $this->deleteUrl(
                $conversation,
                $document
            )
        )->assertUnauthorized();

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
        ]);

        Storage::disk('local')->assertExists(
            $document->file_path
        );
    }

    public function test_user_cannot_delete_another_users_attachment(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $conversation =
            $this->createConversation($owner);

        $knowledgeBase =
            $this->createInternalKnowledgeBase(
                $owner,
                $conversation
            );

        $document =
            $this->createDocument($knowledgeBase);

        $this->actingAs($otherUser)
            ->deleteJson(
                $this->deleteUrl(
                    $conversation,
                    $document
                )
            )
            ->assertNotFound();

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
        ]);

        Storage::disk('local')->assertExists(
            $document->file_path
        );
    }

    public function test_user_cannot_delete_another_conversations_attachment(): void
    {
        $user = User::factory()->create();

        $firstConversation =
            $this->createConversation($user);

        $secondConversation =
            $this->createConversation($user);

        $knowledgeBase =
            $this->createInternalKnowledgeBase(
                $user,
                $firstConversation
            );

        $document =
            $this->createDocument($knowledgeBase);

        $this->actingAs($user)
            ->deleteJson(
                $this->deleteUrl(
                    $secondConversation,
                    $document
                )
            )
            ->assertNotFound();

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
        ]);
    }

    public function test_regular_knowledge_base_document_cannot_be_deleted_as_chat_attachment(): void
    {
        $user = User::factory()->create();

        $conversation =
            $this->createConversation($user);

        $regularKnowledgeBase =
            KnowledgeBase::create([
                'user_id' => $user->id,
                'name' => 'Regular Knowledge Base',
            ]);

        $document = $this->createDocument(
            $regularKnowledgeBase
        );

        $this->actingAs($user)
            ->deleteJson(
                $this->deleteUrl(
                    $conversation,
                    $document
                )
            )
            ->assertNotFound();

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
        ]);

        Storage::disk('local')->assertExists(
            $document->file_path
        );
    }

    public function test_ready_attachment_and_its_chunks_and_sources_are_deleted(): void
    {
        $user = User::factory()->create();

        $conversation =
            $this->createConversation($user);

        $knowledgeBase =
            $this->createInternalKnowledgeBase(
                $user,
                $conversation
            );

        $document = $this->createDocument(
            $knowledgeBase,
            'ready'
        );

        /*
         * Create a chunk containing an embedding.
         */
        $chunk = DocumentChunk::create([
            'document_id' => $document->id,
            'chunk_index' => 0,
            'content' => 'Training budget is 15000 BDT.',
            'embedding' => [0.1, 0.2, 0.3],
            'token_count' => 6,
        ]);

        /*
         * Create an existing assistant message
         * referencing this document chunk.
         */
        $message = $conversation
            ->messages()
            ->create([
                'role' => 'assistant',
                'content' => 'The budget is 15000 BDT.',
            ]);

        $source = $message
            ->sources()
            ->create([
                'document_chunk_id' => $chunk->id,
                'rank' => 1,
                'score' => 0.95,
            ]);

        /*
         * Another document in the same conversation
         * must not be deleted.
         */
        $otherDocument = $this->createDocument(
            $knowledgeBase,
            'ready',
            'other.txt'
        );

        $response = $this->actingAs($user)
            ->deleteJson(
                $this->deleteUrl(
                    $conversation,
                    $document
                )
            );

        $response
            ->assertOk()
            ->assertJsonPath('success', true);

        /*
         * Main document removed.
         */
        $this->assertDatabaseMissing('documents', [
            'id' => $document->id,
        ]);

        /*
         * Embedding chunk removed.
         */
        $this->assertDatabaseMissing(
            'document_chunks',
            [
                'id' => $chunk->id,
            ]
        );

        /*
         * Historical citation reference removed.
         */
        $this->assertDatabaseMissing(
            'message_sources',
            [
                'id' => $source->id,
            ]
        );

        /*
         * Physical file removed.
         */
        Storage::disk('local')->assertMissing(
            $document->file_path
        );

        /*
         * Existing chat message remains intact.
         */
        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
        ]);

        /*
         * Other attachments remain intact.
         */
        $this->assertDatabaseHas('documents', [
            'id' => $otherDocument->id,
        ]);

        Storage::disk('local')->assertExists(
            $otherDocument->file_path
        );
    }

    public function test_failed_attachment_can_be_deleted(): void
    {
        $user = User::factory()->create();

        $conversation =
            $this->createConversation($user);

        $knowledgeBase =
            $this->createInternalKnowledgeBase(
                $user,
                $conversation
            );

        $document = $this->createDocument(
            $knowledgeBase,
            'failed'
        );

        $this->actingAs($user)
            ->deleteJson(
                $this->deleteUrl(
                    $conversation,
                    $document
                )
            )
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('documents', [
            'id' => $document->id,
        ]);

        Storage::disk('local')->assertMissing(
            $document->file_path
        );
    }

    public function test_uploaded_attachment_cannot_be_deleted_while_queued(): void
    {
        $this->assertDeletionRejectedForStatus(
            'uploaded'
        );
    }

    public function test_processing_attachment_cannot_be_deleted(): void
    {
        $this->assertDeletionRejectedForStatus(
            'processing'
        );
    }

    private function assertDeletionRejectedForStatus(
        string $status
    ): void {
        $user = User::factory()->create();

        $conversation =
            $this->createConversation($user);

        $knowledgeBase =
            $this->createInternalKnowledgeBase(
                $user,
                $conversation
            );

        $document = $this->createDocument(
            $knowledgeBase,
            $status
        );

        $this->actingAs($user)
            ->deleteJson(
                $this->deleteUrl(
                    $conversation,
                    $document
                )
            )
            ->assertStatus(409);

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'status' => $status,
        ]);

        Storage::disk('local')->assertExists(
            $document->file_path
        );
    }

    public function test_nonexistent_attachment_returns_not_found(): void
    {
        $user = User::factory()->create();

        $conversation =
            $this->createConversation($user);

        $this->createInternalKnowledgeBase(
            $user,
            $conversation
        );

        $this->actingAs($user)
            ->deleteJson(
                "/api/conversations/{$conversation->id}/attachments/999999"
            )
            ->assertNotFound();
    }

    public function test_attachment_can_be_deleted_if_physical_file_is_already_missing(): void
    {
        $user = User::factory()->create();

        $conversation =
            $this->createConversation($user);

        $knowledgeBase =
            $this->createInternalKnowledgeBase(
                $user,
                $conversation
            );

        $document =
            $this->createDocument($knowledgeBase);

        Storage::disk('local')->delete(
            $document->file_path
        );

        $this->actingAs($user)
            ->deleteJson(
                $this->deleteUrl(
                    $conversation,
                    $document
                )
            )
            ->assertOk();

        $this->assertDatabaseMissing('documents', [
            'id' => $document->id,
        ]);
    }
}
