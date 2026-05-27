<?php

namespace App\Services\Embedding;

use App\Exceptions\DimensionMismatchException;

// TODO: Add vector(768) column variant for Ollama-based tenants in a future migration.
class OllamaEmbeddingClient implements EmbeddingClient
{
    private const MODEL = 'nomic-embed-text';

    private const DIMENSION = 768;

    public function __construct(private readonly string $host) {}

    public function embed(string $text): array
    {
        throw new DimensionMismatchException(
            'Ollama produces 768-dim vectors; current schema expects vector(1536). '.
            'Run a 768-column migration before using Ollama in production.',
        );
    }

    public function embedBatch(array $texts): array
    {
        throw new DimensionMismatchException(
            'Ollama produces 768-dim vectors; current schema expects vector(1536). '.
            'Run a 768-column migration before using Ollama in production.',
        );
    }

    public function dimension(): int
    {
        return self::DIMENSION;
    }

    public function model(): string
    {
        return self::MODEL;
    }
}
