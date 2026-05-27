<?php

namespace App\Services\Chatbot;

use App\Models\Chatbot;

class GenerateEmbedCodeService
{
    public function execute(Chatbot $chatbot): string
    {
        return $chatbot->regenerateEmbedCode();
    }
}
