# ADR-0004: Widget Authentication — Session Token (replacing HMAC-per-request)

**Status:** Accepted  
**Date:** 2026-05-28

---

## Context

M3.1 introduced HMAC-per-request authentication for the widget API. Every request from the loader or iframe app had to include `X-RIQ-Signature` and `X-RIQ-Timestamp` headers computed as:

```
HMAC-SHA256("{public_id}:{visitor_id}:{unix_ts}", widget_secret)
```

This required the loader to know `widget_secret` at runtime and sign every request. The following problems emerged during M3.3/M3.4 implementation:

1. **Secret exposure.** The loader is a public IIFE served from a CDN. `widget_secret` cannot be embedded without being visible to any site visitor.
2. **Complexity.** Computing an HMAC and replaying a timestamp window on every request adds bundle weight and fragile clock-sync logic to the loader.
3. **Redundancy.** The HMAC only proves the request came from someone who knows the secret — which is essentially anyone who reads the page source.

## Decision

Replace HMAC-per-request with a **session token** (JWT, HMAC-SHA256 signed with the server-side `APP_KEY`).

### Flow

```
1. Widget loads  → GET /chatbots/{public_id}/config     (origin check only)
2. Visitor opens → POST /conversations                  (origin check only)
                    ← { data: { id, ... }, session_token: "<JWT>" }
3. All subsequent calls use:
   Authorization: Bearer <session_token>
```

The JWT is scoped to a single `(chatbotId, conversationId, visitorId)` triple and expires after 24 hours. Claims:

| Claim | Value |
|-------|-------|
| `iss` | `replyiq.widget` |
| `iat` | issued-at Unix timestamp |
| `exp` | iat + 86400 s |
| `cid` | chatbot `public_id` |
| `cnv` | conversation UUID |
| `vid` | visitor UUID |

### Middleware modes

| Mode | Used on | Auth check |
|------|---------|------------|
| `widget:config` | GET /config, POST /conversations | Origin only |
| `widget:token`  | All mutating / polling endpoints | Bearer JWT + origin |
| `widget`        | (reserved — no active routes) | HMAC (legacy) |

`widget:token` binds `currentChatbot`, `currentOrganization`, `currentVisitorId`, and `currentConversationId` into the container from JWT claims. Controllers read these instead of request body fields, so callers cannot forge identity.

### Implementation

- `App\Services\Public\WidgetSessionToken` — issues and verifies tokens via `lcobucci/jwt ^5`
- `App\Http\Middleware\WidgetAuth` — `handleToken()` mode added
- `apps/api/composer.json` — `lcobucci/jwt:^5` added

## Consequences

**Positive**
- No secret in the loader bundle.
- Simpler loader: one POST to start, then plain Bearer headers.
- Server validates visitor/conversation identity without trusting request body.
- `widget_secret` column retained for the HMAC mode but no longer required at runtime.

**Negative / trade-offs**
- A stolen session token grants access for up to 24 hours. Mitigation: tokens are scoped to a single conversation, not the full chatbot or visitor account. Compromise radius is narrow.
- `POST /conversations` is now public (origin check only). An attacker can create arbitrary conversations. Mitigation: rate-limiting (`throttle:widget` = 60 req/min per IP) and conversations without messages are inert.
