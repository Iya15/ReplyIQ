<?php

namespace Database\Factories;

use App\Models\Chatbot;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChatbotFactory extends Factory
{
    protected $model = Chatbot::class;

    public function definition(): array
    {
        // organization_id is set explicitly so this factory works regardless of
        // whether a currentOrganization is bound in the container.
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->words(2, true).' Bot',
            // Skip PublicIdGenerator (DB check) in factories; use regexify for speed.
            'public_id' => 'cb_'.fake()->regexify('[A-Za-z0-9]{13}'),
            'status' => 'draft',
            'language' => 'en',
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => 'active']);
    }

    public function paused(): static
    {
        return $this->state(['status' => 'paused']);
    }
}
