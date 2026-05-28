<?php

// @requires PostgreSQL (CI/Docker only)

use App\Models\ApiKey;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function makeOwner(): array
{
    $org  = Organization::factory()->create();
    $user = User::factory()->create();
    Membership::factory()->create(['organization_id' => $org->id, 'user_id' => $user->id, 'role' => 'owner']);
    return compact('org', 'user');
}

function ownerHeaders(User $user): array
{
    return ['Authorization' => 'Bearer ' . $user->createToken('test')->plainTextToken];
}

// ── GET /api/v1/api-keys ──────────────────────────────────────────────────────

it('returns empty list when no keys exist', function () {
    ['user' => $user] = makeOwner();

    $this->withHeaders(ownerHeaders($user))
        ->getJson('/api/v1/api-keys')
        ->assertOk()
        ->assertJsonPath('data', []);
});

it('lists keys for the current organization', function () {
    ['org' => $org, 'user' => $user] = makeOwner();

    ApiKey::factory()->create(['organization_id' => $org->id, 'name' => 'Prod Key']);

    $this->withHeaders(ownerHeaders($user))
        ->getJson('/api/v1/api-keys')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Prod Key')
        ->assertJsonMissingExact(['key' => null]); // plain key not in list response
});

it('does not leak keys from another organization', function () {
    ['user' => $user] = makeOwner();
    ApiKey::factory()->create(); // different org

    $this->withHeaders(ownerHeaders($user))
        ->getJson('/api/v1/api-keys')
        ->assertOk()
        ->assertJsonPath('data', []);
});

// ── POST /api/v1/api-keys ─────────────────────────────────────────────────────

it('creates a key and returns the plain key once', function () {
    ['user' => $user] = makeOwner();

    $response = $this->withHeaders(ownerHeaders($user))
        ->postJson('/api/v1/api-keys', ['name' => 'My Key'])
        ->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'name', 'prefix', 'key', 'created_at']]);

    $key = $response->json('data.key');
    expect($key)->toStartWith('rk_live_');
    expect(strlen($key))->toBe(40); // 'rk_live_' (8) + 32 random chars

    $prefix = $response->json('data.prefix');
    expect($prefix)->toBe(substr($key, 0, 12));

    // Hash is in DB; plain key is NOT.
    expect(ApiKey::where('key_hash', hash('sha256', $key))->exists())->toBeTrue();
});

it('validates that name is required', function () {
    ['user' => $user] = makeOwner();

    $this->withHeaders(ownerHeaders($user))
        ->postJson('/api/v1/api-keys', [])
        ->assertUnprocessable();
});

// ── DELETE /api/v1/api-keys/{id} ──────────────────────────────────────────────

it('owner can revoke a key', function () {
    ['org' => $org, 'user' => $user] = makeOwner();
    $key = ApiKey::factory()->create(['organization_id' => $org->id]);

    $this->withHeaders(ownerHeaders($user))
        ->deleteJson("/api/v1/api-keys/{$key->id}")
        ->assertOk();

    expect(ApiKey::find($key->id))->toBeNull();
});

it('cannot revoke a key from another organization', function () {
    ['user' => $user] = makeOwner();
    $otherKey = ApiKey::factory()->create(); // different org

    $this->withHeaders(ownerHeaders($user))
        ->deleteJson("/api/v1/api-keys/{$otherKey->id}")
        ->assertNotFound();
});

// ── ApiKeyAuth middleware ─────────────────────────────────────────────────────

it('api-key middleware resolves the organization from a valid key', function () {
    ['org' => $org] = makeOwner();

    $plain = 'rk_live_' . Str::random(32);
    ApiKey::factory()->create([
        'organization_id' => $org->id,
        'key_hash'        => hash('sha256', $plain),
        'prefix'          => substr($plain, 0, 12),
    ]);

    // Hit a protected external endpoint (stub route registered in test).
    // Use the actual middleware by adding a test route on-the-fly.
    app(\Illuminate\Routing\Router::class)->middleware(['api', 'api-key'])
        ->get('/_test/api-key-probe', fn () => response()->json([
            'org' => app('currentOrganization')?->id,
        ]));

    $this->withHeader('Authorization', "Bearer {$plain}")
        ->getJson('/_test/api-key-probe')
        ->assertOk()
        ->assertJsonPath('org', (string) $org->id);
});

it('api-key middleware rejects an invalid key', function () {
    $this->withHeader('Authorization', 'Bearer rk_live_' . Str::random(32))
        ->getJson('/_test/api-key-probe')
        ->assertUnauthorized();
});

it('api-key middleware rejects a non-rk_live_ bearer token', function () {
    $this->withHeader('Authorization', 'Bearer some_other_token')
        ->getJson('/_test/api-key-probe')
        ->assertUnauthorized();
});
