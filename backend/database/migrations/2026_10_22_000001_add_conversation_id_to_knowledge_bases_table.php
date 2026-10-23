
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_bases', function (Blueprint $table) {
            /*
             * NULL = regular reusable knowledge base.
             *
             * A conversation ID = internal knowledge base
             * dedicated to one conversation's attachments.
             */
            $table->foreignId('conversation_id')
                ->nullable()
                ->unique()
                ->constrained('conversations')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_bases', function (Blueprint $table) {
            $table->dropForeign(['conversation_id']);

            $table->dropUnique([
                'conversation_id',
            ]);

            $table->dropColumn('conversation_id');
        });
    }
};
