<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class LoginService
{
    /**
     * @return array{token: string, user: User}
     *
     * @throws HttpResponseException
     */
    public function execute(array $data): array
    {
        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password_hash)) {
            throw new HttpResponseException(response()->json([
                'error' => [
                    'code' => 'invalid_credentials',
                    'message' => 'The provided credentials are incorrect.',
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        if (! $user->hasVerifiedEmail()) {
            throw new HttpResponseException(response()->json([
                'error' => [
                    'code' => 'email_unverified',
                    'message' => 'Please verify your email address before logging in.',
                ],
            ], Response::HTTP_FORBIDDEN));
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $token = $user->createToken('api-token')->plainTextToken;

        return ['token' => $token, 'user' => $user];
    }
}
