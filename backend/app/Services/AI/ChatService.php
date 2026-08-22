<?php

namespace App\Services\AI;

use App\Contracts\AIProviderInterface;

class ChatService
{
    public function __construct(
        private readonly AIProviderInterface $aiProvider
    ) {
    }

    public function sendMessage(string $message): string
    {
        $messages = [
            [
                'role' => 'system',
                'content' => (string) config(
                    'ai.system_prompt',
                    'You are a helpful AI assistant.'
                ),
            ],
            [
                'role' => 'user',
                'content' => $message,
            ],
        ];

        return $this->aiProvider->chat($messages);
    }
}