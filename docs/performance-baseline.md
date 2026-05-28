# Performance Baseline

> Last updated: 2026-05-29
> Covers Phases 2–5. Run `bash load-tests/setup.sh` before executing k6 scenarios.
> See `load-tests/README.md` for full instructions.

---

## Load Test Scenarios & Pass Criteria

### Scenario 1 — Widget Chat (100 concurrent conversations)

| Metric | Pass Criteria | Notes |
|--------|--------------|-------|
| TTFT p95 | **< 1 000 ms** | Time to First Token (polling approximation; true streaming TTFT ~200–400ms lower) |
| Full reply p95 | **< 8 000 ms** | POST /messages → assistant.status = 'complete' |
| Error rate | < 2% | 429 rate-limit hits count as errors |

```bash
k6 run load-tests/scenarios/widget-chat.js
```

**Expected bottleneck:** OpenAI `gpt-4o-mini` generation latency (300–2 000 ms depending on response length). Not much application-level tuning available. The streaming implementation means visitors see the first token before full generation completes.

### Scenario 2 — Document Ingestion (50 concurrent PDFs)

| Metric | Pass Criteria | Notes |
|--------|--------------|-------|
| All 50 documents reach status='ready' | within **10 minutes** | 8 Horizon ingestion workers in production |
| Upload accepted (202) p95 | < 5 s | HTTP response is async; processing happens in queue |
| Failed documents | < 3 out of 50 | Transient embedding API errors trigger retry (3×) |

```bash
k6 run load-tests/scenarios/document-ingestion.js
```

**Expected bottleneck:** OpenAI embedding API rate limits (2M tokens/min on tier-1). Batched embedding in `ProcessDocumentJob` mitigates this. Scale `supervisor-ingestion.processes` from 8 to 16 if processing time exceeds 10 minutes.

### Scenario 3 — Dashboard Browse (50 concurrent users)

| Metric | Pass Criteria | Notes |
|--------|--------------|-------|
| Read endpoints p95 | **< 500 ms** | GET /chatbots, /conversations, /analytics, /team, /api-keys |
| p99 | < 2 000 ms | Outliers acceptable |
| Error rate (5xx) | < 1% | No server errors tolerated under normal load |

```bash
k6 run load-tests/scenarios/dashboard-browse.js
```

**Expected bottleneck:** DB connection pool (Supavisor). At 50 concurrent users, 10–15 DB connections are needed. See [DB Pool Tuning](#db-connection-pool-supavisor) below.

### Scenario 4 — Vector Search (1 000 retrieval queries)

| Metric | Pass Criteria | Notes |
|--------|--------------|-------|
| Pure HNSW p95 (SQL bench) | **< 200 ms** | Direct DB query, 100k chunks |
| Message-accepted API p95 | < 500 ms | Includes auth + DB + queue dispatch overhead |

```bash
# HTTP-level benchmark
k6 run load-tests/scenarios/vector-search.js

# Pure HNSW SQL benchmark (more accurate)
psql $DATABASE_URL -f load-tests/fixtures/vector-search-bench.sql
```

**Expected bottleneck:** Not HNSW itself (easily < 50ms at 100k chunks). More likely: Supavisor connection checkout latency under concurrent load.

---

## Bottleneck Analysis & Tuning Guide

### LLM API Latency

**Status:** External dependency — not tunable at application level.

- `text-embedding-3-small`: 100–300ms per batch
- `gpt-4o-mini`: 400ms–2s depending on output length
- **Mitigation already in place:** Token streaming via Reverb WebSocket. Visitors see first token ~300–500ms after sending, even if the full response takes 2–4s.
- **Future:** Consider streaming embedding with async worker architecture for further TTFT improvement.

### DB Connection Pool (Supavisor)

Supabase's Supavisor operates in **Transaction mode** by default. Each API request checks out a connection for the duration of its DB transactions only.

**Recommended settings for production:**

| Setting | Value | Notes |
|---------|-------|-------|
| Pool size (Supavisor) | 25 | Per API instance |
| `DB_POOL_SIZE` (Laravel) | 10 | `database.connections.pgsql.options.pool` |
| `shared_buffers` (Postgres) | 512 MB | Ensure HNSW index fits in buffer |
| `max_connections` (Postgres) | 100 | Supabase free tier: 15; Pro: 60; Business: 200 |

Set in Supabase dashboard: **Database → Connection Pooling → Pool Mode: Transaction**.

```env
# .env (production)
DB_HOST=db.<project>.supabase.co  # direct connection (not pooler) for migrations
DB_PORT=5432

# For application traffic, use the Supavisor pooler port:
DB_POOL_HOST=aws-0-us-east-1.pooler.supabase.com
DB_POOL_PORT=5432
```

If you see `SQLSTATE[HY000]: Connection refused` or `too many connections` errors under load, reduce `DB_POOL_SIZE` or upgrade the Supabase plan.

### Queue Worker Throughput (Horizon)

Three named supervisors in `config/horizon.php`:

| Supervisor | Queue | Production workers | Purpose |
|------------|-------|--------------------|---------|
| `supervisor-replies` | `replies` | 20 (auto-scaling) | AI response generation |
| `supervisor-ingestion` | `ingestion` | 8 (fixed) | Document processing + crawling |
| `supervisor-default` | `default` | 5 (auto-scaling) | Notifications, analytics, misc |

**Capacity estimates:**
- At 100 concurrent conversations × avg 4s reply time = 400 VU-seconds/min → 20 workers sustain ~300 replies/min.
- 8 ingestion workers × avg 10s/PDF = 48 PDFs/min → 50 PDFs processed in ~1:10 (well within the 10-minute target).

**Horizontal scaling:** On Render, add additional worker Dynos running `php artisan horizon`. Horizon auto-distributes work across instances via Redis.

### Redis Throughput

At 100 concurrent chat connections:
- `analytics:buffer` receives ~10 pushes/second — well within Redis's 100k ops/sec capacity.
- Reverb uses Redis pub/sub for WebSocket message fan-out. No tuning required at this scale.

For scale beyond 500 concurrent conversations, enable Reverb horizontal scaling:
```env
REVERB_SCALING_ENABLED=true
REVERB_SCALING_CHANNEL=reverb
```

---

## pgvector HNSW Configuration

Index in `2026_05_23_..._create_chunks_table.php`:

```sql
CREATE INDEX chunks_embedding_hnsw_idx
  ON chunks USING hnsw (embedding vector_cosine_ops)
  WITH (m = 16, ef_construction = 64);
```

Query-time: `SET LOCAL hnsw.ef_search = 40` (in `RetrievalService`).

### Expected latency at scale

| Chunk count | ef_search | p50 | p95 | Notes |
|-------------|-----------|-----|-----|-------|
| 1 000 | 40 | < 5 ms | < 15 ms | |
| 10 000 | 40 | 5–15 ms | 20–40 ms | |
| **100 000** | **40** | **15–40 ms** | **50–100 ms** | M5.2 target scale |
| 1 000 000 | 40 | 40–100 ms | 100–250 ms | Scale up `ef_construction` to 128 |

### EXPLAIN ANALYZE reference

```sql
EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT)
SELECT id, document_id, content,
       1 - (embedding <=> '[…1536 dims…]') AS similarity
FROM chunks
WHERE chatbot_id = '<uuid>'
  AND organization_id = '<uuid>'
ORDER BY embedding <=> '[…1536 dims…]'
LIMIT 6;
```

Check: `Index Scan using chunks_embedding_hnsw_idx` — if you see `Seq Scan`, run `VACUUM ANALYZE chunks`.

---

## End-to-End RAG Latency Budget

Target: **< 800 ms TTFT** (non-streaming reference).

| Step | Target | Notes |
|------|--------|-------|
| `text-embedding-3-small` | 100–200 ms | OpenAI API round-trip |
| HNSW search (100k chunks) | 15–50 ms | After Supavisor checkout |
| `PromptBuilder::build()` | < 1 ms | Pure PHP |
| `gpt-4o-mini` first token | 200–500 ms | Streaming; LLM-dependent |
| **TTFT total (p50)** | ~400 ms | With streaming |
| **TTFT total (p95)** | ~900 ms | Use streaming to mask |
| **Full reply (p95)** | 3–7 s | 100–500 token response |

**Recommendation:** Always use streaming (`onToken` callback). The widget displays the first token within 400ms. The full reply latency (3–7s) is acceptable for a chat interface when the user sees incremental progress.

---

## Memory Usage

- HNSW index RAM: ~`chunks × (m+1) × 8 × 2` bytes ≈ **3.4 MB per 10k chunks** (m=16).
- At 100k chunks: ~34 MB. At 1M chunks: ~340 MB.
- Set `shared_buffers = 512 MB` minimum in PostgreSQL for 100k+ chunk workloads.

---

## Queue Worker Reference

| Job | Queue | Typical time | Bottleneck |
|-----|-------|-------------|-----------|
| `GenerateAiReplyJob` | `replies` | 2–8 s | OpenAI latency |
| `ProcessDocumentJob` (PDF, 10 pages) | `ingestion` | 5–15 s | Embedding API |
| `CrawlWebsiteJob` (10 pages) | `ingestion` | 30s–3min | HTTP crawl |
| `TrackUsageJob` (nightly) | `default` | 5–60 s | DB scan |
| `FlushAnalyticsCommand` (1/min) | scheduler | < 1 s | Redis → Postgres batch |

---

## Identified Issues (as tickets)

| # | Issue | Severity | Recommendation |
|---|-------|----------|---------------|
| PERF-001 | TTFT polling approximation in k6 is 200–400ms higher than true streaming TTFT | Low | Implement xk6-websockets TTFT measurement for more accurate benchmarking |
| PERF-002 | Ingestion rate-limited by OpenAI embedding API on free tier (500k tokens/min) | Medium | Upgrade to tier-2 OpenAI API or implement request batching queue |
| PERF-003 | Supabase free tier limited to 15 DB connections — insufficient for 50+ concurrent users | High | Upgrade to Supabase Pro (60 connections) or configure Supavisor pool |
| PERF-004 | Horizon `supervisor-replies` not previously defined — all reply jobs ran on `default` queue mixed with analytics/notifications | Medium | **Fixed in M5.2**: dedicated `supervisor-replies` with 20 production workers |
| PERF-005 | Missing `load-tests/fixtures/sample.pdf` — test must be seeded before running document ingestion scenario | Low | `setup.sh` generates it automatically |
