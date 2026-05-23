# ReplyIQ

ReplyIQ is a multi-tenant SaaS platform that lets businesses deploy AI-powered customer support
chatbots trained exclusively on their own knowledge — documents, websites, and FAQs — and embed
them on any website with a single script tag. Answers are grounded only in retrieved content via
RAG, eliminating hallucination and keeping every response on-brand.

## Monorepo Structure

```
replyiq/
├── apps/
│   ├── web/      # Next.js 15 dashboard + marketing site
│   ├── widget/   # Vite embeddable widget (iframe-isolated)
│   └── api/      # Laravel 11 REST API
├── packages/
│   ├── ui/         # Shared shadcn/ui components
│   ├── types/      # Shared TypeScript DTOs
│   ├── api-client/ # Typed fetch client
│   └── config/     # ESLint, tsconfig, Tailwind preset
└── turbo.json
```

## Prerequisites

- [Node.js](https://nodejs.org/) >= 20 LTS
- [pnpm](https://pnpm.io/) >= 9 (`npm install -g pnpm@9`)
- [PHP](https://www.php.net/) >= 8.3 (for the Laravel API)
- [Composer](https://getcomposer.org/) >= 2

## Quick Start

```bash
# 1. Install all workspace dependencies
pnpm install

# 2. Verify workspaces are registered
pnpm -r ls

# 3. Start all dev servers concurrently (once apps/ exist)
pnpm dev

# 4. Build all packages/apps
pnpm build

# 5. Lint all workspaces
pnpm lint

# 6. Type-check all workspaces
pnpm type-check
```

## Environment Setup

Each app has its own `.env` file. Copy the example and fill in your values:

```bash
cp apps/web/.env.example apps/web/.env.local
cp apps/api/.env.example apps/api/.env
```

## Tech Stack

| Layer | Technology |
|---|---|
| Frontend | Next.js 15 (App Router) + TypeScript + Tailwind + shadcn/ui |
| Widget | React 18 + Vite + TypeScript |
| Backend | Laravel 11 + Sanctum + Horizon |
| Real-time | Laravel Reverb |
| Database | PostgreSQL 16 + pgvector |
| AI | OpenAI (gpt-4o-mini) + Ollama fallback |

See `docs/replyiq-blueprint.md` for the full architecture reference.
