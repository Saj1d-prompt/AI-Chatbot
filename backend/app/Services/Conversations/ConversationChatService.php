<?php

namespace App\Services\Conversations;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\AI\ChatService;
use DomainException;
use Illuminate\Support\Str;

class ConversationChatService
{
    public function __construct(
        private readonly ChatService $chatService
    ) {
    }

    /**
     * Store a new user message and generate its AI response.
     *
     * Every message remains stored in MySQL.
     * Only a limited recent context is sent to the AI.
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
        | Generate Initial Conversation Title
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
        | Build Context Ending At This User Message
        |--------------------------------------------------------------------------
        */

        $history = $this->buildContext(
            $conversation,
            $userMessage->id
        );

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

        $conversation->touch();

        return [
            'user_message' => $userMessage,
            'assistant_message' => $assistantMessage,
        ];
    }

    /**
     * Regenerate the latest AI response.
     *
     * If the latest stored message is a user message, this acts as a retry.
     * If the latest stored message is an assistant message, that assistant
     * response is replaced instead of creating a duplicate user message.
     *
     * @return array{
     *     mode: string,
     *     user_message: Message,
     *     assistant_message: Message
     * }
     */
    public function regenerateLatestResponse(
        Conversation $conversation
    ): array {
        $latestMessage = $conversation
            ->messages()
            ->orderByDesc('id')
            ->first();

        if (! $latestMessage) {
            throw new DomainException(
                'This conversation does not contain any messages to regenerate.'
            );
        }

        $existingAssistantMessage = null;

        /*
        |--------------------------------------------------------------------------
        | Determine Which User Message We Are Answering
        |--------------------------------------------------------------------------
        */

        if ($latestMessage->role === 'user') {
            /*
             * The previous AI call may have failed.
             * The user message already exists, so retry it.
             */

            $userMessage = $latestMessage;
        } elseif ($latestMessage->role === 'assistant') {
            /*
             * Regenerate the latest successful response.
             */

            $existingAssistantMessage = $latestMessage;

            $userMessage = $conversation
                ->messages()
                ->where('id', '<', $latestMessage->id)
                ->where('role', 'user')
                ->orderByDesc('id')
                ->first();

            if (! $userMessage) {
                throw new DomainException(
                    'No user message was found for this assistant response.'
                );
            }
        } else {
            throw new DomainException(
                'The latest conversation message cannot be regenerated.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Build Context Up To The Target User Message
        |--------------------------------------------------------------------------
        |
        | Notice that the previous assistant response is excluded.
        |
        */

        $history = $this->buildContext(
            $conversation,
            $userMessage->id
        );

        /*
        |--------------------------------------------------------------------------
        | Generate Fresh Response
        |--------------------------------------------------------------------------
        */

        $reply = $this
            ->chatService
            ->generateReply($history);

        /*
        |--------------------------------------------------------------------------
        | Replace Existing Assistant Or Create Missing One
        |--------------------------------------------------------------------------
        */

        if ($existingAssistantMessage) {
            $existingAssistantMessage->update([
                'content' => $reply,
                'token_usage' => null,
            ]);

            $assistantMessage =
                $existingAssistantMessage->fresh();

            $mode = 'replaced';
        } else {
            $assistantMessage = $conversation
                ->messages()
                ->create([
                    'role' => 'assistant',
                    'content' => $reply,
                ]);

            $mode = 'created';
        }

        $conversation->touch();

        return [
            'mode' => $mode,
            'user_message' => $userMessage,
            'assistant_message' => $assistantMessage,
        ];
    }

    /**
     * Build the recent AI context ending at a particular message.
     *
     * @return array<int, array{role: string, content: string}>
     */
    private function buildContext(
        Conversation $conversation,
        int $endingMessageId
    ): array {
        $contextLimit = max(
            1,
            (int) config(
                'ai.context_message_limit',
                12
            )
        );

        return $conversation
            ->messages()
            ->select([
                'id',
                'role',
                'content',
            ])
            ->where(
                'id',
                '<=',
                $endingMessageId
            )
            ->whereIn('role', [
                'user',
                'assistant',
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
    }
}