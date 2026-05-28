<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;

class AcceptInvitationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'     => ['sometimes', 'string', 'max:255'],
            'password' => ['sometimes', 'string', 'min:12'],
        ];
    }
}
