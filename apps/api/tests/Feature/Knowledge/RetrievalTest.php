<?php

// @requires PostgreSQL with pgvector extension (runs in CI and local Docker only)

use App\DataObjects\RetrievedChunk;
use App\Enums\DocumentSourceType;
use App\Enums\DocumentStatus;
use App\Models\Chatbot;
use App\Models\Document;
use App\Models\Organization;
use App\Services\Embedding\EmbeddingClient;
use App\Services\Knowledge\RetrievalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Build a pgvector wire-format string with a single hot dimension.
 * All 1536 values are 0.0 except position $hot which is 1.0.
 * Two different hot positions are perfectly orthogonal — cosine similarity = 0.
 * The same position against itself gives cosine similarity = 1.
 */
function unitVectorString(int $hot, int $size = 1536): string
{
    $values = array_fill(0, $size, 0.0);
    $values[$hot] = 1.0;

    return '[' . implode(',', $values) . ']';
}

/**
 * @return float[]
 */
function unitVectorArray(int $hot, int $size = 1536): array
{
    $values = array_fill(0, $size, 0.0);
    $values[$hot] = 1.0;

    return $values;
}

/**
 * Build a RetrievalService with a mock embedder that always returns $vector.
 *
 * @param  float[]  $vector
 */
function makeRetriever(array $vector): RetrievalService
{
    $embedder = new class($vector) implements EmbeddingClient {
        /** @param float[] $v */
        public function __construct(private readonly array $v) {}

        /** @return float[] */
        public function embed(string $text): array { return $this->v; }

        /** @return float[][] */
        public function embedBatch(array $texts): array
        {
            return array_map(fn () => $this->v, $texts);
        }

        public function dimension(): int { return count($this->v); }

        public function model(): string { return 'test'; }
    };

    return new RetrievalService($embedder);
}

function makeRetrievalChatbot(): array
{
    $org = Organization::factory()->create();
    $chatbot = Chatbot::factory()->for($org)->create();
    app()->instance('currentOrganization', $org);

    return [$org, $chatbot];
}

/**
 * Insert a chunk directly via DB (bypasses Eloquent + BelongsToTenant event)
 * so we can control the exact embedding vector.
 */
function insertTestChunk(
    string $orgId,
    string $chatbotId,
    string $documentId,
    string $embeddingVector,
    string $content,
    int $index = 0,
): string {
    $id = Str::uuid()->toString();

    DB::table('chunks')->insert([
        'id'              => $id,
        'organization_id' => $orgId,
        'chatbot_id'      => $chatbotId,
        'document_id'     => $documentId,
        'chunk_index'     => $index,
        'content'         => $content,
        'token_count'     => str_word_count($content),
        'embedding'       => $embeddingVector,
        'metadata'        => json_encode([]),
        'created_at'      => now(),
    ]);

    return $id;
}

function makeReadyDocument(string $orgId, string $chatbotId): Document
{
    return Document::factory()->create([
        'organization_id' => $orgId,
        'chatbot_id'      => $chatbotId,
        'status'          => DocumentStatus::Ready,
        'source_type'     => DocumentSourceType::Manual,
    ]);
}

// ── Tests ─────────────────────────────────────────────────────────────────────

it('returns a chunk whose embedding matches the query vector', function () {
    [$org, $chatbot] = makeRetrievalChatbot();
    $doc = makeReadyDocument($org->id, $chatbot->id);

    // Chunk A: hot at dimension 0 — will have similarity 1.0 with the same query vector.
    insertTestChunk($org->id, $chatbot->id, $doc->id, unitVectorString(0), 'How to reset your password.');

    $retriever = makeRetriever(unitVectorArray(0));
    $results = $retriever->retrieve($chatbot, 'reset password', k: 5, threshold: 0.5);

    expect($results)->toHaveCount(1)
        ->and($results->first())->toBeInstanceOf(RetrievedChunk::class)
        ->and($results->first()->content)->toBe('How to reset your password.')
        ->and($results->first()->similarity)->toBeGreaterThan(0.99);
});

it('returns an empty collection when the query vector is orthogonal to all chunks', function () {
    [$org, $chatbot] = makeRetrievalChatbot();
    $doc = makeReadyDocument($org->id, $chatbot->id);

    // Chunk at dimension 0; query at dimension 1 → cosine similarity = 0.0.
    insertTestChunk($org->id, $chatbot->id, $doc->id, unitVectorString(0), 'Password reset instructions.');

    $retriever = makeRetriever(unitVectorArray(1)); // perpendicular
    $results = $retriever->retrieve($chatbot, 'unrelated topic', k: 5, threshold: 0.5);

    expect($results)->toBeEmpty();
});

it('ranks chunks by descending similarity', function () {
    [$org, $chatbot] = makeRetrievalChatbot();
    $doc = makeReadyDocument($org->id, $chatbot->id);

    // Chunk A: perfect match (dim 0); Chunk B: partial match via blended vector.
    insertTestChunk($org->id, $chatbot->id, $doc->id, unitVectorString(0), 'Exact match content.', index: 0);

    // A blended vector at dim 0 (0.9) and dim 1 (0.1) is still above threshold but less similar.
    $blended = array_fill(0, 1536, 0.0);
    $blended[0] = 0.9;
    $blended[1] = sqrt(1 - 0.9 ** 2); // keep unit length
    $blendedStr = '[' . implode(',', $blended) . ']';

    insertTestChunk($org->id, $chatbot->id, $doc->id, $blendedStr, 'Partial match content.', index: 1);

    $retriever = makeRetriever(unitVectorArray(0));
    $results = $retriever->retrieve($chatbot, 'anything', k: 5, threshold: 0.0);

    expect($results)->toHaveCount(2)
        ->and($results->first()->similarity)->toBeGreaterThan($results->last()->similarity);
});

it('ignores chunks from documents that are not ready', function () {
    [$org, $chatbot] = makeRetrievalChatbot();

    $pendingDoc = Document::factory()->create([
        'organization_id' => $org->id,
        'chatbot_id'      => $chatbot->id,
        'status'          => DocumentStatus::Pending,
        'source_type'     => DocumentSourceType::Manual,
    ]);
    $failedDoc = Document::factory()->create([
        'organization_id' => $org->id,
        'chatbot_id'      => $chatbot->id,
        'status'          => DocumentStatus::Failed,
        'source_type'     => DocumentSourceType::Manual,
    ]);

    insertTestChunk($org->id, $chatbot->id, $pendingDoc->id, unitVectorString(0), 'Pending chunk.');
    insertTestChunk($org->id, $chatbot->id, $failedDoc->id, unitVectorString(0), 'Failed chunk.');

    $retriever = makeRetriever(unitVectorArray(0));
    $results = $retriever->retrieve($chatbot, 'anything', k: 5, threshold: 0.0);

    expect($results)->toBeEmpty();
});

it('never returns chunks belonging to another chatbot', function () {
    [$orgA, $chatbotA] = makeRetrievalChatbot();
    $docA = makeReadyDocument($orgA->id, $chatbotA->id);
    insertTestChunk($orgA->id, $chatbotA->id, $docA->id, unitVectorString(0), 'Content for chatbot A.');

    // Separate org and chatbot — shares the same embedding direction.
    $orgB = Organization::factory()->create();
    $chatbotB = Chatbot::factory()->for($orgB)->create();
    app()->instance('currentOrganization', $orgB);
    $docB = makeReadyDocument($orgB->id, $chatbotB->id);
    insertTestChunk($orgB->id, $chatbotB->id, $docB->id, unitVectorString(0), 'Content for chatbot B.');

    app()->instance('currentOrganization', $orgA);

    $retriever = makeRetriever(unitVectorArray(0));
    $resultsA = $retriever->retrieve($chatbotA, 'anything', k: 5, threshold: 0.0);
    $resultsB = $retriever->retrieve($chatbotB, 'anything', k: 5, threshold: 0.0);

    expect($resultsA)->toHaveCount(1)
        ->and($resultsA->first()->content)->toBe('Content for chatbot A.');

    expect($resultsB)->toHaveCount(1)
        ->and($resultsB->first()->content)->toBe('Content for chatbot B.');
});

it('maps raw rows to RetrievedChunk DTOs with correct fields', function () {
    [$org, $chatbot] = makeRetrievalChatbot();
    $doc = makeReadyDocument($org->id, $chatbot->id);
    $chunkId = insertTestChunk($org->id, $chatbot->id, $doc->id, unitVectorString(0), 'Some content.', index: 0);

    $retriever = makeRetriever(unitVectorArray(0));
    $chunk = $retriever->retrieve($chatbot, 'query', k: 1, threshold: 0.0)->first();

    expect($chunk)->toBeInstanceOf(RetrievedChunk::class)
        ->and($chunk->id)->toBe($chunkId)
        ->and($chunk->content)->toBe('Some content.')
        ->and($chunk->document_id)->toBe($doc->id)
        ->and($chunk->similarity)->toBeFloat()
        ->and($chunk->metadata)->toBeArray();
});

it('respects the k limit and returns at most k chunks', function () {
    [$org, $chatbot] = makeRetrievalChatbot();
    $doc = makeReadyDocument($org->id, $chatbot->id);

    foreach (range(0, 4) as $i) {
        insertTestChunk($org->id, $chatbot->id, $doc->id, unitVectorString(0), "Chunk number {$i}.", index: $i);
    }

    $retriever = makeRetriever(unitVectorArray(0));
    $results = $retriever->retrieve($chatbot, 'query', k: 3, threshold: 0.0);

    expect($results)->toHaveCount(3);
});
