<?php

namespace App\Observers;

use App\Models\Chatbot;

class ChatbotObserver
{
    public function created(Chatbot $chatbot): void
    {
        // All column defaults are defined in the migration; passing an empty
        // array lets the DB supply them, keeping the observer free of magic
        // defaults that could drift out of sync with the schema.
        $chatbot->settings()->create([]);
    }
}
