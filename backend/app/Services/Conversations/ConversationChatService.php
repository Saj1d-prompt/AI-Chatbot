<?php

namespace App\Services\Conversations;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\AI\ChatService;
use Illuminate\Support\Str;

class ConversationChatService
{
    public function __construct(
        private readonly ChatService $chatService
    ) {
    }

    /**
     * Store the user's message, load recent context,
     * generate an AI response, and store that response.
     *
     * IMPORTANT:
     * Every message remains stored in the database.
     * Only the AI context is limited.
     *
     * @return array{
     *     user_message: Message,
     *     assistant_message: Message
     * }
     */
    public function sendMessage(
        Conversation $conversation,
        string $content
    ): array {
        /*
        |--------------------------------------------------------------------------
        | Store User Message
        |--------------------------------------------------------------------------
        */

        $userMessage = $conversation
            ->messages()
            ->create([
                'role' => 'user',
                'content' => $content,
            ]);

        /*
        |--------------------------------------------------------------------------
        | Create Initial Conversation Title
        |--------------------------------------------------------------------------
        */

        if (blank($conversation->title)) {
            $title = Str::of($content)
                ->replaceMatches('/\s+/', ' ')
                ->trim()
                ->limit(60)
                ->toString();

            $conversation->update([
                'title' => $title,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Load Recent AI Context
        |--------------------------------------------------------------------------
        |
        | This does NOT remove or ignore old messages from the database.
        |
        | Example:
        |
        | Database = 500 messages
        | AI_CONTEXT_MESSAGE_LIMIT = 12
        |
        | Database keeps 500.
        | Cloudflare receives the most recent 12.
        |
        */

        $contextLimit = max(
            1,
            (int) config(
                'ai.context_message_limit',
                12
            )
        );

        $history = $conversation
            ->messages()
            ->select([
                'id',
                'role',
                'content',
            ])
            ->orderByDesc('id')
            ->limit($contextLimit)
            ->get()
            ->reverse()
            ->values()
            ->map(function (Message $message) {
                return [
                    'role' => $message->role,
                    'content' => $message->content,
                ];
            })
            ->all();

        /*
        |--------------------------------------------------------------------------
        | Generate AI Reply
        |--------------------------------------------------------------------------
        */

        $reply = $this
            ->chatService
            ->generateReply($history);

        /*
        |--------------------------------------------------------------------------
        | Store Assistant Message
        |--------------------------------------------------------------------------
        */

        $assistantMessage = $conversation
            ->messages()
            ->create([
                'role' => 'assistant',
                'content' => $reply,
            ]);

        /*
        |--------------------------------------------------------------------------
        | Update Conversation Activity
        |--------------------------------------------------------------------------
        */

        $conversation->touch();

        return [
            'user_message' => $userMessage,
            'assistant_message' => $assistantMessage,
        ];
    }
}