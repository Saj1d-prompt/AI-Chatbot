<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentChunk extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'chunk_index',
        'content',
        'embedding',
        'page_number',
        'section_title',
        'token_count',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'chunk_index' => 'integer',
            'embedding' => 'array',
            'page_number' => 'integer',
            'token_count' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(
            Document::class
        );
    }

    public function messageSources(): HasMany
    {
        return $this->hasMany(
            MessageSource::class
        );
    }
}