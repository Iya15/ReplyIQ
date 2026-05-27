<?php

namespace App\Console\Commands;

use App\Services\Embedding\EmbeddingClient;
use Illuminate\Console\Command;

class EmbedTestCommand extends Command
{
    protected $signature = 'ai:embed {text}';

    protected $description = 'Smoke-test the embedding service with a short text string';

    public function handle(EmbeddingClient $client): int
    {
        $text = (string) $this->argument('text');
        $embedding = $client->embed($text);

        $preview = array_slice($embedding, 0, 8);
        $previewStr = implode(', ', array_map(fn(float $v) => number_format($v, 6), $preview));

        $this->info("Model:     {$client->model()}");
        $this->info("Dimension: {$client->dimension()}");
        $this->info("First 8:   [{$previewStr}]");

        return self::SUCCESS;
    }
}
