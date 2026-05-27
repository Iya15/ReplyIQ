# ADR-0001: Monorepo with Turborepo

**Status:** Accepted  
**Date:** 2026-05-23  
**Deciders:** @Iya15

---

## Context

ReplyIQ has three deployable units that share types and configuration:

1. **`apps/web`** — the Next.js dashboard (frontend)
2. **`apps/api`** — the Laravel REST API (backend)
3. **`apps/widget`** — an embeddable chat widget (Phase 2)

Additionally, typed contracts between the frontend and API need to stay in sync, and shared Tailwind/tsconfig presets avoid repetition.

The question was whether to manage these as separate repositories or a single monorepo.

---

## Decision

We use a **pnpm workspace monorepo** with **Turborepo** as the task runner.

Directory layout:
```
apps/   — deployable applications
packages/ — shared, non-deployable packages
```

Turborepo handles the task graph (`build → lint → test`) with output caching.

---

## Reasons

**Atomic cross-cutting changes.** A schema change in the Laravel API (e.g., adding a field to `ChatbotResource`) requires a simultaneous update to `packages/api-client/src/types.ts` and potentially the frontend. A monorepo makes this a single commit and a single PR, keeping the type contract and its consumers always in sync.

**Shared packages without publishing.** `@replyiq/api-client` and `@replyiq/config` are imported via `workspace:*` — no npm publishing, no versioning overhead. Changes are always live.

**Turborepo remote caching.** CI skips tasks whose inputs haven't changed. A backend-only PR skips the `web` build; a `packages/config` change correctly invalidates downstream consumers.

**Single CI pipeline.** The `.github/workflows/ci.yml` has explicit jobs per app (`web`, `api`, `widget`) sharing one checkout, one cache warm, one workflow file to maintain.

---

## Consequences

**Positive**
- Type drift between API response and frontend consumption is caught at compile time.
- New developers clone one repo and run `pnpm install` to get everything.
- Turborepo's `--filter` flag lets us run `pnpm --filter web dev` to work on just the frontend.

**Negative / Trade-offs**
- Composer (PHP) lives inside `apps/api/` while pnpm manages the JS workspaces at the root. Developers need both toolchains. This is mitigated by the three-terminal quickstart in the README.
- `pnpm-lock.yaml` is shared, which means any JS dep change creates a lockfile conflict if two branches modify different apps. Branches should be short-lived.
- Turborepo's remote cache requires a token (`TURBO_TOKEN`) for CI speed wins. Local dev is always cold-cache unless `turbo login` is run.

---

## Alternatives Considered

**Polyrepo:** Separate repositories for each app. Rejected because it makes cross-cutting type changes error-prone and requires a published version of `api-client` to be consumed by the widget and web. Keeping three repos in sync across a small team adds friction without benefit at this stage.

**Nx:** More powerful than Turborepo for large monorepos (affected detection, generators). Rejected as overly complex for a project of this size. Turborepo is simpler to configure and understand.
