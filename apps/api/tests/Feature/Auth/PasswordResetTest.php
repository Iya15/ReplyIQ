<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

function makeUserForReset(string $email = 'reset@example.com'): User
{
    $user = User::create([
        'name' => 'Reset User',
        'email' => $email,
        'email_verified_at' => now(),
    ]);
    $user->password_hash = Hash::make('oldsupersecret123');
    $user->save();

    return $user;
}

// ── Forgot password ───────────────────────────────────────────────────────────

it('sends a password reset notification when the email exists', function () {
    Notification::fake();

    makeUserForReset();

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'reset@example.com'])
        ->assertOk()
        ->assertJsonPath('data.message', fn ($v) => str_contains($v, 'Reset link'));

    Notification::assertSentTo(
        User::where('email', 'reset@example.com')->first(),
        ResetPassword::class
    );
});

it('returns ok even when email does not exist (prevents enumeration)', function () {
    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])
        ->assertOk();
});

// ── Reset password ────────────────────────────────────────────────────────────

it('resets the password and revokes all existing tokens', function () {
    Notification::fake();

    $user = makeUserForReset();
    $oldToken = $user->createToken('old-session')->plainTextToken;

    // Request a reset link to get a valid broker token.
    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'reset@example.com']);

    $brokerToken = null;
    Notification::assertSentTo($user, ResetPassword::class, function ($n) use (&$brokerToken) {
        $brokerToken = $n->token;

        return true;
    });

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => $brokerToken,
        'email' => 'reset@example.com',
        'password' => 'newsupersecret5678',
        'password_confirmation' => 'newsupersecret5678',
    ])->assertOk()
        ->assertJsonPath('data.message', fn ($v) => str_contains($v, 'reset successfully'));

    // Old password no longer works.
    expect(Hash::check('oldsupersecret123', $user->fresh()->password_hash))->toBeFalse();
    // New password works.
    expect(Hash::check('newsupersecret5678', $user->fresh()->password_hash))->toBeTrue();
    // Old token revoked.
    expect($user->fresh()->tokens()->count())->toBe(0);
});

it('rejects an invalid reset token', function () {
    makeUserForReset();

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => 'invalid-token',
        'email' => 'reset@example.com',
        'password' => 'newsupersecret5678',
        'password_confirmation' => 'newsupersecret5678',
    ])->assertStatus(422);
});

it('rejects a new password shorter than 12 characters', function () {
    $this->postJson('/api/v1/auth/reset-password', [
        'token' => 'anything',
        'email' => 'reset@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});
