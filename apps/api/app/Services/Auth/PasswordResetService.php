<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Password;

class PasswordResetService
{
    public function sendResetLink(string $email): string
    {
        return Password::sendResetLink(['email' => $email]);
    }

    public function reset(array $data): string
    {
        return Password::reset(
            $data,
            function (User $user, string $password): void {
                // The 'hashed' cast on password_hash hashes it automatically on set.
                $user->password_hash = $password;
                $user->save();

                // Revoke all existing Sanctum tokens so old sessions are invalidated.
                $user->tokens()->delete();
            }
        );
    }
}
