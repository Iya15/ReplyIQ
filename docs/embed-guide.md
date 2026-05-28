# ReplyIQ Widget — Embed Guide

## Quick start

Add the following snippet to your website's `<head>` or just before `</body>`. Replace `YOUR_CHATBOT_ID` with the **public ID** from your chatbot's Embed tab.

```html
<script>
  (function(d,s,o,f,js,fjs){
    d[o]=d[o]||function(){(d[o].q=d[o].q||[]).push(arguments)};
    js=d.createElement(s);fjs=d.getElementsByTagName(s)[0];
    js.id=o;js.src=f;js.async=1;fjs.parentNode.insertBefore(js,fjs);
  }(document,'script','riq','https://cdn.replyiq.com/widget.js'));
  riq('init', { chatbotId: 'YOUR_CHATBOT_ID' });
</script>
```

The launcher button appears in the bottom-right corner within ~200 ms.

---

## Options

| Command | Arguments | Description |
|---|---|---|
| `riq('init', { chatbotId })` | `chatbotId: string` (required) | Initializes the widget. Must be called first. |
| `riq('open')` | — | Programmatically open the chat window. |
| `riq('close')` | — | Programmatically close the chat window. |
| `riq('setVisitor', { email?, name? })` | Optional visitor fields | Attach identity to the conversation (shown in dashboard). |
| `riq('destroy')` | — | Remove the launcher and iframe from the DOM. |

### Identify your users

Call `setVisitor` after login to attach an email/name to the conversation record:

```html
<script>
  riq('init', { chatbotId: 'YOUR_CHATBOT_ID' });

  // After your own auth resolves:
  riq('setVisitor', { email: 'user@example.com', name: 'Alice' });
</script>
```

### Open programmatically

```html
<button onclick="riq('open')">Chat with us</button>
```

---

## Content Security Policy (CSP)

If your site uses a CSP header, add these directives:

```
script-src  https://cdn.replyiq.com;
frame-src   https://cdn.replyiq.com;
connect-src https://api.replyiq.com wss://ws.replyiq.com;
```

The widget loads one external script (`widget.js`) and one iframe from `cdn.replyiq.com`. All API calls and WebSocket connections go to `api.replyiq.com` and `ws.replyiq.com` respectively.

---

## Cross-browser notes

| Browser | Notes |
|---|---|
| **Chrome / Edge** | Full support. |
| **Firefox** | Full support. If Enhanced Tracking Protection is on, WebSocket streaming may be blocked; the widget falls back to polling automatically. |
| **Safari (desktop)** | Full support. ITP does not affect the widget since it uses `sessionStorage` (not `localStorage` cross-site cookies) for session persistence. |
| **Safari (iOS)** | Full support on iOS 14.5+. On older iOS, the `crypto.randomUUID` polyfill path is used for visitor IDs. |
| **Content blockers** | uBlock Origin / AdBlock Plus may block `widget.js` if the CDN domain is on a blocklist. Consider self-hosting (see below). |

### Content-blocker mitigation

If your audience commonly uses ad blockers, you can proxy the widget through your own domain:

1. Create a CNAME: `chat.yourdomain.com` → `cdn.replyiq.com`
2. Use `chat.yourdomain.com/widget.js` in the embed snippet instead of `cdn.replyiq.com/widget.js`
3. Set `CORS_ALLOWED_ORIGINS` in your ReplyIQ API config to include your domain.

---

## Rate limits

The widget enforces the following limits to protect shared infrastructure:

| Limit | Value |
|---|---|
| Messages per conversation per minute | 30 |
| Messages per chatbot per minute | 1 000 |
| Requests per IP per minute (all public endpoints) | 60 |

When a visitor exceeds the per-conversation limit, the widget shows "Slow down a moment..." and automatically retries after the `Retry-After` interval.

---

## Deploy flow (CDN update)

```bash
# 1. Build
cd apps/widget
WIDGET_BASE=https://cdn.replyiq.com/widget pnpm build

# 2. Upload (requires Wrangler + R2 bucket configured)
bash scripts/deploy-widget.sh

# 3. Purge widget.js cache in Cloudflare dashboard (or via API)
#    Versioned assets under dist/app/assets/ are immutable — no purge needed.
```

The `?v=<git-sha>` appended to the iframe URL ensures visitors always load the
latest app bundle after `widget.js` refreshes (max-age=3600, so within 1 hour).

---

## Troubleshooting

**Launcher doesn't appear**
- Check the browser console for `[ReplyIQ]` warnings.
- Confirm `chatbotId` is the **public ID** from the Embed tab, not the UUID.
- Verify `widget.js` loaded (Network tab → search `widget.js`).

**Messages don't send**
- Open DevTools → Network → filter `api.replyiq.com`. Look for 4xx/5xx responses.
- Check CORS: the API must list your domain in `CORS_ALLOWED_ORIGINS`.

**WebSocket not connecting (no real-time streaming)**
- The widget falls back to polling automatically. Check DevTools → Network → WS tab.
- Verify `ws.replyiq.com` is accessible from the visitor's browser (not blocked by firewall or VPN).
