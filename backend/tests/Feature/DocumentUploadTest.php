<?php

namespace Tests\Feature;

use App\Models\KnowledgeBase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_upload_document(): void
    {
        $user = User::factory()->create();

        $knowledgeBase = KnowledgeBase::create([
            'user_id' => $user->id,
            'name' => 'Private Knowledge',
        ]);

        $file = UploadedFile::fake()
            ->createWithContent(
                'policy.txt',
                'Employees receive 25 paid leave days.'
            );

        $response = $this->postJson(
            "/api/knowledge-bases/{$knowledgeBase->id}/documents",
            [
                'file' => $file,
            ]
        );

        $response->assertUnauthorized();
    }

    public function test_user_can_upload_txt_document_to_their_knowledge_base(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();

        $knowledgeBase = KnowledgeBase::create([
            'user_id' => $user->id,
            'name' => 'Company Knowledge',
        ]);

        Sanctum::actingAs(
            $user,
            ['*']
        );

        $file = UploadedFile::fake()
            ->createWithContent(
                'company-policy.txt',
                'Employees receive 25 paid leave days each year.'
            )
            ->mimeType('text/plain');

        $response = $this->postJson(
            "/api/knowledge-bases/{$knowledgeBase->id}/documents",
            [
                'file' => $file,
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.knowledge_base_id',
                $knowledgeBase->id
            )
            ->assertJsonPath(
                'data.original_name',
                'company-policy.txt'
            )
            ->assertJsonPath(
                'data.status',
                'uploaded'
            );

        $this->assertDatabaseHas(
            'documents',
            [
                'knowledge_base_id' =>
                    $knowledgeBase->id,

                'original_name' =>
                    'company-policy.txt',

                'status' =>
                    'uploaded',
            ]
        );

        $storedPath = $response->json(
            'data.file_path'
        );

        Storage::disk('local')
            ->assertExists(
                $storedPath
            );
    }

    public function test_user_cannot_upload_document_to_another_users_knowledge_base(): void
    {
        Storage::fake('local');

        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $knowledgeBase = KnowledgeBase::create([
            'user_id' => $userA->id,
            'name' => 'User A Knowledge',
        ]);

        Sanctum::actingAs(
            $userB,
            ['*']
        );

        $file = UploadedFile::fake()
            ->createWithContent(
                'secret.txt',
                'Private information.'
            )
            ->mimeType('text/plain');

        $response = $this->postJson(
            "/api/knowledge-bases/{$knowledgeBase->id}/documents",
            [
                'file' => $file,
            ]
        );

        $response->assertNotFound();

        $this->assertDatabaseCount(
            'documents',
            0
        );
    }

    public function test_non_txt_document_is_rejected(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();

        $knowledgeBase = KnowledgeBase::create([
            'user_id' => $user->id,
            'name' => 'Test Knowledge',
        ]);

        Sanctum::actingAs(
            $user,
            ['*']
        );

        $file = UploadedFile::fake()
            ->create(
                'document.pdf',
                100,
                'application/pdf'
            );

        $response = $this->postJson(
            "/api/knowledge-bases/{$knowledgeBase->id}/documents",
            [
                'file' => $file,
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'file',
            ]);

        $this->assertDatabaseCount(
            'documents',
            0
        );
    }
}