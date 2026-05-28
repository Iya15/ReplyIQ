<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\SendMessageRequest;
use App\Http\Requests\Public\StartConversationRequest;
use App\Http\Requests\Public\UpdateVisitorRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Chatbot;
use App\Models\Conversation;
use App\Services\Public\SendMessageService;
use App\Services\Public\StartConversationService;
use App\Services\Public\WidgetSessionToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConversationsController extends Controller
{
    /**
     * POST /api/v1/public/conversations
     *
     * Start a new conversation and return a session token scoped to it.
     * No HMAC required — protected by origin check + rate-limiting only.
     *
     * Response includes `session_token` which the loader stores in localStorage
     * and passes as `Authorization: Bearer {token}` on all subsequent requests.
     */
    public function store(
        StartConversationRequest $request,
        StartConversationService $conversationSvc,
        WidgetSessionToken       $tokenSvc,
    ): JsonResponse {
        /** @var Chatbot $chatbot */
        $chatbot = app('currentChatbot');

        $visitorId    = $request->string('visitor_id')->toString();
        $conversation = $conversationSvc->execute(
            chatbot:   $chatbot,
            visitorId: $visitorId,
            sourceUrl: $request->string('source_url')->value() ?: null,
            userAgent: $request->string('user_agent')->value() ?: null,
            ip:        $request->ip(),
        );

        $token = $tokenSvc->issue($chatbot->public_id, (string) $conversation->id, $visitorId);

        return response()->json([
            'data'          => ConversationResource::make($conversation),
            'session_token' => $token,
        ], Response::HTTP_CREATED);
    }

    /**
     * PATCH /api/v1/public/conversations/{id}
     *
     * Update visitor metadata (email, name) on the conversation.
     * Called immediately when the host page invokes riq('setVisitor', {...}).
     * Protected by widget:token — visitor_id is authoritative from the JWT.
     */
    public function updateVisitor(UpdateVisitorRequest $request, string $id): JsonResponse
    {
        $conversation = $this->resolveConversation($id);

        $updates = array_filter([
            'visitor_email' => $request->string('visitor_email')->value() ?: null,
            'visitor_name'  => $request->string('visitor_name')->value() ?: null,
        ]);

        if (! empty($updates)) {
            $conversation->update($updates);
        }

        return $this->ok(ConversationResource::make($conversation->fresh()), $request);
    }

    /**
     * POST /api/v1/public/conversations/{id}/messages
     *
     * Send a user message and enqueue AI reply generation.
     */
    public function sendMessage(
        SendMessageRequest $request,
        string             $id,
        SendMessageService $service,
    ): JsonResponse {
        $conversation = $this->resolveConversation($id);

        $messages = $service->execute($conversation, $request->string('content')->toString());

        return $this->ok([
            'user_message'      => MessageResource::make($messages['user']),
            'assistant_message' => $messages['assistant']
                ? MessageResource::make($messages['assistant'])
                : null,
        ], $request, Response::HTTP_ACCEPTED);
    }

    /**
     * GET /api/v1/public/conversations/{id}/messages
     *
     * Poll for all messages in the conversation.
     */
    public function messages(Request $request, string $id): JsonResponse
    {
        $conversation = $this->resolveConversation($id);

        $messages = $conversation->messages()->get();

        return $this->ok(MessageResource::collection($messages), $request);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Load and ownership-verify a conversation.
     *
     * Under widget:token, visitor_id is read from the JWT claims bound to
     * 'currentVisitorId' by WidgetAuth — the caller cannot forge it.
     * Under the legacy widget (HMAC) mode, it falls back to the request input.
     */
    private function resolveConversation(string $id, ?string $fallbackVisitorId = null): Conversation
    {
        /** @var Chatbot $chatbot */
        $chatbot = app('currentChatbot');

        $visitorId = app()->bound('currentVisitorId')
            ? (string) app('currentVisitorId')
            : $fallbackVisitorId;

        $conversation = Conversation::where('id', $id)
            ->where('chatbot_id', $chatbot->id)
            ->first();

        if (! $conversation) {
            abort(Response::HTTP_NOT_FOUND, json_encode([
                'error' => ['code' => 'conversation_not_found', 'message' => 'Conversation not found.'],
            ]));
        }

        if ((string) $visitorId !== $conversation->visitor_id) {
            abort(Response::HTTP_FORBIDDEN, json_encode([
                'error' => ['code' => 'visitor_mismatch', 'message' => 'Visitor ID does not match.'],
            ]));
        }

        return $conversation;
    }
}
