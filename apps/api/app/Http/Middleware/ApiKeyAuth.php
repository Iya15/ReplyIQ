<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates requests using a ReplyIQ API key.
 *
 * Reads Authorization: Bearer rk_live_... and resolves the tenant
 * organisation from the hashed key. Applied to the /api/v1/external/* group.
 *
 * last_used_at is updated at most once per hour to avoid a DB write on
 * every request while still giving users a useful "last used" indicator.
 */
class ApiKeyAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if (! $bearer || ! str_starts_with($bearer, 'rk_live_')) {
            return response()->json([
                'error' => ['code' => 'invalid_api_key', 'message' => 'A valid API key is required.'],
            ], Response::HTTP_UNAUTHORIZED);
        }

        $apiKey = ApiKey::where('key_hash', hash('sha256', $bearer))
            ->with('organization')
            ->first();

        if (! $apiKey || ! $apiKey->organization) {
            return response()->json([
                'error' => ['code' => 'invalid_api_key', 'message' => 'API key not found or revoked.'],
            ], Response::HTTP_UNAUTHORIZED);
        }

        app()->instance('currentOrganization', $apiKey->organization);
        app()->instance('currentApiKey', $apiKey);

        // Throttle last_used_at writes to once per hour maximum.
        if ($apiKey->last_used_at === null || $apiKey->last_used_at->diffInMinutes(now()) >= 60) {
            $apiKey->update(['last_used_at' => now()]);
        }

        return $next($request);
    }
}
