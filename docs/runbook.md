# Operational Runbook — ReplyIQ

Common operational tasks for engineers on-call or handling deployments.

---

## Table of Contents

1. [Deploy API to Production (Render)](#1-deploy-api-to-production-render)
2. [Deploy Dashboard to Production (Vercel)](#2-deploy-dashboard-to-production-vercel)
3. [Deploy Widget to CDN (Cloudflare R2)](#3-deploy-widget-to-cdn-cloudflare-r2)
4. [Run Database Migrations in Production](#4-run-database-migrations-in-production)
5. [Rollback API Deployment](#5-rollback-api-deployment)
6. [Rollback Widget CDN Deploy](#6-rollback-widget-cdn-deploy)
7. [Seed Production Admin User](#7-seed-production-admin-user)
8. [Horizon — Check Queue Health](#8-horizon--check-queue-health)
9. [Incident Response](#9-incident-response)
10. [Certificate Renewal](#10-certificate-renewal)
11. [Database Backup / Restore](#11-database-backup--restore)
12. [Scale Horizon Workers](#13-scale-horizon-workers)
13. [Rotate API Keys](#14-rotate-api-keys)
14. [End-to-End Production Smoke Test](#15-end-to-end-production-smoke-test)

---

## 1. Deploy API to Production (Render)

**Auto-deploy:** Push to `main` → Render deploys automatically.

**Manual deploy:**
```bash
# Trigger via deploy hook (set RENDER_DEPLOY_HOOK_URL in GitHub secrets)
curl -X POST "$RENDER_DEPLOY_HOOK_URL"
```

**Pre-deploy checklist:**
- [ ] Migrations reviewed (`git diff main -- apps/api/database/migrations/`)
- [ ] PHPStan passes: `cd apps/api && ./vendor/bin/phpstan analyse`
- [ ] Pest tests green (CI must pass before merge to main)

**Render pre-deploy command** (set in Render → Settings → Pre-deploy Command):
```bash
php artisan migrate --force
```

**Verify deploy:**
```bash
curl https://api.replyiq.com/up
# Expected: {"status":"ok"} with HTTP 200
```

---

## 2. Deploy Dashboard to Production (Vercel)

**Auto-deploy:** Push to `main` → Vercel deploys automatically.

**Manual deploy:**
```bash
npx vercel --prod --cwd apps/web
```

**Verify deploy:**
```bash
curl -I https://app.replyiq.com
# Expected: HTTP/2 200
```

---

## 3. Deploy Widget to CDN (Cloudflare R2)

```bash
# Build with production WIDGET_BASE
WIDGET_BASE=https://cdn.replyiq.com/widget pnpm --filter widget build

# Upload to Cloudflare R2 (requires wrangler authenticated + CF_ZONE_ID + CF_API_TOKEN)
CF_ZONE_ID=$CF_ZONE_ID CF_API_TOKEN=$CF_API_TOKEN \
  bash scripts/deploy-widget.sh --bucket replyiq-widget --base-url https://cdn.replyiq.com
```

**Verify:**
```bash
curl -I https://cdn.replyiq.com/widget.js
# Expected: HTTP/2 200, cache-control: public, max-age=3600
```

---

## 4. Run Database Migrations in Production

Render runs `php artisan migrate --force` automatically on each deploy.

**To run manually (e.g., after a hotfix):**
1. Open Render dashboard → API service → Shell
2. Run: `php artisan migrate --force`
3. Check output — any "Nothing to migrate" message is fine.

**If a migration fails:**
```bash
# Check what failed
php artisan migrate:status

# Roll back the last batch
php artisan migrate:rollback --step=1

# Fix the migration, redeploy
```

---

## 5. Rollback API Deployment

In Render:
1. Go to **API service → Deploys**
2. Find the last known-good deploy
3. Click **Rollback to this deploy**
4. Render re-deploys the old image (no re-build)

**If migrations ran on the bad deploy:**
- If the migration has a reversible `down()`, run `php artisan migrate:rollback` first.
- If not reversible, coordinate with the team before rolling back.

---

## 6. Rollback Widget CDN Deploy

```bash
# Re-deploy the previous commit's build
git checkout <previous-sha> -- apps/widget/dist/
bash scripts/deploy-widget.sh --bucket replyiq-widget --base-url https://cdn.replyiq.com
```

Or, using Cloudflare R2 object versioning (if enabled):
1. R2 dashboard → `replyiq-widget` bucket → `widget.js`
2. Restore previous version

**Cache note:** `widget.js` has `max-age=3600`. Existing visitors will get the old file for up to 1 hour even after a rollback. Hashed assets under `/widget/assets/*` are immutable and unaffected by widget.js rollbacks.

---

## 7. Seed Production Admin User

```bash
# Run in Render shell or via SSH
php artisan tinker

# Inside tinker:
$user = App\Models\User::create(['name' => 'Admin', 'email' => 'admin@replyiq.com']);
$user->password_hash = 'your-secure-password';
$user->email_verified_at = now();
$user->save();

$org = App\Models\Organization::create(['name' => 'ReplyIQ', 'slug' => 'replyiq', 'plan' => 'business', 'settings' => []]);

App\Models\Membership::create(['organization_id' => $org->id, 'user_id' => $user->id, 'role' => 'owner']);
```

---

## 8. Horizon — Check Queue Health

**Horizon dashboard:** `https://api.replyiq.com/horizon` (protected by IP or admin auth in production)

**CLI health check:**
```bash
# Check Horizon is running
php artisan horizon:status
# Expected: "Horizon is running."

# Check queue depths
php artisan horizon:list

# Check failed jobs
php artisan queue:failed
```

**Restart Horizon after a deploy:**
```bash
php artisan horizon:terminate   # graceful — waits for running jobs to finish
# Render's pre-deploy hook restarts the process automatically
```

**If queue depth is growing:**
1. Check `php artisan horizon:list` for stuck jobs.
2. Increase worker count in `config/horizon.php` (see production values in the `environments.production` section).
3. `php artisan horizon:terminate` to apply new config.
4. Long-term: add a second Render worker Dyno running `php artisan horizon`.

---

## 9. Incident Response

### Severity definitions

| Level | Example | Response SLA |
|-------|---------|-------------|
| P0 — Critical | All widgets down, auth broken | Page immediately, 30-min response |
| P1 — High | AI replies failing for all users | 1-hour response |
| P2 — Medium | Analytics delayed > 1h | Next business day |
| P3 — Low | Single chatbot issue | Sprint planning |

### P0/P1 Checklist

1. **Check status page** — `https://status.replyiq.com` — is it already known?
2. **Check Sentry** — look for error spikes in the last 30 minutes.
3. **Check `/up` endpoint** — `curl https://api.replyiq.com/up`
4. **Check Render** — any failed deploys or unhealthy instances?
5. **Check Horizon** — any queue back-up or failed jobs?
6. **Check Supabase** — DB connection errors? Disk usage?
7. **Check OpenAI status** — `https://status.openai.com`
8. **Rollback** if a recent deploy is the root cause (see §5).
9. **Post incident update** to status page.

### Post-incident

- Write a brief post-mortem in `docs/incidents/YYYY-MM-DD-title.md`.
- File tickets for root-cause fixes.
- Update the runbook if a new pattern was discovered.

---

## 10. Certificate Renewal

All TLS certificates are managed by:
- **Vercel** (app.replyiq.com, replyiq.com) — auto-renews via Let's Encrypt.
- **Render** (api.replyiq.com) — auto-renews via Let's Encrypt.
- **Cloudflare** (cdn.replyiq.com) — Cloudflare Universal SSL, auto-renews.

No manual action required. If a certificate expires unexpectedly:
1. Check the DNS CNAME is pointing to the correct provider.
2. Trigger a re-issue in the Render/Vercel certificate UI.

---

## 11. Database Backup / Restore

### Backups

Supabase Pro tier provides:
- **Point-in-time recovery (PITR)** — restore to any second in the last 7 days.
- **Daily backups** — retained for 30 days.

**Verify backups are working:**
1. Supabase dashboard → **Database → Backups**
2. Confirm the last successful backup timestamp.

### Restore procedure

```bash
# Via Supabase dashboard:
# Database → Backups → Restore → select timestamp → confirm

# OR via supabase CLI:
supabase db restore --backup-id <id> --project-ref <ref>
```

**After restore:**
1. Run `php artisan migrate:status` to check migration state.
2. Re-run any missed migrations if necessary.
3. Notify affected users if data was lost.

---

## 12. Scale Horizon Workers

Edit `apps/api/config/horizon.php`:

```php
'environments' => [
    'production' => [
        'supervisor-replies' => [
            'maxProcesses' => 40,  // was 20 — doubled for high load
        ],
        'supervisor-ingestion' => [
            'processes' => 16,     // was 8
        ],
    ],
],
```

Deploy via `git push main`. Render re-deploys and `php artisan horizon:terminate` is called by the pre-deploy command.

**Horizontal scaling:** Add a second Render worker Dyno with start command `php artisan horizon`. Horizon distributes work automatically via Redis.

---

## 13. Rotate API Keys

**ReplyIQ API keys (rk_live_...):**
1. User revokes old key in Settings → API Keys.
2. Creates a new key and updates their integration.

**Platform API keys (OpenAI, Stripe, etc.):**
1. Generate new key in the provider dashboard.
2. Update the environment variable in Render/Vercel.
3. Trigger a redeploy.

**Sanctum tokens:** Expire on logout. To revoke all tokens for a user:
```bash
# php artisan tinker
App\Models\User::where('email', 'user@example.com')->first()->tokens()->delete();
```

---

## 14. End-to-End Production Smoke Test

Run after every production deploy:

```bash
export API=https://api.replyiq.com/api/v1
export PUBLIC_ID=<your-test-chatbot-public-id>

# 1. Health check
curl -sf "$API/../up" && echo "✓ API up"

# 2. Widget config
curl -sf "$API/public/chatbots/$PUBLIC_ID/config" | jq .data.name && echo "✓ Widget config"

# 3. Start conversation
CONV=$(curl -sf -X POST "$API/public/conversations" \
  -H "Content-Type: application/json" \
  -d "{\"public_id\":\"$PUBLIC_ID\",\"visitor_id\":\"smoke-test-$(date +%s)\"}")
TOKEN=$(echo $CONV | jq -r .session_token)
CONV_ID=$(echo $CONV | jq -r .data.id)
echo "✓ Conversation started: $CONV_ID"

# 4. Send message (async — AI reply takes a few seconds)
curl -sf -X POST "$API/public/conversations/$CONV_ID/messages" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"content":"Hello, can you help me?"}' | jq .data.user_message.id && echo "✓ Message sent"

echo ""
echo "All smoke tests passed. Check Horizon for AI reply completion."
```
