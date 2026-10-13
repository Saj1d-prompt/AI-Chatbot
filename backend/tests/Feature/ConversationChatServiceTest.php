<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\KnowledgeBase;
use App\Models\User;
use App\Services\AI\ChatService;
use App\Services\Conversations\ConversationChatService;
use App\Services\RAG\RagRetrievalService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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

        $this->assertSame(
            0,
            $result['assistant_message']->sources()->count()
        );
    }

    public function test_chat_uses_retrieved_knowledge_and_saves_sources(): void
    {
        $user = User::factory()->create();

        $conversation = Conversation::create([
            'user_id' => $user->id,
            'title' => null,
        ]);

        $knowledgeBase = KnowledgeBase::create([
            'user_id' => $user->id,
            'name' => 'Company Policies',
            'description' => 'Company policy documents',
        ]);

        $conversation->knowledgeBases()->attach($knowledgeBase->id);

        $document = Document::create([
            'knowledge_base_id' => $knowledgeBase->id,
            'original_name' => 'leave-policy.txt',
            'stored_name' => 'leave-policy.txt',
            'mime_type' => 'text/plain',
            'file_path' => 'documents/leave-policy.txt',
            'file_size' => 100,
            'status' => 'completed',
        ]);

        $chunk = DocumentChunk::create([
            'document_id' => $document->id,
            'chunk_index' => 0,
            'content' => 'Employees receive 20 days of annual leave.',
            'embedding' => [0.1, 0.2, 0.3],
        ]);

        // Simulate the similarity score normally added by vector search.
        $chunk->setAttribute('similarity_score', 0.92);

        $question = 'How many annual leave days do employees receive?';
        $expectedAnswer = 'Employees receive 20 days of annual leave.';

        $chatService = Mockery::mock(ChatService::class);

        $chatService->shouldReceive('generateReply')
            ->once()
            ->with(
                Mockery::on(fn (array $messages) =>
                    count($messages) === 1
                    && $messages[0]['role'] === 'user'
                    && $messages[0]['content'] === $question
                ),
                Mockery::on(fn (?string $context) =>
                    $context !== null
                    && str_contains(
                        $context,
                        'Employees receive 20 days of annual leave.'
                    )
                    && str_contains($context, 'leave-policy.txt')
                )
            )
            ->andReturn($expectedAnswer);

        $ragService = Mockery::mock(RagRetrievalService::class);

        $ragService->shouldReceive('retrieveFromKnowledgeBases')
            ->once()
            ->with(
                $question,
                [$knowledgeBase->id],
                Mockery::type('int'),
                Mockery::type('float')
            )
            ->andReturn(new EloquentCollection([$chunk]));

        $service = new ConversationChatService(
            $chatService,
            $ragService
        );

        $result = $service->sendMessage($conversation, $question);

        $this->assertSame(
            $expectedAnswer,
            $result['assistant_message']->content
        );

        $this->assertDatabaseHas('message_sources', [
            'message_id' => $result['assistant_message']->id,
            'document_chunk_id' => $chunk->id,
            'rank' => 1,
        ]);

        $source = $result['assistant_message']->sources()->first();

        $this->assertNotNull($source);
        $this->assertEquals(0.92, (float) $source->score);
    }
}