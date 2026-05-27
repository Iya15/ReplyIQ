<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth is handled by WidgetAuth middleware.
    }

    public function rules(): array
    {
        return [
            'public_id'  => ['required', 'string'],
            'visitor_id' => ['required', 'string', 'max:64'],
            'content'    => ['required', 'string', 'min:1', 'max:4000'],
        ];
    }
}
