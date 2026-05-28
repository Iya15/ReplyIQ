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
            // public_id and visitor_id are not required under widget:token auth
            // (they come from the JWT); kept optional for backwards compatibility.
            'content' => ['required', 'string', 'min:1', 'max:4000'],
        ];
    }
}
