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
            'id'         => $conv->id,
            'chatbot_id' => $conv->chatbot_id,
            'visitor_id' => $conv->visitor_id,
            'status'     => $conv->status->value,
            'created_at' => $conv->created_at,
        ];
    }
}
