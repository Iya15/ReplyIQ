<?php

namespace App\Http\Resources;

use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiKeyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var ApiKey $key */
        $key = $this->resource;

        return [
            'id'           => (string) $key->id,
            'name'         => (string) $key->name,
            'prefix'       => (string) $key->prefix,
            'last_used_at' => $key->last_used_at,
            'created_at'   => $key->created_at,
            // Included only immediately after creation — never shown again.
            'key'          => $this->when($key->plainKey !== null, $key->plainKey),
        ];
    }
}
