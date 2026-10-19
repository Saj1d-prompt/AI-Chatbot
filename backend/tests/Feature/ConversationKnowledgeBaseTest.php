<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\KnowledgeBase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationKnowledgeBaseTest extends TestCase
{
    use RefreshDatabase;

    private function createConversation(User $user): Conversation
    {
        return Conversation::create([
            'user_id' => $user->id,
            'title' => 'Test Conversation',
        ]);
    }

    private function createKnowledgeBase(
        User $user,
        string $name = 'Test Knowledge Base'
    ): KnowledgeBase {
        return KnowledgeBase::create([
            'user_id' => $user->id,
            'name' => $name,
            'description' => 'Knowledge base for testing',
        ]);
    }

    private function attachmentUrl(
        Conversation $conversation
    ): string {
        return "/api/conversations/{$conversation->id}/knowledge-bases";
    }

    private function getAttachedIds(
        Conversation $conversation
    ): array {
        return $conversation
            ->knowledgeBases()
            ->pluck('knowledge_bases.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function test_unauthenticated_user_cannot_attach_knowledge_bases(): void
    {
        $user = User::factory()->create();

        $conversation = $this->createConversation($user);
        $knowledgeBase = $this->createKnowledgeBase($user);

        $response = $this->putJson(
            $this->attachmentUrl($conversation),
            [
                'knowledge_base_ids' => [
                    $knowledgeBase->id,
                ],
            ]
        );

        $response->assertUnauthorized();

        $this->assertSame(
            [],
            $this->getAttachedIds($conversation)
        );
    }

    public function test_user_cannot_modify_another_users_conversation(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $conversation = $this->createConversation($owner);
        $knowledgeBase = $this->createKnowledgeBase($owner);

        $response = $this
            ->actingAs($otherUser)
            ->putJson(
                $this->attachmentUrl($conversation),
                [
                    'knowledge_base_ids' => [
                        $knowledgeBase->id,
                    ],
                ]
            );

        $response->assertNotFound();

        $this->assertSame(
            [],
            $this->getAttachedIds($conversation)
        );
    }

    public function test_owner_can_attach_one_knowledge_base(): void
    {
        $user = User::factory()->create();

        $conversation = $this->createConversation($user);
        $knowledgeBase = $this->createKnowledgeBase($user);

        $response = $this
            ->actingAs($user)
            ->putJson(
                $this->attachmentUrl($conversation),
                [
                    'knowledge_base_ids' => [
                        $knowledgeBase->id,
                    ],
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'knowledge_base_ids',
                [$knowledgeBase->id]
            );

        $this->assertDatabaseHas(
            'conversation_knowledge_base',
            [
                'conversation_id' => $conversation->id,
                'knowledge_base_id' => $knowledgeBase->id,
            ]
        );

        $this->assertSame(
            [$knowledgeBase->id],
            $this->getAttachedIds($conversation)
        );
    }

    public function test_owner_can_attach_multiple_knowledge_bases(): void
    {
        $user = User::factory()->create();

        $conversation = $this->createConversation($user);

        $first = $this->createKnowledgeBase(
            $user,
            'Company Policies'
        );

        $second = $this->createKnowledgeBase(
            $user,
            'Employee Benefits'
        );

        $response = $this
            ->actingAs($user)
            ->putJson(
                $this->attachmentUrl($conversation),
                [
                    'knowledge_base_ids' => [
                        $first->id,
                        $second->id,
                    ],
                ]
            );

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $response->json('knowledge_base_ids')
        );

        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $this->getAttachedIds($conversation)
        );

        $this->assertDatabaseCount(
            'conversation_knowledge_base',
            2
        );
    }

    public function test_updating_attachments_replaces_previous_knowledge_bases(): void
    {
        $user = User::factory()->create();

        $conversation = $this->createConversation($user);

        $first = $this->createKnowledgeBase(
            $user,
            'Old Knowledge Base'
        );

        $second = $this->createKnowledgeBase(
            $user,
            'New Knowledge Base'
        );

        $conversation->knowledgeBases()->attach($first->id);

        $response = $this
            ->actingAs($user)
            ->putJson(
                $this->attachmentUrl($conversation),
                [
                    'knowledge_base_ids' => [
                        $second->id,
                    ],
                ]
            );

        $response->assertOk();

        $this->assertSame(
            [$second->id],
            $this->getAttachedIds($conversation)
        );

        $this->assertDatabaseMissing(
            'conversation_knowledge_base',
            [
                'conversation_id' => $conversation->id,
                'knowledge_base_id' => $first->id,
            ]
        );

        $this->assertDatabaseHas(
            'conversation_knowledge_base',
            [
                'conversation_id' => $conversation->id,
                'knowledge_base_id' => $second->id,
            ]
        );
    }

    public function test_empty_array_detaches_all_knowledge_bases(): void
    {
        $user = User::factory()->create();

        $conversation = $this->createConversation($user);

        $first = $this->createKnowledgeBase(
            $user,
            'First Knowledge Base'
        );

        $second = $this->createKnowledgeBase(
            $user,
            'Second Knowledge Base'
        );

        $conversation->knowledgeBases()->attach([
            $first->id,
            $second->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->putJson(
                $this->attachmentUrl($conversation),
                [
                    'knowledge_base_ids' => [],
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'knowledge_base_ids',
                []
            );

        $this->assertSame(
            [],
            $this->getAttachedIds($conversation)
        );

        $this->assertDatabaseCount(
            'conversation_knowledge_base',
            0
        );
    }

    public function test_user_cannot_attach_another_users_knowledge_base(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $conversation = $this->createConversation($user);

        $foreignKnowledgeBase = $this->createKnowledgeBase(
            $otherUser,
            'Private Knowledge Base'
        );

        $response = $this
            ->actingAs($user)
            ->putJson(
                $this->attachmentUrl($conversation),
                [
                    'knowledge_base_ids' => [
                        $foreignKnowledgeBase->id,
                    ],
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'knowledge_base_ids',
            ]);

        $this->assertSame(
            [],
            $this->getAttachedIds($conversation)
        );
    }

    public function test_invalid_attachment_does_not_remove_existing_attachments(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $conversation = $this->createConversation($user);

        $ownedKnowledgeBase = $this->createKnowledgeBase(
            $user,
            'My Knowledge Base'
        );

        $foreignKnowledgeBase = $this->createKnowledgeBase(
            $otherUser,
            'Another Users Knowledge Base'
        );

        $conversation->knowledgeBases()->attach(
            $ownedKnowledgeBase->id
        );

        $response = $this
            ->actingAs($user)
            ->putJson(
                $this->attachmentUrl($conversation),
                [
                    'knowledge_base_ids' => [
                        $ownedKnowledgeBase->id,
                        $foreignKnowledgeBase->id,
                    ],
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'knowledge_base_ids',
            ]);

        $this->assertSame(
            [$ownedKnowledgeBase->id],
            $this->getAttachedIds($conversation)
        );

        $this->assertDatabaseMissing(
            'conversation_knowledge_base',
            [
                'conversation_id' => $conversation->id,
                'knowledge_base_id' => $foreignKnowledgeBase->id,
            ]
        );
    }

    public function test_duplicate_knowledge_base_ids_are_rejected(): void
    {
        $user = User::factory()->create();

        $conversation = $this->createConversation($user);
        $knowledgeBase = $this->createKnowledgeBase($user);

        $response = $this
            ->actingAs($user)
            ->putJson(
                $this->attachmentUrl($conversation),
                [
                    'knowledge_base_ids' => [
                        $knowledgeBase->id,
                        $knowledgeBase->id,
                    ],
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'knowledge_base_ids.1',
            ]);

        $this->assertSame(
            [],
            $this->getAttachedIds($conversation)
        );
    }

    public function test_conversation_show_returns_attached_knowledge_base_ids(): void
    {
        $user = User::factory()->create();

        $conversation = $this->createConversation($user);

        $first = $this->createKnowledgeBase(
            $user,
            'Company Policies'
        );

        $second = $this->createKnowledgeBase(
            $user,
            'Employee Benefits'
        );

        $conversation->knowledgeBases()->attach([
            $first->id,
            $second->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/conversations/{$conversation->id}"
            );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'conversation.id',
                $conversation->id
            );

        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $response->json('knowledge_base_ids')
        );
    }
}
