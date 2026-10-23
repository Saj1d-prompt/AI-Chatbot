<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Document;
use App\Models\KnowledgeBase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationAttachmentStorageTest extends TestCase
{
    use RefreshDatabase;

    private function createConversation(
        User $user
    ): Conversation {
        return Conversation::create([
            'user_id' => $user->id,
            'title' => 'Attachment Test',
        ]);
    }

    private function createAttachmentKnowledgeBase(
        User $user,
        Conversation $conversation
    ): KnowledgeBase {
        return KnowledgeBase::create([
            'user_id' => $user->id,
            'conversation_id' => $conversation->id,
            'name' => 'Chat Attachments',
            'description' =>
                'Internal document storage for this conversation.',
        ]);
    }

    public function test_regular_knowledge_base_has_no_conversation_scope(): void
    {
        $user = User::factory()->create();

        $knowledgeBase = KnowledgeBase::create([
            'user_id' => $user->id,
            'name' => 'Company Policies',
        ]);

        $this->assertNull(
            $knowledgeBase->conversation_id
        );

        $this->assertFalse(
            $knowledgeBase->isChatAttachmentKnowledgeBase()
        );

        $this->assertNull(
            $knowledgeBase->chatConversation
        );
    }

    public function test_conversation_can_have_internal_attachment_knowledge_base(): void
    {
        $user = User::factory()->create();

        $conversation = $this->createConversation($user);

        $knowledgeBase = $this->createAttachmentKnowledgeBase(
            $user,
            $conversation
        );

        $this->assertTrue(
            $knowledgeBase->isChatAttachmentKnowledgeBase()
        );

        $this->assertEquals(
            $conversation->id,
            $knowledgeBase->chatConversation->id
        );

        $this->assertEquals(
            $knowledgeBase->id,
            $conversation->attachmentKnowledgeBase->id
        );

        $this->assertDatabaseHas('knowledge_bases', [
            'id' => $knowledgeBase->id,
            'user_id' => $user->id,
            'conversation_id' => $conversation->id,
        ]);
    }

    public function test_two_conversations_have_separate_attachment_storage(): void
    {
        $user = User::factory()->create();

        $firstConversation = $this->createConversation($user);
        $secondConversation = $this->createConversation($user);

        $firstKnowledgeBase = $this->createAttachmentKnowledgeBase(
            $user,
            $firstConversation
        );

        $secondKnowledgeBase = $this->createAttachmentKnowledgeBase(
            $user,
            $secondConversation
        );

        $this->assertNotEquals(
            $firstKnowledgeBase->id,
            $secondKnowledgeBase->id
        );

        $this->assertEquals(
            $firstKnowledgeBase->id,
            $firstConversation->attachmentKnowledgeBase->id
        );

        $this->assertEquals(
            $secondKnowledgeBase->id,
            $secondConversation->attachmentKnowledgeBase->id
        );
    }

    public function test_deleting_conversation_deletes_its_attachment_records(): void
    {
        $user = User::factory()->create();

        $conversation = $this->createConversation($user);

        $knowledgeBase = $this->createAttachmentKnowledgeBase(
            $user,
            $conversation
        );

        $document = Document::create([
            'knowledge_base_id' => $knowledgeBase->id,
            'original_name' => 'test.txt',
            'stored_name' => 'test.txt',
            'mime_type' => 'text/plain',
            'file_path' => 'documents/test.txt',
            'file_size' => 100,
            'status' => 'uploaded',
        ]);

        $conversation->delete();

        $this->assertDatabaseMissing('conversations', [
            'id' => $conversation->id,
        ]);

        $this->assertDatabaseMissing('knowledge_bases', [
            'id' => $knowledgeBase->id,
        ]);

        $this->assertDatabaseMissing('documents', [
            'id' => $document->id,
        ]);
    }
}
