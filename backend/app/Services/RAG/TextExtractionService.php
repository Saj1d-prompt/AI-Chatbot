<?php

namespace App\Services\RAG;

use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class TextExtractionService
{
    /**
     * Extract readable text from a document.
     *
     * Currently supported:
     * - TXT
     */
    public function extract(Document $document): string
    {
        $this->ensureFileExists($document);

        $extension = strtolower(
            pathinfo(
                $document->stored_name,
                PATHINFO_EXTENSION
            )
        );

        return match ($extension) {
            'txt' => $this->extractTxt($document),

            default => throw new RuntimeException(
                "Unsupported document type: {$extension}"
            ),
        };
    }

    /**
     * Extract text from a plain TXT file.
     */
    private function extractTxt(
        Document $document
    ): string {
        $contents = Storage::disk('local')
            ->get($document->file_path);

        /*
         * Remove UTF-8 BOM if present.
         */
        $contents = preg_replace(
            '/^\xEF\xBB\xBF/',
            '',
            $contents
        ) ?? $contents;

        /*
         * Normalize Windows/Mac line endings.
         *
         * \r\n → \n
         * \r   → \n
         */
        $contents = preg_replace(
            "/\r\n|\r/",
            "\n",
            $contents
        ) ?? $contents;

        $contents = trim($contents);

        if ($contents === '') {
            throw new RuntimeException(
                'The document contains no readable text.'
            );
        }

        return $contents;
    }

    /**
     * Ensure the physical document still exists.
     */
    private function ensureFileExists(
        Document $document
    ): void {
        if (
            !Storage::disk('local')
                ->exists($document->file_path)
        ) {
            throw new RuntimeException(
                'The document file could not be found.'
            );
        }
    }
}