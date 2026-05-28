<?php

namespace App\Services\Knowledge;

use App\DataObjects\RetrievedChunk;
use App\Models\Chatbot;
use App\Services\Embedding\EmbeddingClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RetrievalService
{
    // Default values are overridden by chatbot settings when available.
    private const DEFAULT_K = 5;

    private const DEFAULT_THRESHOLD = 0.75;

    public function __construct(private readonly EmbeddingClient $embedder) {}

    /**
     * Retrieve the top-k chunks most semantically similar to $query.
     *
     * Steps:
     *  1. Embed the query (24-h Redis cache keyed by model+text hash).
     *  2. Run cosine-similarity search via pgvector's <=> operator.
     *  3. Map raw rows to RetrievedChunk DTOs.
     *
     * Only chunks whose parent document has status = 'ready' are considered,
     * preventing partial-ingestion results from surfacing during processing.
     *
     * Note on ef_search: SET hnsw.ef_search controls the HNSW beam width.
     * Higher values improve recall at the cost of latency. 40 (pgvector default)
     * gives good quality for datasets up to ~100k vectors. Increase to 80-100
     * for production if recall quality matters more than p99 latency.
     *
     * @return Collection<int, RetrievedChunk>
     */
    public function retrieve(
        Chatbot $chatbot,
        string $query,
        ?int $k = null,
        ?float $threshold = null,
    ): Collection {
        $k = $k ?? $chatbot->settings?->retrieval_k ?? self::DEFAULT_K;
        $threshold = $threshold ?? $chatbot->settings?->similarity_threshold ?? self::DEFAULT_THRESHOLD;

        $vector = $this->formatVector($this->embedder->embed($query));

        // ef_search = 40 is the pgvector default; set explicitly so behaviour is
        // predictable regardless of server-level config changes.
        DB::statement('SET hnsw.ef_search = 40');

        $rows = DB::select(
            <<<'SQL'
            SELECT c.id,
                   c.content,
                   c.document_id,
                   c.metadata,
                   1 - (c.embedding <=> ?::vector) AS similarity
            FROM chunks c
            INNER JOIN documents d ON d.id = c.document_id
                                  AND d.status = 'ready'
            WHERE c.chatbot_id = ?
              AND 1 - (c.embedding <=> ?::vector) > ?
            ORDER BY c.embedding <=> ?::vector
            LIMIT ?
            SQL,
            [$vector, $chatbot->id, $vector, $threshold, $vector, $k],
        );

        return collect($rows)->map(fn (object $row) => new RetrievedChunk(
            id: $row->id,
            content: $row->content,
            similarity: (float) $row->similarity,
            document_id: $row->document_id,
            metadata: is_array($row->metadata)
                ? $row->metadata
                : (array) json_decode((string) $row->metadata, true),
        ));
    }

    /**
     * Hybrid retrieval: merge vector-similarity results with trigram (pg_trgm)
     * full-text matches, deduplicate by chunk id, and re-rank by similarity.
     *
     * TODO (Phase 2.5+): implement trigram query and RRF/score fusion merge.
     *   1. Run SELECT … WHERE content % ? (trigram similarity) with GIN index.
     *   2. Union with vector results, deduplicate by id.
     *   3. Re-rank by weighted combination of both scores.
     *   Requires: CREATE INDEX ON chunks USING gin(content gin_trgm_ops);
     *
     * @return Collection<int, RetrievedChunk>
     */
    public function retrieveWithHybrid(
        Chatbot $chatbot,
        string $query,
        ?int $k = null,
        ?float $threshold = null,
    ): Collection {
        // Placeholder — delegates to pure-vector retrieval until hybrid is built.
        return $this->retrieve($chatbot, $query, $k, $threshold);
    }

    /**
     * Format a float[] embedding as pgvector wire format: '[x1,x2,...,xn]'
     *
     * @param  float[]  $values
     */
    private function formatVector(array $values): string
    {
        return '['.implode(',', $values).']';
    }
}
