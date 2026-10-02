<?php

namespace Tests\Feature;

use App\Services\RAG\TextChunkingService;
use InvalidArgumentException;
use Tests\TestCase;

class TextChunkingServiceTest extends TestCase
{
    public function test_short_text_creates_single_chunk(): void
    {
        config([
            'rag.chunk_size' => 10,
            'rag.chunk_overlap' => 2,
        ]);

        $service = app(
            TextChunkingService::class
        );

        $chunks = $service->chunk(
            'Laravel Sanctum provides secure SPA authentication.'
        );

        $this->assertCount(
            1,
            $chunks
        );

        $this->assertSame(
            0,
            $chunks[0]['chunk_index']
        );

        $this->assertSame(
            'Laravel Sanctum provides secure SPA authentication.',
            $chunks[0]['content']
        );

        $this->assertSame(
            6,
            $chunks[0]['token_count']
        );
    }

    public function test_long_text_creates_overlapping_chunks(): void
    {
        config([
            'rag.chunk_size' => 5,
            'rag.chunk_overlap' => 2,
        ]);

        $service = app(
            TextChunkingService::class
        );

        $chunks = $service->chunk(
            'one two three four five six seven eight nine ten'
        );

        $this->assertCount(
            3,
            $chunks
        );

        $this->assertSame(
            'one two three four five',
            $chunks[0]['content']
        );

        $this->assertSame(
            'four five six seven eight',
            $chunks[1]['content']
        );

        $this->assertSame(
            'seven eight nine ten',
            $chunks[2]['content']
        );

        $this->assertSame(
            0,
            $chunks[0]['chunk_index']
        );

        $this->assertSame(
            1,
            $chunks[1]['chunk_index']
        );

        $this->assertSame(
            2,
            $chunks[2]['chunk_index']
        );
    }

    public function test_empty_text_returns_no_chunks(): void
    {
        config([
            'rag.chunk_size' => 300,
            'rag.chunk_overlap' => 50,
        ]);

        $service = app(
            TextChunkingService::class
        );

        $chunks = $service->chunk(
            "   \n\n   "
        );

        $this->assertSame(
            [],
            $chunks
        );
    }

    public function test_overlap_cannot_be_equal_to_or_larger_than_chunk_size(): void
    {
        config([
            'rag.chunk_size' => 50,
            'rag.chunk_overlap' => 50,
        ]);

        $service = app(
            TextChunkingService::class
        );

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'RAG chunk overlap must be smaller than the chunk size.'
        );

        $service->chunk(
            'Some document text.'
        );
    }
}