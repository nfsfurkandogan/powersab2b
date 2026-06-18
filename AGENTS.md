# AGENTS.md

## Scope

This repository contains the Powersa B2B system:

- `apps/api`: Laravel API, Sanctum auth, PostgreSQL/Redis/Meilisearch integration, Logo sync endpoints, queues, policies, and tests.
- `apps/web`: Next.js/React frontend with TypeScript, Tailwind CSS, shadcn/Radix UI, TanStack Query, and cookie-based API calls.
- `tools/logo-sync`: Node.js scripts for Logo SQL read/write sync and export flows.
- `infra`: local and Hostinger deployment/runtime notes.

## Working Rules

- Do not delete files unless the user explicitly approves deletion.
- Do not deploy, run production commands, run migrations, reset data, or start live Logo sync unless the user explicitly approves it.
- Treat real `.env` files, API keys, tokens, passwords, SQL credentials, Hostinger details, and Logo integration keys as secrets. Do not print or commit them.
- Before edits, check `git status --short --branch` and preserve unrelated local changes.
- Keep edits narrowly scoped to the requested feature or fix.
- After each change, report touched files, behavior changed, and how to verify it.

## Backend Guidelines

- Use Laravel conventions already present in `apps/api`.
- Prefer FormRequest validation, policies, route middleware, transactions, and Eloquent/query bindings.
- Keep Sanctum SPA cookie auth intact: `/sanctum/csrf-cookie` must precede login from the frontend.
- For SQL-heavy areas, avoid string interpolation in raw SQL. Use bound parameters and local helper methods.
- For auth, role, menu permission, customer scope, cart, order, POS, warehouse, and Logo integration changes, add or update focused feature tests.

## Frontend Guidelines

- Preserve the existing visual system: theme variables in `apps/web/src/app/globals.css`, fonts in the root layout, compact table language, sidebar behavior, and mobile responsiveness.
- Use existing UI primitives in `apps/web/src/components/ui` and existing page/component patterns.
- Keep API access centralized through `apps/web/src/lib/api.ts` unless there is a clear reason not to.
- Do not add dependencies unless the existing stack cannot reasonably handle the task.
- For UI work, verify with the narrowest practical lint/build/browser check requested by the user.

## Verification Defaults

- Backend targeted test example: `cd apps/api && php artisan test --filter=AuthApiTest`.
- Backend formatting example: `cd apps/api && ./vendor/bin/pint --dirty`.
- Frontend lint/build examples: `cd apps/web && npm run lint` and `cd apps/web && npm run build`.
- Do not run deploy scripts or live sync scripts as verification without explicit user approval.

