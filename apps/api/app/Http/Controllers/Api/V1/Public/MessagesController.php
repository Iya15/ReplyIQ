<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\Chatbot;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MessagesController extends Controller
{
    /**
     * POST /api/v1/public/messages/{id}/feedback
     *
     * Record visitor feedback (helpful / not_helpful) on an assistant message.
     */
    public function feedback(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'public_id'  => ['required', 'string'],
            'visitor_id' => ['required', 'string'],
            'feedback'   => ['required', 'string', 'in:helpful,not_helpful'],
        ]);

        /** @var Chatbot $chatbot */
        $chatbot = app('currentChatbot');

        // Load message → verify it belongs to this chatbot's conversation.
        $message = Message::whereHas('conversation', function ($query) use ($chatbot, $request) {
            $query->where('chatbot_id', $chatbot->id)
                  ->where('visitor_id', $request->input('visitor_id'));
        })->find($id);

        if (! $message) {
            abort(Response::HTTP_NOT_FOUND, json_encode([
                'error' => ['code' => 'message_not_found', 'message' => 'Message not found.'],
            ]));
        }

        // Only assistant messages can receive feedback.
        if ($message->role->value !== 'assistant') {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, json_encode([
                'error' => ['code' => 'invalid_feedback_target', 'message' => 'Feedback can only be applied to assistant messages.'],
            ]));
        }

        $message->update(['feedback' => $request->input('feedback')]);

        return $this->ok(MessageResource::make($message->fresh()), $request);
    }
}
