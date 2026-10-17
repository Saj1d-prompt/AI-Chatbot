<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\KnowledgeBase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentListingTest extends TestCase
{
    use RefreshDatabase;

    private function createKnowledgeBase(User $user): KnowledgeBase
    {
        return KnowledgeBase::create([
            'user_id' => $user->id,
            'name' => 'Test Knowledge Base',
            'description' => 'Test documents',
        ]);
    }

    private function createDocument(
        KnowledgeBase $knowledgeBase,
        string $name
    ): Document {
        return Document::create([
            'knowledge_base_id' => $knowledgeBase->id,
            'original_name' => $name,
            'stored_name' => $name,
            'mime_type' => 'text/plain',
            'file_path' => 'documents/' . $name,
            'file_size' => 100,
            'status' => 'uploaded',
        ]);
    }

    public function test_unauthenticated_user_cannot_list_documents(): void
    {
        $user = User::factory()->create();

        $knowledgeBase = $this->createKnowledgeBase($user);

        $response = $this->getJson(
            "/api/knowledge-bases/{$knowledgeBase->id}/documents"
        );

        $response->assertUnauthorized();
    }

    public function test_owner_can_list_their_documents(): void
    {
        $user = User::factory()->create();

        $knowledgeBase = $this->createKnowledgeBase($user);

        $document = $this->createDocument(
            $knowledgeBase,
            'company-policy.txt'
        );

        $response = $this->actingAs($user)->getJson(
            "/api/knowledge-bases/{$knowledgeBase->id}/documents"
        );

        $response->assertOk();

        $response->assertJsonPath('success', true);

        $response->assertJsonCount(1, 'data');

        $response->assertJsonPath(
            'data.0.id',
            $document->id
        );

        $response->assertJsonPath(
            'data.0.original_name',
            'company-policy.txt'
        );

        $response->assertJsonPath(
            'data.0.status',
            'uploaded'
        );

        $response->assertJsonPath(
            'data.0.chunks_count',
            0
        );
    }

    public function test_user_cannot_list_another_users_documents(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $knowledgeBase = $this->createKnowledgeBase($owner);

        $this->createDocument(
            $knowledgeBase,
            'private-document.txt'
        );

        $response = $this->actingAs($otherUser)->getJson(
            "/api/knowledge-bases/{$knowledgeBase->id}/documents"
        );

        $response->assertNotFound();
    }

    public function test_document_listing_only_contains_requested_knowledge_base_documents(): void
    {
        $user = User::factory()->create();

        $firstKnowledgeBase = $this->createKnowledgeBase($user);

        $secondKnowledgeBase = $this->createKnowledgeBase($user);

        $firstDocument = $this->createDocument(
            $firstKnowledgeBase,
            'first-document.txt'
        );

        $secondDocument = $this->createDocument(
            $secondKnowledgeBase,
            'second-document.txt'
        );

        $response = $this->actingAs($user)->getJson(
            "/api/knowledge-bases/{$firstKnowledgeBase->id}/documents"
        );

        $response->assertOk();

        $response->assertJsonCount(1, 'data');

        $response->assertJsonFragment([
            'id' => $firstDocument->id,
            'original_name' => 'first-document.txt',
        ]);

        $response->assertJsonMissing([
            'id' => $secondDocument->id,
            'original_name' => 'second-document.txt',
        ]);
    }
}
