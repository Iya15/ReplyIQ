# ReplyIQ

Multi-tenant SaaS platform for deploying AI-powered customer support chatbots. Businesses train
chatbots on their own knowledge (documents, websites, FAQs) via RAG, then embed them anywhere with
a single `<script>` tag. Every answer is grounded in retrieved content — no hallucination, fully
on-brand.

**Status:** Phase 1 complete (auth, org management, chatbot CRUD). Phase 2 complete (AI/RAG pipeline, document ingestion, URL crawling, knowledge base UI). Phase 3 in progress (chat widget public API, real-time streaming via Reverb).

## Monorepo Structure

```
replyiq/
├── apps/
│   ├── web/       Next.js 16 dashboard (App Router, TypeScript)
│   ├── api/       Laravel 11 REST API (Sanctum, Horizon, pgvector)
│   └── widget/    Embeddable chat widget — Phase 2
├── packages/
│   ├── api-client/ Typed fetch client shared by web + widget
│   └── config/     Shared Tailwind preset, tsconfig bases
├── docs/
│   ├── architecture/decisions/  Architecture Decision Records (ADR-0001–0003)
│   ├── rag-quality-notes.md     RAG quality findings and Phase 3 recommendations
│   ├── performance-baseline.md  pgvector HNSW config and latency benchmarks
│   ├── cost-model.md            OpenAI cost projections per tier
│   └── deploy/
└── turbo.json
```

## Dev Quickstart

You need **four terminals** running simultaneously (Phase 3 adds Reverb for real-time chat).

**Prerequisites:** Node ≥ 20, pnpm ≥ 9, PHP 8.2, Composer 2, PostgreSQL 16 with pgvector, Redis.

```bash
# 1. Clone and install
git clone https://github.com/your-org/replyiq.git && cd replyiq
pnpm install
cd apps/api && composer install && cd ../..

# 2. Configure env files
cp apps/api/.env.example apps/api/.env
cp apps/web/.env.example apps/web/.env.local
```

**Terminal 1 — API server**
```bash
cd apps/api
php artisan key:generate
php artisan migrate
php artisan serve          # http://localhost:8000
```

**Terminal 2 — Reverb WebSocket server** (real-time token streaming)
```bash
cd apps/api
php artisan reverb:start   # ws://localhost:8080
```

**Terminal 3 — Queue worker** (AI reply generation, document ingestion, URL crawling)
```bash
cd apps/api
php artisan horizon        # or: php artisan queue:listen --tries=1
```

**Terminal 4 — Next.js dev server**
```bash
pnpm --filter web dev      # http://localhost:3000
```

## Environment Variables

### `apps/api/.env` (required)

| Variable | Example | Notes |
|---|---|---|
| `APP_KEY` | *(generated)* | `php artisan key:generate` |
| `DB_HOST` | `127.0.0.1` | PostgreSQL host |
| `DB_DATABASE` | `replyiq` | Must exist, pgvector extension enabled |
| `DB_USERNAME` / `DB_PASSWORD` | `replyiq` / `secret` | |
| `FRONTEND_URL` | `http://localhost:3000` | Used in email verification links |
| `MAIL_MAILER` | `log` (dev) / `smtp` (prod) | |
| `OPENAI_API_KEY` | `sk-...` | Required for Phase 2 AI features |
| `AI_PROVIDER` | `openai` | LLM provider; only `openai` supported in Phase 2 |
| `QUEUE_CONNECTION` | `redis` | Use `redis` with Horizon; `database` for local dev without Redis |
| `REDIS_HOST` | `127.0.0.1` | Required when `QUEUE_CONNECTION=redis`; also used by Horizon & Reverb |
| `REDIS_PORT` | `6379` | Default Redis port |
| `REVERB_APP_ID` | `replyiq` | Reverb app identifier |
| `REVERB_APP_KEY` | `replyiq-key` | Reverb app key (must match `NEXT_PUBLIC_REVERB_APP_KEY`) |
| `REVERB_APP_SECRET` | `replyiq-secret` | Reverb app secret (server-side only, never expose) |
| `REVERB_HOST` | `localhost` | Reverb server host |
| `REVERB_PORT` | `8080` | Reverb WebSocket port |
| `REVERB_SCHEME` | `http` | `http` for local dev, `https` for production |
| `BROADCAST_CONNECTION` | `reverb` | Set to `reverb` to enable real-time streaming |

### `apps/web/.env.local` (required)

| Variable | Example | Notes |
|---|---|---|
| `NEXT_PUBLIC_API_URL` | `http://localhost:8000/api/v1` | API base URL |
| `NEXT_PUBLIC_REVERB_APP_KEY` | `replyiq-key` | Must match `REVERB_APP_KEY` in `apps/api/.env` |
| `NEXT_PUBLIC_REVERB_HOST` | `localhost` | Reverb server host |
| `NEXT_PUBLIC_REVERB_PORT` | `8080` | Reverb WebSocket port |
| `NEXT_PUBLIC_REVERB_SCHEME` | `http` | `http` for local dev, `https` for production |

## Common Commands

```bash
# Run all tests
pnpm test                         # turbo: runs vitest (web) + pest (api)

# Type-check all packages
pnpm type-check

# Lint all packages
pnpm lint

# Build everything
pnpm build

# Format (Prettier for TS, Pint for PHP)
pnpm format                       # TS/JSON/CSS
cd apps/api && vendor/bin/pint    # PHP
```

## Tech Stack

| Layer | Technology |
|---|---|
| Frontend | Next.js 16 · React 19 · TypeScript · Tailwind CSS v3 · shadcn/ui |
| State | Zustand v5 · TanStack Query v5 |
| Backend | Laravel 11 · Sanctum · Horizon · Reverb |
| Database | PostgreSQL 16 · pgvector |
| AI (Phase 2) | OpenAI gpt-4o-mini · RAG via pgvector |
| Build | Turborepo · pnpm workspaces |
| CI/CD | GitHub Actions · Vercel (web) · Render (api) |

## Staging

| Service | URL |
|---|---|
| Dashboard | https://staging.replyiq.com |
| API | https://api-staging.replyiq.com |

See [docs/deploy/staging.md](docs/deploy/staging.md) for provisioning instructions.

## Architecture

See [docs/architecture/decisions/](docs/architecture/decisions/) for Architecture Decision Records.
Full blueprint: [replyiq-blueprint.md](replyiq-blueprint.md).
