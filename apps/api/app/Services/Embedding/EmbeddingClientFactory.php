<?php

namespace App\Services\Embedding;

use Illuminate\Contracts\Foundation\Application;

class EmbeddingClientFactory
{
    public static function resolve(Application $app): EmbeddingClient
    {
        if (env('AI_PROVIDER') === 'ollama') {
            return new OllamaEmbeddingClient(
                (string) config('services.ollama.host', 'http://localhost:11434'),
            );
        }

        return new OpenAiEmbeddingClient($app->make(\OpenAI\Contracts\ClientContract::class));
    }
}
