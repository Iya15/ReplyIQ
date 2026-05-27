<?php

namespace App\Services\Embedding;

interface EmbeddingClient
{
    /** @return float[] */
    public function embed(string $text): array;

    /**
     * @param  string[]  $texts
     * @return float[][]
     */
    public function embedBatch(array $texts): array;

    public function dimension(): int;

    public function model(): string;
}
