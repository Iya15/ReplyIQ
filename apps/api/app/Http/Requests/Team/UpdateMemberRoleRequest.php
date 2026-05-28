<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMemberRoleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', 'in:owner,admin,member'],
        ];
    }
}
