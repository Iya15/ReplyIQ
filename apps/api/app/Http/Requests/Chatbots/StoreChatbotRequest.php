<?php

namespace App\Http\Requests\Chatbots;

use Illuminate\Foundation\Http\FormRequest;

class StoreChatbotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // tenant gate enforced by ResolveTenant middleware
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'language' => ['sometimes', 'string', 'max:10'],
        ];
    }
}
