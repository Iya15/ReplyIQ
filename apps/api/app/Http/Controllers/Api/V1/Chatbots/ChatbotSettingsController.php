<?php

namespace App\Http\Controllers\Api\V1\Chatbots;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chatbots\UpdateChatbotSettingsRequest;
use App\Http\Resources\ChatbotSettingsResource;
use App\Models\Chatbot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatbotSettingsController extends Controller
{
    public function show(Request $r, Chatbot $chatbot): JsonResponse
    {
        $this->authorize('view', $chatbot);

        return $this->ok(ChatbotSettingsResource::make($chatbot->settings), $r);
    }

    public function update(UpdateChatbotSettingsRequest $r, Chatbot $chatbot): JsonResponse
    {
        $this->authorize('update', $chatbot);
        $chatbot->settings->update($r->validated());

        return $this->ok(ChatbotSettingsResource::make($chatbot->settings->refresh()), $r);
    }
}
