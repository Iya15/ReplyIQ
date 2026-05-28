<?php

namespace App\Http\Middleware;

use App\Models\Chatbot;
use App\Services\Public\WidgetSessionToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates requests from the embeddable widget.
 *
 * Modes (pass as middleware parameter):
 *
 *   widget:config  — origin check only; no token required.
 *                    Used for: GET /chatbots/{id}/config, POST /conversations
 *
 *   widget:token   — Bearer JWT session token + origin check.
 *                    Token issued by POST /conversations, scoped to one
 *                    (chatbot, conversation, visitor) triple for 24 h.
 *                    Used for: all mutating/polling conversation endpoints.
 *
 *   widget         — HMAC + origin check (kept for WebSocket presence auth).
 *                    Used for: POST /broadcasting/auth
 *
 * See docs/architecture/decisions/0004-widget-auth.md for the migration from
 * the M3.1 HMAC-per-request scheme to session tokens.
 */
class WidgetAuth
{
    private const REPLAY_WINDOW_SECONDS = 300; // 5 minutes

    public function handle(Request $request, Closure $next, string $mode = 'full'): Response
    {
        if ($mode === 'token') {
            return $this->handleToken($request, $next);
        }

        // ── Shared: resolve public_id + load chatbot ──────────────────────────
        $publicId = $request->route('public_id') ?? $request->input('public_id');

        if (! $publicId) {
            return $this->deny($request, 'missing_public_id', 'public_id is required.');
        }

        $chatbot = Chatbot::query()
            ->where('public_id', $publicId)
            ->with('settings')
            ->first();

        if (! $chatbot || ! $chatbot->settings) {
            return $this->deny($request, 'chatbot_not_found', 'Chatbot not found.');
        }

        $this->verifyOrigin($request, $chatbot);

        if ($mode !== 'config') {
            $result = $this->verifyHmac($request, $chatbot);
            if ($result !== null) {
                return $result;
            }
        }

        $chatbot->loadMissing('organization');
        app()->instance('currentChatbot', $chatbot);
        app()->instance('currentOrganization', $chatbot->organization);

        return $next($request);
    }

    // ── Session-token mode ────────────────────────────────────────────────────

    private function handleToken(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if (! $bearer) {
            return $this->deny($request, 'missing_token', 'Authorization: Bearer session token required.');
        }

        /** @var WidgetSessionToken $tokenSvc */
        $tokenSvc = app(WidgetSessionToken::class);
        $claims = $tokenSvc->verify($bearer);

        if (! $claims) {
            return $this->deny($request, 'invalid_token', 'Session token is invalid or expired.');
        }

        $chatbot = Chatbot::query()
            ->where('public_id', $claims['chatbot_id'])
            ->with('settings', 'organization')
            ->first();

        if (! $chatbot || ! $chatbot->settings) {
            return $this->deny($request, 'chatbot_not_found', 'Chatbot not found.');
        }

        $this->verifyOrigin($request, $chatbot);

        app()->instance('currentChatbot', $chatbot);
        app()->instance('currentOrganization', $chatbot->organization);
        // Expose JWT claims so controllers can read visitor / conversation IDs
        // without trusting request body/query — caller cannot forge these.
        app()->instance('currentVisitorId', $claims['visitor_id']);
        app()->instance('currentConversationId', $claims['conversation_id']);

        return $next($request);
    }

    // ── Origin validation ─────────────────────────────────────────────────────

    private function verifyOrigin(Request $request, Chatbot $chatbot): void
    {
        $allowedDomains = $chatbot->settings->allowed_domains ?? [];

        if (empty($allowedDomains)) {
            return;
        }

        $origin = $request->header('Origin', '');
        $host = (string) parse_url($origin, PHP_URL_HOST);

        foreach ($allowedDomains as $domain) {
            $pattern = ltrim($domain, '*.');
            if ($host === $pattern || str_ends_with($host, '.'.$pattern)) {
                return;
            }
        }

        abort(Response::HTTP_UNAUTHORIZED, json_encode([
            'error' => ['code' => 'origin_not_allowed', 'message' => 'Origin not permitted.'],
        ]));
    }

    // ── HMAC verification (broadcasting/auth only) ────────────────────────────

    private function verifyHmac(Request $request, Chatbot $chatbot): ?Response
    {
        $timestamp = $request->header('X-RIQ-Timestamp');
        $signature = $request->header('X-RIQ-Signature');

        if (! $timestamp || ! $signature) {
            return $this->deny($request, 'missing_signature', 'X-RIQ-Signature and X-RIQ-Timestamp headers are required.');
        }

        $ts = (int) $timestamp;
        if (abs(now()->timestamp - $ts) > self::REPLAY_WINDOW_SECONDS) {
            return $this->deny($request, 'timestamp_expired', 'Request timestamp is outside the 5-minute window.');
        }

        $visitorId = $request->input('visitor_id');
        if (! $visitorId) {
            return $this->deny($request, 'missing_visitor_id', 'visitor_id is required.');
        }

        $payload = "{$chatbot->public_id}:{$visitorId}:{$timestamp}";
        $expected = hash_hmac('sha256', $payload, $chatbot->settings->widget_secret);

        if (! hash_equals($expected, strtolower((string) $signature))) {
            return $this->deny($request, 'invalid_signature', 'HMAC signature verification failed.');
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function deny(Request $request, string $code, string $message): Response
    {
        return response()->json([
            'error' => ['code' => $code, 'message' => $message],
            'meta' => ['request_id' => $request->header('X-Request-Id', (string) str()->uuid())],
        ], Response::HTTP_UNAUTHORIZED);
    }
}
