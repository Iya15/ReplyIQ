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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// ── Helpers (scoped to avoid collision with RagPipelineTest helpers) ──────────

function failureChatbot(): array
{
    $org     = Organization::factory()->create(['name' => 'Failure Org']);
    $chatbot = Chatbot::factory()->for($org)->create(['name' => 'FailureBot']);

    $chatbot->settings->update(['similarity_threshold' => 0.5]);
    $chatbot->load(['settings', 'organization']);
    app()->instance('currentOrganization', $org);

    return [$org, $chatbot];
}

function failureDoc(string $orgId, string $chatbotId): Document
{
    return Document::factory()->create([
        'organization_id' => $orgId,
        'chatbot_id'      => $chatbotId,
        'status'          => DocumentStatus::Ready,
        'source_type'     => DocumentSourceType::Manual,
    ]);
}

/** Unit-vector embedding: all zeros except dimension $hot = 1.0 */
function failureUnitVec(int $hot, int $size = 1536): string
{
    $v       = array_fill(0, $size, 0.0);
    $v[$hot] = 1.0;

    return '[' . implode(',', $v) . ']';
}

/** @return float[] */
function failureUnitArr(int $hot, int $size = 1536): array
{
    $v       = array_fill(0, $size, 0.0);
    $v[$hot] = 1.0;

    return $v;
}

function insertFailureChunk(
    string $orgId,
    string $chatbotId,
    string $documentId,
    string $content = 'Some retrievable content.',
): string {
    $id = Str::uuid()->toString();

    DB::table('chunks')->insert([
        'id'              => $id,
        'organization_id' => $orgId,
        'chatbot_id'      => $chatbotId,
        'document_id'     => $documentId,
        'chunk_index'     => 0,
        'content'         => $content,
        'token_count'     => str_word_count($content),
        'embedding'       => failureUnitVec(0),
        'metadata'        => json_encode([]),
        'created_at'      => now(),
    ]);

    return $id;
}

function failureEmbedder(): EmbeddingClient
{
    return new class implements EmbeddingClient {
        /** @return float[] */
        public function embed(string $text): array { return failureUnitArr(0); }

        /** @return float[][] */
        public function embedBatch(array $texts): array
        {
            return array_map(fn () => failureUnitArr(0), $texts);
        }

        public function dimension(): int { return 1536; }

        public function model(): string { return 'test'; }
    };
}

function makePipelineWithLlm(LlmClient $llm): RagPipeline
{
    return new RagPipeline(
        new RetrievalService(failureEmbedder()),
        new PromptBuilder(),
        $llm,
    );
}

// ── LLM failure tests ─────────────────────────────────────────────────────────

it('returns graceful reply when LLM chat() throws a RuntimeException', function () {
    [$org, $chatbot] = failureChatbot();
    $doc             = failureDoc($org->id, $chatbot->id);
    insertFailureChunk($org->id, $chatbot->id, $doc->id);

    $llm = new class implements LlmClient {
        public function chat(array $messages, string $model, int $maxTokens, float $temperature): LlmResponse
        {
            throw new \RuntimeException('OpenAI API timeout');
        }

        public function chatStream(array $messages, string $model, int $maxTokens, float $temperature): \Generator
        {
            yield '';
        }

        public function model(): string { return 'mock'; }
    };

    $reply = makePipelineWithLlm($llm)->execute($chatbot, 'What is the refund policy?');

    expect($reply)->toBeInstanceOf(GeneratedReply::class)
        ->and($reply->content)->toContain("unable to respond right now")
        ->and($reply->confidence)->toBe(0.0)
        ->and($reply->sources)->toBeEmpty()
        ->and($reply->tokens_used)->toBe(0)
        ->and($reply->latency_ms)->toBeGreaterThanOrEqual(0);
});

it('returns graceful reply when LLM chatStream() throws mid-generation', function () {
    [$org, $chatbot] = failureChatbot();
    $doc             = failureDoc($org->id, $chatbot->id);
    insertFailureChunk($org->id, $chatbot->id, $doc->id);

    $llm = new class implements LlmClient {
        public function chat(array $messages, string $model, int $maxTokens, float $temperature): LlmResponse
        {
            return new LlmResponse('ok', 10, 5, 'stop', $model);
        }

        public function chatStream(array $messages, string $model, int $maxTokens, float $temperature): \Generator
        {
            yield 'Partial ';
            throw new \RuntimeException('Stream interrupted');
        }

        public function model(): string { return 'mock'; }
    };

    $received = [];
    $reply    = makePipelineWithLlm($llm)->execute(
        $chatbot,
        'Tell me something',
        onToken: function (string $d) use (&$received): void { $received[] = $d; },
    );

    expect($reply->content)->toContain("unable to respond right now")
        ->and($reply->confidence)->toBe(0.0)
        ->and($reply->sources)->toBeEmpty()
        ->and($reply->tokens_used)->toBe(0);
});

it('logs the error with chatbot_id and model when LLM fails', function () {
    Log::spy();

    [$org, $chatbot] = failureChatbot();
    $doc             = failureDoc($org->id, $chatbot->id);
    insertFailureChunk($org->id, $chatbot->id, $doc->id);

    $llm = new class implements LlmClient {
        public function chat(array $messages, string $model, int $maxTokens, float $temperature): LlmResponse
        {
            throw new \RuntimeException('API key invalid');
        }

        public function chatStream(array $messages, string $model, int $maxTokens, float $temperature): \Generator
        {
            yield '';
        }

        public function model(): string { return 'mock'; }
    };

    makePipelineWithLlm($llm)->execute($chatbot, 'query');

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(function (string $message, array $context) use ($chatbot): bool {
            return str_contains($message, 'RagPipeline')
                && $context['chatbot_id'] === $chatbot->id
                && str_contains($context['error'], 'API key invalid');
        });
});

it('does not call LLM and returns fallback when retrieval yields no chunks', function () {
    [$org, $chatbot] = failureChatbot();

    // No chunks inserted → retrieval will return empty collection.
    $called = false;
    $llm    = new class($called) implements LlmClient {
        public function __construct(private bool &$called) {}

        public function chat(array $messages, string $model, int $maxTokens, float $temperature): LlmResponse
        {
            $this->called = true;
            return new LlmResponse('should not reach here', 0, 0, 'stop', $model);
        }

        public function chatStream(array $messages, string $model, int $maxTokens, float $temperature): \Generator
        {
            $this->called = true;
            yield '';
        }

        public function model(): string { return 'mock'; }
    };

    $reply = makePipelineWithLlm($llm)->execute($chatbot, 'Anything');

    expect($called)->toBeFalse()
        ->and($reply->confidence)->toBe(0.0)
        ->and($reply->sources)->toBeEmpty()
        ->and($reply->tokens_used)->toBe(0);
});

it('returns graceful reply when LLM throws a non-RuntimeException Throwable', function () {
    [$org, $chatbot] = failureChatbot();
    $doc             = failureDoc($org->id, $chatbot->id);
    insertFailureChunk($org->id, $chatbot->id, $doc->id);

    $llm = new class implements LlmClient {
        public function chat(array $messages, string $model, int $maxTokens, float $temperature): LlmResponse
        {
            throw new \Error('Fatal error in LLM driver');
        }

        public function chatStream(array $messages, string $model, int $maxTokens, float $temperature): \Generator
        {
            yield '';
        }

        public function model(): string { return 'mock'; }
    };

    $reply = makePipelineWithLlm($llm)->execute($chatbot, 'query');

    // A bare \Error (not \Exception) must also be caught.
    expect($reply->content)->toContain("unable to respond right now")
        ->and($reply->confidence)->toBe(0.0);
});
