<?php

namespace App\Services\Ai;

use App\DataObjects\LlmResponse;
use App\Services\Ai\Contracts\LlmClient;
use OpenAI\Contracts\ClientContract;

class OpenAiLlmClient implements LlmClient
{
    public function __construct(private readonly ClientContract $client) {}

    public function chat(
        array $messages,
        string $model,
        int $maxTokens,
        float $temperature,
    ): LlmResponse {
        $start = hrtime(true);

        $response = $this->client->chat()->create([
            'model'       => $model,
            'messages'    => $messages,
            'max_tokens'  => $maxTokens,
            'temperature' => $temperature,
        ]);

        $latencyMs = (int) ((hrtime(true) - $start) / 1_000_000);
        $choice = $response->choices[0];

        return new LlmResponse(
            content:       $choice->message->content ?? '',
            tokens_used:   $response->usage->totalTokens,
            latency_ms:    $latencyMs,
            finish_reason: $choice->finishReason ?? 'stop',
            model:         $response->model,
        );
    }

    public function chatStream(
        array $messages,
        string $model,
        int $maxTokens,
        float $temperature,
    ): \Generator {
        $stream = $this->client->chat()->createStreamed([
            'model'       => $model,
            'messages'    => $messages,
            'max_tokens'  => $maxTokens,
            'temperature' => $temperature,
        ]);

        foreach ($stream as $response) {
            $delta = $response->choices[0]->delta->content ?? '';
            if ($delta !== '') {
                yield $delta;
            }
        }
    }

    public function model(): string
    {
        return 'gpt-4o-mini';
    }
}
