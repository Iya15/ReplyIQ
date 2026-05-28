# Staging Deployment Guide

Target URLs:
- Dashboard → `https://staging.replyiq.com` (Vercel)
- API → `https://api-staging.replyiq.com` (Render)
- Database → Supabase project (free tier)

---

## 1. Supabase — Database

1. Create account at https://supabase.com → **New Project**
2. Region: choose closest to your users (e.g., `us-east-1`)
3. Note the **Connection string** (Settings → Database → Connection string → URI)
4. Enable extensions via Supabase SQL editor:
   ```sql
   create extension if not exists "uuid-ossp";
   create extension if not exists "vector";
   create extension if not exists "pg_trgm";
   ```
5. Keep the connection string — you'll need it for Render.

---

## 2. Render — API

1. Create account at https://render.com → **New Web Service**
2. Connect your GitHub repo, root directory: `apps/api`
3. Runtime: **Docker** (picks up `apps/api/Dockerfile` automatically)
4. Region: match Supabase region
5. **Environment variables** — add all values from `.env.production.example`:

   | Key | Value |
   |---|---|
   | `APP_KEY` | Run `php artisan key:generate --show` locally |
   | `APP_ENV` | `production` |
   | `APP_DEBUG` | `false` |
   | `APP_URL` | `https://api-staging.replyiq.com` |
   | `FRONTEND_URL` | `https://staging.replyiq.com` |
   | `DB_*` | From Supabase connection string |
   | `REDIS_URL` | From Render Redis (see below) |
   | `MAIL_*` | Your SMTP provider (Postmark / Resend recommended) |

6. **Render Redis** → New → Redis → same region → copy the internal URL into `REDIS_URL`
7. **Health check path:** `/up` (Laravel 11 built-in)
8. **Deploy hook** — Render → Settings → Deploy Hook → copy the URL  
   Add to GitHub repo secrets as `RENDER_DEPLOY_HOOK_URL`

### Run migrations on deploy

Add a **Pre-deploy command** in Render:
```bash
php artisan migrate --force
```

---

## 3. Vercel — Web Dashboard

1. Create account at https://vercel.com → **New Project** → import GitHub repo
2. Framework preset: **Next.js**
3. Root directory: `apps/web`
4. **Environment variables:**

   | Key | Value |
   |---|---|
   | `NEXT_PUBLIC_API_URL` | `https://api-staging.replyiq.com/api/v1` |

5. **Deployment branch:** `main`
6. Note the Vercel project URL — you'll use it to confirm staging.replyiq.com DNS.

---

## 4. Cloudflare DNS

Assuming your domain is managed by Cloudflare:

1. **staging.replyiq.com** → CNAME → `cname.vercel-dns.com` (Vercel provides this)
   - Proxy: **DNS only** (orange cloud off) so Vercel can issue TLS
   - Or use Vercel's domain panel to generate the correct CNAME value

2. **api-staging.replyiq.com** → CNAME → your Render service URL  
   (`<service-name>.onrender.com`)
   - Proxy: **DNS only** so Render can issue TLS

3. After DNS propagates (~5 min with Cloudflare): verify with
   ```bash
   curl https://api-staging.replyiq.com/up
   # → {"timestamp":"...","cache":"...","database":"...",...}
   ```

---

## 5. Widget CDN (Cloudflare R2)

### One-time setup

1. **Cloudflare R2** → Create bucket `replyiq-widget-staging`
2. **Custom domain** → R2 bucket settings → Add domain: `cdn-staging.replyiq.com`
   - Cloudflare automatically handles TLS + CDN caching rules
3. **Wrangler** → Install: `npm install -g wrangler` → `wrangler login`
4. **CORS rule** on the R2 bucket:
   ```json
   [{"AllowedOrigins":["*"],"AllowedMethods":["GET","HEAD"],"AllowedHeaders":["*"],"ExposeHeaders":["ETag"]}]
   ```

### Deploy

```bash
# From repo root
WIDGET_BASE=https://cdn-staging.replyiq.com/widget \
  pnpm --filter widget build

bash scripts/deploy-widget.sh \
  --bucket replyiq-widget-staging \
  --base-url https://cdn-staging.replyiq.com
```

The script uploads:
- `public/widget.js` → `Cache-Control: public, max-age=3600`
- `dist/app/assets/*` → `Cache-Control: public, max-age=31536000, immutable`
- `dist/app/index.html` → `Cache-Control: public, max-age=60`

### Purge after deploy

After uploading a new `widget.js`, purge the Cloudflare cache:

```bash
curl -X POST "https://api.cloudflare.com/client/v4/zones/$CF_ZONE_ID/purge_cache" \
  -H "Authorization: Bearer $CF_API_TOKEN" \
  -H "Content-Type: application/json" \
  --data '{"files":["https://cdn-staging.replyiq.com/widget.js"]}'
```

### Widget smoke test

After deploy:
- [ ] `curl -I https://cdn-staging.replyiq.com/widget.js` returns 200 with `cache-control: max-age=3600`
- [ ] Open a test page that embeds `widget.js` from `cdn-staging.replyiq.com`
- [ ] Launcher appears within 1 second
- [ ] Open chat → welcome message shown
- [ ] Send a message → reply appears (streaming or polling)
- [ ] Check DevTools → Network → `widget.js` response has `access-control-allow-origin: *`

---

## 6. Smoke Test Checklist

After deploy, run through this flow manually:

- [ ] `GET https://api-staging.replyiq.com/up` returns 200
- [ ] Open `https://staging.replyiq.com` → redirects to `/login`
- [ ] Register a new account → receives verification email
- [ ] Click verification link → redirected to dashboard
- [ ] Log in with new credentials
- [ ] Create a chatbot → appears in list
- [ ] Open chatbot → Customize tab → change primary color → Save → color persists on refresh
- [ ] Embed tab → copy code snippet → contains chatbot `public_id`
- [ ] Settings tab → rename chatbot → save
- [ ] Log out → redirected to `/login`
- [ ] Attempting to visit `/dashboard` while logged out → redirects to `/login`

---

## 6. GitHub Actions Secrets Required

Add these in GitHub → Settings → Secrets → Actions:

| Secret | Used by |
|---|---|
| `RENDER_DEPLOY_HOOK_URL` | (optional) trigger Render deploy from CI |
| `VERCEL_TOKEN` | (optional) Vercel CLI deploy from CI |
| `VERCEL_ORG_ID` | (optional) |
| `VERCEL_PROJECT_ID` | (optional) |

For the initial setup, Vercel and Render auto-deploy on push to `main` without needing these secrets in CI.
