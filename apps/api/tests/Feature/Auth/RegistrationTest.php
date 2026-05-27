<?php

use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

function validRegistrationPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Alice Smith',
        'email' => 'alice@example.com',
        'password' => 'supersecret1234',
        'organization_name' => 'Acme Corp',
    ], $overrides);
}

// ── Happy path ────────────────────────────────────────────────────────────────

it('registers a user and returns a token', function () {
    Event::fake([Registered::class]);

    $response = $this->postJson('/api/v1/auth/register', validRegistrationPayload());

    $response->assertStatus(201)
        ->assertJsonPath('data.token', fn ($v) => is_string($v) && strlen($v) > 10)
        ->assertJsonPath('data.user.email', 'alice@example.com')
        ->assertJsonStructure(['data' => ['token', 'user'], 'meta' => ['request_id']]);
});

it('creates user, organization and owner membership on register', function () {
    Event::fake([Registered::class]);

    $this->postJson('/api/v1/auth/register', validRegistrationPayload());

    $user = User::where('email', 'alice@example.com')->firstOrFail();
    $org = Organization::where('name', 'Acme Corp')->firstOrFail();

    expect(Membership::where([
        'user_id' => $user->id,
        'organization_id' => $org->id,
        'role' => 'owner',
    ])->exists())->toBeTrue();
});

it('dispatches the Registered event so verification email is queued', function () {
    Event::fake([Registered::class]);

    $this->postJson('/api/v1/auth/register', validRegistrationPayload());

    Event::assertDispatched(Registered::class, fn ($e) => $e->user->email === 'alice@example.com');
});

// ── Validation failures ───────────────────────────────────────────────────────

it('rejects registration with a duplicate email', function () {
    Event::fake([Registered::class]);

    $this->postJson('/api/v1/auth/register', validRegistrationPayload());

    $this->postJson('/api/v1/auth/register', validRegistrationPayload())
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('rejects a password shorter than 12 characters', function () {
    $this->postJson('/api/v1/auth/register', validRegistrationPayload(['password' => 'short']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

it('requires all fields', function () {
    $this->postJson('/api/v1/auth/register', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'password', 'organization_name']);
});
