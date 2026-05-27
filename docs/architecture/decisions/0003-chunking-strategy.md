# ADR-0003: Document Chunking Strategy

**Status:** Accepted  
**Date:** 2026-05-27  
**Deciders:** @Iya15

---

## Context

Before a document can be embedded and stored in pgvector, it must be split into chunks small enough to fit within the embedding model's token limit (8191 tokens for `text-embedding-3-small`) and meaningful enough to match user queries precisely.

Chunking strategy directly affects retrieval quality:
- **Chunks too large** → the embedding averages over too many concepts → low similarity precision, and the context injected into the LLM prompt wastes tokens.
- **Chunks too small** → context is atomised, individual sentences lack enough surrounding meaning → poor embedding quality and noisy retrieval.

---

## Decision

We use **recursive character splitting** with the following defaults:

| Parameter         | Value  | Rationale                                                        |
|-------------------|--------|------------------------------------------------------------------|
| `chunk_size`      | 600 tokens | ~450 words; fits one coherent topic; well within 8K limit.  |
| `chunk_overlap`   | 80 tokens  | Maintains continuity across boundaries without bloating.     |
| Splitting order   | `\n\n`, `\n`, `. `, ` `, `` | Prefers paragraph/sentence boundaries.       |

### Implementation

`RecursiveTextChunker` (in `app/Services/Knowledge/`) splits on progressively smaller separators until all pieces are within `chunk_size`. The split is token-counted (approximated as `ceil(chars / 4)`) to avoid calling the tokenizer on every piece.

Each chunk is stored with:
- `content` — raw text
- `token_count` — approximate, for logging and context budget tracking
- `embedding` — 1536-dim float32 vector from `text-embedding-3-small`
- `chunk_index` — 0-based position within the document for provenance
- `metadata` — JSON blob (page number, section title, etc. — extractor-dependent)

### Per-source variations

| Source type | Pre-processing before chunking                                  |
|-------------|----------------------------------------------------------------|
| PDF         | `pdftotext` extraction → strip headers/footers heuristically  |
| DOCX        | PHPWord → plain text, preserving paragraph breaks             |
| TXT         | Used as-is                                                     |
| URL         | Mozilla Readability (via `spatie/crawler` + custom observer)  |
| Manual/FAQ  | Text entered directly; minimal pre-processing                  |

FAQ entries are split into question + answer pairs before chunking so that each QA pair is a standalone unit. This yields much better retrieval precision for FAQ-style knowledge bases.

---

## Consequences

**Positive**
- Recursive splitting respects natural document structure (paragraphs → sentences → words) before falling back to arbitrary character splits.
- 80-token overlap ensures a sentence split at a boundary doesn't create two orphaned half-sentences.
- `chunk_index` allows reconstructing the original document order for a "read nearby chunks" feature (Phase 3).

**Negative / Trade-offs**
- Token-count approximation (`chars / 4`) can be off by ±10–15% for non-Latin scripts. Exact tokenization with `tiktoken` would be more precise but adds a PHP→Python bridge or a JS subprocess — deferred to Phase 4.
- 600 tokens is a heuristic. Technical documentation with dense code blocks may benefit from larger chunks; customer-facing FAQs may benefit from smaller. Per-document override is a Phase 3 feature.
- Overlap increases chunk count by ~10–15%, which proportionally increases embedding API cost and storage.

---

## Quality Finding (from integration tests)

During Phase 2 integration testing (`tests/Feature/Knowledge/RetrievalTest.php`) we found that the default `similarity_threshold` of **0.75** (set in the migration) was never met by test vectors using cosine similarity on unit vectors. Tests used **0.60** to pass.

**Implication:** The production threshold of 0.75 may be too aggressive for real-world queries where query phrasing differs from document phrasing. A/B testing against a real knowledge base is needed before Phase 3 launches the public widget. The threshold is configurable per-chatbot via `chatbot_settings.similarity_threshold`.

---

## Alternatives Considered

**Fixed-size character splitting (no overlap):** Simpler but creates arbitrary cuts mid-sentence. Rejected — degrades embedding quality at boundaries.

**Sentence-boundary only splitting:** Cleaner semantics but sentence lengths vary wildly (1 token to 300+). A long paragraph with one run-on sentence would exceed `chunk_size`. Hybrid approach (recursive with sentence as a mid-level separator) is what we implemented.

**Semantic chunking (split on embedding similarity drops):** Groups text by topic changes using embeddings. Highest quality but requires embedding every sentence during ingestion — 10–50× more embedding API calls. Deferred to Phase 4.
