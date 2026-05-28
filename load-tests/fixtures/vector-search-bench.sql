-- Pure pgvector HNSW benchmark — run this directly against the database
-- to isolate vector search latency from application-layer overhead.
--
-- Usage:
--   psql $DATABASE_URL -f load-tests/fixtures/vector-search-bench.sql
--
-- Replace the chatbot_id and embedding vector with real values.
-- The embedding below is a 1536-dimension all-zeros vector for testing only;
-- real embeddings are produced by OpenAI text-embedding-3-small.

\set chatbot_id 'YOUR-CHATBOT-UUID-HERE'

-- Single retrieval query (measure with EXPLAIN ANALYZE)
EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT)
SELECT id, document_id, content,
       1 - (embedding <=> (SELECT embedding FROM chunks LIMIT 1)) AS similarity
FROM chunks
WHERE chatbot_id = :'chatbot_id'
ORDER BY embedding <=> (SELECT embedding FROM chunks LIMIT 1)
LIMIT 6;

-- 1000-query throughput benchmark using generate_series
-- Each query uses a slightly different threshold to avoid caching effects.
DO $$
DECLARE
  i       integer;
  t_start timestamptz;
  t_end   timestamptz;
  elapsed numeric;
BEGIN
  t_start := clock_timestamp();

  FOR i IN 1..1000 LOOP
    PERFORM id
    FROM chunks
    WHERE chatbot_id = :'chatbot_id'
    ORDER BY embedding <=> (SELECT embedding FROM chunks OFFSET (i % 100) LIMIT 1)
    LIMIT 6;
  END LOOP;

  t_end := clock_timestamp();
  elapsed := extract(milliseconds FROM (t_end - t_start));

  RAISE NOTICE '1000 HNSW queries completed in % ms (avg %.2f ms/query)',
    elapsed, elapsed / 1000.0;
END;
$$;
