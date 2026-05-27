<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired for each LLM token delta during streaming generation.
 *
 * Uses ShouldBroadcastNow so tokens are emitted immediately (not re-queued)
 * — this event is dispatched from inside GenerateAiReplyJob which is already
 * running on the 'replies' queue.
 *
 * Client event name: 'message.token'
 */
class MessageTokenStreamed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $conversationId,
        public readonly string $messageId,
        public readonly string $token,
    ) {}

    /** @return Channel[] */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('chat.' . $this->conversationId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.token';
    }
}
