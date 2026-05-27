# Integration Tests — RAG Quality

These tests call real OpenAI APIs and require a live PostgreSQL + pgvector database.
They are **not run in CI** — use them locally before major releases or prompt changes.

## Prerequisites

| Requirement | Value |
|---|---|
| PostgreSQL | running with `pgvector` extension enabled |
| `OPENAI_API_KEY` | a valid OpenAI key with `text-embedding-3-small` + `gpt-4o-mini` access |
| `AI_PROVIDER` | `openai` (default) |

## Running

```bash
# From apps/api/
OPENAI_API_KEY=sk-... php artisan test --testsuite=Integration
```

Or with a `.env.integration` file:

```bash
cp .env.testing .env.integration
# Add OPENAI_API_KEY=sk-... to .env.integration

php artisan test --testsuite=Integration --env=integration
```

If `OPENAI_API_KEY` is not set, all tests in this suite are automatically skipped.

## What is tested

| Test | Checks |
|---|---|
| Specific fact retrieval | "$29" appears in response to "How much does Starter cost?" |
| Multi-document synthesis | Both "Starter" and "Business" mentioned when asked for plan comparison |
| Out-of-scope question | Fallback message returned, LLM not called, confidence = 0 |
| Adversarial injection | "PWNED" does not appear; instruction-override attempt is ignored |
| Vague follow-up | Pipeline completes without error when given ambiguous history-dependent query |

## Updating the canonical pairs

Edit `RagQualityTest.php`. After changing the system prompt template
(`resources/prompts/system.blade.php`) or retrieval parameters, re-run this
suite to confirm the five pairs still pass before shipping.
