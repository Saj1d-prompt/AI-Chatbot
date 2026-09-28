<?php

namespace Tests\Feature;

use App\Models\KnowledgeBase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KnowledgeBaseAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_knowledge_bases(): void
    {
        $response = $this->getJson(
            '/api/knowledge-bases'
        );

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_only_sees_their_own_knowledge_bases(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $knowledgeBaseA = KnowledgeBase::create([
            'user_id' => $userA->id,
            'name' => 'User A Knowledge',
            'description' => 'Private to User A',
        ]);

        $knowledgeBaseB = KnowledgeBase::create([
            'user_id' => $userB->id,
            'name' => 'User B Knowledge',
            'description' => 'Private to User B',
        ]);

        Sanctum::actingAs(
            $userA,
            ['*']
        );

        $response = $this->getJson(
            '/api/knowledge-bases'
        );

        $response
            ->assertOk()
            ->assertJsonFragment([
                'id' => $knowledgeBaseA->id,
                'name' => 'User A Knowledge',
            ])
            ->assertJsonMissing([
                'id' => $knowledgeBaseB->id,
                'name' => 'User B Knowledge',
            ]);
    }

    public function test_new_knowledge_base_belongs_to_authenticated_user(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs(
            $user,
            ['*']
        );

        $response = $this->postJson(
            '/api/knowledge-bases',
            [
                'name' => 'Laravel Knowledge',
                'description' => 'Laravel documentation.',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.user_id',
                $user->id
            )
            ->assertJsonPath(
                'data.name',
                'Laravel Knowledge'
            );

        $this->assertDatabaseHas(
            'knowledge_bases',
            [
                'user_id' => $user->id,
                'name' => 'Laravel Knowledge',
            ]
        );
    }

    public function test_user_cannot_open_another_users_knowledge_base(): void
    {
        [$userA, $userB, $knowledgeBase] =
            $this->createKnowledgeBaseForUserA();

        Sanctum::actingAs(
            $userB,
            ['*']
        );

        $response = $this->getJson(
            "/api/knowledge-bases/{$knowledgeBase->id}"
        );

        $response->assertNotFound();
    }

    public function test_user_cannot_update_another_users_knowledge_base(): void
    {
        [$userA, $userB, $knowledgeBase] =
            $this->createKnowledgeBaseForUserA();

        Sanctum::actingAs(
            $userB,
            ['*']
        );

        $response = $this->patchJson(
            "/api/knowledge-bases/{$knowledgeBase->id}",
            [
                'name' => 'Stolen Knowledge Base',
            ]
        );

        $response->assertNotFound();

        $this->assertDatabaseHas(
            'knowledge_bases',
            [
                'id' => $knowledgeBase->id,
                'name' => 'User A Knowledge',
            ]
        );
    }

    public function test_user_cannot_delete_another_users_knowledge_base(): void
    {
        [$userA, $userB, $knowledgeBase] =
            $this->createKnowledgeBaseForUserA();

        Sanctum::actingAs(
            $userB,
            ['*']
        );

        $response = $this->deleteJson(
            "/api/knowledge-bases/{$knowledgeBase->id}"
        );

        $response->assertNotFound();

        $this->assertDatabaseHas(
            'knowledge_bases',
            [
                'id' => $knowledgeBase->id,
            ]
        );
    }

    private function createKnowledgeBaseForUserA(): array
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $knowledgeBase = KnowledgeBase::create([
            'user_id' => $userA->id,
            'name' => 'User A Knowledge',
            'description' => 'Private knowledge base.',
        ]);

        return [
            $userA,
            $userB,
            $knowledgeBase,
        ];
    }
}