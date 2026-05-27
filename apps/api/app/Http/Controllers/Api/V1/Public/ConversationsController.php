<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\SendMessageRequest;
use App\Http\Requests\Public\StartConversationRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Chatbot;
use App\Models\Conversation;
use App\Services\Public\SendMessageService;
use App\Services\Public\StartConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConversationsController extends Controller
{
    /**
     * POST /api/v1/public/conversations
     *
     * Start a new conversation for the anonymous visitor.
     */
    public function store(
        StartConversationRequest $request,
        StartConversationService $service,
    ): JsonResponse {
        /** @var Chatbot $chatbot */
        $chatbot = app('currentChatbot');

        $conversation = $service->execute(
            chatbot:   $chatbot,
            visitorId: $request->string('visitor_id')->toString(),
            sourceUrl: $request->string('source_url')->value() ?: null,
            userAgent: $request->string('user_agent')->value() ?: null,
            ip:        $request->ip(),
        );

        return $this->ok(ConversationResource::make($conversation), $request, Response::HTTP_CREATED);
    }

    /**
     * POST /api/v1/public/conversations/{id}/messages
     *
     * Send a user message and enqueue AI reply generation.
     * Returns both the user message and the pending assistant message.
     */
    public function sendMessage(
        SendMessageRequest $request,
        string             $id,
        SendMessageService $service,
    ): JsonResponse {
        $conversation = $this->resolveConversation($id, $request->input('visitor_id'));

        $messages = $service->execute($conversation, $request->string('content')->toString());

        return $this->ok([
            'user_message'      => MessageResource::make($messages['user']),
            'assistant_message' => MessageResource::make($messages['assistant']),
        ], $request, Response::HTTP_ACCEPTED);
    }

    /**
     * GET /api/v1/public/conversations/{id}/messages
     *
     * Poll for all messages in a conversation. The widget polls until the
     * assistant message status transitions from 'pending' to 'complete'.
     */
    public function messages(Request $request, string $id): JsonResponse
    {
        $conversation = $this->resolveConversation($id, $request->query('visitor_id'));

        $messages = $conversation->messages()->get();

        return $this->ok(MessageResource::collection($messages), $request);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Load and ownership-verify a conversation.
     *
     * The conversation must belong to the currentChatbot AND the visitor_id
     * in the request must match the one recorded in the conversation — this
     * is the second line of defence after HMAC signature verification.
     */
    private function resolveConversation(string $id, ?string $visitorId): Conversation
    {
        /** @var Chatbot $chatbot */
        $chatbot = app('currentChatbot');

        $conversation = Conversation::where('id', $id)
            ->where('chatbot_id', $chatbot->id)
            ->first();

        if (! $conversation) {
            abort(Response::HTTP_NOT_FOUND, json_encode([
                'error' => ['code' => 'conversation_not_found', 'message' => 'Conversation not found.'],
            ]));
        }

        // Bind visitor_id to the conversation record — prevents one visitor
        // from interacting with another visitor's conversation.
        if ((string) $visitorId !== $conversation->visitor_id) {
            abort(Response::HTTP_FORBIDDEN, json_encode([
                'error' => ['code' => 'visitor_mismatch', 'message' => 'Visitor ID does not match.'],
            ]));
        }

        return $conversation;
    }
}
