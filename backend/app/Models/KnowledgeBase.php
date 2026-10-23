<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeBase extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'conversation_id',
    ];

    /**
     * The user who owns this knowledge base.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Documents stored in this knowledge base.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Conversations that use this knowledge base.
     *
     * Used for reusable knowledge bases and
     * for the existing RAG retrieval pipeline.
     */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(
            Conversation::class
        )->withTimestamps();
    }

    /**
     * The specific conversation that owns this
     * internal chat-attachment knowledge base.
     *
     * NULL for ordinary reusable knowledge bases.
     */
    public function chatConversation(): BelongsTo
    {
        return $this->belongsTo(
            Conversation::class,
            'conversation_id'
        );
    }

    /**
     * Whether this is an internal knowledge base
     * for a single conversation.
     */
    public function isChatAttachmentKnowledgeBase(): bool
    {
        return $this->conversation_id !== null;
    }
}
