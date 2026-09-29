<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Services\RAG\TextExtractionService;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class TextExtractionServiceTest extends TestCase
{
    public function test_it_extracts_text_from_txt_document(): void
    {
        Storage::fake('local');

        $path = 'rag/test/company-policy.txt';

        Storage::disk('local')->put(
            $path,
            "Employees receive 25 paid leave days.\r\n"
            . "Remote work is allowed three days per week."
        );

        $document = new Document([
            'original_name' =>
                'company-policy.txt',

            'stored_name' =>
                'company-policy.txt',

            'mime_type' =>
                'text/plain',

            'file_path' =>
                $path,

            'file_size' =>
                100,

            'status' =>
                'uploaded',
        ]);

        $service = app(
            TextExtractionService::class
        );

        $text = $service->extract(
            $document
        );

        $this->assertSame(
            "Employees receive 25 paid leave days.\n"
            . "Remote work is allowed three days per week.",
            $text
        );
    }

    public function test_it_removes_utf8_bom_from_txt_document(): void
    {
        Storage::fake('local');

        $path = 'rag/test/bom-file.txt';

        Storage::disk('local')->put(
            $path,
            "\xEF\xBB\xBF"
            . "This document begins with a UTF-8 BOM."
        );

        $document = new Document([
            'original_name' =>
                'bom-file.txt',

            'stored_name' =>
                'bom-file.txt',

            'mime_type' =>
                'text/plain',

            'file_path' =>
                $path,

            'file_size' =>
                100,

            'status' =>
                'uploaded',
        ]);

        $service = app(
            TextExtractionService::class
        );

        $text = $service->extract(
            $document
        );

        $this->assertSame(
            'This document begins with a UTF-8 BOM.',
            $text
        );
    }

    public function test_it_rejects_empty_txt_document(): void
    {
        Storage::fake('local');

        $path = 'rag/test/empty.txt';

        Storage::disk('local')->put(
            $path,
            "   \n\n   "
        );

        $document = new Document([
            'original_name' =>
                'empty.txt',

            'stored_name' =>
                'empty.txt',

            'mime_type' =>
                'text/plain',

            'file_path' =>
                $path,

            'file_size' =>
                10,

            'status' =>
                'uploaded',
        ]);

        $service = app(
            TextExtractionService::class
        );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'The document contains no readable text.'
        );

        $service->extract(
            $document
        );
    }

    public function test_it_fails_when_physical_file_does_not_exist(): void
    {
        Storage::fake('local');

        $document = new Document([
            'original_name' =>
                'missing.txt',

            'stored_name' =>
                'missing.txt',

            'mime_type' =>
                'text/plain',

            'file_path' =>
                'rag/test/missing.txt',

            'file_size' =>
                100,

            'status' =>
                'uploaded',
        ]);

        $service = app(
            TextExtractionService::class
        );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'The document file could not be found.'
        );

        $service->extract(
            $document
        );
    }
}