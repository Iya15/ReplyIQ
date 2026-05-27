<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatbotSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'logo_url' => $this->logo_url,
            'avatar_url' => $this->avatar_url,
            'primary_color' => $this->primary_color,
            'text_color' => $this->text_color,
            'font_family' => $this->font_family,
            'welcome_message' => $this->welcome_message,
            'placeholder_text' => $this->placeholder_text,
            'ai_tone' => $this->ai_tone,
            'ai_persona' => $this->ai_persona,
            'position' => $this->position,
            'theme' => $this->theme,
            'show_branding' => $this->show_branding,
            'model' => $this->model,
            'temperature' => $this->temperature,
            'max_tokens' => $this->max_tokens,
            'similarity_threshold' => $this->similarity_threshold,
            'retrieval_k' => $this->retrieval_k,
            'fallback_message' => $this->fallback_message,
            'allowed_domains' => $this->allowed_domains,
        ];
    }
}
