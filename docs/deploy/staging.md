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

## 5. Smoke Test Checklist

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
