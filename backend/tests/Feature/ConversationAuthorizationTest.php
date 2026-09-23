<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConversationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_conversations(): void
    {
        $response = $this->getJson(
            '/api/conversations'
        );

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_only_sees_their_own_conversations(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $conversationA = Conversation::create([
            'user_id' => $userA->id,
            'title' => 'User A Conversation',
        ]);

        $conversationB = Conversation::create([
            'user_id' => $userB->id,
            'title' => 'User B Conversation',
        ]);

        Sanctum::actingAs(
            $userA,
            ['*']
        );

        $response = $this->getJson(
            '/api/conversations'
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonFragment([
                'id' => $conversationA->id,
                'title' => 'User A Conversation',
            ])
            ->assertJsonMissing([
                'id' => $conversationB->id,
                'title' => 'User B Conversation',
            ]);
    }

    public function test_new_conversation_belongs_to_authenticated_user(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs(
            $user,
            ['*']
        );

        $response = $this->postJson(
            '/api/conversations',
            []
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'success',
                true
            );

        $conversationId =
            $response->json(
                'conversation.id'
            );

        $this->assertDatabaseHas(
            'conversations',
            [
                'id' => $conversationId,
                'user_id' => $user->id,
            ]
        );
    }

    public function test_user_cannot_open_another_users_conversation(): void
    {
        [$userA, $userB, $conversation] =
            $this->createConversationOwnedByFirstUser();

        Sanctum::actingAs(
            $userB,
            ['*']
        );

        $response = $this->getJson(
            "/api/conversations/{$conversation->id}"
        );

        $response->assertNotFound();
    }

    public function test_user_cannot_read_another_users_messages(): void
    {
        [$userA, $userB, $conversation] =
            $this->createConversationOwnedByFirstUser();

        Message::create([
            'conversation_id' =>
                $conversation->id,

            'role' => 'user',

            'content' =>
                'Private message from User A.',
        ]);

        Sanctum::actingAs(
            $userB,
            ['*']
        );

        $response = $this->getJson(
            "/api/conversations/{$conversation->id}/messages"
        );

        $response->assertNotFound();
    }

    public function test_user_cannot_rename_another_users_conversation(): void
    {
        [$userA, $userB, $conversation] =
            $this->createConversationOwnedByFirstUser();

        Sanctum::actingAs(
            $userB,
            ['*']
        );

        $response = $this->patchJson(
            "/api/conversations/{$conversation->id}",
            [
                'title' => 'Hacked title',
            ]
        );

        $response->assertNotFound();

        $this->assertDatabaseHas(
            'conversations',
            [
                'id' => $conversation->id,
                'title' =>
                    'Private Conversation',
            ]
        );
    }

    public function test_user_cannot_delete_another_users_conversation(): void
    {
        [$userA, $userB, $conversation] =
            $this->createConversationOwnedByFirstUser();

        Sanctum::actingAs(
            $userB,
            ['*']
        );

        $response = $this->deleteJson(
            "/api/conversations/{$conversation->id}"
        );

        $response->assertNotFound();

        $this->assertDatabaseHas(
            'conversations',
            [
                'id' => $conversation->id,
                'user_id' => $userA->id,
            ]
        );
    }

    public function test_user_cannot_send_message_to_another_users_conversation(): void
    {
        [$userA, $userB, $conversation] =
            $this->createConversationOwnedByFirstUser();

        Sanctum::actingAs(
            $userB,
            ['*']
        );

        $response = $this->postJson(
            "/api/conversations/{$conversation->id}/messages",
            [
                'message' =>
                    'Trying to access another user conversation.',
            ]
        );

        /*
         * Ownership is checked before the AI service is called,
         * so this test does not make an external Cloudflare request.
         */
        $response->assertNotFound();

        $this->assertDatabaseMissing(
            'messages',
            [
                'conversation_id' =>
                    $conversation->id,

                'content' =>
                    'Trying to access another user conversation.',
            ]
        );
    }

    public function test_user_cannot_regenerate_another_users_conversation(): void
    {
        [$userA, $userB, $conversation] =
            $this->createConversationOwnedByFirstUser();

        Message::create([
            'conversation_id' =>
                $conversation->id,

            'role' => 'user',

            'content' => 'Private prompt',
        ]);

        Sanctum::actingAs(
            $userB,
            ['*']
        );

        $response = $this->postJson(
            "/api/conversations/{$conversation->id}/regenerate"
        );

        /*
         * Again, ownership should fail before Cloudflare
         * is contacted.
         */
        $response->assertNotFound();
    }

    private function createConversationOwnedByFirstUser(): array
    {
        $userA =
            User::factory()->create();

        $userB =
            User::factory()->create();

        $conversation =
            Conversation::create([
                'user_id' =>
                    $userA->id,

                'title' =>
                    'Private Conversation',
            ]);

        return [
            $userA,
            $userB,
            $conversation,
        ];
    }
}