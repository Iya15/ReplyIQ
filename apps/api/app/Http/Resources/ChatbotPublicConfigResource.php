<?php

namespace App\Http\Resources;

use App\Models\Chatbot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public-safe chatbot configuration for the widget.
 *
 * NEVER include: ai_persona, model, temperature, max_tokens, similarity_threshold,
 * retrieval_k, fallback_message, allowed_domains, widget_secret.
 *
 * Only return fields the widget needs to render itself correctly.
 */
class ChatbotPublicConfigResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Chatbot $chatbot */
        $chatbot = $this->resource;
        $settings = $chatbot->settings;

        return [
            'public_id' => $chatbot->public_id,
            'name' => $chatbot->name,
            // ── Branding ──────────────────────────────────────────────────────
            'logo_url' => $settings?->logo_url,
            'avatar_url' => $settings?->avatar_url,
            'primary_color' => $settings?->primary_color ?? '#4F46E5',
            'text_color' => $settings?->text_color ?? '#0F172A',
            'font_family' => $settings?->font_family ?? 'Inter',
            // ── Behaviour ─────────────────────────────────────────────────────
            'welcome_message' => $settings?->welcome_message ?? 'Hi! How can I help you today?',
            'placeholder_text' => $settings?->placeholder_text ?? 'Ask me anything...',
            // ── Widget layout ─────────────────────────────────────────────────
            'position' => $settings?->position ?? 'bottom-right',
            'theme' => $settings?->theme ?? 'light',
            'show_branding' => $settings?->show_branding ?? true,
        ];
    }
}
