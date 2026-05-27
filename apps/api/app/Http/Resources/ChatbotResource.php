<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatbotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'public_id' => $this->public_id,
            'status' => $this->status,
            'language' => $this->language,
            'created_at' => $this->created_at,
            'settings' => ChatbotSettingsResource::make($this->whenLoaded('settings')),
        ];
    }
}
