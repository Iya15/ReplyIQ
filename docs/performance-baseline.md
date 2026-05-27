# Performance Baseline — Phase 2

> Last updated: 2026-05-27  
> Reference numbers for Phase 2. Compare against these when profiling Phase 3.

---

## pgvector HNSW Configuration

Index created in migration `2026_05_23_..._create_chunks_table.php`:

```sql
CREATE INDEX chunks_embedding_hnsw_idx
  ON chunks USING hnsw (embedding vector_cosine_ops)
  WITH (m = 16, ef_construction = 64);
```

Query-time search width set in `RetrievalService`:

```php
DB::statement('SET LOCAL hnsw.ef_search = 40');
```

### Parameter meanings

| Parameter        | Value | Effect                                                              |
|------------------|-------|---------------------------------------------------------------------|
| `m`              | 16    | Max outgoing edges per node. Higher → better recall, more RAM.     |
| `ef_construction`| 64    | Candidate list size during build. Higher → slower build, better index quality. |
| `ef_search`      | 40    | Candidate list during query. Higher → better recall, slower query. |

### Expected query latency (local Docker, no load)

| Chunk count | ef_search | Approx. p50 latency | Approx. p95 latency |
|-------------|-----------|---------------------|---------------------|
| 1,000       | 40        | < 5 ms              | < 15 ms             |
| 10,000      | 40        | 5–15 ms             | 20–40 ms            |
| 100,000     | 40        | 15–40 ms            | 50–100 ms           |
| 1,000,000   | 40        | 40–100 ms           | 100–250 ms          |

These are single-connection estimates. Under concurrent load, connection pool exhaustion (pgBouncer) is typically the bottleneck before index performance becomes an issue.

---

## EXPLAIN ANALYZE Reference

Run this in psql to profile a retrieval query on a real dataset:

```sql
EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT)
SELECT id, document_id, content, similarity
FROM (
  SELECT
    id,
    document_id,
    content,
    1 - (embedding <=> '[/* paste 1536-dim vector here */]') AS similarity
  FROM chunks
  WHERE chatbot_id = '<your-chatbot-uuid>'
    AND organization_id = '<your-org-uuid>'
  ORDER BY embedding <=> '[/* same vector */]'
  LIMIT 5
) ranked
WHERE similarity >= 0.75;
```

Key things to check in the output:
- `Index Scan using chunks_embedding_hnsw_idx` — confirms HNSW is used (not a SeqScan).
- `Rows Removed by Filter` — high values (>100) suggest the threshold is too aggressive.
- `Buffers: shared hit=...` — low shared reads after warm-up means the index fits in `shared_buffers`.

---

## End-to-End RAG Latency Budget

Target: **< 800 ms** total response time for the first token (non-streaming).

| Step                              | Target     | Notes                                       |
|-----------------------------------|------------|---------------------------------------------|
| Embedding query (`text-embedding-3-small`) | 100–200 ms | OpenAI API round-trip                  |
| pgvector HNSW search (10K chunks) | 10–30 ms   | See table above                             |
| `PromptBuilder.build()`           | < 1 ms     | Pure PHP string concatenation               |
| OpenAI `gpt-4o-mini` (non-stream)| 300–600 ms | 200–800 token response; network-dependent   |
| **Total (p50)**                   | ~500 ms    |                                             |
| **Total (p95)**                   | ~900 ms    | Above target; use streaming to hide latency |

**Recommendation:** Always use streaming (`onToken` callback) in the widget to display the first token within ~400 ms even if the full response takes 1–2 s.

---

## Memory Usage

- HNSW index RAM estimate: ~`chunks_count × (m + 1) × 8 bytes × 2` ≈ **3.4 MB per 10K chunks** (m=16, 1536 dims stored separately as floats).
- At 1M chunks: ~340 MB. PostgreSQL's `shared_buffers` should be set to at least 512 MB in production for this workload.

---

## Queue Worker Throughput

Document ingestion goes through `ProcessDocumentJob` (dispatched on the `default` queue).

| Document type | Typical processing time | Bottleneck                        |
|---------------|-------------------------|-----------------------------------|
| TXT (< 10 KB) | 1–3 s                   | Embedding API call (batched)      |
| PDF (10 pages)| 5–15 s                  | `pdftotext` extraction + embedding|
| DOCX (20 pages)| 8–20 s                 | PHPWord parse + embedding         |
| URL (10 pages) | 30 s – 3 min            | HTTP crawl + rendering wait       |

Horizon configuration (`config/horizon.php`) runs **3 workers** on the `default` queue by default. Scale workers horizontally if the queue depth exceeds 50 jobs.
