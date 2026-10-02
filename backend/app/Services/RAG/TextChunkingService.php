<?php

namespace App\Services\RAG;

use InvalidArgumentException;

class TextChunkingService
{
    /**
     * Split document text into overlapping chunks.
     */
    public function chunk(string $text): array
    {
        $chunkSize = (int) config(
            'rag.chunk_size',
            300
        );

        $chunkOverlap = (int) config(
            'rag.chunk_overlap',
            50
        );

        $this->validateConfiguration(
            $chunkSize,
            $chunkOverlap
        );

        $text = trim($text);

        if ($text === '') {
            return [];
        }

        /*
         * Split using Unicode-aware whitespace.
         *
         * This works better than str_word_count()
         * because our system may later contain
         * multilingual text such as Bangla.
         */
        $words = preg_split(
            '/\s+/u',
            $text,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        if (
            $words === false ||
            count($words) === 0
        ) {
            return [];
        }

        $chunks = [];

        $totalWords = count($words);

        $start = 0;
        $chunkIndex = 0;

        while ($start < $totalWords) {
            $chunkWords = array_slice(
                $words,
                $start,
                $chunkSize
            );

            if (count($chunkWords) === 0) {
                break;
            }

            $content = implode(
                ' ',
                $chunkWords
            );

            $chunks[] = [
                'chunk_index' =>
                    $chunkIndex,

                'content' =>
                    $content,

                /*
                 * For now this is an approximate
                 * token count based on whitespace
                 * units.
                 *
                 * Later the embedding stage can use
                 * model-aware token information.
                 */
                'token_count' =>
                    count($chunkWords),
            ];

            /*
             * Stop if this was the final chunk.
             */
            if (
                $start + $chunkSize >=
                $totalWords
            ) {
                break;
            }

            /*
             * Example:
             *
             * chunk size = 300
             * overlap = 50
             *
             * next start:
             * 300 - 50 = 250
             */
            $start +=
                $chunkSize -
                $chunkOverlap;

            $chunkIndex++;
        }

        return $chunks;
    }

    /**
     * Prevent invalid chunk settings.
     */
    private function validateConfiguration(
        int $chunkSize,
        int $chunkOverlap
    ): void {
        if ($chunkSize <= 0) {
            throw new InvalidArgumentException(
                'RAG chunk size must be greater than zero.'
            );
        }

        if ($chunkOverlap < 0) {
            throw new InvalidArgumentException(
                'RAG chunk overlap cannot be negative.'
            );
        }

        if (
            $chunkOverlap >=
            $chunkSize
        ) {
            throw new InvalidArgumentException(
                'RAG chunk overlap must be smaller than the chunk size.'
            );
        }
    }
}