<?php

use App\Models\Chatbot;
use App\Models\Conversation;
use App\Models\Organization;
use App\Services\Public\WidgetSessionToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function authChatbot(array $settingsOverrides = []): array
{
    $org = Organization::factory()->create();
    $chatbot = Chatbot::factory()->for($org)->create(['status' => 'active']);

    if ($settingsOverrides) {
        $chatbot->settings->update($settingsOverrides);
    }

    $chatbot->load('settings');

    return [$org, $chatbot];
}

function makeSessionToken(Chatbot $chatbot, string $conversationId, string $visitorId): string
{
    return app(WidgetSessionToken::class)->issue($chatbot->public_id, $conversationId, $visitorId);
}

// ── POST /conversations — widget:config (no HMAC required) ───────────────────

it('creates a conversation without auth headers and returns a session token', function () {
    [, $chatbot] = authChatbot();
    $visitorId = (string) Str::uuid();

    $response = $this->postJson('/api/v1/public/conversations', [
        'public_id' => $chatbot->public_id,
        'visitor_id' => $visitorId,
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['id'], 'session_token']);

    expect($response->json('session_token'))->toBeString()->not->toBeEmpty();
});

// ── Token auth — widget:token ─────────────────────────────────────────────────

it('returns 401 when no Bearer token is provided on a token-protected endpoint', function () {
    [, $chatbot] = authChatbot();
    $visitorId = (string) Str::uuid();

    $conversation = Conversation::factory()->create([
        'chatbot_id' => $chatbot->id,
        'organization_id' => $chatbot->organization_id,
        'visitor_id' => $visitorId,
    ]);

    $this->getJson("/api/v1/public/conversations/{$conversation->id}/messages")
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'missing_token');
});

it('returns 401 when the Bearer token is malformed', function () {
    [, $chatbot] = authChatbot();
    $visitorId = (string) Str::uuid();

    $conversation = Conversation::factory()->create([
        'chatbot_id' => $chatbot->id,
        'organization_id' => $chatbot->organization_id,
        'visitor_id' => $visitorId,
    ]);

    $this->withHeaders(['Authorization' => 'Bearer not.a.valid.jwt'])
        ->getJson("/api/v1/public/conversations/{$conversation->id}/messages")
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'invalid_token');
});

it('returns 401 when the Bearer token is signed with the wrong key', function () {
    [, $chatbot] = authChatbot();
    $visitorId = (string) Str::uuid();

    $conversation = Conversation::factory()->create([
        'chatbot_id' => $chatbot->id,
        'organization_id' => $chatbot->organization_id,
        'visitor_id' => $visitorId,
    ]);

    $fakeKey = InMemory::plainText(str_repeat('x', 32));
    $config = Configuration::forSymmetricSigner(new Sha256, $fakeKey);
    $badToken = $config->builder()
        ->issuedBy('replyiq.widget')
        ->issuedAt(new DateTimeImmutable)
        ->expiresAt((new DateTimeImmutable)->modify('+24 hours'))
        ->withClaim('cid', $chatbot->public_id)
        ->withClaim('cnv', (string) $conversation->id)
        ->withClaim('vid', $visitorId)
        ->getToken($config->signer(), $config->signingKey())
        ->toString();

    $this->withHeaders(['Authorization' => "Bearer {$badToken}"])
        ->getJson("/api/v1/public/conversations/{$conversation->id}/messages")
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'invalid_token');
});

it('accepts a valid session token on a token-protected endpoint', function () {
    [, $chatbot] = authChatbot();
    $visitorId = (string) Str::uuid();

    $conversation = Conversation::factory()->create([
        'chatbot_id' => $chatbot->id,
        'organization_id' => $chatbot->organization_id,
        'visitor_id' => $visitorId,
    ]);

    $token = makeSessionToken($chatbot, (string) $conversation->id, $visitorId);

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson("/api/v1/public/conversations/{$conversation->id}/messages")
        ->assertOk();
});

// ── Origin validation — widget:config mode (POST /conversations) ──────────────

it('allows a request from a permitted domain', function () {
    [, $chatbot] = authChatbot(['allowed_domains' => ['mystore.com']]);
    $visitorId = (string) Str::uuid();

    $this->postJson('/api/v1/public/conversations', [
        'public_id' => $chatbot->public_id,
        'visitor_id' => $visitorId,
    ], ['Origin' => 'https://mystore.com'])->assertCreated();
});

it('blocks a request from a non-permitted domain when allowed_domains is set', function () {
    [, $chatbot] = authChatbot(['allowed_domains' => ['mystore.com']]);
    $visitorId = (string) Str::uuid();

    $this->postJson('/api/v1/public/conversations', [
        'public_id' => $chatbot->public_id,
        'visitor_id' => $visitorId,
    ], ['Origin' => 'https://phishing.com'])
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'origin_not_allowed');
});

it('allows any origin when allowed_domains is empty', function () {
    [, $chatbot] = authChatbot(['allowed_domains' => []]);
    $visitorId = (string) Str::uuid();

    $this->postJson('/api/v1/public/conversations', [
        'public_id' => $chatbot->public_id,
        'visitor_id' => $visitorId,
    ], ['Origin' => 'https://any-random-site.com'])->assertCreated();
});
