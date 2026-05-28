<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Enums\ConversationStatus;
use App\Events\ConversationEscalatedEvent;
use App\Http\Controllers\Controller;
use App\Models\Chatbot;
use App\Models\Conversation;
use App\Models\Membership;
use App\Models\User;
use App\Notifications\EscalationNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HandoffController extends Controller
{
    /**
     * POST /api/v1/public/conversations/{id}/request-human
     *
     * Visitor requests a human agent. Escalates the conversation, broadcasts
     * the status change, and notifies org admins by email (+ Slack if configured).
     */
    public function requestHuman(Request $r, string $id): JsonResponse
    {
        /** @var Chatbot $chatbot */
        $chatbot = app('currentChatbot');

        if ((string) app('currentConversationId') !== $id) {
            abort(403);
        }

        $conversation = Conversation::find($id);
        if (! $conversation) {
            abort(404);
        }

        // Idempotent — already escalated or resolved.
        if ($conversation->status !== ConversationStatus::Active) {
            return $this->ok(['status' => $conversation->status->value], $r);
        }

        $conversation->update([
            'status' => ConversationStatus::Escalated,
            'escalated_at' => now(),
        ]);

        broadcast(new ConversationEscalatedEvent($conversation, 'user'));

        // Notify all org admins and owners by email.
        $this->notifyAdmins($conversation, $chatbot);

        return $this->ok(['status' => 'escalated'], $r);
    }

    private function notifyAdmins(Conversation $conversation, Chatbot $chatbot): void
    {
        $userIds = Membership::where('organization_id', $conversation->organization_id)
            ->whereIn('role', ['owner', 'admin'])
            ->pluck('user_id');

        User::whereIn('id', $userIds)
            ->get()
            ->each(fn (User $user) => $user->notify(new EscalationNotification($conversation, $chatbot)));

        EscalationNotification::notifySlack($conversation, $chatbot);
    }
}
