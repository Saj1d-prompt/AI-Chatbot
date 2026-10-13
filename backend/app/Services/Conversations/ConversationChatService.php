<?php

namespace App\Services\Conversations;

use App\Models\Conversation;
use App\Models\DocumentChunk;
use App\Models\Message;
use App\Services\AI\ChatService;
use App\Services\RAG\RagRetrievalService;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ConversationChatService
{
    public function __construct(
        private readonly ChatService $chatService,
        private readonly RagRetrievalService $ragRetrievalService,
    ) {}

    /**
     * Store a user message and generate its AI response.
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
        $userMessage = $conversation->messages()->create([
            'role' => 'user',
            'content' => $content,
        ]);

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

        $history = $this->buildContext(
            $conversation,
            $userMessage->id
        );

        // Retrieve context from the conversation's attached knowledge bases.
        [$ragContext, $chunks] = $this->retrieveKnowledge(
            $conversation,
            $content
        );

        $reply = $this->chatService->generateReply(
            $history,
            $ragContext
        );

        $assistantMessage = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $reply,
        ]);

        // Save source references only after the assistant reply is created.
        $this->saveSources($assistantMessage, $chunks);

        $conversation->touch();

        return [
            'user_message' => $userMessage,
            'assistant_message' => $assistantMessage,
        ];
    }

    /**
     * Regenerate the latest AI response.
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
        $latestMessage = $conversation->messages()
            ->orderByDesc('id')
            ->first();

        if (! $latestMessage) {
            throw new DomainException(
                'This conversation does not contain any messages to regenerate.'
            );
        }

        $existingAssistantMessage = null;

        if ($latestMessage->role === 'user') {
            // Retry a user message whose previous AI call failed.
            $userMessage = $latestMessage;
        } elseif ($latestMessage->role === 'assistant') {
            $existingAssistantMessage = $latestMessage;

            $userMessage = $conversation->messages()
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

        // Exclude the existing assistant answer from the new AI context.
        $history = $this->buildContext(
            $conversation,
            $userMessage->id
        );

        [$ragContext, $chunks] = $this->retrieveKnowledge(
            $conversation,
            $userMessage->content
        );

        $reply = $this->chatService->generateReply(
            $history,
            $ragContext
        );

        if ($existingAssistantMessage) {
            $existingAssistantMessage->update([
                'content' => $reply,
                'token_usage' => null,
            ]);

            $assistantMessage = $existingAssistantMessage->fresh();

            // Remove old references before storing the newly retrieved ones.
            $assistantMessage->sources()->delete();

            $mode = 'replaced';
        } else {
            $assistantMessage = $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $reply,
            ]);

            $mode = 'created';
        }

        $this->saveSources($assistantMessage, $chunks);

        $conversation->touch();

        return [
            'mode' => $mode,
            'user_message' => $userMessage,
            'assistant_message' => $assistantMessage,
        ];
    }

    /**
     * Retrieve relevant chunks from knowledge bases attached to this
     * conversation and owned by its user.
     *
     * @return array{0: ?string, 1: Collection<int, DocumentChunk>}
     */
    private function retrieveKnowledge(
        Conversation $conversation,
        string $question
    ): array {
        $knowledgeBaseIds = $conversation->knowledgeBases()
            ->where(
                'knowledge_bases.user_id',
                $conversation->user_id
            )
            ->pluck('knowledge_bases.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($knowledgeBaseIds === []) {
            return [null, collect()];
        }

        $limit = max(
            1,
            (int) config('rag.retrieval_limit', 5)
        );

        $minimumScore = (float) config(
            'rag.minimum_score',
            0.0
        );

        $chunks = $this->ragRetrievalService
            ->retrieveFromKnowledgeBases(
                question: $question,
                knowledgeBaseIds: $knowledgeBaseIds,
                limit: $limit,
                minimumScore: $minimumScore,
            );

        if ($chunks->isEmpty()) {
            return [null, collect()];
        }

        $contextParts = [];

        foreach ($chunks as $index => $chunk) {
            $document = $chunk->document;

            // Support common filename attributes without assuming a
            // particular document filename column exists.
            $documentName = $document?->getAttribute('original_name')
                ?? $document?->getAttribute('file_name')
                ?? $document?->getAttribute('name')
                ?? 'Document';

            $contextParts[] = sprintf(
                "[Source %d: %s]\n%s",
                $index + 1,
                $documentName,
                $chunk->content
            );
        }

        return [
            implode("\n\n---\n\n", $contextParts),
            $chunks,
        ];
    }

    /**
     * Persist source references for an assistant message.
     *
     * @param iterable<int, DocumentChunk> $chunks
     */
    private function saveSources(
        Message $assistantMessage,
        iterable $chunks
    ): void {
        foreach ($chunks as $index => $chunk) {
            $assistantMessage->sources()->create([
                'document_chunk_id' => $chunk->id,
                'score' => (float) $chunk->similarity_score,
                'rank' => $index + 1,
            ]);
        }
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
            (int) config('ai.context_message_limit', 12)
        );

        return $conversation->messages()
            ->select([
                'id',
                'role',
                'content',
            ])
            ->where('id', '<=', $endingMessageId)
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