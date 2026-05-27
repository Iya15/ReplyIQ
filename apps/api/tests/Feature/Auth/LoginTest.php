<?php

use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function makeVerifiedUser(string $email = 'user@example.com', string $password = 'supersecret1234'): User
{
    $user = User::create([
        'name' => 'Test User',
        'email' => $email,
        'email_verified_at' => now(),
    ]);
    $user->password_hash = Hash::make($password);
    $user->save();

    $org = Organization::create(['name' => 'Test Org', 'slug' => 'test-org', 'plan' => 'free', 'settings' => []]);
    Membership::create(['organization_id' => $org->id, 'user_id' => $user->id, 'role' => 'owner']);

    return $user;
}

// ── Happy path ────────────────────────────────────────────────────────────────

it('returns a token on successful login', function () {
    makeVerifiedUser();

    $this->postJson('/api/v1/auth/login', ['email' => 'user@example.com', 'password' => 'supersecret1234'])
        ->assertOk()
        ->assertJsonPath('data.token', fn ($v) => is_string($v) && strlen($v) > 10)
        ->assertJsonPath('data.user.email', 'user@example.com')
        ->assertJsonStructure(['data' => ['token', 'user'], 'meta' => ['request_id']]);
});

it('updates last_login_at on successful login', function () {
    $user = makeVerifiedUser();
    expect($user->fresh()->last_login_at)->toBeNull();

    $this->postJson('/api/v1/auth/login', ['email' => 'user@example.com', 'password' => 'supersecret1234'])
        ->assertOk();

    expect($user->fresh()->last_login_at)->not->toBeNull();
});

// ── Failure cases ─────────────────────────────────────────────────────────────

it('rejects login with wrong password', function () {
    makeVerifiedUser();

    $this->postJson('/api/v1/auth/login', ['email' => 'user@example.com', 'password' => 'wrongpassword99'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'invalid_credentials');
});

it('rejects login for unverified email', function () {
    $user = User::create([
        'name' => 'Unverified',
        'email' => 'unverified@example.com',
        'email_verified_at' => null,
    ]);
    $user->password_hash = Hash::make('supersecret1234');
    $user->save();

    $this->postJson('/api/v1/auth/login', ['email' => 'unverified@example.com', 'password' => 'supersecret1234'])
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'email_unverified');
});

it('rejects login for non-existent email', function () {
    $this->postJson('/api/v1/auth/login', ['email' => 'nobody@example.com', 'password' => 'supersecret1234'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'invalid_credentials');
});

// ── Logout ────────────────────────────────────────────────────────────────────

it('logout deletes the current access token', function () {
    $user = makeVerifiedUser();
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/auth/logout')
        ->assertOk();

    expect($user->tokens()->count())->toBe(0);
});

// ── Me ────────────────────────────────────────────────────────────────────────

it('me returns the authenticated user with their organization', function () {
    $user = makeVerifiedUser();
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.user.email', 'user@example.com')
        ->assertJsonPath('data.current_organization.slug', 'test-org')
        ->assertJsonPath('data.role', 'owner');
});
