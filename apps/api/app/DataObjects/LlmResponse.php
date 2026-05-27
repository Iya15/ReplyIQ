<?php

namespace App\DataObjects;

final readonly class LlmResponse
{
    public function __construct(
        public string $content,
        public int $tokens_used,
        public int $latency_ms,
        public string $finish_reason,
        public string $model,
    ) {}
}
