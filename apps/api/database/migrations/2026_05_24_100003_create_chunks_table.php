<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chunks', function (Blueprint $table) {
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

            $table->uuid('document_id');
            $table->foreign('document_id')
                ->references('id')
                ->on('documents')
                ->onDelete('cascade');

            // Sequential index within the source document. Used to reconstruct
            // context windows around a retrieved chunk.
            $table->integer('chunk_index');

            $table->text('content');
            $table->integer('token_count')->nullable();

            // embedding is added below via raw SQL — Blueprint has no vector type.
            // See comment on DB::statement below.

            $table->jsonb('metadata')->default('{}');

            // Chunks are immutable once created; no updated_at.
            $table->timestampTz('created_at')->useCurrent();
        });

        // vector(1536) is the native pgvector type. 1536 dimensions matches
        // OpenAI text-embedding-3-small. Changing to a different model (e.g.
        // text-embedding-3-large at 3072d) requires a full re-embed migration.
        // Store the embedding model name in metadata['model'] to track this.
        DB::statement('ALTER TABLE chunks ADD COLUMN embedding vector(1536)');

        // B-tree index on chatbot_id for tenant-scoped chunk lookups.
        DB::statement('CREATE INDEX idx_chunks_chatbot ON chunks (chatbot_id)');

        // HNSW index for approximate nearest-neighbour cosine similarity search.
        // Without this, vector search is a full sequential scan and will be very
        // slow once a chatbot accumulates > ~1k chunks.
        // m=16 / ef_construction=64 are pgvector recommended defaults for this
        // vector size; tune ef_search at query time (default 40).
        DB::statement(
            'CREATE INDEX idx_chunks_embedding ON chunks
             USING hnsw (embedding vector_cosine_ops)
             WITH (m = 16, ef_construction = 64)'
        );

        // GIN trgm index enables hybrid keyword + vector search. Used when the
        // pure-vector result set is weak (very short queries, typos, exact-match
        // keywords). Re-ranked downstream by the retrieval service.
        DB::statement(
            'CREATE INDEX idx_chunks_content_trgm ON chunks
             USING GIN (content gin_trgm_ops)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('chunks');
    }
};
