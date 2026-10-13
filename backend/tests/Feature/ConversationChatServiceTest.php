<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use App\Services\AI\ChatService;
use App\Services\Conversations\ConversationChatService;
use App\Services\RAG\RagRetrievalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ConversationChatServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_without_attached_knowledge_bases_works_normally(): void
    {
        $user = User::factory()->create();

        $conversation = Conversation::create([
            'user_id' => $user->id,
            'title' => null,
        ]);

        $chatService = Mockery::mock(ChatService::class);

        $chatService->shouldReceive('generateReply')
            ->once()
            ->with(
                Mockery::on(fn (array $messages) =>
                    count($messages) === 1
                    && $messages[0]['role'] === 'user'
                    && $messages[0]['content'] === 'Hello!'
                ),
                null
            )
            ->andReturn('Hello! How can I help you?');

        $ragService = Mockery::mock(RagRetrievalService::class);

        $ragService->shouldNotReceive('retrieveFromKnowledgeBases');

        $service = new ConversationChatService(
            $chatService,
            $ragService
        );

        $result = $service->sendMessage($conversation, 'Hello!');

        $this->assertSame('user', $result['user_message']->role);
        $this->assertSame('Hello!', $result['user_message']->content);

        $this->assertSame('assistant', $result['assistant_message']->role);
        $this->assertSame(
            'Hello! How can I help you?',
            $result['assistant_message']->content
        );

        $this->assertDatabaseHas('messages', [
            'id' => $result['user_message']->id,
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Hello!',
        ]);

        $this->assertDatabaseHas('messages', [
            'id' => $result['assistant_message']->id,
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Hello! How can I help you?',
        ]);

        $this->assertSame(0, $result['assistant_message']->sources()->count());
    }
}