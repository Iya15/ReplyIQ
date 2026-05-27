<?php

namespace App\DataObjects;

final readonly class GeneratedReply
{
    /**
     * @param  array<int, array{chunk_id: string, document_id: string, similarity: float}>  $sources
     */
    public function __construct(
        public string $content,
        public float $confidence,
        public array $sources,
        public int $tokens_used,
        public int $latency_ms,
    ) {}
}
