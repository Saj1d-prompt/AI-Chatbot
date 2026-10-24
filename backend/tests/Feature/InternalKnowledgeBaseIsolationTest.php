<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\KnowledgeBase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalKnowledgeBaseIsolationTest extends TestCase
{
    use RefreshDatabase;

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
            'name' => 'Internal Chat Attachments',
            'description' => 'Private conversation files',
        ]);
    }

    public function test_regular_list_does_not_expose_internal_knowledge_bases(): void
    {
        $user = User::factory()->create();

        $regular = KnowledgeBase::create([
            'user_id' => $user->id,
            'name' => 'Company Policies',
        ]);

        $internal = $this->createInternalKnowledgeBase($user);

        $response = $this->actingAs($user)
            ->getJson('/api/knowledge-bases');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');

        $response->assertJsonPath(
            'data.0.id',
            $regular->id
        );

        $response->assertJsonMissing([
            'id' => $internal->id,
        ]);
    }

    public function test_internal_knowledge_base_cannot_be_opened_through_regular_api(): void
    {
        $user = User::factory()->create();

        $internal = $this->createInternalKnowledgeBase($user);

        $this->actingAs($user)
            ->getJson("/api/knowledge-bases/{$internal->id}")
            ->assertNotFound();
    }

    public function test_internal_knowledge_base_cannot_be_renamed_through_regular_api(): void
    {
        $user = User::factory()->create();

        $internal = $this->createInternalKnowledgeBase($user);

        $this->actingAs($user)
            ->patchJson(
                "/api/knowledge-bases/{$internal->id}",
                ['name' => 'Renamed Knowledge Base']
            )
            ->assertNotFound();

        $this->assertDatabaseHas('knowledge_bases', [
            'id' => $internal->id,
            'name' => 'Internal Chat Attachments',
        ]);
    }

    public function test_internal_knowledge_base_cannot_be_deleted_through_regular_api(): void
    {
        $user = User::factory()->create();

        $internal = $this->createInternalKnowledgeBase($user);

        $this->actingAs($user)
            ->deleteJson("/api/knowledge-bases/{$internal->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('knowledge_bases', [
            'id' => $internal->id,
        ]);
    }
}
