<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'knowledge_base_id',
        'original_name',
        'stored_name',
        'mime_type',
        'file_path',
        'file_size',
        'status',
        'error_message',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(
            KnowledgeBase::class
        );
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(
            DocumentChunk::class
        )->orderBy('chunk_index');
    }
}