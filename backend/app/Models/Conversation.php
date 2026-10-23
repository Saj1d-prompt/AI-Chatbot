<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
    ];

    /**
     * Owner of this conversation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Messages belonging to this conversation.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * All knowledge bases enabled for this conversation.
     *
     * Includes reusable knowledge bases and the internal
     * attachment knowledge base once it is connected.
     */
    public function knowledgeBases(): BelongsToMany
    {
        return $this->belongsToMany(
            KnowledgeBase::class
        )->withTimestamps();
    }

    /**
     * Internal knowledge base that stores documents
     * uploaded directly within this conversation.
     */
    public function attachmentKnowledgeBase(): HasOne
    {
        return $this->hasOne(
            KnowledgeBase::class,
            'conversation_id'
        );
    }
}
