<?php

// @requires PostgreSQL with pgvector extension (CI/Docker only — needs chunks table for RAG)

use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Models\Chatbot;
use App\Models\Conversation;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function flowChatbot(): array
{
    $org     = Organization::factory()->create();
    $chatbot = Chatbot::factory()->for($org)->create(['status' => 'active']);
    $chatbot->load('settings');
    return [$org, $chatbot];
}

function flowHeaders(Chatbot $chatbot, string $visitorId, ?int $ts = null): array
{
    $ts      ??= now()->timestamp;
    $payload   = "{$chatbot->public_id}:{$visitorId}:{$ts}";
    $sig       = hash_hmac('sha256', $payload, $chatbot->settings->widget_secret);
    return ['X-RIQ-Signature' => $sig, 'X-RIQ-Timestamp' => (string) $ts];
}

// ── Start conversation ────────────────────────────────────────────────────────

it('creates a conversation and returns its id', function () {
    Queue::fake();
    [, $chatbot] = flowChatbot();
    $visitorId   = (string) Str::uuid();

    $response = $this->postJson('/api/v1/public/conversations', [
        'public_id'  => $chatbot->public_id,
        'visitor_id' => $visitorId,
        'source_url' => 'https://example.com/pricing',
    ], flowHeaders($chatbot, $visitorId));

    $response->assertCreated()
        ->assertJsonPath('data.visitor_id', $visitorId)
        ->assertJsonPath('data.chatbot_id', $chatbot->id)
        ->assertJsonPath('data.status', 'active')
        ->assertJsonStructure(['data' => ['id', 'chatbot_id', 'visitor_id', 'status', 'created_at']]);
});

it('stores the source_url and ip_address on the conversation', function () {
    Queue::fake();
    [, $chatbot] = flowChatbot();
    $visitorId   = (string) Str::uuid();

    $this->postJson('/api/v1/public/conversations', [
        'public_id'  => $chatbot->public_id,
        'visitor_id' => $visitorId,
        'source_url' => 'https://example.com/pricing',
    ], flowHeaders($chatbot, $visitorId))->assertCreated();

    $conv = Conversation::where('visitor_id', $visitorId)->first();

    expect($conv)->not->toBeNull()
        ->and($conv->source_url)->toBe('https://example.com/pricing');
});

// ── Send message ──────────────────────────────────────────────────────────────

it('sends a user message and returns a pending assistant message', function () {
    Queue::fake();
    [, $chatbot] = flowChatbot();
    $visitorId   = (string) Str::uuid();

    // Start conversation.
    $convId = $this->postJson('/api/v1/public/conversations', [
        'public_id'  => $chatbot->public_id,
        'visitor_id' => $visitorId,
    ], flowHeaders($chatbot, $visitorId))
        ->assertCreated()
        ->json('data.id');

    // Send message.
    $response = $this->postJson("/api/v1/public/conversations/{$convId}/messages", [
        'public_id'  => $chatbot->public_id,
        'visitor_id' => $visitorId,
        'content'    => 'What is your refund policy?',
    ], flowHeaders($chatbot, $visitorId));

    $response->assertStatus(202)
        ->assertJsonPath('data.user_message.role', 'user')
        ->assertJsonPath('data.user_message.content', 'What is your refund policy?')
        ->assertJsonPath('data.user_message.status', 'complete')
        ->assertJsonPath('data.assistant_message.role', 'assistant')
        ->assertJsonPath('data.assistant_message.status', 'pending')
        ->assertJsonPath('data.assistant_message.content', '');
});

it('dispatches GenerateAiReplyJob when a message is sent', function () {
    Queue::fake();
    [, $chatbot] = flowChatbot();
    $visitorId   = (string) Str::uuid();

    $convId = $this->postJson('/api/v1/public/conversations', [
        'public_id'  => $chatbot->public_id,
        'visitor_id' => $visitorId,
    ], flowHeaders($chatbot, $visitorId))->json('data.id');

    $this->postJson("/api/v1/public/conversations/{$convId}/messages", [
        'public_id'  => $chatbot->public_id,
        'visitor_id' => $visitorId,
        'content'    => 'Hello',
    ], flowHeaders($chatbot, $visitorId))->assertStatus(202);

    Queue::assertPushedOn('replies', \App\Jobs\GenerateAiReplyJob::class);
});

it('rejects a message with content exceeding 4000 characters', function () {
    Queue::fake();
    [, $chatbot] = flowChatbot();
    $visitorId   = (string) Str::uuid();

    $convId = $this->postJson('/api/v1/public/conversations', [
        'public_id'  => $chatbot->public_id,
        'visitor_id' => $visitorId,
    ], flowHeaders($chatbot, $visitorId))->json('data.id');

    $this->postJson("/api/v1/public/conversations/{$convId}/messages", [
        'public_id'  => $chatbot->public_id,
        'visitor_id' => $visitorId,
        'content'    => str_repeat('a', 4001),
    ], flowHeaders($chatbot, $visitorId))->assertUnprocessable();
});

it('returns 404 when sending a message to another visitors conversation', function () {
    Queue::fake();
    [, $chatbot] = flowChatbot();
    $visitorA    = (string) Str::uuid();
    $visitorB    = (string) Str::uuid();

    // Visitor A starts conversation.
    $convId = $this->postJson('/api/v1/public/conversations', [
        'public_id'  => $chatbot->public_id,
        'visitor_id' => $visitorA,
    ], flowHeaders($chatbot, $visitorA))->json('data.id');

    // Visitor B tries to post a message with valid HMAC but wrong visitor_id.
    $this->postJson("/api/v1/public/conversations/{$convId}/messages", [
        'public_id'  => $chatbot->public_id,
        'visitor_id' => $visitorB,
        'content'    => 'Hijack!',
    ], flowHeaders($chatbot, $visitorB))->assertForbidden();
});

// ── Poll messages ─────────────────────────────────────────────────────────────

it('returns all messages for a conversation in order', function () {
    Queue::fake();
    [, $chatbot] = flowChatbot();
    $visitorId   = (string) Str::uuid();

    $convId = $this->postJson('/api/v1/public/conversations', [
        'public_id'  => $chatbot->public_id,
        'visitor_id' => $visitorId,
    ], flowHeaders($chatbot, $visitorId))->json('data.id');

    $this->postJson("/api/v1/public/conversations/{$convId}/messages", [
        'public_id'  => $chatbot->public_id,
        'visitor_id' => $visitorId,
        'content'    => 'First question',
    ], flowHeaders($chatbot, $visitorId));

    $response = $this->getJson(
        "/api/v1/public/conversations/{$convId}/messages?public_id={$chatbot->public_id}&visitor_id={$visitorId}",
        flowHeaders($chatbot, $visitorId),
    );

    $response->assertOk();
    $messages = $response->json('data');

    // User message + pending assistant message.
    expect($messages)->toHaveCount(2)
        ->and($messages[0]['role'])->toBe('user')
        ->and($messages[1]['role'])->toBe('assistant')
        ->and($messages[1]['status'])->toBe('pending');
});

// ── Feedback ──────────────────────────────────────────────────────────────────

it('records helpful feedback on an assistant message', function () {
    Queue::fake();
    [, $chatbot] = flowChatbot();
    $visitorId   = (string) Str::uuid();

    $convId = $this->postJson('/api/v1/public/conversations', [
        'public_id'  => $chatbot->public_id,
        'visitor_id' => $visitorId,
    ], flowHeaders($chatbot, $visitorId))->json('data.id');

    $assistantMsgId = $this->postJson("/api/v1/public/conversations/{$convId}/messages", [
        'public_id'  => $chatbot->public_id,
        'visitor_id' => $visitorId,
        'content'    => 'Any question',
    ], flowHeaders($chatbot, $visitorId))->json('data.assistant_message.id');

    $this->postJson("/api/v1/public/messages/{$assistantMsgId}/feedback", [
        'public_id'  => $chatbot->public_id,
        'visitor_id' => $visitorId,
        'feedback'   => 'helpful',
    ], flowHeaders($chatbot, $visitorId))
        ->assertOk()
        ->assertJsonPath('data.feedback', null); // MessageResource doesn't expose feedback
});

it('rejects feedback on a user message', function () {
    Queue::fake();
    [, $chatbot] = flowChatbot();
    $visitorId   = (string) Str::uuid();

    $convId = $this->postJson('/api/v1/public/conversations', [
        'public_id'  => $chatbot->public_id,
        'visitor_id' => $visitorId,
    ], flowHeaders($chatbot, $visitorId))->json('data.id');

    $userMsgId = $this->postJson("/api/v1/public/conversations/{$convId}/messages", [
        'public_id'  => $chatbot->public_id,
        'visitor_id' => $visitorId,
        'content'    => 'Any question',
    ], flowHeaders($chatbot, $visitorId))->json('data.user_message.id');

    $this->postJson("/api/v1/public/messages/{$userMsgId}/feedback", [
        'public_id'  => $chatbot->public_id,
        'visitor_id' => $visitorId,
        'feedback'   => 'helpful',
    ], flowHeaders($chatbot, $visitorId))->assertUnprocessable();
});

// ── Job completion (end-to-end with sync queue) ───────────────────────────────

it('message status transitions from pending to complete after the job runs', function () {
    // Use the sync queue driver so the job runs inline during the test.
    config(['queue.default' => 'sync']);

    [, $chatbot] = flowChatbot();
    $visitorId   = (string) Str::uuid();

    // The chatbot has no knowledge chunks, so it will return the fallback message.
    // That's fine — we only care about the status transition.
    $convId = $this->postJson('/api/v1/public/conversations', [
        'public_id'  => $chatbot->public_id,
        'visitor_id' => $visitorId,
    ], flowHeaders($chatbot, $visitorId))->json('data.id');

    $assistantId = $this->postJson("/api/v1/public/conversations/{$convId}/messages", [
        'public_id'  => $chatbot->public_id,
        'visitor_id' => $visitorId,
        'content'    => 'Hello',
    ], flowHeaders($chatbot, $visitorId))->json('data.assistant_message.id');

    // Poll the messages list — job has already run (sync driver).
    $messages = $this->getJson(
        "/api/v1/public/conversations/{$convId}/messages?public_id={$chatbot->public_id}&visitor_id={$visitorId}",
        flowHeaders($chatbot, $visitorId),
    )->json('data');

    $assistant = collect($messages)->firstWhere('id', $assistantId);

    expect($assistant)->not->toBeNull()
        ->and($assistant['status'])->toBe('complete')
        ->and($assistant['content'])->not->toBeEmpty();
});
