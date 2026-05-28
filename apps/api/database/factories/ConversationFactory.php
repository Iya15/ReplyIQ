<?php

namespace Database\Factories;

use App\Enums\ConversationStatus;
use App\Models\Chatbot;
use App\Models\Conversation;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        $chatbot = Chatbot::factory();

        return [
            'chatbot_id' => $chatbot,
            'visitor_id' => (string) Str::uuid(),
            'source_url' => $this->faker->optional(0.6)->url(),
            'user_agent' => $this->faker->optional(0.8)->userAgent(),
            'ip_address' => $this->faker->optional(0.7)->ipv4(),
            'country' => $this->faker->optional(0.7)->countryCode(),
            'status' => ConversationStatus::Active->value,
            'resolved_at' => null,
        ];
    }

    /** Create a conversation within a specific organization. */
    public function withinOrganization(Organization $org): static
    {
        return $this->state([
            'organization_id' => $org->id,
            'chatbot_id' => Chatbot::factory()->for($org),
        ]);
    }

    public function resolved(): static
    {
        return $this->state([
            'status' => ConversationStatus::Resolved->value,
            'resolved_at' => now(),
        ]);
    }
}
