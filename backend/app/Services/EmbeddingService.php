<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class EmbeddingService
{
    public function embed(string $text): array
    {
        $text = trim($text);

        if ($text === '') {
            throw new RuntimeException(
                'Cannot generate an embedding for empty text.'
            );
        }

        try {
            $response = Http::timeout(60)
                ->post(
                    rtrim(
                        config('services.ollama.base_url'),
                        '/'
                    ) . '/api/embed',
                    [
                        'model' => config(
                            'services.ollama.embedding_model'
                        ),
                        'input' => $text,
                    ]
                );
        } catch (ConnectionException $exception) {
            throw new RuntimeException(
                'Unable to connect to the Ollama embedding service.',
                0,
                $exception
            );
        }

        if ($response->failed()) {
            throw new RuntimeException(
                'Ollama embedding request failed: '
                . $response->body()
            );
        }

        $embeddings = $response->json('embeddings');

        if (
            !is_array($embeddings) ||
            !isset($embeddings[0]) ||
            !is_array($embeddings[0])
        ) {
            throw new RuntimeException(
                'Ollama returned an invalid embedding response.'
            );
        }

        $embedding = $embeddings[0];

        if (count($embedding) !== 768) {
            throw new RuntimeException(
                'Unexpected embedding dimension: '
                . count($embedding)
            );
        }

        return array_map(
            static fn ($value) => (float) $value,
            $embedding
        );
    }
}