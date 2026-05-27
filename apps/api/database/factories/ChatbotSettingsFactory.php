<?php

namespace Database\Factories;

use App\Models\Chatbot;
use App\Models\ChatbotSettings;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChatbotSettingsFactory extends Factory
{
    protected $model = ChatbotSettings::class;

    // NOTE: In practice, settings are auto-created by ChatbotObserver when a
    // Chatbot is created. Use this factory only when you need to assert on or
    // override specific settings values:
    //   $chatbot->settings->update(['primary_color' => '#FF0000']);
    // Calling ChatbotSettings::factory()->create() directly will fail with a
    // unique-key violation if the related Chatbot was also created via factory
    // (the observer will have already inserted its settings row).
    public function definition(): array
    {
        return [
            'chatbot_id' => Chatbot::factory(),
            'logo_url' => null,
            'avatar_url' => null,
            'primary_color' => '#4F46E5',
            'text_color' => '#0F172A',
            'font_family' => 'Inter',
            'welcome_message' => 'Hi! How can I help you today?',
            'placeholder_text' => 'Ask me anything...',
            'ai_tone' => 'professional',
            'ai_persona' => null,
            'position' => 'bottom-right',
            'theme' => 'light',
            'show_branding' => true,
            'model' => 'gpt-4o-mini',
            'temperature' => 0.3,
            'max_tokens' => 800,
            'similarity_threshold' => 0.75,
            'retrieval_k' => 5,
            'fallback_message' => "I don't have information about that. Please contact our support team.",
            'allowed_domains' => [],
        ];
    }
}
