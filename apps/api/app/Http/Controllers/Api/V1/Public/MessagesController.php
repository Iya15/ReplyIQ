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
            'feedback' => ['required', 'string', 'in:helpful,not_helpful'],
        ]);

        /** @var Chatbot $chatbot */
        $chatbot = app('currentChatbot');

        // Under widget:token, visitor_id is authoritative from the JWT.
        // Falls back to request body for backwards compatibility.
        $visitorId = app()->bound('currentVisitorId')
            ? (string) app('currentVisitorId')
            : $request->input('visitor_id');

        $message = Message::whereHas('conversation', function ($query) use ($chatbot, $visitorId) {
            $query->where('chatbot_id', $chatbot->id)
                ->where('visitor_id', $visitorId);
        })->find($id);

        if (! $message) {
            abort(Response::HTTP_NOT_FOUND, json_encode([
                'error' => ['code' => 'message_not_found', 'message' => 'Message not found.'],
            ]));
        }

        if ($message->role->value !== 'assistant') {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, json_encode([
                'error' => ['code' => 'invalid_feedback_target', 'message' => 'Feedback can only be applied to assistant messages.'],
            ]));
        }

        $message->update(['feedback' => $request->input('feedback')]);

        return $this->ok(MessageResource::make($message->fresh()), $request);
    }
}
