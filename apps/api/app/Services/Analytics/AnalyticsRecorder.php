<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsEvent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

/**
 * Buffers analytics events in a Redis list and flushes them to PostgreSQL
 * in batches for efficiency.
 *
 * record() is fire-and-forget: Redis errors are logged and swallowed so
 * analytics never breaks the critical path (conversation start, message send).
 *
 * Flush happens automatically when the buffer reaches FLUSH_THRESHOLD, and
 * also via the analytics:flush command scheduled every minute.
 */
class AnalyticsRecorder
{
    private const BUFFER_KEY      = 'analytics:buffer';
    private const FLUSH_THRESHOLD = 100;

    public function record(
        string  $eventType,
        string  $organizationId,
        ?string $chatbotId      = null,
        ?string $conversationId = null,
        array   $context        = [],
    ): void {
        try {
            $payload = (string) json_encode([
                'id'              => (string) Str::uuid(),
                'organization_id' => $organizationId,
                'chatbot_id'      => $chatbotId,
                'conversation_id' => $conversationId,
                'event_type'      => $eventType,
                'context'         => $context,
                'occurred_at'     => now()->toIso8601String(),
            ]);

            $length = (int) Redis::rpush(self::BUFFER_KEY, $payload);

            if ($length >= self::FLUSH_THRESHOLD) {
                $this->flush();
            }
        } catch (\Throwable $e) {
            Log::error('AnalyticsRecorder: failed to buffer event', [
                'event_type' => $eventType,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Drain the Redis buffer and batch-insert into PostgreSQL.
     * Returns the number of events flushed.
     *
     * Not strictly atomic — a concurrent worker may grab some events between
     * lrange and del. For analytics this is acceptable (occasional duplicate
     * is better than a blocking lock).
     */
    public function flush(): int
    {
        /** @var string[] $payloads */
        $payloads = (array) Redis::lrange(self::BUFFER_KEY, 0, -1);
        Redis::del(self::BUFFER_KEY);

        if (empty($payloads)) {
            return 0;
        }

        $rows = [];
        foreach ($payloads as $json) {
            $data = json_decode((string) $json, true);
            if (! is_array($data)) {
                continue;
            }

            $rows[] = [
                'id'              => $data['id'] ?? (string) Str::uuid(),
                'organization_id' => $data['organization_id'],
                'chatbot_id'      => $data['chatbot_id'] ?? null,
                'conversation_id' => $data['conversation_id'] ?? null,
                'event_type'      => $data['event_type'],
                'context'         => json_encode($data['context'] ?? []),
                'occurred_at'     => $data['occurred_at'],
            ];
        }

        // Batch insert in chunks of 500 to stay under parameter limits.
        foreach (array_chunk($rows, 500) as $chunk) {
            AnalyticsEvent::insert($chunk);
        }

        return count($rows);
    }
}
