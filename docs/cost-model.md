# Cost Model — Phase 2

> Last updated: 2026-05-27  
> Prices from OpenAI as of 2026-05. Verify at https://openai.com/api/pricing before budgeting.

---

## Models Used

| Model                    | Use case                | Input price         | Output price        |
|--------------------------|-------------------------|---------------------|---------------------|
| `text-embedding-3-small` | Embedding queries + docs| $0.020 / 1M tokens  | N/A                 |
| `gpt-4o-mini`            | Answer generation       | $0.150 / 1M tokens  | $0.600 / 1M tokens  |
| `gpt-4o`                 | (Optional upgrade)      | $2.50 / 1M tokens   | $10.00 / 1M tokens  |

---

## Per-Conversation Cost (gpt-4o-mini, typical)

### Assumptions
- Query: ~30 tokens
- Retrieved context: 5 chunks × 600 tokens = **3,000 tokens**
- System prompt: ~200 tokens
- History (3 turns): ~300 tokens
- Total input: ~3,530 tokens
- LLM response: ~300 tokens output

### Math

```
Embedding (query):   30 tokens  × $0.020/1M  = $0.0000006
Input tokens:     3,530 tokens  × $0.150/1M  = $0.0005295
Output tokens:      300 tokens  × $0.600/1M  = $0.0001800
─────────────────────────────────────────────────────────
Per conversation:                              ≈ $0.00072
Per 1,000 conversations:                       ≈ $0.72
Per 10,000 conversations/month:                ≈ $7.20
Per 100,000 conversations/month:               ≈ $72.00
```

**Take-away:** At gpt-4o-mini prices, answering 100K user questions costs ~$72/month in LLM fees — highly affordable at typical SaaS margins.

---

## Per-Document Ingestion Cost

### Assumptions
- Average document: 5,000 tokens of text content
- Chunks generated: ~9 chunks (5,000 / 600 with ~80-token overlap)
- Embedding each chunk: 600 tokens × 9 = 5,400 tokens

```
Embedding (chunks):  5,400 tokens × $0.020/1M  = $0.000108 per document
Per 1,000 documents:                             ≈ $0.11
Per 10,000 documents:                            ≈ $1.08
```

Ingestion cost is negligible relative to inference cost.

---

## Monthly Cost Projections by Tier

| Plan      | Chatbots | Docs/chatbot | Convos/month | Est. LLM cost/month |
|-----------|----------|--------------|--------------|---------------------|
| Free      | 1        | 20           | 100          | ~$0.07              |
| Starter   | 3        | 100          | 1,000        | ~$0.72              |
| Pro       | 10       | 500          | 10,000       | ~$7.20              |
| Business  | unlimited| unlimited    | 100,000      | ~$72.00             |

These are LLM API pass-through costs only. Add hosting (Fly.io / Railway), database, queue workers, and CDN costs for full unit economics.

---

## Cost Reduction Levers

### 1. Shorter context windows
Reduce `chunk_size` from 600 → 400 tokens, or reduce `top_k` from 5 → 3.  
**Savings:** ~20–40% input token reduction. Quality impact: test before shipping.

### 2. Caching common queries
Cache `(chatbot_id, query_hash) → GeneratedReply` in Redis with a 1-hour TTL.  
**Savings:** Depends on query repetition rate. For FAQ-heavy bots, cache hit rate can reach 40–60%.  
Implementation: Redis cache in `RagPipeline::execute()` before the retrieval step.

### 3. Semantic deduplication
Before embedding a new query, check if a semantically similar query was answered recently (vector distance < 0.05 in a Redis cache).  
**Savings:** More aggressive than exact-match caching; requires a secondary embedding store per chatbot.

### 4. gpt-4o-mini vs gpt-4o selection
`gpt-4o` costs ~17× more per input token than `gpt-4o-mini`. Only upgrade for:
- Complex multi-step reasoning (e.g., step-by-step troubleshooting).
- When gpt-4o-mini answer quality is demonstrably insufficient (A/B test first).

---

## Budget Alerts (Recommended Phase 3 Action)

Add a Laravel scheduled command that checks daily OpenAI usage via the API and sends a Slack/email alert when:
- Daily spend > $5 (unexpected spike)
- Monthly spend > 80% of budgeted cap
- Any single chatbot accounts for >50% of monthly spend (runaway bot)
