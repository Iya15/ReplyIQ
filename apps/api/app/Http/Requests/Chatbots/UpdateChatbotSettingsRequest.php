<?php

namespace App\Http\Requests\Chatbots;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChatbotSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // ── Branding ──────────────────────────────────────────────────────
            'logo_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'avatar_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'primary_color' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'text_color' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'font_family' => ['sometimes', 'string', 'max:100'],

            // ── Behavior ──────────────────────────────────────────────────────
            'welcome_message' => ['sometimes', 'string', 'max:1000'],
            'placeholder_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'ai_tone' => ['sometimes', 'string', Rule::in(['professional', 'friendly', 'casual', 'formal'])],
            'ai_persona' => ['sometimes', 'nullable', 'string', 'max:2000'],

            // ── Widget ────────────────────────────────────────────────────────
            'position' => ['sometimes', 'string', Rule::in(['bottom-right', 'bottom-left', 'top-right', 'top-left'])],
            'theme' => ['sometimes', 'string', Rule::in(['light', 'dark', 'auto'])],
            'show_branding' => ['sometimes', 'boolean'],

            // ── AI Config ─────────────────────────────────────────────────────
            'model' => ['sometimes', 'string', Rule::in(['gpt-4o-mini', 'gpt-4o', 'gpt-4-turbo'])],
            'temperature' => ['sometimes', 'numeric', 'min:0', 'max:2'],
            'max_tokens' => ['sometimes', 'integer', 'min:100', 'max:4000'],

            'similarity_threshold' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'retrieval_k' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'fallback_message' => ['sometimes', 'string', 'max:1000'],

            // Each domain must look like a hostname — no scheme, no path.
            'allowed_domains' => ['sometimes', 'array'],
            'allowed_domains.*' => [
                'string',
                'max:253',
                'regex:/^(?:[a-zA-Z0-9](?:[a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'primary_color.regex' => 'The primary color must be a valid 6-digit hex color (e.g. #4F46E5).',
            'text_color.regex' => 'The text color must be a valid 6-digit hex color (e.g. #0F172A).',
            'allowed_domains.*.regex' => 'Each domain must be a valid hostname with no scheme or path (e.g. example.com).',
        ];
    }
}
