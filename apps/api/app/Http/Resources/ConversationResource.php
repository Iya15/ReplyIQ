<?php

namespace App\Http\Resources;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Conversation $conv */
        $conv = $this->resource;

        return [
            'id'           => $conv->id,
            'chatbot_id'   => $conv->chatbot_id,
            'visitor_id'   => $conv->visitor_id,
            'source_url'   => $conv->source_url,
            'country'      => $conv->country,
            'status'       => $conv->status->value,
            'resolved_at'  => $conv->resolved_at,
            'escalated_at' => $conv->escalated_at,
            'agent_id'     => $conv->agent_id,
            'agent_name'   => $this->whenLoaded('agent', fn () => $conv->agent?->name),
            'created_at'   => $conv->created_at,
            'message_count' => $this->whenCounted('messages'),
            'last_message' => $this->whenLoaded('latestMessage', function () use ($conv) {
                $msg = $conv->latestMessage;
                if (! $msg) return null;
                return [
                    'id'         => $msg->id,
                    'role'       => $msg->role->value,
                    'content'    => mb_substr($msg->content, 0, 120),
                    'created_at' => $msg->created_at,
                ];
            }),
        ];
    }
}
