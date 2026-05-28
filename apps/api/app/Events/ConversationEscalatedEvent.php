<?php

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a conversation is escalated — either by the visitor requesting
 * a human or by an agent taking over via the dashboard.
 *
 * Widget receives this on `presence-chat.{id}` and shows an in-chat banner.
 */
class ConversationEscalatedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly string $conversationId;

    public readonly string $initiatedBy;  // 'user' | 'agent'

    public readonly ?string $agentName;

    public function __construct(Conversation $conversation, string $initiatedBy, ?string $agentName = null)
    {
        $this->conversationId = (string) $conversation->id;
        $this->initiatedBy = $initiatedBy;
        $this->agentName = $agentName;
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
        return 'conversation.escalated';
    }
}
