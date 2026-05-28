# ADR-0005: Widget delivered as a sandboxed iframe

**Status:** Accepted  
**Date:** 2026-05-28  
**Deciders:** @Iya15

---

## Context

The ReplyIQ chat widget runs on customer websites. We needed to decide how to
deliver and isolate the widget's UI from the host page.

Two options were considered:

1. **Inline injection** — insert React/DOM directly into the customer's `<body>`
2. **Sandboxed iframe** — render the widget inside a cross-origin `<iframe>`

---

## Decision

The widget UI runs inside a **sandboxed `<iframe>`** served from
`cdn.replyiq.com`. The loader script (`widget.js`) injects the iframe and
communicates with it exclusively via `window.postMessage`.

The iframe element is created with:
```html
<iframe
  sandbox="allow-scripts allow-same-origin allow-forms allow-popups"
  src="https://cdn.replyiq.com/widget/{chatbotId}?v={buildHash}"
/>
```

Communication protocol:
| Direction | Message type | Payload |
|---|---|---|
| iframe → host | `riq:ready` | `{}` — iframe has loaded |
| host → iframe | `riq:init` | `{token, conversationId, visitorId, apiBase}` |
| host → iframe | `riq:visitor` | `{email?, name?}` |
| iframe → host | `riq:close` | `{}` — user clicked close |
| iframe → host | `riq:resize` | `{height: number}` |

---

## Reasons

**Style isolation.** The widget has its own Tailwind stylesheet and CSS custom
properties (`--riq-primary`, `--riq-bg`, etc.). An inline widget would need
aggressive CSS scoping to avoid conflicts with arbitrary customer stylesheets.
The iframe boundary provides perfect isolation at zero cost.

**Script isolation.** If a customer's page defines a global like
`window.fetch = customFetch`, it does not affect the widget. The iframe has its
own `window` and JavaScript context.

**Security.** The `sandbox` attribute restricts what the iframe can do:
- `allow-scripts` — React app can run.
- `allow-same-origin` — required for `sessionStorage` and WebSocket auth
  headers (these need the iframe to be treated as its own origin, not a unique
  sandboxed null origin).
- `allow-forms` — prevents form submissions from navigating the host page.
- `allow-popups` — allows links to open in new tabs.

Missing permissions (`allow-top-navigation`, `allow-pointer-lock`, etc.) mean
the widget cannot redirect the host page.

**Versioning.** The CDN serves the iframe app at
`/widget/{chatbotId}?v={buildHash}`. The `?v=` param is for cache-busting the
initial HTML document. Hashed asset filenames under `dist/app/assets/` already
carry content-addressed names for immutable caching.

**`widget.js` (loader) is kept tiny.** Only ~4 KB minified, no React, no
external deps — just DOM APIs. This minimises the impact on the host page's
performance. The full React app is loaded lazily when the visitor first opens
the chat.

---

## Consequences

**Positive**
- Zero style or script conflicts with customer sites.
- Widget updates ship without any action from customers.
- Browser security model enforces message-passing discipline.
- Content Security Policy headers on the CDN protect the widget app independently.

**Negative / Trade-offs**
- `postMessage` adds a thin coordination layer. All host ↔ widget communication
  must be modelled as messages rather than direct function calls.
- Safari requires `allow-same-origin` for sessionStorage and WebSocket auth.
  Without it, the iframe is treated as a null origin and auth headers cannot be
  set by the Widget app. This is a known browser quirk.
- Customers with strict CSP headers must explicitly allow `frame-src cdn.replyiq.com`.
  The embed guide documents the required directives.

---

## Alternatives Considered

**Inline injection (no iframe)**
Rejected. Style conflicts are near-impossible to prevent without Shadow DOM
(which has its own limitations for forms and CSS custom properties) or very
heavy CSS scoping. Script isolation requires careful global-variable hygiene.
Inline widgets also expose the customer page to accidental mutation by widget
code.

**Shadow DOM**
Considered as a middle ground — provides style isolation without full iframe
overhead. Rejected because:
- Browser support for `adoptedStyleSheets` required by this approach was
  limited in Safari at the time.
- WebSocket auth and `sessionStorage` still require special handling.
- The iframe approach is simpler and more battle-tested across the industry.
