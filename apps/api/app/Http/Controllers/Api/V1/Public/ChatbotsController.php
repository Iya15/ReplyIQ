<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChatbotPublicConfigResource;
use App\Models\Chatbot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatbotsController extends Controller
{
    /**
     * GET /api/v1/public/chatbots/{public_id}/config
     *
     * Returns the widget-safe branding and behaviour config.
     * Chatbot is already verified and bound by WidgetAuth middleware.
     */
    public function config(Request $request, string $publicId): JsonResponse
    {
        // WidgetAuth already loaded the chatbot with settings — retrieve it.
        /** @var Chatbot $chatbot */
        $chatbot = app('currentChatbot');

        return $this->ok(ChatbotPublicConfigResource::make($chatbot), $request);
    }
}
