<?php

namespace App\Http\Middleware;

use App\Models\Chatbot;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates requests from the embeddable widget.
 *
 * Pass the "config" parameter to skip HMAC verification (read-only config endpoint):
 *   Route::middleware('widget:config')
 *
 * For all other public endpoints use:
 *   Route::middleware('widget')
 *
 * HMAC scheme:
 *   payload   = "{public_id}:{visitor_id}:{timestamp_unix_seconds}"
 *   signature = HMAC-SHA256(payload, chatbot.settings.widget_secret)
 *   headers   = X-RIQ-Signature: <hex>, X-RIQ-Timestamp: <unix_seconds>
 */
class WidgetAuth
{
    private const REPLAY_WINDOW_SECONDS = 300; // 5 minutes

    public function handle(Request $request, Closure $next, string $mode = 'full'): Response
    {
        // ── 1. Resolve public_id ──────────────────────────────────────────────
        // Route param takes priority (GET config endpoint).
        // All other endpoints pass it in the request body or query string.
        $publicId = $request->route('public_id')
            ?? $request->input('public_id');

        if (! $publicId) {
            return $this->deny($request, 'missing_public_id', 'public_id is required.');
        }

        // ── 2. Load the chatbot ───────────────────────────────────────────────
        $chatbot = Chatbot::query()
            ->where('public_id', $publicId)
            ->with('settings')
            ->first();

        if (! $chatbot || ! $chatbot->settings) {
            return $this->deny($request, 'chatbot_not_found', 'Chatbot not found.');
        }

        // ── 3. Origin check ───────────────────────────────────────────────────
        $this->verifyOrigin($request, $chatbot);

        // ── 4. HMAC verification (skipped for config endpoint) ────────────────
        if ($mode !== 'config') {
            $result = $this->verifyHmac($request, $chatbot);
            if ($result !== null) {
                return $result;
            }
        }

        // ── 5. Bind chatbot + tenant to the container ─────────────────────────
        $chatbot->loadMissing('organization');
        app()->instance('currentChatbot', $chatbot);
        app()->instance('currentOrganization', $chatbot->organization);

        return $next($request);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Check Origin header against the chatbot's allowed_domains list.
     * An empty list means the chatbot is in "open mode" — any origin is permitted.
     */
    private function verifyOrigin(Request $request, Chatbot $chatbot): void
    {
        $allowedDomains = $chatbot->settings->allowed_domains ?? [];

        if (empty($allowedDomains)) {
            return; // Open mode — all origins allowed.
        }

        $origin = $request->header('Origin', '');
        $host   = (string) parse_url($origin, PHP_URL_HOST);

        foreach ($allowedDomains as $domain) {
            $pattern = ltrim($domain, '*.');
            if ($host === $pattern || str_ends_with($host, '.'.$pattern)) {
                return; // Allowed.
            }
        }

        abort(Response::HTTP_UNAUTHORIZED, json_encode([
            'error' => ['code' => 'origin_not_allowed', 'message' => 'Origin not permitted.'],
        ]));
    }

    /**
     * Verify the HMAC-SHA256 signature and replay-protection timestamp.
     * Returns a JsonResponse on failure, or null on success.
     */
    private function verifyHmac(Request $request, Chatbot $chatbot): ?Response
    {
        $timestamp = $request->header('X-RIQ-Timestamp');
        $signature = $request->header('X-RIQ-Signature');

        if (! $timestamp || ! $signature) {
            return $this->deny($request, 'missing_signature', 'X-RIQ-Signature and X-RIQ-Timestamp headers are required.');
        }

        // ── Replay protection ─────────────────────────────────────────────────
        $ts = (int) $timestamp;
        if (abs(now()->timestamp - $ts) > self::REPLAY_WINDOW_SECONDS) {
            return $this->deny($request, 'timestamp_expired', 'Request timestamp is outside the 5-minute window.');
        }

        // ── Visitor id ────────────────────────────────────────────────────────
        $visitorId = $request->input('visitor_id');
        if (! $visitorId) {
            return $this->deny($request, 'missing_visitor_id', 'visitor_id is required.');
        }

        // ── Compute and compare ───────────────────────────────────────────────
        $payload  = "{$chatbot->public_id}:{$visitorId}:{$timestamp}";
        $expected = hash_hmac('sha256', $payload, $chatbot->settings->widget_secret);

        // hash_equals() prevents timing side-channel attacks.
        if (! hash_equals($expected, strtolower((string) $signature))) {
            return $this->deny($request, 'invalid_signature', 'HMAC signature verification failed.');
        }

        return null;
    }

    private function deny(Request $request, string $code, string $message): Response
    {
        return response()->json([
            'error' => ['code' => $code, 'message' => $message],
            'meta'  => ['request_id' => $request->header('X-Request-Id', (string) str()->uuid())],
        ], Response::HTTP_UNAUTHORIZED);
    }
}
