<?php

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentTextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy enforced in controller.
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:500'],
            'content' => ['required', 'string', 'max:1000000'],
        ];
    }
}
