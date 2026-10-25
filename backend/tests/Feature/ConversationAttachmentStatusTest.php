<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Document;
use App\Models\KnowledgeBase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationAttachmentStatusTest extends TestCase
{
    use RefreshDatabase;

    private function createConversation(
        User $user
    ): Conversation {
        return Conversation::create([
            'user_id' => $user->id,
            'title' => 'Attachment Status Test',
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
        string $name,
        string $status = 'uploaded'
    ): Document {
        return Document::create([
            'knowledge_base_id' => $knowledgeBase->id,
            'original_name' => $name,
            'stored_name' => $name,
            'mime_type' => 'text/plain',
            'file_path' => 'documents/' . $name,
            'file_size' => 100,
            'status' => $status,
        ]);
    }

    private function statusUrl(
        Conversation $conversation
    ): string {
        return "/api/conversations/{$conversation->id}/attachments";
    }

    public function test_unauthenticated_user_cannot_list_attachments(): void
    {
        $owner = User::factory()->create();

        $conversation = $this->createConversation($owner);

        $this->getJson(
            $this->statusUrl($conversation)
        )->assertUnauthorized();
    }

    public function test_user_cannot_list_another_users_attachments(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $conversation = $this->createConversation($owner);

        $this->actingAs($otherUser)
            ->getJson($this->statusUrl($conversation))
            ->assertNotFound();
    }

    public function test_conversation_without_attachments_returns_empty_list(): void
    {
        $user = User::factory()->create();

        $conversation = $this->createConversation($user);

        $this->actingAs($user)
            ->getJson($this->statusUrl($conversation))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data');
    }

    public function test_owner_can_read_attachment_processing_statuses(): void
    {
        $user = User::factory()->create();

        $conversation = $this->createConversation($user);

        $knowledgeBase = $this->createInternalKnowledgeBase(
            $user,
            $conversation
        );

        $first = $this->createDocument(
            $knowledgeBase,
            'first.txt',
            'uploaded'
        );

        $second = $this->createDocument(
            $knowledgeBase,
            'second.txt',
            'ready'
        );

        $response = $this->actingAs($user)
            ->getJson($this->statusUrl($conversation));

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath(
                'data.0.original_name',
                'first.txt'
            )
            ->assertJsonPath(
                'data.0.status',
                'uploaded'
            )
            ->assertJsonPath(
                'data.0.chunks_count',
                0
            )
            ->assertJsonPath('data.1.id', $second->id)
            ->assertJsonPath(
                'data.1.status',
                'ready'
            );
    }

    public function test_attachments_from_another_conversation_are_not_returned(): void
    {
        $user = User::factory()->create();

        $firstConversation = $this->createConversation($user);
        $secondConversation = $this->createConversation($user);

        $firstKnowledgeBase = $this->createInternalKnowledgeBase(
            $user,
            $firstConversation
        );

        $secondKnowledgeBase = $this->createInternalKnowledgeBase(
            $user,
            $secondConversation
        );

        $firstDocument = $this->createDocument(
            $firstKnowledgeBase,
            'first-private.txt'
        );

        $secondDocument = $this->createDocument(
            $secondKnowledgeBase,
            'second-private.txt'
        );

        $response = $this->actingAs($user)
            ->getJson($this->statusUrl($firstConversation));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $firstDocument->id
            );

        $returnedIds = collect(
            $response->json('data')
        )->pluck('id')->all();

        $this->assertNotContains(
            $secondDocument->id,
            $returnedIds
        );
    }
}
