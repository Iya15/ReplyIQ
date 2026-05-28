# ReplyIQ Load Tests

Performance baseline tests using [k6](https://k6.io).

---

## Prerequisites

```bash
# Install k6
# macOS:
brew install k6
# Linux (Debian/Ubuntu):
sudo gpg -k
sudo gpg --no-default-keyring --keyring /usr/share/keyrings/k6-archive-keyring.gpg \
     --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys C5AD17C747E3415A3642D57D77C6C491D6AC1D69
echo "deb [signed-by=/usr/share/keyrings/k6-archive-keyring.gpg] https://dl.k6.io/deb stable main" \
     | sudo tee /etc/apt/sources.list.d/k6.list
sudo apt-get update && sudo apt-get install k6
```

---

## Quick Start

### 1. Seed test data

```bash
export STAGING_API_URL=https://api-staging.replyiq.com/api/v1
export STAGING_EMAIL=your@email.com
export STAGING_PASSWORD=your-password

bash load-tests/setup.sh
source load-tests/.env.k6   # sets API_BASE_URL, CHATBOT_PUBLIC_ID, etc.
```

### 2. Run scenarios

```bash
# Scenario 1: Widget chat (100 VUs × 10 messages)
k6 run --out json=results/widget-chat.json load-tests/scenarios/widget-chat.js

# Scenario 2: Document ingestion (50 concurrent PDFs)
k6 run --out json=results/document-ingestion.json load-tests/scenarios/document-ingestion.js

# Scenario 3: Dashboard browsing (50 VUs)
k6 run --out json=results/dashboard-browse.json load-tests/scenarios/dashboard-browse.js

# Scenario 4: Vector search (1000 queries)
k6 run --out json=results/vector-search.json load-tests/scenarios/vector-search.js
```

---

## Pass Criteria

| Scenario | Metric | Target |
|----------|--------|--------|
| Widget chat | TTFT p95 | < 1 s |
| Widget chat | Full reply p95 | < 8 s |
| Document ingestion | All 50 docs processed | ≤ 10 min |
| Dashboard browse | Read endpoint p95 | < 500 ms |
| Dashboard browse | Error rate | < 1% |
| Vector search | Message accepted p95 | < 500 ms |
| Vector search | Pure HNSW (SQL bench) | < 200 ms |

---

## Environment Variables

| Variable | Required | Description |
|----------|----------|-------------|
| `API_BASE_URL` | Yes | API base URL (e.g. `http://localhost:8000/api/v1`) |
| `CHATBOT_PUBLIC_ID` | Yes | Public ID of the test chatbot |
| `CHATBOT_UUID` | Yes | Internal UUID of the test chatbot |
| `DASHBOARD_TOKEN` | Yes | Bearer token for dashboard endpoints |
| `WS_BASE_URL` | No | WebSocket URL for Reverb (default: `ws://localhost:8080`) |

---

## Vector Search SQL Benchmark

For a pure database-level HNSW benchmark (no HTTP overhead):

```bash
psql $DATABASE_URL -f load-tests/fixtures/vector-search-bench.sql
```

This runs 1000 cosine-similarity queries directly and reports the average.

---

## Interpreting Results

### Widget Chat — TTFT accuracy

The `ttft_ms` metric uses polling (200ms interval) as a proxy for the WebSocket streaming first-token time. The actual streaming TTFT is approximately **200–400ms lower** than the polling result. To measure true TTFT:

1. Run with real-time streaming enabled in the app.
2. Use `xk6-websockets` extension to connect to the Reverb presence channel.
3. Measure from `POST /messages` to the first `message.token` WebSocket event.

### Document Ingestion — Queue Depth

During Scenario 2, watch the Horizon dashboard (`/horizon`) for queue depth. If the `ingestion` queue depth grows beyond 50 jobs, increase the `supervisor-ingestion.processes` in `config/horizon.php`.

### Dashboard Browse — Slow Endpoints

Sort the JSON output by `http_req_duration`:
```bash
jq '[.[] | select(.type=="Point") | select(.metric=="http_req_duration")] | sort_by(.data.value)' \
  results/dashboard-browse.json | tail -20
```

### Supavisor Connection Pool

If DB connection timeouts appear under load, tune the Supavisor pool:

```env
# In Render / Supabase dashboard:
DB_POOL_SIZE=25        # per API instance
DB_MAX_OVERFLOW=10     # burst connections
```

For a single Render instance + Supabase free tier (max 15 connections):
- Set `DB_POOL_SIZE=10` in the API env
- Enable pgBouncer/Supavisor in Transaction mode
- The Supavisor proxy multiplexes connections efficiently for short DB transactions

---

## CI Integration

Add to `.github/workflows/load-test.yml`:

```yaml
name: Load Test (Staging)
on:
  workflow_dispatch:       # manual trigger only
  schedule:
    - cron: '0 2 * * 1'   # weekly, Monday 2am UTC

jobs:
  load-test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: grafana/setup-k6-action@v1
      - name: Seed test data
        env:
          STAGING_API_URL: ${{ secrets.STAGING_API_URL }}
          STAGING_EMAIL:   ${{ secrets.STAGING_EMAIL }}
          STAGING_PASSWORD: ${{ secrets.STAGING_PASSWORD }}
        run: bash load-tests/setup.sh
      - name: Run widget chat test
        env:
          API_BASE_URL:       ${{ secrets.STAGING_API_URL }}
          CHATBOT_PUBLIC_ID:  ${{ env.CHATBOT_PUBLIC_ID }}
          CHATBOT_UUID:       ${{ env.CHATBOT_UUID }}
          DASHBOARD_TOKEN:    ${{ env.DASHBOARD_TOKEN }}
        run: k6 run --out json=results/widget-chat.json load-tests/scenarios/widget-chat.js
      - name: Upload results
        uses: actions/upload-artifact@v4
        with:
          name: k6-results
          path: results/
```
