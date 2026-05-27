<?php

namespace App\Http\Requests\Chatbots;

use App\Enums\ChatbotStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChatbotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', Rule::enum(ChatbotStatus::class)],
            'language' => ['sometimes', 'string', 'max:10'],
            // public_id and organization_id are intentionally absent — immutable after creation.
        ];
    }
}
