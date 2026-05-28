<?php

use App\Models\Chatbot;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function configChatbot(array $settingsOverrides = []): array
{
    $org = Organization::factory()->create();
    $chatbot = Chatbot::factory()->for($org)->create(['status' => 'active']);

    if ($settingsOverrides) {
        $chatbot->settings->update($settingsOverrides);
    }

    $chatbot->load('settings');

    return [$org, $chatbot];
}

function configUrl(Chatbot $chatbot): string
{
    return "/api/v1/public/chatbots/{$chatbot->public_id}/config";
}

// ── Config shape ──────────────────────────────────────────────────────────────

it('returns the expected public config shape', function () {
    [, $chatbot] = configChatbot([
        'primary_color' => '#FF0000',
        'welcome_message' => 'Hello there!',
        'position' => 'bottom-left',
        'theme' => 'dark',
    ]);

    $response = $this->getJson(configUrl($chatbot));

    $response->assertOk()
        ->assertJsonPath('data.public_id', $chatbot->public_id)
        ->assertJsonPath('data.name', $chatbot->name)
        ->assertJsonPath('data.primary_color', '#FF0000')
        ->assertJsonPath('data.welcome_message', 'Hello there!')
        ->assertJsonPath('data.position', 'bottom-left')
        ->assertJsonPath('data.theme', 'dark');
});

it('omits private fields from the config response', function () {
    [, $chatbot] = configChatbot(['ai_persona' => 'You are a pirate.']);

    $response = $this->getJson(configUrl($chatbot));
    $data = $response->json('data');

    $response->assertOk();

    // These fields must NEVER appear in the public config.
    expect($data)->not->toHaveKey('ai_persona')
        ->not->toHaveKey('model')
        ->not->toHaveKey('temperature')
        ->not->toHaveKey('max_tokens')
        ->not->toHaveKey('similarity_threshold')
        ->not->toHaveKey('retrieval_k')
        ->not->toHaveKey('fallback_message')
        ->not->toHaveKey('allowed_domains')
        ->not->toHaveKey('widget_secret');
});

it('returns 404 for an unknown public_id', function () {
    $this->getJson('/api/v1/public/chatbots/nonexistent-id/config')
        ->assertUnauthorized(); // WidgetAuth returns 401, not 404, to avoid enumeration.
});

it('includes branding fields with sensible defaults', function () {
    [, $chatbot] = configChatbot();

    $response = $this->getJson(configUrl($chatbot));

    $response->assertOk()
        ->assertJsonPath('data.font_family', 'Inter')
        ->assertJsonPath('data.show_branding', true)
        ->assertJsonPath('data.placeholder_text', 'Ask me anything...');
});

// ── Origin validation ─────────────────────────────────────────────────────────

it('allows any origin when allowed_domains is empty', function () {
    [, $chatbot] = configChatbot(['allowed_domains' => []]);

    $this->getJson(configUrl($chatbot), ['Origin' => 'https://random-site.com'])
        ->assertOk();
});

it('allows a request from an allowed domain', function () {
    [, $chatbot] = configChatbot(['allowed_domains' => ['example.com']]);

    $this->getJson(configUrl($chatbot), ['Origin' => 'https://example.com'])
        ->assertOk();
});

it('allows a request from a subdomain of an allowed domain', function () {
    [, $chatbot] = configChatbot(['allowed_domains' => ['example.com']]);

    $this->getJson(configUrl($chatbot), ['Origin' => 'https://app.example.com'])
        ->assertOk();
});

it('blocks a request from a non-allowed domain', function () {
    [, $chatbot] = configChatbot(['allowed_domains' => ['example.com']]);

    $this->getJson(configUrl($chatbot), ['Origin' => 'https://evil.com'])
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'origin_not_allowed');
});
