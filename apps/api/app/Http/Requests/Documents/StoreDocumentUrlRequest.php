<?php

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentUrlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy enforced in controller.
    }

    public function rules(): array
    {
        return [
            'url'       => ['required', 'url', 'max:2000'],
            'max_pages' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ];
    }
}
