<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\Chatbot;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BroadcastingAuthController extends Controller
{
    /**
     * POST /api/v1/public/broadcasting/auth
     *
     * Authenticate a widget visitor for Reverb presence channels.
     *
     * The widget:token middleware already verified the session JWT, so
     * visitor_id and conversation_id are authoritative from the container.
     *
     * Returns a Pusher-compatible presence auth response:
     *   auth         = HMAC-SHA256(socket_id + ":" + channel_name + ":" + channel_data, app_secret)
     *   channel_data = {"user_id": visitor_id, "user_info": {"type": "widget"}}
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'socket_id'    => ['required', 'string'],
            'channel_name' => ['required', 'string', 'regex:/^presence-chat\./'],
        ]);

        $socketId    = $request->input('socket_id');
        $channelName = $request->input('channel_name');

        // JWT claims bound by widget:token middleware.
        $visitorId      = (string) app('currentVisitorId');
        $conversationId = (string) app('currentConversationId');

        // Verify the channel matches the conversation in the JWT.
        $expectedChannel = 'presence-chat.' . $conversationId;
        if ($channelName !== $expectedChannel) {
            abort(Response::HTTP_FORBIDDEN, 'Channel does not match session.');
        }

        /** @var Chatbot $chatbot */
        $chatbot = app('currentChatbot');

        // Verify the conversation still exists and belongs to this chatbot.
        $exists = Conversation::where('id', $conversationId)
            ->where('chatbot_id', $chatbot->id)
            ->where('visitor_id', $visitorId)
            ->exists();

        if (! $exists) {
            abort(Response::HTTP_FORBIDDEN, 'Conversation not found.');
        }

        $channelData = (string) json_encode([
            'user_id'   => $visitorId,
            'user_info' => ['type' => 'widget'],
        ]);

        $appKey    = (string) config('broadcasting.connections.reverb.key');
        $appSecret = (string) config('broadcasting.connections.reverb.secret');

        $stringToSign = $socketId . ':' . $channelName . ':' . $channelData;
        $signature    = hash_hmac('sha256', $stringToSign, $appSecret);

        return response()->json([
            'auth'         => $appKey . ':' . $signature,
            'channel_data' => $channelData,
        ]);
    }
}
