<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class StartConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth is handled by WidgetAuth middleware.
    }

    public function rules(): array
    {
        return [
            'public_id' => ['required', 'string'],
            'visitor_id' => ['required', 'string', 'max:64'],
            'source_url' => ['nullable', 'url', 'max:2048'],
            'user_agent' => ['nullable', 'string', 'max:500'],
        ];
    }
}
