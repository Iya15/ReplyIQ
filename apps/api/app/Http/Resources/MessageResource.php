<?php

namespace App\Http\Resources;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Message $msg */
        $msg = $this->resource;

        return [
            'id'         => $msg->id,
            'role'       => $msg->role->value,
            'content'    => $msg->content,
            'status'     => $msg->status->value,
            'sources'    => $msg->sources ?? [],
            'confidence' => $msg->confidence,
            'created_at' => $msg->created_at,
        ];
    }
}
