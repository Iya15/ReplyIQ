<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\LlmClient;
use Illuminate\Contracts\Foundation\Application;

class LlmClientFactory
{
    public static function resolve(Application $app): LlmClient
    {
        if (env('AI_PROVIDER') === 'ollama') {
            return new OllamaLlmClient();
        }

        return new OpenAiLlmClient($app->make(\OpenAI\Contracts\ClientContract::class));
    }
}
