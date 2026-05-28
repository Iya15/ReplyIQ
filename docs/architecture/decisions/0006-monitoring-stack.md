# ADR-0006: Monitoring Stack

**Status:** Accepted  
**Date:** 2026-05-29  
**Deciders:** @Iya15

---

## Context

ReplyIQ needs observability across three layers:
1. **Errors** — which exceptions are occurring, in which org/user context?
2. **Logs** — structured log aggregation for debugging + alerting
3. **Uptime** — are the public-facing endpoints healthy?
4. **Cost** — what is the OpenAI API spending trend?

We are a small team on a startup budget. The stack should be zero-config where possible, work well with the existing Render + Vercel + Cloudflare R2 infrastructure, and add minimal operational overhead.

---

## Decision

### 1. Error Tracking — Sentry

**Sentry** is used for both the Laravel API and the Next.js dashboard.

- **Backend:** `sentry/sentry-laravel` — auto-captures unhandled exceptions, queue job failures, and slow HTTP transactions. The `SentryContext` middleware tags every event with `org.id`, `org.plan`, and the authenticated `user.id` for quick org-level triage.
- **Frontend:** `@sentry/nextjs` — captures client errors, server-component errors, and edge function errors. Session Replay is enabled at 1% sample rate (100% on errors) to reproduce browser issues.
- **Source maps:** Uploaded at build time via the Sentry webpack plugin so stack traces show original TypeScript instead of minified bundles.
- **Sampling:** 5% of performance transactions (traces) are sent. Errors are always sent.

**Why Sentry over alternatives:**
- Better Laravel and Next.js integrations than Datadog/New Relic at this price point.
- Source map upload is fully automated via the webpack plugin.
- Issue grouping and org/user context out of the box.

### 2. Log Aggregation — Better Stack (Logtail)

**Better Stack (Logtail)** aggregates structured JSON logs from the Laravel API.

The logging stack is: Render container stdout/stderr → Better Stack → Logtail dashboard.

Laravel is configured to write JSON to `stderr` (the `stderr-json` Monolog channel using `JsonFormatter`). The Logtail channel uses the `logtail/monolog-logtail` Monolog handler as a secondary sink for direct HTTP ingestion when `BETTERSTACK_SOURCE_TOKEN` is set.

Production `LOG_STACK=stderr-json,logtail` sends to both:
- `stderr-json` — parsed by the container log driver and Render's built-in log viewer.
- `logtail` — forwarded to Better Stack for retention, search, and alerting.

**Why Better Stack:**
- 1-minute log retention on the free tier is enough for debugging.
- Built-in uptime monitoring (see §3) in the same dashboard.
- Cheaper than Datadog Logs at startup scale.

### 3. Uptime Monitoring — Better Stack Uptime

Better Stack's built-in uptime monitoring polls three endpoints every 1 minute:

| Monitor | URL | Alert on |
|---------|-----|----------|
| API health | `GET https://api.replyiq.com/up` | Non-200, > 3s |
| Widget config | `GET https://api.replyiq.com/api/v1/public/chatbots/{id}/config` | Non-200, > 2s |
| Dashboard | `GET https://app.replyiq.com` | Non-200, > 5s |

Alerts fire to email + Slack (`#oncall` channel) when a monitor fails for 2 consecutive checks (2 minutes).

**Status page:** `https://status.replyiq.com` is powered by Better Stack's public status page feature. No extra infrastructure required.

**Why Better Stack over UptimeRobot:**
- Already in the stack for logs — one fewer vendor.
- Status page is included at no extra charge.
- Better Slack integration.

### 4. Cost Monitoring — DailyOpenAiCostReportJob

A scheduled Laravel job (`DailyOpenAiCostReportJob`) runs at 06:00 UTC daily and:
1. Queries the OpenAI usage API for the previous day.
2. Aggregates token counts + estimated USD cost by model.
3. Queries `analytics_events` to attribute usage per organization.
4. Logs a structured JSON record (ingested by Logtail).
5. Posts a Slack summary if `SLACK_COST_WEBHOOK_URL` is set.

This is a best-effort approximation — exact per-request costs would require storing the `tokens_used` field from every `messages` record and multiplying by the model rate. The job uses `messages.tokens_used` (already stored) for per-org attribution.

**Why no dedicated cost dashboard (e.g., OpenAI Cost Center):**
- OpenAI's built-in usage dashboard doesn't segment by our org/tenant.
- Per-org attribution requires our own analytics.
- A nightly job is sufficient — we don't need real-time cost visibility at startup scale.

---

## Rejected Alternatives

**Datadog:** Feature-rich but expensive. $31/host/month for APM; not justified at early stage.

**New Relic:** Similar pricing concern. Also has less idiomatic Laravel integration.

**Grafana + Prometheus:** Self-hosted. Operational overhead is not worth it vs. managed SaaS at this stage.

**Papertrail:** Considered for logs, but Better Stack is cheaper and includes uptime monitoring.

**Pingdom / UptimeRobot:** Considered for uptime, but Better Stack is already in the stack.

---

## Consequences

**Positive:**
- Single vendor (Better Stack) covers logs + uptime + status page.
- Sentry auto-instruments Laravel + Next.js with minimal configuration.
- `SentryContext` middleware ensures every error is attributed to an org/user without per-endpoint boilerplate.
- Cost monitoring runs serverlessly in the existing queue — no extra services.

**Negative:**
- If Better Stack has an outage, both logs and uptime alerts are affected simultaneously.
  - Mitigation: Render has its own log viewer; we're not completely blind.
- Sentry's 5% transaction sample rate may miss rare performance issues.
  - Mitigation: Increase `SENTRY_TRACES_SAMPLE_RATE` temporarily when investigating.
- OpenAI cost report is a polling job, not a real-time alert.
  - Mitigation: Set a budget alert in the OpenAI dashboard ($X/month threshold).

---

## Configuration Reference

```env
# API (.env)
SENTRY_LARAVEL_DSN=https://...@sentry.io/...
SENTRY_TRACES_SAMPLE_RATE=0.05
BETTERSTACK_SOURCE_TOKEN=<token>
LOG_STACK=stderr-json,logtail
SLACK_COST_WEBHOOK_URL=https://hooks.slack.com/...

# Web (.env.local)
NEXT_PUBLIC_SENTRY_DSN=https://...@sentry.io/...
SENTRY_AUTH_TOKEN=<ci-secret>
SENTRY_ORG=replyiq
SENTRY_PROJECT=replyiq-web
```
