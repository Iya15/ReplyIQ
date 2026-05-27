<?php

namespace App\Console\Commands;

use App\Models\Chatbot;
use App\Services\Knowledge\RetrievalService;
use Illuminate\Console\Command;

class RetrieveTestCommand extends Command
{
    protected $signature = 'rag:retrieve
                            {chatbot_id : UUID of the chatbot to query against}
                            {query      : The natural-language question to retrieve context for}
                            {--k=5      : Maximum number of chunks to return}
                            {--threshold=0.0 : Minimum similarity score (0–1); defaults to chatbot setting}';

    protected $description = 'Debug RAG retrieval quality for a chatbot';

    public function handle(RetrievalService $service): int
    {
        $chatbot = Chatbot::find($this->argument('chatbot_id'));

        if (! $chatbot) {
            $this->error('Chatbot not found.');

            return self::FAILURE;
        }

        $k = (int) $this->option('k');
        $threshold = (float) $this->option('threshold') ?: null;

        $this->info("Querying chatbot \"{$chatbot->name}\" (k={$k})…");
        $this->line('');

        $chunks = $service->retrieve($chatbot, (string) $this->argument('query'), $k, $threshold);

        if ($chunks->isEmpty()) {
            $this->warn('No chunks above threshold. Try lowering --threshold or check that documents are ingested.');

            return self::SUCCESS;
        }

        foreach ($chunks as $i => $chunk) {
            $preview = mb_substr($chunk->content, 0, 100);
            $preview = str_replace(["\n", "\r"], ' ', $preview);
            $score = number_format($chunk->similarity, 4);

            $this->line(sprintf(
                '<fg=green>#%d</> [%s] %s%s',
                $i + 1,
                $score,
                $preview,
                mb_strlen($chunk->content) > 100 ? '…' : '',
            ));
        }

        $this->line('');
        $this->info("Retrieved {$chunks->count()} chunk(s).");

        return self::SUCCESS;
    }
}
