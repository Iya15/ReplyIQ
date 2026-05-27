<?php

use App\Models\Chatbot;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function authChatbot(array $settingsOverrides = []): array
{
    $org     = Organization::factory()->create();
    $chatbot = Chatbot::factory()->for($org)->create(['status' => 'active']);

    if ($settingsOverrides) {
        $chatbot->settings->update($settingsOverrides);
    }

    $chatbot->load('settings');
    return [$org, $chatbot];
}

/**
 * Build a signed request header set for the given chatbot / visitor.
 *
 * @return array{X-RIQ-Signature: string, X-RIQ-Timestamp: string}
 */
function makeWidgetHeaders(Chatbot $chatbot, string $visitorId, ?int $timestamp = null): array
{
    $ts      = (string) ($timestamp ?? now()->timestamp);
    $payload = "{$chatbot->public_id}:{$visitorId}:{$ts}";
    $sig     = hash_hmac('sha256', $payload, $chatbot->settings->widget_secret);

    return [
        'X-RIQ-Signature' => $sig,
        'X-RIQ-Timestamp' => $ts,
    ];
}

// ── HMAC validation ───────────────────────────────────────────────────────────

it('accepts a request with a valid HMAC signature', function () {
    [, $chatbot] = authChatbot();
    $visitorId   = (string) Str::uuid();

    $response = $this->postJson('/api/v1/public/conversations', [
        'public_id'  => $chatbot->public_id,
        'visitor_id' => $visitorId,
    ], makeWidgetHeaders($chatbot, $visitorId));

    $response->assertCreated();
});

it('rejects a request with an invalid HMAC signature', function () {
    [, $chatbot] = authChatbot();
    $visitorId   = (string) Str::uuid();
    $ts          = (string) now()->timestamp;

    $response = $this->postJson('/api/v1/public/conversations', [
        'public_id'  => $chatbot->public_id,
        'visitor_id' => $visitorId,
    ], [
        'X-RIQ-Signature' => 'deadbeef' . str_repeat('0', 56), // wrong signature
        'X-RIQ-Timestamp' => $ts,
    ]);

    $response->assertUnauthorized()
        ->assertJsonPath('error.code', 'invalid_signature');
});

it('rejects a request with an expired timestamp', function () {
    [, $chatbot] = authChatbot();
    $visitorId   = (string) Str::uuid();
    $staleTs     = now()->subMinutes(6)->timestamp; // 6 min ago > 5 min window

    $response = $this->postJson('/api/v1/public/conversations', [
        'public_id'  => $chatbot->public_id,
        'visitor_id' => $visitorId,
    ], makeWidgetHeaders($chatbot, $visitorId, $staleTs));

    $response->assertUnauthorized()
        ->assertJsonPath('error.code', 'timestamp_expired');
});

it('accepts a request whose timestamp is at the edge of the 5-minute window', function () {
    [, $chatbot] = authChatbot();
    $visitorId   = (string) Str::uuid();
    $edgeTs      = now()->subMinutes(4)->subSeconds(59)->timestamp; // just inside window

    $response = $this->postJson('/api/v1/public/conversations', [
        'public_id'  => $chatbot->public_id,
        'visitor_id' => $visitorId,
    ], makeWidgetHeaders($chatbot, $visitorId, $edgeTs));

    $response->assertCreated();
});

it('rejects a request with missing signature headers', function () {
    [, $chatbot] = authChatbot();

    $this->postJson('/api/v1/public/conversations', [
        'public_id'  => $chatbot->public_id,
        'visitor_id' => (string) Str::uuid(),
    ])->assertUnauthorized()
      ->assertJsonPath('error.code', 'missing_signature');
});

it('rejects a request with missing public_id', function () {
    [, $chatbot] = authChatbot();
    $visitorId   = (string) Str::uuid();

    $this->postJson('/api/v1/public/conversations', [
        'visitor_id' => $visitorId,
    ], makeWidgetHeaders($chatbot, $visitorId))
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'missing_public_id');
});

// ── Origin validation ─────────────────────────────────────────────────────────

it('allows a request from a permitted domain', function () {
    [, $chatbot] = authChatbot(['allowed_domains' => ['mystore.com']]);
    $visitorId   = (string) Str::uuid();

    $this->postJson('/api/v1/public/conversations', [
        'public_id'  => $chatbot->public_id,
        'visitor_id' => $visitorId,
    ], array_merge(
        makeWidgetHeaders($chatbot, $visitorId),
        ['Origin' => 'https://mystore.com'],
    ))->assertCreated();
});

it('blocks a request from a non-permitted domain when allowed_domains is set', function () {
    [, $chatbot] = authChatbot(['allowed_domains' => ['mystore.com']]);
    $visitorId   = (string) Str::uuid();

    $this->postJson('/api/v1/public/conversations', [
        'public_id'  => $chatbot->public_id,
        'visitor_id' => $visitorId,
    ], array_merge(
        makeWidgetHeaders($chatbot, $visitorId),
        ['Origin' => 'https://phishing.com'],
    ))->assertUnauthorized()
      ->assertJsonPath('error.code', 'origin_not_allowed');
});

it('allows any origin when allowed_domains is empty', function () {
    [, $chatbot] = authChatbot(['allowed_domains' => []]);
    $visitorId   = (string) Str::uuid();

    $this->postJson('/api/v1/public/conversations', [
        'public_id'  => $chatbot->public_id,
        'visitor_id' => $visitorId,
    ], array_merge(
        makeWidgetHeaders($chatbot, $visitorId),
        ['Origin' => 'https://any-random-site.com'],
    ))->assertCreated();
});
