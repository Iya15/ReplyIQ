/**
 * Shared configuration for all ReplyIQ k6 load tests.
 *
 * All values are read from environment variables so CI and local runs
 * can target different environments without modifying scripts.
 *
 * Usage:
 *   export API_BASE_URL=https://api-staging.replyiq.com/api/v1
 *   export CHATBOT_PUBLIC_ID=<public_id from dashboard>
 *   export DASHBOARD_TOKEN=<bearer token from /auth/login>
 *   k6 run --out json=results.json load-tests/scenarios/widget-chat.js
 */

// ── Target environment ────────────────────────────────────────────────────────

export const API_BASE = __ENV.API_BASE_URL || 'http://localhost:8000/api/v1';

// WebSocket base for Reverb (used in TTFT measurement)
export const WS_BASE = __ENV.WS_BASE_URL || 'ws://localhost:8080';

// ── Test data ─────────────────────────────────────────────────────────────────

// A chatbot that has documents ingested and is active.
// Seed with: php artisan db:seed --class=LoadTestSeeder
export const CHATBOT_PUBLIC_ID = __ENV.CHATBOT_PUBLIC_ID || '';

// A dashboard user token (from POST /api/v1/auth/login).
export const DASHBOARD_TOKEN = __ENV.DASHBOARD_TOKEN || '';

// A chatbot UUID (internal) for dashboard endpoints.
export const CHATBOT_UUID = __ENV.CHATBOT_UUID || '';

// ── Pass/fail thresholds ──────────────────────────────────────────────────────
// These match the milestone requirements exactly.

export const WIDGET_CHAT_THRESHOLDS = {
  // Time from POST /messages to first non-empty assistant message content (polling approx).
  // k6 custom metric 'ttft_ms' is populated in the scenario script.
  ttft_ms:                               ['p(95)<1000'],
  // Time from POST /messages to completed assistant message (full reply).
  'http_req_duration{name:full_reply}':  ['p(95)<8000'],
  // Individual HTTP requests should not time out
  http_req_failed:                       ['rate<0.02'],
};

export const INGESTION_THRESHOLDS = {
  // All uploads accepted (202) — processing time measured separately via queue
  'http_req_duration{name:upload_doc}':  ['p(95)<5000'],
  http_req_failed:                       ['rate<0.01'],
};

export const DASHBOARD_THRESHOLDS = {
  // All read endpoints < 500ms p95
  'http_req_duration{name:dashboard_read}': ['p(95)<500'],
  http_req_failed:                          ['rate<0.01'],
  http_req_failed:                          ['rate<0.01'],
};

export const VECTOR_SEARCH_THRESHOLDS = {
  // Retrieval queries must complete < 200ms p95
  'http_req_duration{name:search_query}': ['p(95)<200'],
  http_req_failed:                        ['rate<0.01'],
};

// ── Helper: auth headers ──────────────────────────────────────────────────────

export function dashboardHeaders() {
  return {
    Authorization: `Bearer ${DASHBOARD_TOKEN}`,
    'Content-Type': 'application/json',
    Accept: 'application/json',
  };
}

export function widgetHeaders(sessionToken) {
  return {
    Authorization: `Bearer ${sessionToken}`,
    'Content-Type': 'application/json',
    Accept: 'application/json',
  };
}

// ── Sample messages ───────────────────────────────────────────────────────────
// Realistic user questions that would trigger retrieval + LLM generation.

export const SAMPLE_MESSAGES = [
  'What is your refund policy?',
  'How do I get started?',
  'Do you offer a free trial?',
  'Can I cancel my subscription at any time?',
  'What payment methods do you accept?',
  'How do I contact support?',
  'What are the system requirements?',
  'Is my data secure?',
  'How long does it take to set up?',
  'Do you have an API?',
];

export function randomMessage() {
  return SAMPLE_MESSAGES[Math.floor(Math.random() * SAMPLE_MESSAGES.length)];
}
