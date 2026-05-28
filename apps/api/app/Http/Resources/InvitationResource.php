<?php

namespace App\Http\Resources;

use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Invitation $inv */
        $inv = $this->resource;

        return [
            'id'           => (string) $inv->id,
            'email'        => (string) $inv->email,
            'role'         => (string) $inv->role,
            'expires_at'   => $inv->expires_at,
            'created_at'   => $inv->created_at,
            'invited_by'   => $this->whenLoaded('invitedBy', fn () => UserResource::make($inv->invitedBy)),
        ];
    }
}
