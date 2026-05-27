<?php

namespace App\DataObjects;

final readonly class ExtractedDocument
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $content,
        public ?string $title,
        public array $metadata,
    ) {}
}
