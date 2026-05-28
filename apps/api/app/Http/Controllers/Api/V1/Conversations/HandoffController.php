<?php

namespace App\Http\Controllers\Api\V1\Conversations;

use App\Enums\ConversationStatus;
use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Events\ConversationEscalatedEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandoffController extends Controller
{
    /**
     * POST /api/v1/conversations/{conversation}/takeover
     *
     * Agent claims an active conversation, pausing AI replies.
     */
    public function takeover(Request $r, Conversation $conversation): JsonResponse
    {
        $this->authorize('update', $conversation);

        if ($conversation->status !== ConversationStatus::Active) {
            return $this->error('invalid_status', 'Only active conversations can be taken over.', $r, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var \App\Models\User $agent */
        $agent = $r->user();

        $conversation->update([
            'status'       => ConversationStatus::Escalated,
            'agent_id'     => $agent->id,
            'escalated_at' => now(),
        ]);

        broadcast(new ConversationEscalatedEvent($conversation, 'agent', $agent->name));

        return $this->ok(ConversationResource::make($conversation->fresh()->load('agent')), $r);
    }

    /**
     * POST /api/v1/conversations/{conversation}/agent-message
     *
     * Agent sends a message to the visitor in an escalated conversation.
     * Broadcasts so the widget receives it in real-time.
     */
    public function agentMessage(Request $r, Conversation $conversation): JsonResponse
    {
        $this->authorize('update', $conversation);

        $r->validate(['content' => ['required', 'string', 'max:4000']]);

        if ($conversation->status !== ConversationStatus::Escalated) {
            return $this->error('not_escalated', 'Conversation is not under agent control.', $r, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $message = Message::create([
            'organization_id' => $conversation->organization_id,
            'conversation_id' => $conversation->id,
            'role'            => MessageRole::Agent,
            'content'         => $r->string('content')->toString(),
            'status'          => MessageStatus::Complete,
        ]);

        broadcast(new \App\Events\MessageCompleted($message));

        return $this->ok(MessageResource::make($message), $r, Response::HTTP_CREATED);
    }
}
