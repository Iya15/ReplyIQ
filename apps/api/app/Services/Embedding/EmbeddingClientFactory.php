<?php

namespace App\Services\Embedding;

use Illuminate\Contracts\Foundation\Application;
use OpenAI\Contracts\ClientContract;

class EmbeddingClientFactory
{
    public static function resolve(Application $app): EmbeddingClient
    {
        if (config('services.ai.provider') === 'ollama') {
            return new OllamaEmbeddingClient(
                (string) config('services.ollama.host', 'http://localhost:11434'),
            );
        }

        return new OpenAiEmbeddingClient($app->make(ClientContract::class));
    }
}
