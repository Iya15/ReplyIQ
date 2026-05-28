<?php

namespace App\Http\Resources;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Represents a User returned through the org->users() BelongsToMany relation.
 * The pivot carries role + joined_at from the memberships table.
 */
class MemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        /** @var Membership|null $pivot */
        $pivot = $user->pivot; /** @phpstan-ignore-line */

        return [
            'id' => (string) $user->id,
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'avatar_url' => $user->avatar_url,
            'role' => (string) ($pivot?->role ?? 'member'),
            'joined_at' => $pivot?->created_at
                ? (string) $pivot->created_at
                : null,
        ];
    }
}
