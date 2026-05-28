<?php

// @requires PostgreSQL with pgvector extension (CI/Docker only — needs chunks table for RAG)

use App\Events\MessageCompleted;
use App\Events\MessageTokenStreamed;
use App\Jobs\GenerateAiReplyJob;
use App\Models\Chatbot;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Organization;
use App\Models\User;
use App\Services\Public\WidgetSessionToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function broadcastChatbot(): array
{
    $org     = Organization::factory()->create();
    $chatbot = Chatbot::factory()->for($org)->create(['status' => 'active']);
    $chatbot->load('settings');
    return [$org, $chatbot];
}

function broadcastToken(Chatbot $chatbot, string $conversationId, string $visitorId): string
{
    return app(WidgetSessionToken::class)->issue($chatbot->public_id, $conversationId, $visitorId);
}

// ── MessageTokenStreamed event ─────────────────────────────────────────────────

it('broadcasts MessageTokenStreamed events during streaming generation', function () {
    Event::fake([MessageTokenStreamed::class, MessageCompleted::class]);

    [$org, $chatbot] = broadcastChatbot();

    $conversation = Conversation::factory()
        ->withinOrganization($org)
        ->create(['chatbot_id' => $chatbot->id, 'visitor_id' => 'test-visitor']);

    $assistantMessage = Message::factory()
        ->for($conversation)
        ->pending()
        ->assistant()
        ->create(['organization_id' => $org->id]);

    app()->bind(
        \App\Services\Ai\Contracts\LlmClient::class,
        fn () => new class implements \App\Services\Ai\Contracts\LlmClient {
            public function chat(array $messages, string $model, int $maxTokens, float $temperature): \App\DataObjects\LlmResponse
            {
                return new \App\DataObjects\LlmResponse('Hello world', 2);
            }

            public function embed(string $text, string $model): array { return array_fill(0, 1536, 0.0); }

            public function chatStream(array $messages, string $model, int $maxTokens, float $temperature): \Generator
            {
                yield 'Hello';
                yield ' world';
                yield '!';
            }
        },
    );

    (new GenerateAiReplyJob($conversation, $assistantMessage))
        ->handle(app(\App\Services\Ai\RagPipeline::class));

    Event::assertDispatched(MessageTokenStreamed::class, 3);

    Event::assertDispatched(MessageTokenStreamed::class, function (MessageTokenStreamed $e) use ($conversation, $assistantMessage) {
        return $e->conversationId === $conversation->id
            && $e->messageId === $assistantMessage->id
            && in_array($e->token, ['Hello', ' world', '!'], true);
    });
});

// ── MessageCompleted event ─────────────────────────────────────────────────────

it('broadcasts MessageCompleted after the assistant message is persisted', function () {
    Event::fake([MessageTokenStreamed::class, MessageCompleted::class]);

    [$org, $chatbot] = broadcastChatbot();

    $conversation = Conversation::factory()
        ->withinOrganization($org)
        ->create(['chatbot_id' => $chatbot->id, 'visitor_id' => 'test-visitor']);

    $assistantMessage = Message::factory()
        ->for($conversation)
        ->pending()
        ->assistant()
        ->create(['organization_id' => $org->id]);

    app()->bind(
        \App\Services\Ai\Contracts\LlmClient::class,
        fn () => new class implements \App\Services\Ai\Contracts\LlmClient {
            public function chat(array $messages, string $model, int $maxTokens, float $temperature): \App\DataObjects\LlmResponse
            {
                return new \App\DataObjects\LlmResponse('Hello world', 2);
            }

            public function embed(string $text, string $model): array { return array_fill(0, 1536, 0.0); }

            public function chatStream(array $messages, string $model, int $maxTokens, float $temperature): \Generator
            {
                yield 'Hello world';
            }
        },
    );

    (new GenerateAiReplyJob($conversation, $assistantMessage))
        ->handle(app(\App\Services\Ai\RagPipeline::class));

    Event::assertDispatched(MessageCompleted::class, 1);

    Event::assertDispatched(MessageCompleted::class, function (MessageCompleted $e) use ($conversation) {
        return $e->conversationId === $conversation->id
            && $e->status === 'complete';
    });
});

it('MessageCompleted carries the full content and broadcasts on the correct channel', function () {
    $conversation = Conversation::factory()->create();
    $message      = Message::factory()
        ->for($conversation)
        ->assistant()
        ->create([
            'organization_id' => $conversation->organization_id,
            'content'         => 'Final answer.',
            'status'          => \App\Enums\MessageStatus::Complete,
        ]);

    $event = new MessageCompleted($message);

    expect($event->conversationId)->toBe($conversation->id)
        ->and($event->messageId)->toBe($message->id)
        ->and($event->content)->toBe('Final answer.')
        ->and($event->status)->toBe('complete')
        ->and($event->broadcastAs())->toBe('message.completed')
        ->and($event->broadcastOn()[0])->toBeInstanceOf(\Illuminate\Broadcasting\PresenceChannel::class);
});

it('MessageTokenStreamed broadcasts on the correct channel with the right event name', function () {
    $convId = (string) Str::uuid();
    $msgId  = (string) Str::uuid();
    $event  = new MessageTokenStreamed($convId, $msgId, 'tok');

    expect($event->broadcastAs())->toBe('message.token')
        ->and($event->broadcastOn()[0])->toBeInstanceOf(\Illuminate\Broadcasting\PresenceChannel::class)
        ->and($event->token)->toBe('tok');
});

// ── Widget broadcasting auth endpoint (widget:token) ──────────────────────────

it('returns a signed presence auth response for a valid session token', function () {
    [, $chatbot] = broadcastChatbot();
    $visitorId   = (string) Str::uuid();

    $conversation = Conversation::factory()->create([
        'chatbot_id'      => $chatbot->id,
        'organization_id' => $chatbot->organization_id,
        'visitor_id'      => $visitorId,
    ]);

    $token    = broadcastToken($chatbot, (string) $conversation->id, $visitorId);
    $socketId = '123.456';
    $channel  = 'presence-chat.' . $conversation->id;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson('/api/v1/public/broadcasting/auth', [
            'socket_id'    => $socketId,
            'channel_name' => $channel,
        ]);

    $response->assertOk()
        ->assertJsonStructure(['auth', 'channel_data']);

    $auth = $response->json('auth');
    expect($auth)->toContain(':')
        ->and(explode(':', $auth, 2)[0])->toBe((string) config('broadcasting.connections.reverb.key'));

    $channelData = json_decode($response->json('channel_data'), true);
    expect($channelData['user_id'])->toBe($visitorId)
        ->and($channelData['user_info']['type'])->toBe('widget');
});

it('returns 403 when the channel name does not match the conversation in the token', function () {
    [, $chatbot] = broadcastChatbot();
    $visitorId   = (string) Str::uuid();

    $convA = Conversation::factory()->create([
        'chatbot_id'      => $chatbot->id,
        'organization_id' => $chatbot->organization_id,
        'visitor_id'      => $visitorId,
    ]);
    $convB = Conversation::factory()->create([
        'chatbot_id'      => $chatbot->id,
        'organization_id' => $chatbot->organization_id,
        'visitor_id'      => $visitorId,
    ]);

    // Token is scoped to convA but request asks for convB's channel.
    $token = broadcastToken($chatbot, (string) $convA->id, $visitorId);

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson('/api/v1/public/broadcasting/auth', [
            'socket_id'    => '123.456',
            'channel_name' => 'presence-chat.' . $convB->id,
        ])->assertForbidden();
});

it('returns 401 when no Bearer token is provided for broadcasting auth', function () {
    $this->postJson('/api/v1/public/broadcasting/auth', [
        'socket_id'    => '123.456',
        'channel_name' => 'presence-chat.' . Str::uuid(),
    ])->assertUnauthorized()
      ->assertJsonPath('error.code', 'missing_token');
});

it('rejects a non-presence channel name', function () {
    [, $chatbot] = broadcastChatbot();
    $visitorId   = (string) Str::uuid();

    $conversation = Conversation::factory()->create([
        'chatbot_id'      => $chatbot->id,
        'organization_id' => $chatbot->organization_id,
        'visitor_id'      => $visitorId,
    ]);

    $token = broadcastToken($chatbot, (string) $conversation->id, $visitorId);

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson('/api/v1/public/broadcasting/auth', [
            'socket_id'    => '123.456',
            'channel_name' => 'private-chat.something', // not presence-chat.*
        ])->assertUnprocessable();
});

// ── Dashboard presence channel auth (channels.php) ────────────────────────────

it('allows a dashboard user who is a member of the owning organization to join the channel', function () {
    $org  = Organization::factory()->create();
    $user = User::factory()->create();
    $user->organizations()->attach($org->id, ['role' => 'admin']);

    $chatbot      = Chatbot::factory()->for($org)->create();
    $conversation = Conversation::factory()->create([
        'chatbot_id'      => $chatbot->id,
        'organization_id' => $org->id,
        'visitor_id'      => 'visitor-123',
    ]);

    $token = $user->createToken('test')->plainTextToken;

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson('/broadcasting/auth', [
            'socket_id'    => '111.222',
            'channel_name' => 'presence-chat.' . $conversation->id,
        ])->assertOk();
});

it('denies a dashboard user who does not belong to the owning organization', function () {
    $org      = Organization::factory()->create();
    $otherOrg = Organization::factory()->create();
    $user     = User::factory()->create();
    $user->organizations()->attach($otherOrg->id, ['role' => 'member']);

    $chatbot      = Chatbot::factory()->for($org)->create();
    $conversation = Conversation::factory()->create([
        'chatbot_id'      => $chatbot->id,
        'organization_id' => $org->id,
        'visitor_id'      => 'visitor-456',
    ]);

    $token = $user->createToken('test')->plainTextToken;

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson('/broadcasting/auth', [
            'socket_id'    => '111.222',
            'channel_name' => 'presence-chat.' . $conversation->id,
        ])->assertForbidden();
});
