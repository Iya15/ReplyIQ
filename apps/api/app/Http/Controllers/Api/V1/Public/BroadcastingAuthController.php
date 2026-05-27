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
     * The WidgetAuth middleware runs first and validates the HMAC signature,
     * so by the time we get here we know the request is legitimate.
     *
     * Returns a standard Pusher-compatible presence auth response so that
     * Laravel Echo can subscribe to `presence-chat.{conversationId}`.
     *
     * Auth string format (Pusher protocol):
     *   signature = HMAC-SHA256(socket_id + ":" + channel_name + ":" + channel_data, app_secret)
     *   auth      = app_key + ":" + signature
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'socket_id'    => ['required', 'string'],
            'channel_name' => ['required', 'string', 'regex:/^presence-chat\./'],
            'visitor_id'   => ['required', 'string'],
        ]);

        $socketId    = $request->input('socket_id');
        $channelName = $request->input('channel_name');
        $visitorId   = $request->input('visitor_id');

        // Extract conversationId from "presence-chat.{id}".
        $conversationId = (string) substr($channelName, strlen('presence-chat.'));

        /** @var Chatbot $chatbot */
        $chatbot = app('currentChatbot');

        // Verify the conversation belongs to this chatbot and this visitor.
        $conversation = Conversation::where('chatbot_id', $chatbot->id)
            ->where('visitor_id', $visitorId)
            ->find($conversationId);

        if (! $conversation) {
            abort(Response::HTTP_FORBIDDEN, 'Conversation not found.');
        }

        // Build presence channel data (identifies the visitor in the channel).
        $channelData = (string) json_encode([
            'user_id'   => $visitorId,
            'user_info' => ['type' => 'widget'],
        ]);

        // Generate Pusher-compatible HMAC auth signature.
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
