# ReplyIQ

Multi-tenant SaaS platform for deploying AI-powered customer support chatbots. Businesses train
chatbots on their own knowledge (documents, websites, FAQs) via RAG, then embed them anywhere with
a single `<script>` tag. Every answer is grounded in retrieved content — no hallucination, fully
on-brand.

**Status:** Phase 1 complete (auth, org management, chatbot CRUD). Phase 2 (AI/RAG) in progress.

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
│   └── architecture/decisions/  Architecture Decision Records
└── turbo.json
```

## Dev Quickstart

You need three terminals running simultaneously.

**Prerequisites:** Node ≥ 20, pnpm ≥ 9, PHP 8.2, Composer 2, PostgreSQL 16 with pgvector.

```bash
# 1. Clone and install
git clone https://github.com/your-org/replyiq.git && cd replyiq
pnpm install
cd apps/api && composer install && cd ../..

# 2. Configure env files
cp apps/api/.env.example apps/api/.env
cp apps/web/.env.example apps/web/.env.local   # if not present, create manually
```

**Terminal 1 — API server**
```bash
cd apps/api
php artisan key:generate
php artisan migrate
php artisan serve          # http://localhost:8000
```

**Terminal 2 — Queue worker** (needed for email verification, password reset)
```bash
cd apps/api
php artisan queue:listen --tries=1
```

**Terminal 3 — Next.js dev server**
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
| `OPENAI_API_KEY` | `sk-...` | Phase 2 — leave empty for now |

### `apps/web/.env.local` (required)

| Variable | Example | Notes |
|---|---|---|
| `NEXT_PUBLIC_API_URL` | `http://localhost:8000/api/v1` | API base URL |

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

## Architecture

See [docs/architecture/decisions/](docs/architecture/decisions/) for Architecture Decision Records.
Full blueprint: [replyiq-blueprint.md](replyiq-blueprint.md).
