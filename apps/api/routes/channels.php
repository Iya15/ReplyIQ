<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Presence channel: chat.{conversationId}
 *
 * Authorized for dashboard users via the standard /broadcasting/auth endpoint
 * (Sanctum). Widget visitors use the custom HMAC auth at
 * /api/v1/public/broadcasting/auth which bypasses this callback.
 *
 * Returns ['id', 'name'] so other presence members can identify the agent.
 *
 * @return array<string, string>|false
 */
Broadcast::channel('chat.{conversationId}', function ($user, string $conversationId): array|false {
    $conversation = Conversation::find($conversationId);

    if (! $conversation) {
        return false;
    }

    // User must be a member of the organization that owns this conversation.
    $isMember = $user->organizations()
        ->where('organizations.id', $conversation->organization_id)
        ->exists();

    if (! $isMember) {
        return false;
    }

    return ['id' => $user->id, 'name' => $user->name];
});
