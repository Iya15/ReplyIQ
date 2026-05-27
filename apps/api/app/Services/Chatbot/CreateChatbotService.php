<?php

namespace App\Services\Chatbot;

use App\Models\Chatbot;
use App\Models\Organization;
use App\Repositories\ChatbotRepository;
use Illuminate\Support\Facades\DB;

class CreateChatbotService
{
    public function __construct(private ChatbotRepository $chatbots) {}

    public function execute(Organization $org, array $data): Chatbot
    {
        return DB::transaction(function () use ($org, $data) {
            // public_id is generated in Chatbot::boot(); settings are created by
            // ChatbotObserver — both fire inside this transaction for atomicity.
            $chatbot = $this->chatbots->create([
                'organization_id' => $org->id,
                'name' => $data['name'],
                'language' => $data['language'] ?? 'en',
            ]);

            return $chatbot->load('settings');
        });
    }
}
