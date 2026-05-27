<?php

namespace App\Services\Chatbot;

use App\Models\Chatbot;
use App\Repositories\ChatbotRepository;

class UpdateChatbotService
{
    public function __construct(private ChatbotRepository $chatbots) {}

    public function execute(Chatbot $chatbot, array $data): Chatbot
    {
        return $this->chatbots->update($chatbot, $data);
    }
}
