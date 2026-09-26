<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('document_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unsignedInteger('chunk_index');

            $table->longText('content');

            /*
             * During our first RAG implementation,
             * embeddings will be stored as JSON.
             *
             * Later we can move them to a dedicated
             * vector database without changing the
             * rest of the RAG architecture.
             */
            $table->json('embedding')
                ->nullable();

            $table->unsignedInteger('page_number')
                ->nullable();

            $table->string('section_title')
                ->nullable();

            $table->unsignedInteger('token_count')
                ->nullable();

            $table->json('metadata')
                ->nullable();

            $table->timestamps();

            $table->index('document_id');

            $table->unique([
                'document_id',
                'chunk_index',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
    }
};