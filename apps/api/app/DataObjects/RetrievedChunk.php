<?php

namespace App\DataObjects;

final readonly class RetrievedChunk
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $id,
        public string $content,
        public float $similarity,
        public string $document_id,
        public array $metadata,
    ) {}
}
