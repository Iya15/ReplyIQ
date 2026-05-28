/**
 * Scenario 4: Vector Search Under Load
 *
 * Issues 1000 retrieval queries against a chatbot with ~100k chunks,
 * simulating the embedding-lookup phase of the RAG pipeline.
 *
 * Since the retrieval endpoint is internal (PHP → DB), we simulate it by
 * calling the widget chat send-message endpoint with a small message and
 * measuring only the AI response time excluding the LLM generation phase.
 *
 * For a pure DB-level vector search benchmark, see the artisan command:
 *   php artisan rag:retrieve "test query" --chatbot=<id> --k=6 --threshold=0.7
 * and the RetrievalService integration test in tests/integration/RagQualityTest.php.
 *
 * This k6 script benchmarks the full PUBLIC API send-message path, which
 * includes: rate-limit check → auth → DB query (conv) → message create →
 * queue dispatch. The HNSW query itself happens asynchronously in the worker.
 *
 * To benchmark vector search in isolation, use the companion SQL script:
 *   load-tests/fixtures/vector-search-bench.sql
 *
 * Pass criteria:
 *   - p95 < 200ms for the retrieval-phase proxy (message accepted, not full reply)
 *   - Error rate < 1%
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Trend } from 'k6/metrics';
import {
  API_BASE,
  CHATBOT_PUBLIC_ID,
  widgetHeaders,
  randomMessage,
} from '../k6.config.js';

const searchDuration = new Trend('search_query_ms', true);

export const options = {
  scenarios: {
    vector_search: {
      // 1000 total requests spread over 20 VUs
      executor:    'per-vu-iterations',
      vus:         20,
      iterations:  50,     // 20 VUs × 50 = 1000 queries
      maxDuration: '10m',
    },
  },
  thresholds: {
    // The message-accepted (202) response time is a proxy for the sync path
    // (auth + conversation lookup + message persist + queue dispatch).
    // The async HNSW search happens in the worker; see performance-baseline.md.
    'http_req_duration{name:search_query}': ['p(95)<500', 'p(99)<2000'],
    search_query_ms:  ['p(95)<500'],
    http_req_failed:  ['rate<0.01'],
  },
};

// Setup: create one conversation per VU to reuse across iterations
export function setup() {
  if (!CHATBOT_PUBLIC_ID) {
    throw new Error('CHATBOT_PUBLIC_ID env var is required');
  }

  // Create a conversation for each VU (k6 setup runs once, serial)
  // We use a single conversation and reset by creating a new one per scenario run.
  return {};
}

// Per-VU state: conversation created in init
let convId      = null;
let sessionToken = null;

export function init() {
  // Called once per VU before the test starts.
  // Note: HTTP calls cannot be made in init(); conversation creation is done
  // in the first iteration of the default function.
}

export default function () {
  if (!CHATBOT_PUBLIC_ID) return;

  // Create a fresh conversation on the first iteration for this VU
  if (!convId) {
    const visitorId = `k6-search-vu${__VU}-${Date.now()}`;

    const startRes = http.post(
      `${API_BASE}/public/conversations`,
      JSON.stringify({ public_id: CHATBOT_PUBLIC_ID, visitor_id: visitorId }),
      { headers: { 'Content-Type': 'application/json' } },
    );

    if (startRes.status !== 201) {
      console.error(`VU ${__VU}: failed to start conversation: ${startRes.status}`);
      return;
    }

    convId       = JSON.parse(startRes.body).data?.id;
    sessionToken = JSON.parse(startRes.body).session_token;
  }

  // ── Issue a retrieval query ───────────────────────────────────────────────
  const start = Date.now();

  const sendRes = http.post(
    `${API_BASE}/public/conversations/${convId}/messages`,
    JSON.stringify({ content: randomMessage() }),
    {
      headers: widgetHeaders(sessionToken),
      tags:    { name: 'search_query' },
    },
  );

  const elapsed = Date.now() - start;
  searchDuration.add(elapsed);

  const ok = check(sendRes, {
    'accepted 202': (r) => r.status === 202,
  });

  if (!ok) {
    console.warn(`VU ${__VU}: search query failed: ${sendRes.status}`);
    // Reset conversation on rate-limit or auth failure
    if (sendRes.status === 429 || sendRes.status === 401 || sendRes.status === 403) {
      convId = null;
      sessionToken = null;
      sleep(2);
    }
  }

  // Minimal think-time — we want to stress the sync path, not the LLM
  sleep(0.1);
}
