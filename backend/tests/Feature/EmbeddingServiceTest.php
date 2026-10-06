<?php

namespace Tests\Feature;

use App\Services\EmbeddingService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class EmbeddingServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.ollama.url' => 'http://ollama.test',
            'services.ollama.embedding_model' => 'nomic-embed-text',
            'services.ollama.timeout' => 60,
        ]);
    }

    public function test_it_generates_an_embedding(): void
    {
        Http::fake([
            'http://ollama.test/api/embed' => Http::response([
                'model' => 'nomic-embed-text',
                'embeddings' => [
                    [
                        0.1,
                        -0.2,
                        0.3,
                        0.4,
                    ],
                ],
            ], 200),
        ]);

        $service = app(EmbeddingService::class);

        $embedding = $service->embed(
            'Laravel Sanctum provides authentication for SPA applications.'
        );

        $this->assertSame(
            [
                0.1,
                -0.2,
                0.3,
                0.4,
            ],
            $embedding
        );

        Http::assertSent(
            function (Request $request): bool {
                return $request->url() ===
                    'http://ollama.test/api/embed'
                    && $request->method() === 'POST'
                    && $request->data()['model'] ===
                        'nomic-embed-text'
                    && $request->data()['input'] ===
                        'Laravel Sanctum provides authentication for SPA applications.';
            }
        );
    }

    public function test_it_rejects_empty_text(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Text cannot be empty when generating an embedding.'
        );

        $service = app(EmbeddingService::class);

        $service->embed('   ');
    }

    public function test_it_throws_when_ollama_returns_an_error(): void
    {
        Http::fake([
            'http://ollama.test/api/embed' => Http::response([
                'error' => 'model not found',
            ], 404),
        ]);

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Ollama embedding request failed.'
        );

        $service = app(EmbeddingService::class);

        $service->embed(
            'Laravel Sanctum authentication.'
        );
    }

    public function test_it_rejects_an_invalid_ollama_response(): void
    {
        Http::fake([
            'http://ollama.test/api/embed' => Http::response([
                'model' => 'nomic-embed-text',
            ], 200),
        ]);

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Ollama returned an invalid embedding response.'
        );

        $service = app(EmbeddingService::class);

        $service->embed(
            'Laravel Sanctum authentication.'
        );
    }
}