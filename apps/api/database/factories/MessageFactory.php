<?php

namespace Database\Factories;

use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'role' => MessageRole::User->value,
            'content' => $this->faker->sentence(),
            'status' => MessageStatus::Complete->value,
            'sources' => [],
            'confidence' => null,
            'tokens_used' => null,
            'latency_ms' => null,
            'feedback' => null,
        ];
    }

    public function user(): static
    {
        return $this->state(['role' => MessageRole::User->value]);
    }

    public function assistant(): static
    {
        return $this->state([
            'role' => MessageRole::Assistant->value,
            'sources' => [],
            'confidence' => $this->faker->randomFloat(2, 0.5, 1.0),
            'tokens_used' => $this->faker->numberBetween(50, 500),
            'latency_ms' => $this->faker->numberBetween(200, 1500),
        ]);
    }

    public function pending(): static
    {
        return $this->state([
            'role' => MessageRole::Assistant->value,
            'content' => '',
            'status' => MessageStatus::Pending->value,
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'role' => MessageRole::Assistant->value,
            'status' => MessageStatus::Failed->value,
        ]);
    }
}
