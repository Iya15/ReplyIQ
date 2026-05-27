<?php

namespace Database\Factories;

use App\Enums\DocumentSourceType;
use App\Enums\DocumentStatus;
use App\Models\Chatbot;
use App\Models\Document;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'chatbot_id' => Chatbot::factory(),
            'source_type' => DocumentSourceType::Manual,
            'source_url' => null,
            'title' => fake()->sentence(4),
            'status' => DocumentStatus::Pending,
            'error_message' => null,
            'metadata' => [],
            'char_count' => null,
            'chunk_count' => 0,
            'processed_at' => null,
        ];
    }

    public function ready(): static
    {
        return $this->state([
            'status' => DocumentStatus::Ready,
            'char_count' => fake()->numberBetween(500, 50000),
            'chunk_count' => fake()->numberBetween(1, 50),
            'processed_at' => now(),
        ]);
    }

    public function processing(): static
    {
        return $this->state(['status' => DocumentStatus::Processing]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => DocumentStatus::Failed,
            'error_message' => 'Processing failed: unsupported file encoding',
            'processed_at' => now(),
        ]);
    }

    public function url(): static
    {
        return $this->state([
            'source_type' => DocumentSourceType::Url,
            'source_url' => fake()->url(),
        ]);
    }
}
