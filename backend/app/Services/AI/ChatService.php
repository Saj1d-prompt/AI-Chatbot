<?php

namespace App\Services\AI;

use App\Contracts\AIProviderInterface;

class ChatService
{
    public function __construct(
        private readonly AIProviderInterface $aiProvider
    ) {
    }

    /**
     * Send one standalone message without conversation persistence.
     */
    public function sendMessage(string $message): string
    {
        return $this->generateReply([
            [
                'role' => 'user',
                'content' => $message,
            ],
        ]);
    }

    /**
     * Generate an AI reply using the supplied conversation context.
     *
     * @param array<int, array{role: string, content: string}> $conversationMessages
     */
    public function generateReply(
        array $conversationMessages
    ): string {
        $messages = [
            [
                'role' => 'system',
                'content' => (string) config(
                    'ai.system_prompt',
                    'You are a helpful AI assistant.'
                ),
            ],

            ...$conversationMessages,
        ];

        return $this->aiProvider->chat($messages);
    }
}