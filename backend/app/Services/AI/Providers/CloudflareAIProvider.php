<?php

namespace App\Services\AI\Providers;

use App\Contracts\AIProviderInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CloudflareAIProvider implements AIProviderInterface
{
    private string $accountId;

    private string $token;

    private string $model;

    private int $maxTokens;

    public function __construct()
    {
        $this->accountId = (string) config(
            'ai.providers.cloudflare.account_id'
        );

        $this->token = (string) config(
            'ai.providers.cloudflare.token'
        );

        $this->model = (string) config(
            'ai.providers.cloudflare.model'
        );

        $this->maxTokens = (int) config(
            'ai.max_tokens',
            1024
        );

        if (
            $this->accountId === '' ||
            $this->token === '' ||
            $this->model === ''
        ) {
            throw new RuntimeException(
                'Cloudflare AI configuration is incomplete.'
            );
        }
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function chat(array $messages): string
    {
        $url = sprintf(
            'https://api.cloudflare.com/client/v4/accounts/%s/ai/run/%s',
            $this->accountId,
            $this->model
        );

        $response = Http::withToken($this->token)
            ->acceptJson()
            ->asJson()
            ->timeout(60)
            ->connectTimeout(10)
            ->post($url, [
                'messages' => $messages,
                'max_tokens' => $this->maxTokens,
            ]);

        $response->throw();

        $data = $response->json();

        if (($data['success'] ?? false) !== true) {
            throw new RuntimeException(
                'Cloudflare AI reported an unsuccessful response.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Normalize Cloudflare Response
        |--------------------------------------------------------------------------
        */

        $reply = data_get(
            $data,
            'result.response'
        );

        if (
            ! is_string($reply) ||
            trim($reply) === ''
        ) {
            $reply = data_get(
                $data,
                'result.choices.0.message.content'
            );
        }

        if (
            ! is_string($reply) ||
            trim($reply) === ''
        ) {
            throw new RuntimeException(
                'Cloudflare AI returned no usable assistant response.'
            );
        }

        return trim($reply);
    }
}