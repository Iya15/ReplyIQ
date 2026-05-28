<?php

// @requires PostgreSQL with pgvector extension (CI/Docker only — needs chunks table for RAG)

use App\Jobs\GenerateAiReplyJob;
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
    $org = Organization::factory()->create();
    $chatbot = Chatbot::factory()->for($org)->create(['status' => 'active']);
    $chatbot->load('settings');

    return [$org, $chatbot];
}

/**
 * Start a conversation via the HTTP endpoint and return
 * ['conv_id' => ..., 'token' => ..., 'visitor_id' => ...].
 */
function flowStart(Chatbot $chatbot, ?string $visitorId = null): array
{
    $visitorId ??= (string) Str::uuid();

    $response = test()->postJson('/api/v1/public/conversations', [
        'public_id' => $chatbot->public_id,
        'visitor_id' => $visitorId,
    ]);

    return [
        'conv_id' => $response->json('data.id'),
        'token' => $response->json('session_token'),
        'visitor_id' => $visitorId,
    ];
}

function flowBearer(string $token): array
{
    return ['Authorization' => "Bearer {$token}"];
}

// ── Start conversation ────────────────────────────────────────────────────────

it('creates a conversation and returns a session token', function () {
    Queue::fake();
    [, $chatbot] = flowChatbot();

    $response = $this->postJson('/api/v1/public/conversations', [
        'public_id' => $chatbot->public_id,
        'visitor_id' => (string) Str::uuid(),
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'chatbot_id', 'visitor_id', 'status', 'created_at'], 'session_token']);

    expect($response->json('session_token'))->toBeString()->not->toBeEmpty();
});

it('stores the source_url on the conversation', function () {
    Queue::fake();
    [, $chatbot] = flowChatbot();
    $visitorId = (string) Str::uuid();

    $this->postJson('/api/v1/public/conversations', [
        'public_id' => $chatbot->public_id,
        'visitor_id' => $visitorId,
        'source_url' => 'https://example.com/pricing',
    ])->assertCreated();

    $conv = Conversation::where('visitor_id', $visitorId)->first();

    expect($conv)->not->toBeNull()
        ->and($conv->source_url)->toBe('https://example.com/pricing');
});

// ── Update visitor ────────────────────────────────────────────────────────────

it('updateVisitor persists visitor email and name on the conversation', function () {
    Queue::fake();
    [, $chatbot] = flowChatbot();
    $session = flowStart($chatbot);

    $this->withHeaders(flowBearer($session['token']))
        ->patchJson("/api/v1/public/conversations/{$session['conv_id']}", [
            'visitor_email' => 'alice@example.com',
            'visitor_name' => 'Alice',
        ])
        ->assertOk();

    $conv = Conversation::find($session['conv_id']);
    expect($conv->visitor_email)->toBe('alice@example.com')
        ->and($conv->visitor_name)->toBe('Alice');
});

// ── Send message ──────────────────────────────────────────────────────────────

it('sends a user message and returns a pending assistant message', function () {
    Queue::fake();
    [, $chatbot] = flowChatbot();
    $session = flowStart($chatbot);

    $response = $this->withHeaders(flowBearer($session['token']))
        ->postJson("/api/v1/public/conversations/{$session['conv_id']}/messages", [
            'content' => 'What is your refund policy?',
        ]);

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
    $session = flowStart($chatbot);

    $this->withHeaders(flowBearer($session['token']))
        ->postJson("/api/v1/public/conversations/{$session['conv_id']}/messages", [
            'content' => 'Hello',
        ])->assertStatus(202);

    Queue::assertPushedOn('replies', GenerateAiReplyJob::class);
});

it('rejects a message with content exceeding 4000 characters', function () {
    Queue::fake();
    [, $chatbot] = flowChatbot();
    $session = flowStart($chatbot);

    $this->withHeaders(flowBearer($session['token']))
        ->postJson("/api/v1/public/conversations/{$session['conv_id']}/messages", [
            'content' => str_repeat('a', 4001),
        ])->assertUnprocessable();
});

it('returns 403 when a visitor uses their token to post to another visitors conversation', function () {
    Queue::fake();
    [, $chatbot] = flowChatbot();
    $sessionA = flowStart($chatbot);
    $sessionB = flowStart($chatbot);

    // Visitor B's token has visitor B's visitor_id; conv A belongs to visitor A.
    $this->withHeaders(flowBearer($sessionB['token']))
        ->postJson("/api/v1/public/conversations/{$sessionA['conv_id']}/messages", [
            'content' => 'Hijack!',
        ])->assertForbidden();
});

// ── Poll messages ─────────────────────────────────────────────────────────────

it('returns all messages for a conversation in order', function () {
    Queue::fake();
    [, $chatbot] = flowChatbot();
    $session = flowStart($chatbot);

    $this->withHeaders(flowBearer($session['token']))
        ->postJson("/api/v1/public/conversations/{$session['conv_id']}/messages", [
            'content' => 'First question',
        ]);

    $response = $this->withHeaders(flowBearer($session['token']))
        ->getJson("/api/v1/public/conversations/{$session['conv_id']}/messages");

    $response->assertOk();
    $messages = $response->json('data');

    expect($messages)->toHaveCount(2)
        ->and($messages[0]['role'])->toBe('user')
        ->and($messages[1]['role'])->toBe('assistant')
        ->and($messages[1]['status'])->toBe('pending');
});

// ── Feedback ──────────────────────────────────────────────────────────────────

it('records helpful feedback on an assistant message', function () {
    Queue::fake();
    [, $chatbot] = flowChatbot();
    $session = flowStart($chatbot);

    $assistantMsgId = $this->withHeaders(flowBearer($session['token']))
        ->postJson("/api/v1/public/conversations/{$session['conv_id']}/messages", [
            'content' => 'Any question',
        ])->json('data.assistant_message.id');

    $this->withHeaders(flowBearer($session['token']))
        ->postJson("/api/v1/public/messages/{$assistantMsgId}/feedback", [
            'feedback' => 'helpful',
        ])->assertOk();
});

it('rejects feedback on a user message', function () {
    Queue::fake();
    [, $chatbot] = flowChatbot();
    $session = flowStart($chatbot);

    $userMsgId = $this->withHeaders(flowBearer($session['token']))
        ->postJson("/api/v1/public/conversations/{$session['conv_id']}/messages", [
            'content' => 'Any question',
        ])->json('data.user_message.id');

    $this->withHeaders(flowBearer($session['token']))
        ->postJson("/api/v1/public/messages/{$userMsgId}/feedback", [
            'feedback' => 'helpful',
        ])->assertUnprocessable();
});

// ── Job completion (end-to-end with sync queue) ───────────────────────────────

it('message status transitions from pending to complete after the job runs', function () {
    config(['queue.default' => 'sync']);

    [, $chatbot] = flowChatbot();
    $session = flowStart($chatbot);

    $assistantId = $this->withHeaders(flowBearer($session['token']))
        ->postJson("/api/v1/public/conversations/{$session['conv_id']}/messages", [
            'content' => 'Hello',
        ])->json('data.assistant_message.id');

    $messages = $this->withHeaders(flowBearer($session['token']))
        ->getJson("/api/v1/public/conversations/{$session['conv_id']}/messages")
        ->json('data');

    $assistant = collect($messages)->firstWhere('id', $assistantId);

    expect($assistant)->not->toBeNull()
        ->and($assistant['status'])->toBe('complete')
        ->and($assistant['content'])->not->toBeEmpty();
});
