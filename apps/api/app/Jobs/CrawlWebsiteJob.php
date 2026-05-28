<?php

namespace App\Jobs;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Services\Crawling\WebsiteCrawler;
use App\Services\Embedding\EmbeddingClient;
use App\Services\Knowledge\Chunker;
use App\Services\Knowledge\TextNormalizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CrawlWebsiteJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900; // 15 min — crawling is slow

    public int $tries = 2;

    private const EMBED_BATCH = 50;

    private const TOKEN_RATIO = 1.3;

    private const MIN_PAGE_CHARS = 100;

    private const MAX_TOTAL_CHARS = 5_000_000; // 5 MB

    public function __construct(public readonly Document $document)
    {
        $this->onQueue('ingestion');
    }

    public function handle(
        WebsiteCrawler $crawler,
        TextNormalizer $normalizer,
        Chunker $chunker,
        EmbeddingClient $embedder,
    ): void {
        $this->document->update(['status' => DocumentStatus::Processing]);

        try {
            $startUrl = (string) $this->document->source_url;
            $maxPages = (int) ($this->document->metadata['max_pages'] ?? 50);

            // ── 1. Crawl ───────────────────────────────────────────────────────
            $pages = $crawler->crawl($startUrl, ['max_pages' => $maxPages]);

            // ── 2. Deduplicate + filter + combine ─────────────────────────────
            $combined = '';
            $seenHashes = [];
            $crawledUrls = [];
            $totalChars = 0;

            foreach ($pages as $i => $page) {
                if (mb_strlen($page->content) < self::MIN_PAGE_CHARS) {
                    continue; // likely an error page or empty redirect
                }

                $hash = md5($page->content);

                if (isset($seenHashes[$hash])) {
                    continue; // exact-duplicate content (e.g., canonical redirect)
                }

                $seenHashes[$hash] = true;
                $crawledUrls[] = $page->url;

                $pageHeader = sprintf("=== Page %d: %s ===\n\n", count($crawledUrls), $page->url);
                $combined .= $pageHeader.$page->content."\n\n";
                $totalChars = mb_strlen($combined);

                if ($totalChars >= self::MAX_TOTAL_CHARS) {
                    break; // 5 MB hard cap reached
                }
            }

            $pageCount = count($crawledUrls);

            if ($combined === '') {
                throw new \RuntimeException(
                    'No usable content found. The site may require JavaScript rendering '
                    .'or all pages were below the minimum content threshold.',
                );
            }

            // ── 3. Normalise + chunk ───────────────────────────────────────────
            $cleanText = $normalizer->clean($combined);
            $chunks = $chunker->chunk($cleanText);

            // ── 4. Embed (outside transaction — API calls must not hold a lock) ─
            $allEmbeddings = [];

            foreach (array_chunk($chunks, self::EMBED_BATCH) as $batch) {
                $allEmbeddings = array_merge($allEmbeddings, $embedder->embedBatch($batch));
            }

            // ── 5. Persist atomically ──────────────────────────────────────────
            $now = now();
            $charCount = mb_strlen($cleanText);

            DB::transaction(function () use (
                $chunks, $allEmbeddings, $embedder, $now, $charCount, $crawledUrls, $pageCount,
            ): void {
                // Idempotency guard: safe to retry (tries = 2).
                DB::table('chunks')->where('document_id', $this->document->id)->delete();

                $rows = [];

                foreach ($chunks as $index => $content) {
                    $rows[] = [
                        'id' => (string) Str::uuid(),
                        'organization_id' => $this->document->organization_id,
                        'chatbot_id' => $this->document->chatbot_id,
                        'document_id' => $this->document->id,
                        'chunk_index' => $index,
                        'content' => $content,
                        'token_count' => (int) ceil(str_word_count($content) * self::TOKEN_RATIO),
                        'embedding' => $this->formatVector($allEmbeddings[$index]),
                        'metadata' => json_encode(['model' => $embedder->model()]),
                        'created_at' => $now,
                    ];
                }

                foreach (array_chunk($rows, 500) as $insertBatch) {
                    DB::table('chunks')->insert($insertBatch);
                }

                $this->document->update([
                    'status' => DocumentStatus::Ready,
                    'char_count' => $charCount,
                    'chunk_count' => count($chunks),
                    'processed_at' => $now,
                    'error_message' => null,
                    'metadata' => array_merge(
                        $this->document->metadata ?? [],
                        ['crawled_urls' => $crawledUrls, 'page_count' => $pageCount],
                    ),
                ]);
            });

        } catch (\Throwable $e) {
            $this->document->update([
                'status' => DocumentStatus::Failed,
                'error_message' => $e->getMessage(),
                'processed_at' => now(),
            ]);

            Log::error('Website crawl failed', [
                'document_id' => $this->document->id,
                'organization_id' => $this->document->organization_id,
                'source_url' => $this->document->source_url,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /** @param float[] $values */
    private function formatVector(array $values): string
    {
        return '['.implode(',', array_map(
            fn (float $v): string => rtrim(rtrim(number_format($v, 10, '.', ''), '0'), '.'),
            $values,
        )).']';
    }
}
