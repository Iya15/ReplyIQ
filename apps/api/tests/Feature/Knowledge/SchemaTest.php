<?php

use App\Casts\VectorCast;
use App\Enums\DocumentSourceType;
use App\Enums\DocumentStatus;
use App\Models\Chatbot;
use App\Models\Chunk;
use App\Models\Document;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// ── Extensions ────────────────────────────────────────────────────────────────

it('pgvector extension is loaded', function () {
    $result = DB::selectOne(
        "SELECT extname FROM pg_extension WHERE extname = 'vector'"
    );

    expect($result)->not->toBeNull()
        ->and($result->extname)->toBe('vector');
});

it('pg_trgm extension is loaded', function () {
    $result = DB::selectOne(
        "SELECT extname FROM pg_extension WHERE extname = 'pg_trgm'"
    );

    expect($result)->not->toBeNull()
        ->and($result->extname)->toBe('pg_trgm');
});

// ── Documents table ───────────────────────────────────────────────────────────

it('creates a document with the correct default status', function () {
    $org = Organization::factory()->create();
    $chatbot = Chatbot::factory()->for($org)->create();
    $doc = Document::factory()->for($org)->for($chatbot)->create();

    expect($doc->status)->toBe(DocumentStatus::Pending)
        ->and($doc->chunk_count)->toBe(0)
        ->and($doc->processed_at)->toBeNull();
});

it('casts document status to enum and source_type to enum', function () {
    $doc = Document::factory()->create([
        'status' => 'ready',
        'source_type' => 'url',
        'source_url' => 'https://example.com',
        'processed_at' => now(),
    ]);

    expect($doc->fresh()->status)->toBe(DocumentStatus::Ready)
        ->and($doc->fresh()->source_type)->toBe(DocumentSourceType::Url)
        ->and($doc->fresh()->processed_at)->not->toBeNull();
});

it('cascades document deletion to its chunks', function () {
    $org = Organization::factory()->create();
    $chatbot = Chatbot::factory()->for($org)->create();
    $doc = Document::factory()->for($org)->for($chatbot)->create();
    Chunk::factory()->count(3)->for($org)->for($chatbot)->for($doc)->create();

    expect(Chunk::withoutGlobalScopes()->where('document_id', $doc->id)->count())->toBe(3);

    $doc->delete();

    expect(Chunk::withoutGlobalScopes()->where('document_id', $doc->id)->count())->toBe(0);
});

// ── Chunks table & VectorCast ─────────────────────────────────────────────────

it('inserts a 1536-dim embedding and retrieves it as a float array', function () {
    $chunk = Chunk::factory()->create();

    $fresh = Chunk::withoutGlobalScopes()->find($chunk->id);

    expect($fresh->embedding)
        ->toBeArray()
        ->toHaveCount(1536);
});

it('VectorCast round-trips float values through pgvector float4 storage', function () {
    // 0.5 is exactly representable as float4, so no precision loss on storage.
    $embedding = array_fill(0, 1536, 0.5);
    $chunk = Chunk::factory()->create(['embedding' => $embedding]);

    $roundTripped = Chunk::withoutGlobalScopes()->find($chunk->id)->embedding;

    expect($roundTripped)->toHaveCount(1536);

    foreach ($roundTripped as $value) {
        expect($value)->toEqualWithDelta(0.5, 0.000001);
    }
});

it('stores null embedding without error', function () {
    $chunk = Chunk::factory()->withoutEmbedding()->create();

    expect(Chunk::withoutGlobalScopes()->find($chunk->id)->embedding)->toBeNull();
});

it('metadata cast stores and retrieves the embedding model name', function () {
    $chunk = Chunk::factory()->create([
        'metadata' => ['model' => 'text-embedding-3-small', 'source_tokens' => 312],
    ]);

    $meta = Chunk::withoutGlobalScopes()->find($chunk->id)->metadata;

    expect($meta['model'])->toBe('text-embedding-3-small')
        ->and($meta['source_tokens'])->toBe(312);
});

// ── Indexes ───────────────────────────────────────────────────────────────────

it('HNSW index exists on chunks.embedding', function () {
    $index = DB::selectOne(
        "SELECT indexname, indexdef
         FROM pg_indexes
         WHERE tablename = 'chunks'
           AND indexname = 'idx_chunks_embedding'"
    );

    expect($index)->not->toBeNull()
        ->and(strtolower($index->indexdef))->toContain('hnsw');
});

it('GIN trgm index exists on chunks.content', function () {
    $index = DB::selectOne(
        "SELECT indexname
         FROM pg_indexes
         WHERE tablename = 'chunks'
           AND indexname = 'idx_chunks_content_trgm'"
    );

    expect($index)->not->toBeNull();
});

it('B-tree index exists on chunks.chatbot_id', function () {
    $index = DB::selectOne(
        "SELECT indexname
         FROM pg_indexes
         WHERE tablename = 'chunks'
           AND indexname = 'idx_chunks_chatbot'"
    );

    expect($index)->not->toBeNull();
});

// ── Tenancy isolation ─────────────────────────────────────────────────────────

it('TenantScope prevents cross-org chunk reads', function () {
    $orgA = Organization::factory()->create();
    $userA = User::factory()->create();
    Membership::create(['organization_id' => $orgA->id, 'user_id' => $userA->id, 'role' => 'owner']);

    $orgB = Organization::factory()->create();
    $chatbotB = Chatbot::factory()->for($orgB)->create();
    $docB = Document::factory()->for($orgB)->for($chatbotB)->create();
    Chunk::factory()->count(2)->for($orgB)->for($chatbotB)->for($docB)->create();

    // Bind org A as the current tenant.
    app()->instance('currentOrganization', $orgA);

    // Should see zero chunks — all chunks belong to org B.
    expect(Chunk::count())->toBe(0);
});
