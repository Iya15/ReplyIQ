<?php

namespace App\Services\Embedding;

use App\Exceptions\EmbeddingException;
use Illuminate\Support\Facades\Cache;
use OpenAI\Contracts\ClientContract;
use OpenAI\Exceptions\TransporterException;

class OpenAiEmbeddingClient implements EmbeddingClient
{
    private const MODEL = 'text-embedding-3-small';

    private const DIMENSION = 1536;

    private const BATCH_SIZE = 100;

    private const MAX_RETRIES = 3;

    public function __construct(private readonly ClientContract $client) {}

    private const CACHE_TTL = 86400; // 24 h — query embeddings are stable per model version

    public function embed(string $text): array
    {
        // Cache query-time embeddings by (model, text) hash so repeated RAG
        // lookups for the same phrase skip the OpenAI round-trip.
        // embedBatch() is intentionally NOT cached — ingestion batches are
        // one-shot and caching them would waste Redis memory.
        $key = 'embed:' . sha1(self::MODEL . $text);

        /** @var float[] */
        return Cache::remember($key, self::CACHE_TTL, fn () => $this->embedBatch([$text])[0]);
    }

    public function embedBatch(array $texts): array
    {
        if (empty($texts)) {
            return [];
        }

        $results = [];

        foreach (array_chunk($texts, self::BATCH_SIZE) as $chunk) {
            $response = $this->callWithRetry($chunk);

            foreach ($response->embeddings as $embedding) {
                $results[] = $embedding->embedding;
            }
        }

        return $results;
    }

    public function dimension(): int
    {
        return self::DIMENSION;
    }

    public function model(): string
    {
        return self::MODEL;
    }

    private function callWithRetry(array $inputs): \OpenAI\Responses\Embeddings\CreateResponse
    {
        $attempt = 0;

        while (true) {
            try {
                return $this->client->embeddings()->create([
                    'model' => self::MODEL,
                    'input' => $inputs,
                ]);
            } catch (TransporterException $e) {
                $attempt++;

                if ($attempt >= self::MAX_RETRIES) {
                    throw new EmbeddingException(
                        "OpenAI embedding failed after {$attempt} attempts: {$e->getMessage()}",
                        0,
                        $e,
                    );
                }

                $this->retryDelay($attempt);
            }
        }
    }

    protected function retryDelay(int $attempt): void
    {
        sleep(2 ** ($attempt - 1)); // 1s, 2s, 4s
    }
}
