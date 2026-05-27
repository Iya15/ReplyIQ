<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));

            $table->uuid('organization_id');
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->uuid('conversation_id');
            $table->foreign('conversation_id')
                ->references('id')
                ->on('conversations')
                ->onDelete('cascade');

            $table->string('role', 20);       // user|assistant|system
            $table->text('content');

            // Processing lifecycle — user messages are always 'complete' immediately;
            // assistant messages start as 'pending', become 'complete' or 'failed' after the job.
            $table->string('status', 20)->default('complete'); // pending|complete|failed

            // RAG metadata (populated only for assistant messages).
            $table->jsonb('sources')->default('[]');   // [{chunk_id, document_id, similarity}]
            $table->decimal('confidence', 3, 2)->nullable();
            $table->integer('tokens_used')->nullable();
            $table->integer('latency_ms')->nullable();

            // Visitor feedback on assistant messages.
            $table->string('feedback', 20)->nullable(); // helpful|not_helpful

            $table->timestampTz('created_at')->useCurrent();
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->index(['conversation_id', 'created_at'], 'idx_messages_conversation');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
