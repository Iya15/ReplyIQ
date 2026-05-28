<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once when the assistant message is fully generated and persisted.
 *
 * The client receives the final content + metadata so it can replace the
 * optimistic placeholder without an extra polling round-trip.
 *
 * Client event name: 'message.completed'
 */
class MessageCompleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly string $conversationId;

    public readonly string $messageId;

    public readonly string $content;

    public readonly string $status; // 'complete' | 'failed'

    /** @var array<mixed> */
    public readonly array $sources;

    public readonly ?float $confidence;

    public function __construct(Message $message)
    {
        $this->conversationId = (string) $message->conversation_id;
        $this->messageId = (string) $message->id;
        $this->content = $message->content;
        $this->status = $message->status->value;
        $this->sources = $message->sources ?? [];
        $this->confidence = $message->confidence;
    }

    /** @return Channel[] */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('chat.'.$this->conversationId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.completed';
    }
}
