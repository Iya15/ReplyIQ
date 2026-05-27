<?php

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy enforced in controller.
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:25600', 'mimes:pdf,doc,docx,txt'],
            'title' => ['nullable', 'string', 'max:500'],
        ];
    }
}
