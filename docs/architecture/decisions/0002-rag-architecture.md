# ADR-0002: RAG Architecture

**Status:** Accepted  
**Date:** 2026-05-27  
**Deciders:** @Iya15

---

## Context

ReplyIQ chatbots need to answer questions grounded in knowledge documents uploaded by the user. The system must:

- Return accurate, cited answers (not hallucinated content).
- Support multiple chatbots with completely isolated knowledge bases.
- Work within typical free-tier OpenAI costs (~$0.002 / 1K tokens for `gpt-4o-mini`).
- Handle documents of mixed types: PDF, DOCX, plain text, URLs, and manual FAQ entries.

The question was how to design the retrieval-augmented generation (RAG) pipeline and what components it would require.

---

## Decision

We implement a **dense retrieval + LLM synthesis** RAG pipeline with the following components:

```
User query
    │
    ▼
EmbeddingClient          → embed query with text-embedding-3-small (1536-dim)
    │
    ▼
RetrievalService         → cosine similarity search via pgvector (HNSW index)
    │  similarity_threshold filter (default 0.75)
    │
    ▼
RagPipeline              → fallback guard (empty results → configured message)
    │
    ▼
PromptBuilder            → system prompt + injected context chunks + history
    │
    ▼
LlmClient (OpenAI)       → gpt-4o-mini (configurable per-chatbot)
    │
    ▼
GeneratedReply           → content, confidence, sources, tokens_used, latency_ms
```

### Key design choices

**pgvector as the vector store.** We already run PostgreSQL. Adding `vector_cosine_ops` HNSW indexes avoids a separate vector DB (Pinecone, Weaviate) — one less service to operate and one less monthly bill. At Phase 2 scale (<100K chunks per chatbot), pgvector query latency is acceptable (<50 ms at p95).

**Per-chatbot isolation via `chatbot_id` column.** All chunks are stored in a single `chunks` table with an `organization_id` + `chatbot_id` composite. Retrieval queries always filter by `chatbot_id` before the vector scan, keeping knowledge bases isolated without schema-per-tenant complexity.

**Fallback guard before the LLM call.** If retrieval returns zero chunks above the threshold, the pipeline returns a configurable fallback message without incurring an LLM API call. This eliminates hallucinated answers for out-of-scope questions.

**Graceful LLM error handling.** The LLM call (both streaming and non-streaming paths) is wrapped in a try/catch. If the provider is unavailable, the pipeline returns a user-friendly error reply, logs the failure, and does not propagate the exception to the caller.

**`LlmClient` interface.** The concrete `OpenAiClient` is never imported by `RagPipeline`. The pipeline depends only on the `LlmClient` contract, enabling mock injection in tests and future provider swaps (Anthropic, Mistral, local Ollama).

---

## Consequences

**Positive**
- Single-service architecture: PostgreSQL handles both relational and vector storage.
- Full test coverage possible with mock `LlmClient` and `EmbeddingClient` without API calls.
- Confidence score (`max(similarity)` across retrieved chunks) gives callers a quality signal.
- Source attribution (`chunk_id`, `document_id`, `similarity`) is returned with every reply.

**Negative / Trade-offs**
- Dense retrieval alone can miss lexically exact matches (e.g., product codes) that sparse BM25 would find. Hybrid retrieval (RRF fusion) is a Phase 3 option.
- pgvector HNSW index is approximate (ANN). Recall@10 ≈ 95–98% depending on `m` and `ef_search` settings; perfect recall requires exact scan.
- Embedding model mismatch: if embeddings in the DB were created with model A and the query is embedded with model B, similarities are meaningless. Model changes require re-embedding all chunks.

---

## Alternatives Considered

**Separate vector database (Pinecone / Qdrant / Weaviate):** Rejected at this scale — adds operational complexity, cost, and a network hop. Can be revisited at 10M+ chunks.

**Keyword search only (Elasticsearch/Typesense):** Rejected because it cannot capture semantic equivalence (e.g., "pricing" ↔ "cost" ↔ "how much does it cost"). Embedding-based retrieval handles paraphrasing naturally.

**Re-ranker model (cross-encoder):** A two-stage retrieve-then-rerank pipeline would improve precision. Deferred to Phase 4 due to added latency and model hosting cost.
