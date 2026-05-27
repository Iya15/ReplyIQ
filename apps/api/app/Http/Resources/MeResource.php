<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $org = $this->currentOrganization();

        return [
            'user' => UserResource::make($this->resource),
            'current_organization' => $org ? OrganizationResource::make($org) : null,
            'role' => $org
                ? $this->organizations()
                    ->where('organizations.id', $org->id)
                    ->first()
                    ?->pivot
                    ?->role
                : null,
        ];
    }
}
