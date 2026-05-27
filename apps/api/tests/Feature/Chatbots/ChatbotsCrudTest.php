<?php

use App\Enums\ChatbotStatus;
use App\Models\Chatbot;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeOwner(): array
{
    $org = Organization::factory()->create();
    $user = User::factory()->create();
    Membership::create(['organization_id' => $org->id, 'user_id' => $user->id, 'role' => 'owner']);

    return [$org, $user, $user->createToken('t')->plainTextToken];
}

function makeMember(Organization $org): array
{
    $user = User::factory()->create();
    Membership::create(['organization_id' => $org->id, 'user_id' => $user->id, 'role' => 'member']);

    return [$user, $user->createToken('t')->plainTextToken];
}

// ── Index ─────────────────────────────────────────────────────────────────────

it('lists chatbots for the authenticated organization', function () {
    [$org, , $token] = makeOwner();
    Chatbot::factory()->count(3)->for($org)->create();

    $this->withToken($token)
        ->getJson('/api/v1/chatbots')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.total', 3);
});

it('returns an empty list when the organization has no chatbots', function () {
    [, , $token] = makeOwner();

    $this->withToken($token)
        ->getJson('/api/v1/chatbots')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

// ── Store ─────────────────────────────────────────────────────────────────────

it('creates a chatbot with auto-generated public_id and default settings', function () {
    [, , $token] = makeOwner();

    $this->withToken($token)
        ->postJson('/api/v1/chatbots', ['name' => 'My Bot'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'My Bot')
        ->assertJsonPath('data.status', ChatbotStatus::Draft->value)
        ->assertJsonStructure(['data' => ['id', 'public_id', 'settings']]);

    expect(Chatbot::where('name', 'My Bot')->exists())->toBeTrue();
});

it('rejects chatbot creation without a name', function () {
    [, , $token] = makeOwner();

    $this->withToken($token)
        ->postJson('/api/v1/chatbots', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('never accepts organization_id from the client', function () {
    [$orgA, , $tokenA] = makeOwner();
    [$orgB] = makeOwner();

    $this->withToken($tokenA)
        ->postJson('/api/v1/chatbots', ['name' => 'Hijack Bot', 'organization_id' => $orgB->id])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Hijack Bot');

    // The created chatbot must belong to org A, not org B.
    expect(Chatbot::where('name', 'Hijack Bot')->first()->organization_id)->toBe($orgA->id);
});

// ── Show ──────────────────────────────────────────────────────────────────────

it('returns a chatbot with its settings', function () {
    [$org, , $token] = makeOwner();
    $chatbot = Chatbot::factory()->for($org)->create();

    $this->withToken($token)
        ->getJson("/api/v1/chatbots/{$chatbot->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $chatbot->id)
        ->assertJsonStructure(['data' => ['settings']]);
});

// ── Update ────────────────────────────────────────────────────────────────────

it('updates chatbot name, status and language', function () {
    [$org, , $token] = makeOwner();
    $chatbot = Chatbot::factory()->for($org)->create();

    $this->withToken($token)
        ->patchJson("/api/v1/chatbots/{$chatbot->id}", [
            'name' => 'Renamed Bot',
            'status' => 'active',
            'language' => 'fr',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed Bot')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.language', 'fr');
});

it('rejects an invalid status value', function () {
    [$org, , $token] = makeOwner();
    $chatbot = Chatbot::factory()->for($org)->create();

    $this->withToken($token)
        ->patchJson("/api/v1/chatbots/{$chatbot->id}", ['status' => 'deleted'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('ignores public_id in an update payload', function () {
    [$org, , $token] = makeOwner();
    $chatbot = Chatbot::factory()->for($org)->create();
    $original = $chatbot->public_id;

    $this->withToken($token)
        ->patchJson("/api/v1/chatbots/{$chatbot->id}", ['name' => 'New Name', 'public_id' => 'cb_hacked00000000'])
        ->assertOk();

    expect($chatbot->fresh()->public_id)->toBe($original);
});

// ── Delete ────────────────────────────────────────────────────────────────────

it('owner can delete a chatbot', function () {
    [$org, , $token] = makeOwner();
    $chatbot = Chatbot::factory()->for($org)->create();

    $this->withToken($token)
        ->deleteJson("/api/v1/chatbots/{$chatbot->id}")
        ->assertOk();

    expect(Chatbot::find($chatbot->id))->toBeNull();
});

it('member cannot delete a chatbot', function () {
    [$org] = makeOwner();
    [, $memberToken] = makeMember($org);
    $chatbot = Chatbot::factory()->for($org)->create();

    $this->withToken($memberToken)
        ->deleteJson("/api/v1/chatbots/{$chatbot->id}")
        ->assertForbidden();
});

// ── Embed code ────────────────────────────────────────────────────────────────

it('returns an embed code snippet containing the public_id', function () {
    [$org, , $token] = makeOwner();
    $chatbot = Chatbot::factory()->for($org)->create();

    $this->withToken($token)
        ->getJson("/api/v1/chatbots/{$chatbot->id}/embed-code")
        ->assertOk()
        ->assertJsonPath('data.embed_code', fn ($v) => str_contains($v, $chatbot->public_id));
});
