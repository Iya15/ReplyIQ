<?php

namespace Database\Factories;

use App\Models\Chatbot;
use App\Models\Chunk;
use App\Models\Document;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChunkFactory extends Factory
{
    protected $model = Chunk::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'chatbot_id' => Chatbot::factory(),
            'document_id' => Document::factory(),
            'chunk_index' => fake()->numberBetween(0, 100),
            'content' => fake()->paragraphs(2, true),
            'token_count' => fake()->numberBetween(50, 400),
            // 1536-dim embedding of uniform 0.5 values. 0.5 is exactly representable
            // as float4, so it survives the pgvector float4 storage round-trip without
            // precision loss. Ingestion jobs replace this with real OpenAI embeddings.
            'embedding' => array_fill(0, 1536, 0.5),
            'metadata' => ['model' => 'text-embedding-3-small'],
        ];
    }

    /**
     * Chunk with no embedding yet — e.g. freshly chunked but not yet embedded.
     */
    public function withoutEmbedding(): static
    {
        return $this->state(['embedding' => null]);
    }
}
