<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\MeResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\LoginService;
use App\Services\Auth\PasswordResetService;
use App\Services\Auth\RegisterUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegisterUserService $service): JsonResponse
    {
        $user = $service->execute($request->validated());
        $token = $user->createToken('api-token')->plainTextToken;

        return $this->ok(['token' => $token, 'user' => UserResource::make($user)], $request, Response::HTTP_CREATED);
    }

    public function login(LoginRequest $request, LoginService $service): JsonResponse
    {
        $result = $service->execute($request->validated());

        return $this->ok(['token' => $result['token'], 'user' => UserResource::make($result['user'])], $request);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->ok(['message' => 'Logged out.'], $request);
    }

    public function forgotPassword(ForgotPasswordRequest $request, PasswordResetService $service): JsonResponse
    {
        $service->sendResetLink($request->validated('email'));

        return $this->ok(['message' => 'Reset link sent if the email exists.'], $request);
    }

    public function resetPassword(ResetPasswordRequest $request, PasswordResetService $service): JsonResponse
    {
        $status = $service->reset($request->validated());

        if ($status !== Password::PASSWORD_RESET) {
            return $this->error('password_reset_failed', __($status), $request, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->ok(['message' => 'Password reset successfully.'], $request);
    }

    public function verifyEmail(Request $request, string $id, string $hash): JsonResponse
    {
        $user = User::findOrFail($id);

        abort_unless(
            hash_equals(sha1($user->getEmailForVerification()), $hash),
            Response::HTTP_FORBIDDEN,
            'Invalid verification link.'
        );

        abort_unless(
            $request->hasValidSignature(),
            Response::HTTP_FORBIDDEN,
            'Verification link has expired.'
        );

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return $this->ok(['message' => 'Email verified.'], $request);
    }

    public function me(Request $request): JsonResponse
    {
        return $this->ok(MeResource::make($request->user()), $request);
    }
}
