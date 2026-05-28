/**
 * Scenario 3: Dashboard Browsing Under Load
 *
 * 50 concurrent users each perform a realistic dashboard session:
 *   - List chatbots
 *   - View a chatbot + its settings
 *   - List conversations
 *   - View a conversation + its messages
 *   - Check analytics overview
 *   - List team members
 *   - List API keys
 *
 * All are read endpoints (GET). No mutations.
 *
 * Pass criteria:
 *   - p95 < 500ms for all read endpoints
 *   - No 5xx errors
 *   - Error rate < 1%
 */

import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { Rate } from 'k6/metrics';
import {
  API_BASE,
  CHATBOT_UUID,
  DASHBOARD_TOKEN,
  dashboardHeaders,
} from '../k6.config.js';

const serverErrors = new Rate('server_errors');

export const options = {
  scenarios: {
    dashboard_browse: {
      executor: 'constant-vus',
      vus:      50,
      duration: '5m',
    },
  },
  thresholds: {
    'http_req_duration{name:dashboard_read}': ['p(95)<500', 'p(99)<2000'],
    server_errors:  ['rate<0.01'],
    http_req_failed: ['rate<0.01'],
  },
};

export default function () {
  if (!DASHBOARD_TOKEN) {
    console.error('DASHBOARD_TOKEN env var is required');
    return;
  }

  const headers = dashboardHeaders();
  const tags    = { name: 'dashboard_read', scenario: 'dashboard' };

  group('list chatbots', () => {
    const r = http.get(`${API_BASE}/chatbots`, { headers, tags });
    const ok = check(r, {
      '200 OK': (res) => res.status === 200,
      'has data': (res) => JSON.parse(res.body).data != null,
    });
    serverErrors.add(r.status >= 500 ? 1 : 0);
  });

  sleep(0.5);

  if (!CHATBOT_UUID) {
    sleep(1);
    return;
  }

  group('view chatbot', () => {
    const r = http.get(`${API_BASE}/chatbots/${CHATBOT_UUID}`, { headers, tags });
    check(r, { '200 OK': (res) => res.status === 200 });
    serverErrors.add(r.status >= 500 ? 1 : 0);
  });

  group('view settings', () => {
    const r = http.get(`${API_BASE}/chatbots/${CHATBOT_UUID}/settings`, { headers, tags });
    check(r, { '200 OK': (res) => res.status === 200 });
    serverErrors.add(r.status >= 500 ? 1 : 0);
  });

  sleep(0.3);

  group('list conversations', () => {
    const r = http.get(`${API_BASE}/chatbots/${CHATBOT_UUID}/conversations`, { headers, tags });
    check(r, { '200 OK': (res) => res.status === 200 });
    serverErrors.add(r.status >= 500 ? 1 : 0);

    // Pick a random conversation to view
    if (r.status === 200) {
      const convs = JSON.parse(r.body).data ?? [];
      if (convs.length > 0) {
        const conv = convs[Math.floor(Math.random() * convs.length)];
        sleep(0.2);

        group('view conversation', () => {
          const cr = http.get(`${API_BASE}/conversations/${conv.id}`, { headers, tags });
          check(cr, { 'conv 200': (res) => res.status === 200 });
          serverErrors.add(cr.status >= 500 ? 1 : 0);
        });

        group('view messages', () => {
          const mr = http.get(`${API_BASE}/conversations/${conv.id}/messages`, { headers, tags });
          check(mr, { 'msgs 200': (res) => res.status === 200 });
          serverErrors.add(mr.status >= 500 ? 1 : 0);
        });
      }
    }
  });

  sleep(0.5);

  group('analytics overview 7d', () => {
    const r = http.get(
      `${API_BASE}/chatbots/${CHATBOT_UUID}/analytics/overview?range=7d`,
      { headers, tags },
    );
    check(r, { '200 OK': (res) => res.status === 200 });
    serverErrors.add(r.status >= 500 ? 1 : 0);
  });

  sleep(0.3);

  group('team members', () => {
    const r = http.get(`${API_BASE}/organizations/current/members`, { headers, tags });
    check(r, { '200 OK': (res) => res.status === 200 });
    serverErrors.add(r.status >= 500 ? 1 : 0);
  });

  group('api keys', () => {
    const r = http.get(`${API_BASE}/api-keys`, { headers, tags });
    check(r, { '200 OK': (res) => res.status === 200 });
    serverErrors.add(r.status >= 500 ? 1 : 0);
  });

  // Think-time between full browse sessions (simulates user reading pages)
  sleep(Math.random() * 3 + 2);
}
