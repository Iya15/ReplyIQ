<?php

// @requires PostgreSQL (CI/Docker only)

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// ── Email enumeration ─────────────────────────────────────────────────────────

it('login returns the same generic error for wrong password vs non-existent email', function () {
    User::factory()->create(['email' => 'real@example.com']);

    $wrongPass = $this->postJson('/api/v1/auth/login', [
        'email' => 'real@example.com', 'password' => 'wrongpassword123',
    ])->json('error.code');

    $noAccount = $this->postJson('/api/v1/auth/login', [
        'email' => 'ghost@example.com', 'password' => 'somepassword123',
    ])->json('error.code');

    expect($wrongPass)->toBe($noAccount);
});

it('forgot-password returns the same response regardless of whether email exists', function () {
    User::factory()->create(['email' => 'real@example.com']);

    $withAccount = $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'real@example.com',
    ])->json('data.message');

    $noAccount = $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'ghost@example.com',
    ])->json('data.message');

    expect($withAccount)->toBe($noAccount);
});

it('register does not reveal the email-already-taken error specifically', function () {
    Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]);

    User::factory()->create(['email' => 'existing@example.com']);

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Test', 'email' => 'existing@example.com',
        'password' => 'SecurePass123!', 'organization_name' => 'Org',
    ])->assertUnprocessable();

    // Must NOT say "taken" — must be vague
    $message = $response->json('errors.email.0') ?? $response->json('error.message') ?? '';
    expect(strtolower((string) $message))->not->toContain('taken');
    expect(strtolower((string) $message))->not->toContain('already');
});

// ── Password breach check ─────────────────────────────────────────────────────

it('registration rejects a password found in HIBP database', function () {
    // Fake HIBP returning a match: hash suffix of 'password' after first 5 chars
    $plainPw = 'password1234'; // commonly breached
    $hash = strtoupper(sha1($plainPw));
    $suffix = substr($hash, 5);
    $prefix = substr($hash, 0, 5);

    Http::fake([
        "api.pwnedpasswords.com/range/{$prefix}" => Http::response("{$suffix}:5", 200),
    ]);

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Test User', 'email' => 'new@example.com',
        'password' => $plainPw, 'organization_name' => 'ACME',
    ])->assertUnprocessable()
        ->assertJsonPath('errors.password.0', fn ($msg) => str_contains(strtolower((string) $msg), 'breach'));
});

it('registration allows a password not found in HIBP database', function () {
    Http::fake(['api.pwnedpasswords.com/*' => Http::response('ABCDE:0', 200)]);

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Test User', 'email' => 'brand@new.com',
        'password' => 'Xk9!mNv3qP@2024!UNIQUE', 'organization_name' => 'ACME',
    ])->assertCreated();
});

it('registration continues when HIBP API is unreachable (fail-open)', function () {
    Http::fake(['api.pwnedpasswords.com/*' => fn () => throw new Exception('network error')]);

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Test User', 'email' => 'failopen@example.com',
        'password' => 'ValidPassword123!!', 'organization_name' => 'ACME',
    ])->assertCreated();
});

// ── Rate limiting ─────────────────────────────────────────────────────────────

it('login is rate-limited after 5 attempts from same IP', function () {
    User::factory()->create(['email' => 'victim@example.com']);

    // 5 attempts — all fail with credentials error
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/login', ['email' => 'victim@example.com', 'password' => 'wrong']);
    }

    // 6th attempt must be rate-limited
    $this->postJson('/api/v1/auth/login', ['email' => 'victim@example.com', 'password' => 'wrong'])
        ->assertStatus(429);
});
