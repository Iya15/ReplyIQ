<?php

namespace App\Services\Ai\Contracts;

use App\DataObjects\LlmResponse;

interface LlmClient
{
    /**
     * Send a chat completion and return the full response.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function chat(
        array $messages,
        string $model,
        int $maxTokens,
        float $temperature,
    ): LlmResponse;

    /**
     * Send a chat completion and yield content delta strings as they arrive.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return \Generator<string>
     */
    public function chatStream(
        array $messages,
        string $model,
        int $maxTokens,
        float $temperature,
    ): \Generator;

    public function model(): string;
}
