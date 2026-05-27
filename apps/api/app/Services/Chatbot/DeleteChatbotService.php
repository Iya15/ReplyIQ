<?php

namespace App\Services\Chatbot;

use App\Models\Chatbot;
use App\Repositories\ChatbotRepository;

class DeleteChatbotService
{
    public function __construct(private ChatbotRepository $chatbots) {}

    public function execute(Chatbot $chatbot): void
    {
        // Hard delete — DB ON DELETE CASCADE removes documents, conversations,
        // chunks, and analytics when those tables are added in future milestones.
        $this->chatbots->delete($chatbot);
    }
}
