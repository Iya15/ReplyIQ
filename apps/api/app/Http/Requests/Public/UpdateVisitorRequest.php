<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVisitorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by widget:token middleware.
    }

    public function rules(): array
    {
        return [
            'visitor_email' => ['nullable', 'email', 'max:255'],
            'visitor_name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
