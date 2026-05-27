<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));

            $table->uuid('organization_id');
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->uuid('chatbot_id');
            $table->foreign('chatbot_id')
                ->references('id')
                ->on('chatbots')
                ->onDelete('cascade');

            // source_type is constrained to an enum in app code, not the DB,
            // so we can add values without a migration (pdf|docx|txt|url|manual|faq).
            $table->string('source_type', 20);

            $table->text('source_url')->nullable();  // URL or S3 storage path
            $table->string('title', 500)->nullable();
            $table->string('status', 20)->default('pending'); // pending|processing|ready|failed
            $table->text('error_message')->nullable();

            $table->jsonb('metadata')->default('{}');

            $table->integer('char_count')->nullable();
            $table->integer('chunk_count')->default(0);

            $table->timestampTz('created_at')->useCurrent();
            // processed_at is set when status transitions to ready or failed.
            $table->timestampTz('processed_at')->nullable();
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->index('chatbot_id', 'idx_documents_chatbot');
            $table->index('status', 'idx_documents_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
