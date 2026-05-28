<?php

namespace App\Http\Controllers\Api\V1\Conversations;

use App\Enums\ConversationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Chatbot;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationsController extends Controller
{
    public function index(Request $r, Chatbot $chatbot): JsonResponse
    {
        $this->authorize('view', $chatbot);

        $q = $chatbot->conversations()->with('latestMessage')->withCount('messages')->latest();

        if ($r->filled('status')) {
            $q->where('status', $r->input('status'));
        }
        if ($r->filled('search')) {
            $q->where('visitor_id', 'like', '%' . $r->input('search') . '%');
        }

        return $this->paginated($q->paginate(20), ConversationResource::class, $r);
    }

    public function show(Request $r, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        return $this->ok(ConversationResource::make($conversation->loadCount('messages')), $r);
    }

    public function messages(Request $r, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        return $this->ok(MessageResource::collection($conversation->messages), $r);
    }

    public function resolve(Request $r, Conversation $conversation): JsonResponse
    {
        $this->authorize('update', $conversation);

        $conversation->update(['status' => ConversationStatus::Resolved, 'resolved_at' => now()]);

        return $this->ok(ConversationResource::make($conversation), $r);
    }
}
