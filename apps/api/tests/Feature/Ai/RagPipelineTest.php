<?php

// @requires PostgreSQL with pgvector extension (runs in CI and local Docker only)

use App\DataObjects\GeneratedReply;
use App\DataObjects\LlmResponse;
use App\Enums\DocumentSourceType;
use App\Enums\DocumentStatus;
use App\Models\Chatbot;
use App\Models\Document;
use App\Models\Organization;
use App\Services\Ai\Contracts\LlmClient;
use App\Services\Ai\PromptBuilder;
use App\Services\Ai\RagPipeline;
use App\Services\Embedding\EmbeddingClient;
use App\Services\Knowledge\RetrievalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function makePipelineChatbot(array $settingsOverrides = []): array
{
    $org     = Organization::factory()->create(['name' => 'Pipeline Corp']);
    $chatbot = Chatbot::factory()->for($org)->create(['name' => 'PipelineBot']);

    $chatbot->settings->update(array_merge([
        'fallback_message' => 'I have no information about that.',
        'similarity_threshold' => 0.5,
    ], $settingsOverrides));

    $chatbot->load(['settings', 'organization']);
    app()->instance('currentOrganization', $org);

    return [$org, $chatbot];
}

function makePipelineDoc(string $orgId, string $chatbotId): Document
{
    return Document::factory()->create([
        'organization_id' => $orgId,
        'chatbot_id'      => $chatbotId,
        'status'          => DocumentStatus::Ready,
        'source_type'     => DocumentSourceType::Manual,
    ]);
}

function insertPipelineChunk(
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

/** Build a unit-vector embedding string: all zeros except dimension $hot = 1.0 */
function pipelineUnitVec(int $hot, int $size = 1536): string
{
    $v        = array_fill(0, $size, 0.0);
    $v[$hot]  = 1.0;

    return '[' . implode(',', $v) . ']';
}

/** @return float[] */
function pipelineUnitArr(int $hot, int $size = 1536): array
{
    $v       = array_fill(0, $size, 0.0);
    $v[$hot] = 1.0;

    return $v;
}

function makePipelineRetriever(array $vector): RetrievalService
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

function makeMockLlm(string $responseContent): LlmClient
{
    return new class($responseContent) implements LlmClient {
        public function __construct(private readonly string $content) {}

        public function chat(array $messages, string $model, int $maxTokens, float $temperature): LlmResponse
        {
            return new LlmResponse(
                content:       $this->content,
                tokens_used:   50,
                latency_ms:    10,
                finish_reason: 'stop',
                model:         $model,
            );
        }

        public function chatStream(array $messages, string $model, int $maxTokens, float $temperature): \Generator
        {
            yield $this->content;
        }

        public function model(): string { return 'mock'; }
    };
}

function makePipeline(array $queryVector, string $llmContent): RagPipeline
{
    return new RagPipeline(
        makePipelineRetriever($queryVector),
        new PromptBuilder(),
        makeMockLlm($llmContent),
    );
}

// ── Tests ─────────────────────────────────────────────────────────────────────

it('returns LLM content when matching chunks exist', function () {
    [$org, $chatbot] = makePipelineChatbot();
    $doc             = makePipelineDoc($org->id, $chatbot->id);

    insertPipelineChunk($org->id, $chatbot->id, $doc->id, pipelineUnitVec(0), 'Pricing: $29/month Starter.');

    $pipeline = makePipeline(pipelineUnitArr(0), 'The Starter plan costs $29 per month.');
    $reply    = $pipeline->execute($chatbot, 'How much does Starter cost?');

    expect($reply)->toBeInstanceOf(GeneratedReply::class)
        ->and($reply->content)->toBe('The Starter plan costs $29 per month.')
        ->and($reply->tokens_used)->toBe(50);
});

it('returns fallback and skips LLM when no chunks match', function () {
    [$org, $chatbot] = makePipelineChatbot();
    $doc             = makePipelineDoc($org->id, $chatbot->id);

    // Chunk at dim 0; query at dim 1 → cosine similarity = 0 → below threshold.
    insertPipelineChunk($org->id, $chatbot->id, $doc->id, pipelineUnitVec(0), 'Some unrelated content.');

    $llmWasCalled = false;
    $llm = new class($llmWasCalled) implements LlmClient {
        public function __construct(private bool &$called) {}

        public function chat(array $messages, string $model, int $maxTokens, float $temperature): LlmResponse
        {
            $this->called = true;
            return new LlmResponse('should not be called', 0, 0, 'stop', $model);
        }

        public function chatStream(array $messages, string $model, int $maxTokens, float $temperature): \Generator
        {
            $this->called = true;
            yield '';
        }

        public function model(): string { return 'mock'; }
    };

    $pipeline = new RagPipeline(
        makePipelineRetriever(pipelineUnitArr(1)), // orthogonal → no match
        new PromptBuilder(),
        $llm,
    );

    $reply = $pipeline->execute($chatbot, 'What is the meaning of life?');

    expect($reply->content)->toBe('I have no information about that.')
        ->and($reply->confidence)->toBe(0.0)
        ->and($reply->sources)->toBeEmpty()
        ->and($reply->tokens_used)->toBe(0)
        ->and($llmWasCalled)->toBeFalse();
});

it('sets confidence to the max chunk similarity', function () {
    [$org, $chatbot] = makePipelineChatbot();
    $doc             = makePipelineDoc($org->id, $chatbot->id);

    insertPipelineChunk($org->id, $chatbot->id, $doc->id, pipelineUnitVec(0), 'Perfect match.', index: 0);

    $blended    = array_fill(0, 1536, 0.0);
    $blended[0] = 0.9;
    $blended[1] = sqrt(1 - 0.9 ** 2);
    insertPipelineChunk(
        $org->id, $chatbot->id, $doc->id,
        '[' . implode(',', $blended) . ']',
        'Partial match.',
        index: 1,
    );

    $pipeline = makePipeline(pipelineUnitArr(0), 'Some response.');
    $reply    = $pipeline->execute($chatbot, 'query', threshold: 0.0);

    expect($reply->confidence)->toBeGreaterThan(0.99);
});

it('populates sources array with chunk_id, document_id, and similarity', function () {
    [$org, $chatbot] = makePipelineChatbot();
    $doc             = makePipelineDoc($org->id, $chatbot->id);
    $chunkId         = insertPipelineChunk($org->id, $chatbot->id, $doc->id, pipelineUnitVec(0), 'Some content.');

    $pipeline = makePipeline(pipelineUnitArr(0), 'Any reply.');
    $reply    = $pipeline->execute($chatbot, 'query');

    expect($reply->sources)->toHaveCount(1)
        ->and($reply->sources[0]['chunk_id'])->toBe($chunkId)
        ->and($reply->sources[0]['document_id'])->toBe($doc->id)
        ->and($reply->sources[0]['similarity'])->toBeFloat();
});

it('streams content via onToken callback and accumulates full reply', function () {
    [$org, $chatbot] = makePipelineChatbot();
    $doc             = makePipelineDoc($org->id, $chatbot->id);

    insertPipelineChunk($org->id, $chatbot->id, $doc->id, pipelineUnitVec(0), 'Content for streaming.');

    $deltas   = [];
    $pipeline = makePipeline(pipelineUnitArr(0), 'Streaming response here.');
    $reply    = $pipeline->execute($chatbot, 'query', onToken: function (string $delta) use (&$deltas): void {
        $deltas[] = $delta;
    });

    expect(implode('', $deltas))->toBe('Streaming response here.')
        ->and($reply->content)->toBe('Streaming response here.')
        ->and($reply->tokens_used)->toBe(0); // streaming doesn't return usage
});

it('passes history to the prompt builder', function () {
    [$org, $chatbot] = makePipelineChatbot();
    $doc             = makePipelineDoc($org->id, $chatbot->id);

    insertPipelineChunk($org->id, $chatbot->id, $doc->id, pipelineUnitVec(0), 'Relevant context.');

    $capturedMessages = null;
    $llm = new class($capturedMessages) implements LlmClient {
        /** @param array<mixed>|null $captured */
        public function __construct(private mixed &$captured) {}

        public function chat(array $messages, string $model, int $maxTokens, float $temperature): LlmResponse
        {
            $this->captured = $messages;
            return new LlmResponse('ok', 10, 5, 'stop', $model);
        }

        public function chatStream(array $messages, string $model, int $maxTokens, float $temperature): \Generator
        {
            yield 'ok';
        }

        public function model(): string { return 'mock'; }
    };

    $history  = [['role' => 'user', 'content' => 'Prior question']];
    $pipeline = new RagPipeline(makePipelineRetriever(pipelineUnitArr(0)), new PromptBuilder(), $llm);
    $pipeline->execute($chatbot, 'Follow-up', $history);

    // Messages: system + 1 history turn + current user
    expect($capturedMessages)->toHaveCount(3)
        ->and($capturedMessages[1])->toBe(['role' => 'user', 'content' => 'Prior question']);
});
