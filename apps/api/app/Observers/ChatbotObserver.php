<?php

namespace App\Observers;

use App\Models\Chatbot;

class ChatbotObserver
{
    public function created(Chatbot $chatbot): void
    {
        // Most column defaults come from the migration.
        // widget_secret has no DB default — it must be unique per chatbot.
        $chatbot->settings()->create([
            'widget_secret' => bin2hex(random_bytes(32)),
        ]);
    }
}
