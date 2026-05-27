<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // vector — pgvector type for 1536-dim embeddings (OpenAI text-embedding-3-small).
        // Separate from the initial extension migration so vector/trgm can be
        // toggled independently for environments that don't need AI features yet.
        DB::statement('CREATE EXTENSION IF NOT EXISTS "vector"');

        // pg_trgm — trigram index used for hybrid keyword+vector search on
        // chunks.content via GIN index (see create_chunks_table migration).
        DB::statement('CREATE EXTENSION IF NOT EXISTS "pg_trgm"');
    }

    public function down(): void
    {
        // CASCADE drops any columns/indexes that depend on these extension types.
        // The chunks and documents tables must be rolled back before this migration
        // is reversed, which Laravel guarantees by rolling back in reverse order.
        DB::statement('DROP EXTENSION IF EXISTS "pg_trgm" CASCADE');
        DB::statement('DROP EXTENSION IF EXISTS "vector" CASCADE');
    }
};
