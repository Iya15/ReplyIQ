<?php

// @requires PostgreSQL (CI/Docker only)

use App\Enums\ConversationStatus;
use App\Jobs\GenerateAiReplyJob;
use App\Models\Chatbot;
use App\Models\Conversation;
use App\Models\Membership;
use App\Models\Message;
use App\Models\Organization;
use App\Models\User;
use App\Services\Ai\RagPipeline;
use App\Services\Analytics\AnalyticsRecorder;
use App\Services\Public\WidgetSessionToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function handoffTeam(): array
{
    $org = Organization::factory()->create();
    $agent = User::factory()->create();
    $chatbot = Chatbot::factory()->for($org)->create(['status' => 'active']);
    $chatbot->load('settings');

    Membership::factory()->create(['organization_id' => $org->id, 'user_id' => $agent->id, 'role' => 'owner']);

    $conv = Conversation::factory()->create([
        'organization_id' => $org->id,
        'chatbot_id' => $chatbot->id,
        'visitor_id' => (string) Str::uuid(),
        'status' => 'active',
    ]);

    $token = $agent->createToken('test')->plainTextToken;

    return compact('org', 'agent', 'chatbot', 'conv', 'token');
}

function handoffBearer(string $token): array
{
    return ['Authorization' => "Bearer {$token}"];
}

// ── POST /conversations/{id}/takeover ─────────────────────────────────────────

it('agent can take over an active conversation', function () {
    ['conv' => $conv, 'agent' => $agent, 'token' => $token] = handoffTeam();

    $this->withHeaders(handoffBearer($token))
        ->postJson("/api/v1/conversations/{$conv->id}/takeover")
        ->assertOk()
        ->assertJsonPath('data.status', 'escalated');

    expect($conv->fresh()->status)->toBe(ConversationStatus::Escalated);
    expect($conv->fresh()->agent_id)->toBe($agent->id);
});

it('agent cannot take over an already escalated conversation', function () {
    ['conv' => $conv, 'token' => $token] = handoffTeam();
    $conv->update(['status' => 'escalated', 'escalated_at' => now()]);

    $this->withHeaders(handoffBearer($token))
        ->postJson("/api/v1/conversations/{$conv->id}/takeover")
        ->assertUnprocessable();
});

it('agent cannot take over a resolved conversation', function () {
    ['conv' => $conv, 'token' => $token] = handoffTeam();
    $conv->update(['status' => 'resolved', 'resolved_at' => now()]);

    $this->withHeaders(handoffBearer($token))
        ->postJson("/api/v1/conversations/{$conv->id}/takeover")
        ->assertUnprocessable();
});

// ── POST /conversations/{id}/agent-message ────────────────────────────────────

it('agent can send a message to an escalated conversation', function () {
    ['conv' => $conv, 'agent' => $agent, 'token' => $token] = handoffTeam();
    $conv->update(['status' => 'escalated', 'agent_id' => $agent->id, 'escalated_at' => now()]);

    $this->withHeaders(handoffBearer($token))
        ->postJson("/api/v1/conversations/{$conv->id}/agent-message", ['content' => 'Hi, I can help!'])
        ->assertCreated()
        ->assertJsonPath('data.role', 'agent')
        ->assertJsonPath('data.content', 'Hi, I can help!');

    expect(Message::where('conversation_id', $conv->id)->where('role', 'agent')->exists())->toBeTrue();
});

it('agent cannot send message to non-escalated conversation', function () {
    ['conv' => $conv, 'token' => $token] = handoffTeam();

    $this->withHeaders(handoffBearer($token))
        ->postJson("/api/v1/conversations/{$conv->id}/agent-message", ['content' => 'Hello'])
        ->assertUnprocessable();
});

// ── POST /public/conversations/{id}/request-human ────────────────────────────

it('visitor can request a human agent', function () {
    Notification::fake();
    ['chatbot' => $chatbot, 'conv' => $conv] = handoffTeam();

    $tokenSvc = app(WidgetSessionToken::class);
    $widgetJwt = $tokenSvc->issue($chatbot->public_id, (string) $conv->id, $conv->visitor_id);

    $this->withHeader('Authorization', "Bearer {$widgetJwt}")
        ->postJson("/api/v1/public/conversations/{$conv->id}/request-human")
        ->assertOk()
        ->assertJsonPath('data.status', 'escalated');

    expect($conv->fresh()->status)->toBe(ConversationStatus::Escalated);
});

it('request-human is idempotent when already escalated', function () {
    Notification::fake();
    ['chatbot' => $chatbot, 'conv' => $conv] = handoffTeam();
    $conv->update(['status' => 'escalated', 'escalated_at' => now()]);

    $tokenSvc = app(WidgetSessionToken::class);
    $widgetJwt = $tokenSvc->issue($chatbot->public_id, (string) $conv->id, $conv->visitor_id);

    $this->withHeader('Authorization', "Bearer {$widgetJwt}")
        ->postJson("/api/v1/public/conversations/{$conv->id}/request-human")
        ->assertOk()
        ->assertJsonPath('data.status', 'escalated');
});

// ── GenerateAiReplyJob guard ──────────────────────────────────────────────────

it('GenerateAiReplyJob skips and removes placeholder when conversation is escalated', function () {
    Queue::fake();
    ['org' => $org, 'chatbot' => $chatbot, 'conv' => $conv] = handoffTeam();

    // Escalate before job runs (race condition scenario)
    $conv->update(['status' => 'escalated', 'escalated_at' => now()]);

    $assistantMsg = Message::factory()->create([
        'organization_id' => $org->id,
        'conversation_id' => $conv->id,
        'role' => 'assistant',
        'content' => '',
        'status' => 'pending',
    ]);

    $job = new GenerateAiReplyJob($conv, $assistantMsg);
    $job->handle(app(RagPipeline::class), app(AnalyticsRecorder::class));

    expect(Message::find($assistantMsg->id))->toBeNull();
});
