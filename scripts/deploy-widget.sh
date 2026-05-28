#!/usr/bin/env bash
# deploy-widget.sh — Build and upload the widget to Cloudflare R2.
#
# Usage:
#   bash scripts/deploy-widget.sh [--bucket <name>] [--base-url <url>]
#
# Defaults (production):
#   bucket:   replyiq-widget
#   base-url: https://cdn.replyiq.com
#
# Prerequisites:
#   - wrangler installed and authenticated (`wrangler login`)
#   - WIDGET_BASE env var is set OR use the --base-url flag
#   - Run from the repo root
#
# Cache strategy:
#   widget.js            → max-age=3600     (1 h — short so updates propagate fast)
#   dist/app/index.html  → max-age=60       (always re-validate)
#   dist/app/assets/*    → max-age=31536000, immutable  (content-addressed filenames)

set -euo pipefail

# ── Defaults ──────────────────────────────────────────────────────────────────
BUCKET="replyiq-widget"
BASE_URL="https://cdn.replyiq.com"

# ── Arg parsing ───────────────────────────────────────────────────────────────
while [[ $# -gt 0 ]]; do
  case $1 in
    --bucket)   BUCKET="$2";   shift 2 ;;
    --base-url) BASE_URL="$2"; shift 2 ;;
    *) echo "Unknown arg: $1"; exit 1 ;;
  esac
done

# ── Build ─────────────────────────────────────────────────────────────────────
echo "==> Building widget (WIDGET_BASE=${BASE_URL}/widget)"
WIDGET_BASE="${BASE_URL}/widget" pnpm --filter widget build

# ── Upload widget.js (short-lived cache) ──────────────────────────────────────
echo "==> Uploading public/widget.js"
wrangler r2 object put "${BUCKET}/widget.js" \
  --file apps/widget/public/widget.js \
  --content-type "application/javascript; charset=utf-8" \
  --cache-control "public, max-age=3600"

# ── Upload iframe app HTML (re-validate quickly) ──────────────────────────────
echo "==> Uploading dist/app/index.html"
wrangler r2 object put "${BUCKET}/widget/index.html" \
  --file apps/widget/dist/app/index.html \
  --content-type "text/html; charset=utf-8" \
  --cache-control "public, max-age=60"

# ── Upload hashed assets (immutable) ─────────────────────────────────────────
echo "==> Uploading dist/app/assets/*"
for file in apps/widget/dist/app/assets/*; do
  name=$(basename "$file")
  ext="${name##*.}"

  case "$ext" in
    js)  mime="application/javascript; charset=utf-8" ;;
    css) mime="text/css; charset=utf-8" ;;
    *)   mime="application/octet-stream" ;;
  esac

  wrangler r2 object put "${BUCKET}/widget/assets/${name}" \
    --file "$file" \
    --content-type "$mime" \
    --cache-control "public, max-age=31536000, immutable"
done

# ── Purge widget.js from Cloudflare edge cache ────────────────────────────────
if [[ -n "${CF_ZONE_ID:-}" && -n "${CF_API_TOKEN:-}" ]]; then
  echo "==> Purging ${BASE_URL}/widget.js from Cloudflare cache"
  curl -s -X POST \
    "https://api.cloudflare.com/client/v4/zones/${CF_ZONE_ID}/purge_cache" \
    -H "Authorization: Bearer ${CF_API_TOKEN}" \
    -H "Content-Type: application/json" \
    --data "{\"files\":[\"${BASE_URL}/widget.js\"]}" | \
    python3 -c "import sys,json; r=json.load(sys.stdin); print('Cache purged.' if r['success'] else f'Purge error: {r}')"
else
  echo "==> Skipping cache purge (set CF_ZONE_ID and CF_API_TOKEN to enable)"
fi

echo "==> Done. widget.js deployed to ${BASE_URL}/widget.js"
