<?php

namespace App\Http\Controllers\Api\V1\ApiKeys;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiKeys\StoreApiKeyRequest;
use App\Http\Resources\ApiKeyResource;
use App\Models\ApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ApiKeysController extends Controller
{
    public function index(Request $r): JsonResponse
    {
        return $this->ok(ApiKeyResource::collection(ApiKey::latest('created_at')->get()), $r);
    }

    public function store(StoreApiKeyRequest $r): JsonResponse
    {
        $plain = 'rk_live_' . Str::random(32);

        $key = ApiKey::create([
            'name'       => (string) $r->validated('name'),
            'key_hash'   => hash('sha256', $plain),
            'prefix'     => substr($plain, 0, 12),
            'created_by' => $r->user()?->id,
        ]);

        $key->plainKey = $plain;

        return $this->ok(ApiKeyResource::make($key), $r, Response::HTTP_CREATED);
    }

    public function destroy(Request $r, ApiKey $apiKey): JsonResponse
    {
        $apiKey->delete();

        return $this->ok(['message' => 'API key revoked.'], $r);
    }
}
