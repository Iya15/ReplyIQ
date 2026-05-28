<?php

// @requires PostgreSQL (CI/Docker only)

/**
 * Cross-tenant isolation tests.
 *
 * Each test verifies that a user belonging to Org A cannot access or mutate
 * resources owned by Org B. The expected response is 404 (not 403) to avoid
 * leaking the existence of the resource to the attacker.
 */

use App\Models\ApiKey;
use App\Models\Chatbot;
use App\Models\Conversation;
use App\Models\Document;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function isolationOrg(): array
{
    $org = Organization::factory()->create();
    $user = User::factory()->create();
    Membership::factory()->create(['organization_id' => $org->id, 'user_id' => $user->id, 'role' => 'owner']);
    $token = $user->createToken('test')->plainTextToken;

    return compact('org', 'user', 'token');
}

function isolationHeaders(string $token): array
{
    return ['Authorization' => "Bearer {$token}"];
}

// ── Chatbots ──────────────────────────────────────────────────────────────────

it('cannot view a chatbot from another organization', function () {
    ['token' => $tokenA] = isolationOrg();
    ['org' => $orgB] = isolationOrg();

    $chatbot = Chatbot::factory()->for($orgB)->create();

    $this->withHeaders(isolationHeaders($tokenA))
        ->getJson("/api/v1/chatbots/{$chatbot->id}")
        ->assertNotFound();
});

it('cannot update a chatbot from another organization', function () {
    ['token' => $tokenA] = isolationOrg();
    ['org' => $orgB] = isolationOrg();

    $chatbot = Chatbot::factory()->for($orgB)->create();

    $this->withHeaders(isolationHeaders($tokenA))
        ->patchJson("/api/v1/chatbots/{$chatbot->id}", ['name' => 'HACKED'])
        ->assertNotFound();
});

it('cannot delete a chatbot from another organization', function () {
    ['token' => $tokenA] = isolationOrg();
    ['org' => $orgB] = isolationOrg();

    $chatbot = Chatbot::factory()->for($orgB)->create();

    $this->withHeaders(isolationHeaders($tokenA))
        ->deleteJson("/api/v1/chatbots/{$chatbot->id}")
        ->assertNotFound();
});

// ── Documents ─────────────────────────────────────────────────────────────────

it('cannot delete a document from another organization', function () {
    ['token' => $tokenA] = isolationOrg();
    ['org' => $orgB] = isolationOrg();

    $chatbot = Chatbot::factory()->for($orgB)->create();
    $doc = Document::factory()->for($chatbot)->create(['organization_id' => $orgB->id]);

    $this->withHeaders(isolationHeaders($tokenA))
        ->deleteJson("/api/v1/documents/{$doc->id}")
        ->assertNotFound();
});

it('cannot list documents for a chatbot from another organization', function () {
    ['token' => $tokenA] = isolationOrg();
    ['org' => $orgB] = isolationOrg();

    $chatbot = Chatbot::factory()->for($orgB)->create();

    $this->withHeaders(isolationHeaders($tokenA))
        ->getJson("/api/v1/chatbots/{$chatbot->id}/documents")
        ->assertNotFound();
});

// ── Conversations ─────────────────────────────────────────────────────────────

it('cannot view a conversation from another organization', function () {
    ['token' => $tokenA] = isolationOrg();
    ['org' => $orgB] = isolationOrg();

    $chatbot = Chatbot::factory()->for($orgB)->create();
    $conv = Conversation::factory()->withinOrganization($orgB)->create(['chatbot_id' => $chatbot->id]);

    $this->withHeaders(isolationHeaders($tokenA))
        ->getJson("/api/v1/conversations/{$conv->id}")
        ->assertNotFound();
});

it('cannot resolve a conversation from another organization', function () {
    ['token' => $tokenA] = isolationOrg();
    ['org' => $orgB] = isolationOrg();

    $chatbot = Chatbot::factory()->for($orgB)->create();
    $conv = Conversation::factory()->withinOrganization($orgB)->create(['chatbot_id' => $chatbot->id]);

    $this->withHeaders(isolationHeaders($tokenA))
        ->postJson("/api/v1/conversations/{$conv->id}/resolve")
        ->assertNotFound();
});

it('cannot take over a conversation from another organization', function () {
    ['token' => $tokenA] = isolationOrg();
    ['org' => $orgB] = isolationOrg();

    $chatbot = Chatbot::factory()->for($orgB)->create();
    $conv = Conversation::factory()->withinOrganization($orgB)->create(['chatbot_id' => $chatbot->id]);

    $this->withHeaders(isolationHeaders($tokenA))
        ->postJson("/api/v1/conversations/{$conv->id}/takeover")
        ->assertNotFound();
});

// ── API Keys ──────────────────────────────────────────────────────────────────

it('cannot revoke an API key from another organization', function () {
    ['token' => $tokenA] = isolationOrg();
    ['org' => $orgB] = isolationOrg();

    $key = ApiKey::factory()->create(['organization_id' => $orgB->id]);

    $this->withHeaders(isolationHeaders($tokenA))
        ->deleteJson("/api/v1/api-keys/{$key->id}")
        ->assertNotFound();
});

it('API key list only returns keys for the current organization', function () {
    ['org' => $orgA, 'token' => $tokenA] = isolationOrg();
    ['org' => $orgB] = isolationOrg();

    ApiKey::factory()->create(['organization_id' => $orgA->id, 'name' => 'My Key']);
    ApiKey::factory()->create(['organization_id' => $orgB->id, 'name' => 'Other Org Key']);

    $data = $this->withHeaders(isolationHeaders($tokenA))
        ->getJson('/api/v1/api-keys')
        ->assertOk()
        ->json('data');

    expect(collect($data)->pluck('name'))->toContain('My Key');
    expect(collect($data)->pluck('name'))->not->toContain('Other Org Key');
});

// ── Analytics ─────────────────────────────────────────────────────────────────

it('cannot fetch analytics for a chatbot from another organization', function () {
    ['token' => $tokenA] = isolationOrg();
    ['org' => $orgB] = isolationOrg();

    $chatbot = Chatbot::factory()->for($orgB)->create();

    $this->withHeaders(isolationHeaders($tokenA))
        ->getJson("/api/v1/chatbots/{$chatbot->id}/analytics/overview")
        ->assertNotFound();
});

// ── Team / Members ────────────────────────────────────────────────────────────

it('user A cannot remove a member from organization B', function () {
    ['token' => $tokenA] = isolationOrg();
    ['org' => $orgB, 'user' => $userB] = isolationOrg();

    $this->withHeaders(isolationHeaders($tokenA))
        ->deleteJson("/api/v1/organizations/current/members/{$userB->id}")
        ->assertForbidden(); // 403: tenant middleware resolves org A, not B
});
