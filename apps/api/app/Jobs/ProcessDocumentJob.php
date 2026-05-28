<?php

namespace App\Jobs;

use App\DataObjects\ExtractedDocument;
use App\Enums\DocumentSourceType;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Services\Embedding\EmbeddingClient;
use App\Services\Knowledge\Chunker;
use App\Services\Knowledge\Extractors\DocxExtractor;
use App\Services\Knowledge\Extractors\PdfExtractor;
use App\Services\Knowledge\Extractors\TxtExtractor;
use App\Services\Knowledge\TextNormalizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessDocumentJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 3;

    /** @var int[] */
    public array $backoff = [30, 60, 120];

    private const EMBED_BATCH = 50;

    private const TOKEN_RATIO = 1.3;

    public function __construct(public readonly Document $document)
    {
        $this->onQueue('ingestion');
    }

    public function handle(
        EmbeddingClient $embedder,
        Chunker $chunker,
        TextNormalizer $normalizer,
    ): void {
        $this->document->update(['status' => DocumentStatus::Processing]);

        $tempPath = null;

        try {
            // ── 1. Extract ─────────────────────────────────────────────────────
            $extracted = $this->extractContent($tempPath);

            // ── 2. Normalise + Chunk ───────────────────────────────────────────
            $cleanText = $normalizer->clean($extracted->content);
            $chunks = $chunker->chunk($cleanText);

            // ── 3. Embed all chunks (outside transaction) ──────────────────────
            $allEmbeddings = [];

            foreach (array_chunk($chunks, self::EMBED_BATCH) as $batch) {
                $allEmbeddings = array_merge($allEmbeddings, $embedder->embedBatch($batch));
            }

            // ── 4. Persist atomically ──────────────────────────────────────────
            $now = now();
            $charCount = mb_strlen($cleanText);

            DB::transaction(function () use ($chunks, $allEmbeddings, $embedder, $now, $charCount): void {
                // Idempotency guard: clear any existing chunks (safe on retries).
                DB::table('chunks')->where('document_id', $this->document->id)->delete();

                $rows = [];

                foreach ($chunks as $index => $content) {
                    $embedding = $allEmbeddings[$index];

                    $rows[] = [
                        'id' => (string) Str::uuid(),
                        'organization_id' => $this->document->organization_id,
                        'chatbot_id' => $this->document->chatbot_id,
                        'document_id' => $this->document->id,
                        'chunk_index' => $index,
                        'content' => $content,
                        'token_count' => (int) ceil(str_word_count($content) * self::TOKEN_RATIO),
                        'embedding' => $this->formatVector($embedding),
                        'metadata' => json_encode(['model' => $embedder->model()]),
                        'created_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    // Chunk in groups of 500 to stay within DB parameter limits.
                    foreach (array_chunk($rows, 500) as $insertBatch) {
                        DB::table('chunks')->insert($insertBatch);
                    }
                }

                $this->document->update([
                    'status' => DocumentStatus::Ready,
                    'char_count' => $charCount,
                    'chunk_count' => count($chunks),
                    'processed_at' => $now,
                    'error_message' => null,
                ]);
            });

        } catch (\Throwable $e) {
            $this->document->update([
                'status' => DocumentStatus::Failed,
                'error_message' => $e->getMessage(),
                'processed_at' => now(),
            ]);

            Log::error('Document ingestion failed', [
                'document_id' => $this->document->id,
                'organization_id' => $this->document->organization_id,
                'source_type' => $this->document->source_type->value,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;

        } finally {
            if ($tempPath !== null && file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    private function extractContent(?string &$tempPath): ExtractedDocument
    {
        return match ($this->document->source_type) {
            DocumentSourceType::Manual,
            DocumentSourceType::Faq => new ExtractedDocument(
                content: (string) ($this->document->metadata['raw_content'] ?? ''),
                title: $this->document->title,
                metadata: [],
            ),

            DocumentSourceType::Pdf,
            DocumentSourceType::Docx,
            DocumentSourceType::Txt => $this->extractFromStorage($tempPath),

            default => throw new \RuntimeException(
                "Unsupported source type for extraction: {$this->document->source_type->value}"
            ),
        };
    }

    private function extractFromStorage(?string &$tempPath): ExtractedDocument
    {
        $storageKey = (string) $this->document->source_url;
        $ext = pathinfo($storageKey, PATHINFO_EXTENSION) ?: 'bin';

        $tempPath = sys_get_temp_dir().'/replyiq_'.Str::uuid().'.'.$ext;

        $contents = Storage::disk('s3')->get($storageKey);

        if ($contents === null) {
            throw new \RuntimeException("File not found in storage: {$storageKey}");
        }

        file_put_contents($tempPath, $contents);

        $extractor = match ($this->document->source_type) {
            DocumentSourceType::Pdf => new PdfExtractor(),
            DocumentSourceType::Docx => new DocxExtractor(),
            DocumentSourceType::Txt => new TxtExtractor(),
            default => throw new \RuntimeException('Unreachable'),
        };

        return $extractor->extract($tempPath);
    }

    /** @param float[] $values */
    private function formatVector(array $values): string
    {
        return '['.implode(',', array_map(
            fn(float $v): string => rtrim(rtrim(number_format($v, 10, '.', ''), '0'), '.'),
            $values,
        )).']';
    }
}
