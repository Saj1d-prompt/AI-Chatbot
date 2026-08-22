<?php

namespace App\Contracts;

interface AIProviderInterface
{
    /**
     * Send conversation messages to the AI provider.
     *
     * @param array<int, array{role: string, content: string}> $messages
     */
    public function chat(array $messages): string;
}