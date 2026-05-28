<?php

// @requires PostgreSQL (CI/Docker only — needs replyiq_test database)

use App\Models\AnalyticsEvent;
use App\Models\Chatbot;
use App\Models\Organization;
use App\Services\Analytics\AnalyticsRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// ── record() ──────────────────────────────────────────────────────────────────

it('record() pushes a JSON payload to the Redis buffer', function () {
    Redis::shouldReceive('rpush')
        ->once()
        ->withArgs(function (string $key, string $payload): bool {
            $data = json_decode($payload, true);

            return str_contains($key, 'analytics')
                && $data['event_type'] === 'conversation_started'
                && $data['organization_id'] === 'org-uuid'
                && $data['chatbot_id'] === 'chatbot-uuid';
        })
        ->andReturn(1);

    $recorder = app(AnalyticsRecorder::class);
    $recorder->record('conversation_started', 'org-uuid', 'chatbot-uuid', null, ['source_url' => 'https://example.com']);
});

it('record() auto-flushes when the buffer reaches the threshold', function () {
    // Simulate rpush returning 100 (threshold reached).
    Redis::shouldReceive('rpush')->once()->andReturn(100);

    // flush() uses lrange + del.
    Redis::shouldReceive('lrange')->once()->andReturn([]);
    Redis::shouldReceive('del')->once()->andReturn(1);

    $recorder = app(AnalyticsRecorder::class);
    $recorder->record('message_sent', 'org-uuid', 'chatbot-uuid', 'conv-uuid');
});

it('record() silently swallows Redis connection errors', function () {
    Redis::shouldReceive('rpush')->once()->andThrow(new RuntimeException('Connection refused'));

    $recorder = app(AnalyticsRecorder::class);

    // Should not throw.
    expect(fn () => $recorder->record('widget_opened', 'org-uuid', 'chatbot-uuid'))->not->toThrow(Throwable::class);
});

// ── flush() ───────────────────────────────────────────────────────────────────

it('flush() inserts buffered events into the database', function () {
    $orgId = (string) Str::uuid();
    $chatbotId = (string) Str::uuid();

    // Create real org + chatbot so FK constraints are satisfied.
    $org = Organization::factory()->create(['id' => $orgId]);
    Chatbot::factory()->for($org)->create(['id' => $chatbotId]);

    $payload = json_encode([
        'id' => (string) Str::uuid(),
        'organization_id' => $orgId,
        'chatbot_id' => $chatbotId,
        'conversation_id' => null,
        'event_type' => 'widget_opened',
        'context' => ['source_url' => 'https://example.com'],
        'occurred_at' => now()->toIso8601String(),
    ]);

    Redis::shouldReceive('pipeline')
        ->once()
        ->andReturnUsing(function (callable $callback) use ($payload): array {
            $pipe = Mockery::mock();
            $pipe->shouldReceive('lrange')->once();
            $pipe->shouldReceive('del')->once();
            $callback($pipe);

            return [[$payload], 1];
        });

    $recorder = app(AnalyticsRecorder::class);
    $count = $recorder->flush();

    expect($count)->toBe(1);
    expect(AnalyticsEvent::withoutGlobalScopes()->count())->toBe(1);

    $event = AnalyticsEvent::withoutGlobalScopes()->first();
    expect($event->event_type)->toBe('widget_opened')
        ->and($event->organization_id)->toBe($orgId)
        ->and($event->chatbot_id)->toBe($chatbotId);
});

it('flush() returns 0 and inserts nothing when the buffer is empty', function () {
    Redis::shouldReceive('pipeline')
        ->once()
        ->andReturnUsing(function (callable $callback): array {
            $pipe = Mockery::mock();
            $pipe->shouldReceive('lrange')->once();
            $pipe->shouldReceive('del')->once();
            $callback($pipe);

            return [[], 1];
        });

    $recorder = app(AnalyticsRecorder::class);
    $count = $recorder->flush();

    expect($count)->toBe(0);
    expect(AnalyticsEvent::withoutGlobalScopes()->count())->toBe(0);
});

// ── analytics:flush command ───────────────────────────────────────────────────

it('analytics:flush command runs flush() and outputs the count', function () {
    Redis::shouldReceive('pipeline')
        ->once()
        ->andReturnUsing(function (callable $callback): array {
            $pipe = Mockery::mock();
            $pipe->shouldReceive('lrange')->once();
            $pipe->shouldReceive('del')->once();
            $callback($pipe);

            return [[], 1];
        });

    $this->artisan('analytics:flush')->assertSuccessful();
});
