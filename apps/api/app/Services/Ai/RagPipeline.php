<?php

namespace App\Services\Ai;

use App\DataObjects\GeneratedReply;
use App\DataObjects\RetrievedChunk;
use App\Models\Chatbot;
use App\Services\Ai\Contracts\LlmClient;
use App\Services\Knowledge\RetrievalService;
use Illuminate\Support\Facades\Log;

class RagPipeline
{
    public function __construct(
        private readonly RetrievalService $retrieval,
        private readonly PromptBuilder $promptBuilder,
        private readonly LlmClient $llm,
    ) {}

    /**
     * Execute the full RAG pipeline for a single user turn.
     *
     * Steps: retrieve → (fallback guard) → build prompt → LLM → return reply.
     *
     * $history is an array of prior turns in OpenAI message format:
     *   [['role' => 'user', 'content' => '...'], ['role' => 'assistant', 'content' => '...'], ...]
     * M3 will replace this parameter with a Conversation model once that is built.
     *
     * When $onToken is provided the LLM is called in streaming mode and each
     * content delta is forwarded to the callback. tokens_used will be 0 in that
     * case (streaming responses do not include usage by default).
     *
     * @param  array<int, array{role: string, content: string}>  $history
     */
    public function execute(
        Chatbot $chatbot,
        string $query,
        array $history = [],
        ?callable $onToken = null,
    ): GeneratedReply {
        $start = hrtime(true);

        $chunks = $this->retrieval->retrieve($chatbot, $query);

        if ($chunks->isEmpty()) {
            $fallback = $chatbot->settings?->fallback_message
                ?? "I'm sorry, I don't have enough information to answer that question.";

            return new GeneratedReply(
                content:      $fallback,
                confidence:   0.0,
                sources:      [],
                tokens_used:  0,
                latency_ms:   (int) ((hrtime(true) - $start) / 1_000_000),
            );
        }

        $messages = $this->promptBuilder->build($chatbot, $query, $chunks, $history);

        $model       = $chatbot->settings?->model       ?? 'gpt-4o-mini';
        $maxTokens   = $chatbot->settings?->max_tokens  ?? 800;
        $temperature = $chatbot->settings?->temperature ?? 0.3;

        try {
            if ($onToken !== null) {
                $content    = '';
                $tokensUsed = 0;

                foreach ($this->llm->chatStream($messages, $model, $maxTokens, $temperature) as $delta) {
                    $content .= $delta;
                    ($onToken)($delta);
                }
            } else {
                $llmResponse = $this->llm->chat($messages, $model, $maxTokens, $temperature);
                $content     = $llmResponse->content;
                $tokensUsed  = $llmResponse->tokens_used;
            }
        } catch (\Throwable $e) {
            Log::error('RagPipeline: LLM call failed', [
                'chatbot_id' => $chatbot->id,
                'model'      => $model,
                'error'      => $e->getMessage(),
            ]);

            return new GeneratedReply(
                content:     "I'm sorry, I'm unable to respond right now. Please try again in a moment.",
                confidence:  0.0,
                sources:     [],
                tokens_used: 0,
                latency_ms:  (int) ((hrtime(true) - $start) / 1_000_000),
            );
        }

        $confidence = (float) $chunks->max(fn (RetrievedChunk $c) => $c->similarity);

        $sources = $chunks->map(fn (RetrievedChunk $c) => [
            'chunk_id'    => $c->id,
            'document_id' => $c->document_id,
            'similarity'  => $c->similarity,
        ])->values()->all();

        return new GeneratedReply(
            content:     $content,
            confidence:  $confidence,
            sources:     $sources,
            tokens_used: $tokensUsed,
            latency_ms:  (int) ((hrtime(true) - $start) / 1_000_000),
        );
    }
}
