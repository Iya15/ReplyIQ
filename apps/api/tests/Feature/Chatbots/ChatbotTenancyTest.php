<?php

use App\Models\Chatbot;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function tenancyUserWithOrg(): array
{
    $org = Organization::factory()->create();
    $user = User::factory()->create();
    Membership::create(['organization_id' => $org->id, 'user_id' => $user->id, 'role' => 'owner']);

    return [$org, $user->createToken('t')->plainTextToken];
}

// ── Index isolation ───────────────────────────────────────────────────────────

it('index returns only the authenticated user\'s own chatbots', function () {
    [$orgA, $tokenA] = tenancyUserWithOrg();
    [$orgB] = tenancyUserWithOrg();

    Chatbot::factory()->count(2)->for($orgA)->create();
    Chatbot::factory()->count(3)->for($orgB)->create();

    $this->withToken($tokenA)
        ->getJson('/api/v1/chatbots')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

// ── Show isolation ────────────────────────────────────────────────────────────

it('show returns 404 for a chatbot belonging to another organization', function () {
    [, $tokenA] = tenancyUserWithOrg();
    [$orgB] = tenancyUserWithOrg();
    $chatbotB = Chatbot::factory()->for($orgB)->create();

    $this->withToken($tokenA)
        ->getJson("/api/v1/chatbots/{$chatbotB->id}")
        ->assertNotFound();
});

// ── Update isolation ──────────────────────────────────────────────────────────

it('update returns 404 for a chatbot belonging to another organization', function () {
    [, $tokenA] = tenancyUserWithOrg();
    [$orgB] = tenancyUserWithOrg();
    $chatbotB = Chatbot::factory()->for($orgB)->create();

    $this->withToken($tokenA)
        ->patchJson("/api/v1/chatbots/{$chatbotB->id}", ['name' => 'Hijacked'])
        ->assertNotFound();
});

// ── Delete isolation ──────────────────────────────────────────────────────────

it('delete returns 404 for a chatbot belonging to another organization', function () {
    [, $tokenA] = tenancyUserWithOrg();
    [$orgB] = tenancyUserWithOrg();
    $chatbotB = Chatbot::factory()->for($orgB)->create();

    $this->withToken($tokenA)
        ->deleteJson("/api/v1/chatbots/{$chatbotB->id}")
        ->assertNotFound();
});

// ── Embed-code isolation ──────────────────────────────────────────────────────

it('embed-code returns 404 for a chatbot belonging to another organization', function () {
    [, $tokenA] = tenancyUserWithOrg();
    [$orgB] = tenancyUserWithOrg();
    $chatbotB = Chatbot::factory()->for($orgB)->create();

    $this->withToken($tokenA)
        ->getJson("/api/v1/chatbots/{$chatbotB->id}/embed-code")
        ->assertNotFound();
});
