/**
 * Scenario 1: Widget Chat Under Load
 *
 * 100 concurrent virtual users, each simulating a full visitor session:
 *   1. Fetch chatbot config
 *   2. Start a conversation (get session token)
 *   3. Send 10 messages, polling for each reply
 *
 * Measurements:
 *   ttft_ms       — Time to First Token: POST /messages → first non-empty assistant content
 *   full_reply_ms — POST /messages → assistant message status = 'complete'
 *
 * Note on TTFT accuracy:
 *   In production, streaming via WebSocket delivers the first token faster
 *   than polling can detect. This script uses polling (200ms intervals) as
 *   an approximation. The true TTFT is typically 200–400ms lower than what
 *   this script reports. A WebSocket-based measurement would require a custom
 *   k6 extension or xk6-websocket setup targeting the Reverb endpoint.
 *
 * Pass criteria:
 *   ttft_ms        p95 < 1000ms
 *   full_reply_ms  p95 < 8000ms
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Trend, Rate } from 'k6/metrics';
import {
  API_BASE,
  CHATBOT_PUBLIC_ID,
  WIDGET_CHAT_THRESHOLDS,
  widgetHeaders,
  randomMessage,
} from '../k6.config.js';

// Custom metrics
const ttftTrend      = new Trend('ttft_ms',       true); // milliseconds
const fullReplyTrend = new Trend('full_reply_ms',  true);
const replyErrors    = new Rate('reply_errors');

export const options = {
  scenarios: {
    widget_chat: {
      executor:    'per-vu-iterations',
      vus:         100,
      iterations:  1,
      maxDuration: '15m',
    },
  },
  thresholds: {
    ttft_ms:       ['p(95)<1000'],
    full_reply_ms: ['p(95)<8000'],
    reply_errors:  ['rate<0.02'],
    http_req_failed: ['rate<0.02'],
  },
};

// ── Each VU runs this function once ──────────────────────────────────────────

export default function () {
  if (!CHATBOT_PUBLIC_ID) {
    console.error('CHATBOT_PUBLIC_ID env var is required');
    return;
  }

  // ── 1. Fetch chatbot config (no auth required) ────────────────────────────
  const configRes = http.get(
    `${API_BASE}/public/chatbots/${CHATBOT_PUBLIC_ID}/config`,
    { tags: { name: 'config' } },
  );

  check(configRes, {
    'config 200': (r) => r.status === 200,
    'has welcome_message': (r) => JSON.parse(r.body).data?.welcome_message != null,
  });

  if (configRes.status !== 200) return;

  // ── 2. Start a conversation ───────────────────────────────────────────────
  const visitorId = `k6-vu-${__VU}-${Date.now()}`;

  const startRes = http.post(
    `${API_BASE}/public/conversations`,
    JSON.stringify({ public_id: CHATBOT_PUBLIC_ID, visitor_id: visitorId }),
    {
      headers: { 'Content-Type': 'application/json' },
      tags:    { name: 'start_conversation' },
    },
  );

  check(startRes, { 'conversation started 201': (r) => r.status === 201 });
  if (startRes.status !== 201) return;

  const { id: convId, } = JSON.parse(startRes.body).data;
  const sessionToken    = JSON.parse(startRes.body).session_token;

  // ── 3. Send 10 messages and measure reply latency ─────────────────────────
  for (let i = 0; i < 10; i++) {
    const message = randomMessage();
    const sendStart = Date.now();

    const sendRes = http.post(
      `${API_BASE}/public/conversations/${convId}/messages`,
      JSON.stringify({ content: message }),
      {
        headers: widgetHeaders(sessionToken),
        tags:    { name: 'send_message' },
      },
    );

    check(sendRes, { 'message accepted 202': (r) => r.status === 202 });

    if (sendRes.status !== 202) {
      replyErrors.add(1);
      sleep(1);
      continue;
    }

    const assistantId = JSON.parse(sendRes.body).data?.assistant_message?.id;

    if (!assistantId) {
      // Conversation is escalated — no AI reply expected.
      replyErrors.add(0);
      sleep(1);
      continue;
    }

    // ── Poll for reply (approx TTFT + full-reply measurement) ───────────────
    let ttftRecorded = false;
    let attempts     = 0;
    const maxAttempts = 60; // 60 × 200ms = 12s max wait

    while (attempts < maxAttempts) {
      sleep(0.2);
      attempts++;

      const pollRes = http.get(
        `${API_BASE}/public/conversations/${convId}/messages`,
        {
          headers: widgetHeaders(sessionToken),
          tags:    { name: 'poll_messages' },
        },
      );

      if (pollRes.status !== 200) continue;

      const msgs      = JSON.parse(pollRes.body).data ?? [];
      const assistant = msgs.find((m) => m.id === assistantId);

      if (!assistant) continue;

      // TTFT: first time the assistant message has any content
      if (!ttftRecorded && assistant.content && assistant.content.length > 0) {
        ttftTrend.add(Date.now() - sendStart);
        ttftRecorded = true;
      }

      // Full reply: message is complete
      if (assistant.status === 'complete') {
        fullReplyTrend.add(Date.now() - sendStart);
        replyErrors.add(0);
        break;
      }

      // Failed reply
      if (assistant.status === 'failed') {
        replyErrors.add(1);
        break;
      }
    }

    if (attempts >= maxAttempts) {
      replyErrors.add(1);
      console.warn(`VU ${__VU}: message ${i + 1} timed out`);
    }

    // Small think-time between messages (realistic visitor pacing)
    sleep(Math.random() * 2 + 1);
  }
}
