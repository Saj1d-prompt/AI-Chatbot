<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class EmbeddingService
{
    /**
     * Generate an embedding vector for the given text.
     *
     * @return array<int, float>
     */
    public function embed(string $text): array
    {
        $text = trim($text);

        if ($text === '') {
            throw new InvalidArgumentException(
                'Text cannot be empty when generating an embedding.'
            );
        }

        $baseUrl = rtrim(
            (string) config(
                'services.ollama.url',
                'http://127.0.0.1:11434'
            ),
            '/'
        );

        $model = (string) config(
            'services.ollama.embedding_model',
            'nomic-embed-text'
        );

        $timeout = (int) config(
            'services.ollama.timeout',
            60
        );

        $response = Http::baseUrl($baseUrl)
            ->acceptJson()
            ->timeout($timeout)
            ->post('/api/embed', [
                'model' => $model,
                'input' => $text,
            ]);

        if ($response->failed()) {
            $error = $response->json('error');

            throw new RuntimeException(
                'Ollama embedding request failed. '
                . 'HTTP status: '
                . $response->status()
                . (
                    $error
                        ? ' Error: ' . $error
                        : ''
                )
            );
        }

        $embedding = $response->json('embeddings.0');

        if (!is_array($embedding) || $embedding === []) {
            throw new RuntimeException(
                'Ollama returned an invalid embedding response.'
            );
        }

        return array_map(
            static fn ($value): float => (float) $value,
            $embedding
        );
    }
}
