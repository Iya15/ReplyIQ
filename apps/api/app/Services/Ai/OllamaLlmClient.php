<?php

namespace App\Services\Ai;

use App\DataObjects\LlmResponse;
use App\Services\Ai\Contracts\LlmClient;
use RuntimeException;

// TODO: Implement Ollama chat completions once multi-model routing lands.
class OllamaLlmClient implements LlmClient
{
    private const MODEL = 'llama3';

    public function chat(
        array $messages,
        string $model,
        int $maxTokens,
        float $temperature,
    ): LlmResponse {
        throw new RuntimeException(
            'OllamaLlmClient::chat() is not yet implemented. Use OpenAI provider.',
        );
    }

    public function chatStream(
        array $messages,
        string $model,
        int $maxTokens,
        float $temperature,
    ): \Generator {
        throw new RuntimeException(
            'OllamaLlmClient::chatStream() is not yet implemented. Use OpenAI provider.',
        );
        yield; // @phpstan-ignore deadCode.unreachable
    }

    public function model(): string
    {
        return self::MODEL;
    }
}
