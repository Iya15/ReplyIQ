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
use Database\Factories\MessageFactory;
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

function broadcastHeaders(Chatbot $chatbot, string $visitorId, ?int $ts = null): array
{
    $ts    ??= now()->timestamp;
    $payload = "{$chatbot->public_id}:{$visitorId}:{$ts}";
    $sig     = hash_hmac('sha256', $payload, $chatbot->settings->widget_secret);
    return ['X-RIQ-Signature' => $sig, 'X-RIQ-Timestamp' => (string) $ts];
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

    // Inject a streaming LLM stub that emits 3 tokens via onToken.
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

    // Three token events — one per yielded chunk.
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

// ── Widget broadcasting auth endpoint ─────────────────────────────────────────

it('returns a signed presence auth response for a valid widget request', function () {
    [, $chatbot] = broadcastChatbot();
    $visitorId   = (string) Str::uuid();

    // Create a conversation that the visitor owns.
    $conversation = Conversation::factory()->create([
        'chatbot_id'      => $chatbot->id,
        'organization_id' => $chatbot->organization_id,
        'visitor_id'      => $visitorId,
    ]);

    $socketId    = '123.456';
    $channelName = 'presence-chat.' . $conversation->id;
    $headers     = broadcastHeaders($chatbot, $visitorId);

    $response = $this->postJson('/api/v1/public/broadcasting/auth', [
        'public_id'    => $chatbot->public_id,
        'visitor_id'   => $visitorId,
        'socket_id'    => $socketId,
        'channel_name' => $channelName,
    ], $headers);

    $response->assertOk()
        ->assertJsonStructure(['auth', 'channel_data']);

    // Verify the auth string format: "app_key:hmac_signature".
    $auth = $response->json('auth');
    expect($auth)->toContain(':')
        ->and(explode(':', $auth, 2)[0])->toBe((string) config('broadcasting.connections.reverb.key'));

    // Verify channel_data contains user_id = visitor_id.
    $channelData = json_decode($response->json('channel_data'), true);
    expect($channelData['user_id'])->toBe($visitorId)
        ->and($channelData['user_info']['type'])->toBe('widget');
});

it('returns 403 when the visitor does not own the conversation', function () {
    [, $chatbot] = broadcastChatbot();
    $visitorA    = (string) Str::uuid();
    $visitorB    = (string) Str::uuid();

    // Conversation belongs to Visitor A.
    $conversation = Conversation::factory()->create([
        'chatbot_id'      => $chatbot->id,
        'organization_id' => $chatbot->organization_id,
        'visitor_id'      => $visitorA,
    ]);

    // Visitor B tries to auth for Visitor A's conversation with their own HMAC.
    $headers = broadcastHeaders($chatbot, $visitorB);

    $this->postJson('/api/v1/public/broadcasting/auth', [
        'public_id'    => $chatbot->public_id,
        'visitor_id'   => $visitorB,
        'socket_id'    => '123.456',
        'channel_name' => 'presence-chat.' . $conversation->id,
    ], $headers)->assertForbidden();
});

it('returns 401 when the HMAC signature is invalid for broadcasting auth', function () {
    [, $chatbot] = broadcastChatbot();
    $visitorId   = (string) Str::uuid();

    $conversation = Conversation::factory()->create([
        'chatbot_id'      => $chatbot->id,
        'organization_id' => $chatbot->organization_id,
        'visitor_id'      => $visitorId,
    ]);

    $ts      = (string) now()->timestamp;
    $headers = [
        'X-RIQ-Signature' => 'invalidsignature',
        'X-RIQ-Timestamp' => $ts,
    ];

    $this->postJson('/api/v1/public/broadcasting/auth', [
        'public_id'    => $chatbot->public_id,
        'visitor_id'   => $visitorId,
        'socket_id'    => '123.456',
        'channel_name' => 'presence-chat.' . $conversation->id,
    ], $headers)->assertUnauthorized();
});

it('rejects a non-presence channel name', function () {
    [, $chatbot] = broadcastChatbot();
    $visitorId   = (string) Str::uuid();

    $headers = broadcastHeaders($chatbot, $visitorId);

    $this->postJson('/api/v1/public/broadcasting/auth', [
        'public_id'    => $chatbot->public_id,
        'visitor_id'   => $visitorId,
        'socket_id'    => '123.456',
        'channel_name' => 'private-chat.something',  // not presence-chat.*
    ], $headers)->assertUnprocessable();
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
