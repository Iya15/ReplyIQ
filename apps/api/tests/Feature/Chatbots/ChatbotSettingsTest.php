<?php

use App\Models\Chatbot;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function settingsOwner(): array
{
    $org = Organization::factory()->create();
    $user = User::factory()->create();
    Membership::create(['organization_id' => $org->id, 'user_id' => $user->id, 'role' => 'owner']);
    $chatbot = Chatbot::factory()->for($org)->create();

    return [$chatbot, $user->createToken('t')->plainTextToken];
}

// ── Show ──────────────────────────────────────────────────────────────────────

it('returns chatbot settings with all default fields', function () {
    [$chatbot, $token] = settingsOwner();

    $this->withToken($token)
        ->getJson("/api/v1/chatbots/{$chatbot->id}/settings")
        ->assertOk()
        ->assertJsonPath('data.primary_color', '#4F46E5')
        ->assertJsonPath('data.model', 'gpt-4o-mini')
        ->assertJsonPath('data.show_branding', true)
        ->assertJsonPath('data.allowed_domains', []);
});

// ── Update — happy paths ──────────────────────────────────────────────────────

it('updates branding and AI config settings', function () {
    [$chatbot, $token] = settingsOwner();

    $this->withToken($token)
        ->patchJson("/api/v1/chatbots/{$chatbot->id}/settings", [
            'primary_color' => '#FF0000',
            'model' => 'gpt-4o',
            'temperature' => 0.7,
            'retrieval_k' => 10,
        ])
        ->assertOk()
        ->assertJsonPath('data.primary_color', '#FF0000')
        ->assertJsonPath('data.model', 'gpt-4o')
        ->assertJsonPath('data.temperature', 0.7)
        ->assertJsonPath('data.retrieval_k', 10);
});

it('updates allowed_domains and returns them as an array', function () {
    [$chatbot, $token] = settingsOwner();

    $this->withToken($token)
        ->patchJson("/api/v1/chatbots/{$chatbot->id}/settings", [
            'allowed_domains' => ['example.com', 'sub.example.com'],
        ])
        ->assertOk()
        ->assertJsonPath('data.allowed_domains', ['example.com', 'sub.example.com']);
});

// ── Update — validation failures ──────────────────────────────────────────────

it('rejects an invalid hex color', function () {
    [$chatbot, $token] = settingsOwner();

    $this->withToken($token)
        ->patchJson("/api/v1/chatbots/{$chatbot->id}/settings", ['primary_color' => 'red'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['primary_color']);
});

it('rejects a temperature outside 0–2', function () {
    [$chatbot, $token] = settingsOwner();

    $this->withToken($token)
        ->patchJson("/api/v1/chatbots/{$chatbot->id}/settings", ['temperature' => 3.0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['temperature']);
});

it('rejects a similarity_threshold outside 0–1', function () {
    [$chatbot, $token] = settingsOwner();

    $this->withToken($token)
        ->patchJson("/api/v1/chatbots/{$chatbot->id}/settings", ['similarity_threshold' => 1.5])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['similarity_threshold']);
});

it('rejects a retrieval_k outside 1–20', function () {
    [$chatbot, $token] = settingsOwner();

    $this->withToken($token)
        ->patchJson("/api/v1/chatbots/{$chatbot->id}/settings", ['retrieval_k' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['retrieval_k']);
});

it('rejects an unsupported model', function () {
    [$chatbot, $token] = settingsOwner();

    $this->withToken($token)
        ->patchJson("/api/v1/chatbots/{$chatbot->id}/settings", ['model' => 'claude-3-opus'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['model']);
});

it('rejects a domain with a scheme in allowed_domains', function () {
    [$chatbot, $token] = settingsOwner();

    $this->withToken($token)
        ->patchJson("/api/v1/chatbots/{$chatbot->id}/settings", [
            'allowed_domains' => ['https://example.com'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['allowed_domains.0']);
});

it('rejects a domain with a path in allowed_domains', function () {
    [$chatbot, $token] = settingsOwner();

    $this->withToken($token)
        ->patchJson("/api/v1/chatbots/{$chatbot->id}/settings", [
            'allowed_domains' => ['example.com/path'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['allowed_domains.0']);
});
