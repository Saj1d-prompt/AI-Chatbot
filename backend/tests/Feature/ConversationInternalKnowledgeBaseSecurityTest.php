<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\KnowledgeBase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationInternalKnowledgeBaseSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function createConversation(User $user): Conversation
    {
        return Conversation::create([
            'user_id' => $user->id,
            'title' => 'Private Conversation',
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

    private function createInternalKnowledgeBase(
        User $user,
        Conversation $conversation
    ): KnowledgeBase {
        $knowledgeBase = KnowledgeBase::create([
            'user_id' => $user->id,
            'conversation_id' => $conversation->id,
            'name' => 'Chat Attachments',
        ]);

        $conversation->knowledgeBases()->attach(
            $knowledgeBase->id
        );

        return $knowledgeBase;
    }

    private function attachmentUrl(
        Conversation $conversation
    ): string {
        return "/api/conversations/{$conversation->id}/knowledge-bases";
    }

    public function test_other_conversations_internal_knowledge_base_cannot_be_attached(): void
    {
        $user = User::factory()->create();

        $firstConversation = $this->createConversation($user);
        $secondConversation = $this->createConversation($user);

        $internal = $this->createInternalKnowledgeBase(
            $user,
            $firstConversation
        );

        $response = $this->actingAs($user)
            ->putJson(
                $this->attachmentUrl($secondConversation),
                [
                    'knowledge_base_ids' => [$internal->id],
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'knowledge_base_ids',
            ]);

        $this->assertDatabaseMissing(
            'conversation_knowledge_base',
            [
                'conversation_id' => $secondConversation->id,
                'knowledge_base_id' => $internal->id,
            ]
        );
    }

    public function test_empty_selection_preserves_internal_attachment(): void
    {
        $user = User::factory()->create();

        $conversation = $this->createConversation($user);

        $internal = $this->createInternalKnowledgeBase(
            $user,
            $conversation
        );

        $regular = $this->createRegularKnowledgeBase($user);

        $conversation->knowledgeBases()->attach($regular->id);

        $response = $this->actingAs($user)
            ->putJson(
                $this->attachmentUrl($conversation),
                [
                    'knowledge_base_ids' => [],
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath('knowledge_base_ids', []);

        $this->assertDatabaseHas(
            'conversation_knowledge_base',
            [
                'conversation_id' => $conversation->id,
                'knowledge_base_id' => $internal->id,
            ]
        );

        $this->assertDatabaseMissing(
            'conversation_knowledge_base',
            [
                'conversation_id' => $conversation->id,
                'knowledge_base_id' => $regular->id,
            ]
        );
    }

    public function test_show_hides_internal_knowledge_base_id(): void
    {
        $user = User::factory()->create();

        $conversation = $this->createConversation($user);

        $internal = $this->createInternalKnowledgeBase(
            $user,
            $conversation
        );

        $regular = $this->createRegularKnowledgeBase($user);

        $conversation->knowledgeBases()->attach($regular->id);

        $response = $this->actingAs($user)
            ->getJson(
                "/api/conversations/{$conversation->id}"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'knowledge_base_ids',
                [$regular->id]
            );

        $this->assertNotContains(
            $internal->id,
            $response->json('knowledge_base_ids')
        );
    }

    public function test_regular_selection_preserves_internal_attachment(): void
    {
        $user = User::factory()->create();

        $conversation = $this->createConversation($user);

        $internal = $this->createInternalKnowledgeBase(
            $user,
            $conversation
        );

        $regular = $this->createRegularKnowledgeBase($user);

        $response = $this->actingAs($user)
            ->putJson(
                $this->attachmentUrl($conversation),
                [
                    'knowledge_base_ids' => [$regular->id],
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'knowledge_base_ids',
                [$regular->id]
            );

        $this->assertDatabaseHas(
            'conversation_knowledge_base',
            [
                'conversation_id' => $conversation->id,
                'knowledge_base_id' => $internal->id,
            ]
        );

        $this->assertDatabaseHas(
            'conversation_knowledge_base',
            [
                'conversation_id' => $conversation->id,
                'knowledge_base_id' => $regular->id,
            ]
        );
    }

    public function test_internal_knowledge_base_cannot_be_submitted_as_manual_selection(): void
    {
        $user = User::factory()->create();

        $conversation = $this->createConversation($user);

        $internal = $this->createInternalKnowledgeBase(
            $user,
            $conversation
        );

        $response = $this->actingAs($user)
            ->putJson(
                $this->attachmentUrl($conversation),
                [
                    'knowledge_base_ids' => [$internal->id],
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'knowledge_base_ids',
            ]);

        $this->assertDatabaseHas(
            'conversation_knowledge_base',
            [
                'conversation_id' => $conversation->id,
                'knowledge_base_id' => $internal->id,
            ]
        );
    }
}
