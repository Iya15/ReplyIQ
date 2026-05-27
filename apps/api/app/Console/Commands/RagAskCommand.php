<?php

namespace App\Console\Commands;

use App\Models\Chatbot;
use App\Services\Ai\PromptBuilder;
use App\Services\Ai\Contracts\LlmClient;
use App\Services\Embedding\EmbeddingClient;
use App\Services\Knowledge\RetrievalService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;

class RagAskCommand extends Command
{
    protected $signature = 'rag:ask
                            {chatbot_id : UUID of the chatbot to query}
                            {query      : The natural-language question to ask}';

    protected $description = 'Run the full RAG pipeline (retrieve → prompt → LLM) for a chatbot';

    public function handle(
        EmbeddingClient $embedder,
        RetrievalService $retrieval,
        PromptBuilder $builder,
        LlmClient $llm,
    ): int {
        $chatbot = Chatbot::with(['settings', 'organization'])->find($this->argument('chatbot_id'));

        if (! $chatbot) {
            $this->error('Chatbot not found.');

            return self::FAILURE;
        }

        $query = (string) $this->argument('query');

        $this->line('');
        $this->line(sprintf(
            '<options=bold>Chatbot:</> %s — %s',
            $chatbot->name,
            $chatbot->organization->name,
        ));
        $this->line('');

        // ── Step 1: Embed (warms cache so retrieve step shows DB-only time) ────
        $t0        = hrtime(true);
        $embedder->embed($query);
        $embedMs   = (int) ((hrtime(true) - $t0) / 1_000_000);

        // ── Step 2: Retrieve ──────────────────────────────────────────────────
        $t1         = hrtime(true);
        $chunks     = $retrieval->retrieve($chatbot, $query);
        $retrieveMs = (int) ((hrtime(true) - $t1) / 1_000_000);

        // ── Retrieved chunks ──────────────────────────────────────────────────
        $this->comment('RETRIEVED CHUNKS');

        if ($chunks->isEmpty()) {
            $threshold = $chatbot->settings?->similarity_threshold ?? 0.75;
            $this->warn("  No chunks above threshold ({$threshold}). Returning fallback — no LLM call.");
            $this->line('');

            $fallback = $chatbot->settings?->fallback_message
                ?? "I'm sorry, I don't have enough information to answer that question.";

            $this->comment('RESPONSE (fallback)');
            $this->line($fallback);
            $this->line('');

            $this->printMetrics($embedMs, $retrieveMs, null, null);

            return self::SUCCESS;
        }

        $chunkRows = $chunks->values()->map(fn ($c, $i) => [
            $i + 1,
            mb_substr($c->id, 0, 8) . '…',
            mb_substr($c->document_id, 0, 8) . '…',
            number_format($c->similarity, 4),
            mb_substr(str_replace(["\n", "\r"], ' ', $c->content), 0, 60)
                . (mb_strlen($c->content) > 60 ? '…' : ''),
        ])->all();

        $this->table(['#', 'Chunk ID', 'Doc ID', 'Score', 'Preview'], $chunkRows);
        $this->line('');

        // ── Step 3: Build prompt ──────────────────────────────────────────────
        $messages = $builder->build($chatbot, $query, $chunks);

        $this->comment('PROMPT');
        foreach ($messages as $message) {
            $label = strtoupper($message['role']);
            $this->line("<fg=cyan>[{$label}]</>");
            $this->line($message['content']);
            $this->line('');
        }

        // ── Step 4: LLM ───────────────────────────────────────────────────────
        $model       = $chatbot->settings?->model       ?? 'gpt-4o-mini';
        $maxTokens   = $chatbot->settings?->max_tokens  ?? 800;
        $temperature = $chatbot->settings?->temperature ?? 0.3;

        $t2          = hrtime(true);
        $llmResponse = $llm->chat($messages, $model, $maxTokens, $temperature);
        $llmMs       = (int) ((hrtime(true) - $t2) / 1_000_000);

        $this->comment('RESPONSE');
        $this->line($llmResponse->content);
        $this->line('');

        $this->printMetrics($embedMs, $retrieveMs, $llmMs, $llmResponse->tokens_used);

        return self::SUCCESS;
    }

    private function printMetrics(int $embedMs, int $retrieveMs, ?int $llmMs, ?int $tokensUsed): void
    {
        $this->comment('METRICS');

        $table = new Table($this->output);
        $table->setHeaders(['Step', 'ms']);

        $rows = [
            ['Embed',    $embedMs],
            ['Retrieve', $retrieveMs],
        ];

        if ($llmMs !== null) {
            $rows[] = ['LLM', $llmMs];
        }

        $total = $embedMs + $retrieveMs + ($llmMs ?? 0);
        $rows[] = new TableSeparator();
        $rows[] = ['<options=bold>Total</>', "<options=bold>{$total}</>"];

        $table->setRows($rows);
        $table->render();

        $tokens = $tokensUsed ?? 0;
        $this->line("Tokens used: {$tokens}");
        $this->line('');
    }
}
