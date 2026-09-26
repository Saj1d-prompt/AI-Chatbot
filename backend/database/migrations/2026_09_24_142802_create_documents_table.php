<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('knowledge_base_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('original_name');

            $table->string('stored_name');

            $table->string('mime_type')
                ->nullable();

            $table->string('file_path');

            $table->unsignedBigInteger('file_size')
                ->nullable();

            $table->string('status')
                ->default('uploaded');

            $table->text('error_message')
                ->nullable();

            $table->json('metadata')
                ->nullable();

            $table->timestamps();

            $table->index('knowledge_base_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};