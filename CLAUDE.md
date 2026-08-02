# CLAUDE.md — laravel-sales-api

> Human-facing developer docs live in [`.docs/`](./.docs/README.md) — start at
> [`.docs/tldr.md`](./.docs/tldr.md). Keep them in sync when changing behavior they document.

## Project: Sales Management System

A Laravel 10 sales-management demo API built around the Biztory invoicing dataset. It exposes
REST endpoints to store sales and total daily sales over a date range (with payment-status and
payee filters), mirrors that aggregation as a Lighthouse GraphQL `DailyTotalSales` mutation,
and fires a queued + broadcast `InvoiceCreated` event on every sale creation that is logged to
`storage/logs/invoice.log`.

- **Repo:** GitHub — `github.com/dxiiren/laravel-sales-api`
- **Runs locally only** — no CI/CD, no deployment target. `just start` serves on
  `http://127.0.0.1:8111`.

### Tech Stack Quick Reference

| Layer | Technology | Key details |
| --- | --- | --- |
| Backend | Laravel 10 (composer `php: ^8.1`, runs on PHP 8.3) | REST API in `routes/api.php`; single-action (`__invoke`) controllers |
| GraphQL | nuwave/lighthouse 6 + mll-lab/laravel-graphiql | Schema in `graphql/`; endpoint `POST /graphql`, IDE at `/graphiql` |
| ORM | Eloquent | `Sale` model (`sales` table, SoftDeletes) dispatches `InvoiceCreated` on `created` |
| Events | Laravel events + queue + broadcast | `InvoiceCreated` (ShouldQueue + ShouldBroadcast on channel `invoice`) → `LogInvoiceCreated` → `storage/logs/invoice.log` via the `invoice` log channel |
| Database | MySQL schema/data in `biztory.sql`; sqlite for local dev | Repo migrations cover framework tables ONLY — `sales`/`users` exist only in the dump |
| Frontend | Stock `welcome.blade.php` + Vite 5 | `npm run build` once, or `/` 500s with a missing-manifest error |
| Tests | PHPUnit 10 via `just test` | `tests/Unit/` hit the `.env` database and need the `sales` table |

### Project Structure

```
laravel-sales-api/
├── app/
│   ├── Events/InvoiceCreated.php          # queued + broadcast on sale creation
│   ├── Listeners/LogInvoiceCreated.php    # audit line into storage/logs/invoice.log
│   ├── GraphQL/Mutations/DailyTotalSales.php  # GraphQL twin of CountDailySalesController
│   ├── Http/Controllers/                  # StoreSale + CountDailySales (single-action)
│   ├── Http/Requests/                     # StoreSaleRequest, CountDailySalesRequest
│   └── Models/Sale.php                    # SoftDeletes; table `sales`; $dispatchesEvents
├── graphql/                               # Lighthouse schema (schema/sale/user.graphql)
├── routes/api.php                         # POST /api/sales, POST /api/daily-sale, GET /api/store-sale
├── database/                              # framework migrations, SaleFactory, database.sqlite (local, git-ignored)
├── biztory.sql                            # MySQL dump: the real sales/users schema + sample data (do NOT import locally)
├── tests/Unit/                            # PHPUnit endpoint + GraphQL tests
├── justfile / setup.ps1                   # dev recipes / one-time machine bootstrap
└── .docs/                                 # developer documentation (start at tldr.md)
```

## Git Commits

- **Conventional Commits** (`feat:`, `fix:`, `chore:`, `docs:` ...).
- **NEVER** add `Co-Authored-By` lines or "Generated with Claude Code" / session-link footers to
  **any** outward artifact — commit messages, PR descriptions, or issue comments.
- Commit author email for this repo is `mohdakmal875@gmail.com` (set repo-locally).
- Only stage and commit files relevant to the change. **Never auto-commit** after a fix — the
  developer says "commit" first.

## Local Development

- One-time machine setup: `pwsh ./setup.ps1` (idempotent — installs Git, PHP 8.3 + Composer,
  Node LTS, just, the Claude Code CLI). Then `just bootstrap`, then `just start`.
- **PHP is pinned to 8.3, not the newest**: `composer.lock` pins `nette/schema v1.3.0`
  (`php 8.1 - 8.3`) and `nette/utils v4.0.4` (`php >=8.0 <8.4`), so `composer install` fails
  its platform check on PHP 8.4. setup.ps1 installs PHP 8.3 side-by-side at
  `%LOCALAPPDATA%\Programs\php-8.3` and the justfile targets it by absolute path. Do NOT
  "fix" this with `composer update` (lock regeneration is prohibited).
- All day-2 commands are `just` recipes — run `just` to list them. Never invent an alternative
  command for something a recipe already covers.
- `just stop` kills only THIS repo's server processes (matched by repo path on the command
  line) — safe to run while other projects are serving.
- The repo's migrations create framework tables only — the app's `sales`/`users` tables live
  in the MySQL dump `biztory.sql`. On the local sqlite `.env`, `/api/*` endpoints and
  `just test` fail with `no such table: sales`. That is expected — do NOT edit migrations,
  point `.env` at MySQL by default, or import the dump into sqlite to "fix" it.
- `phpunit.xml` has its `DB_CONNECTION`/`DB_DATABASE` overrides commented out — tests run
  against whatever database `.env` points at, not an in-memory one.
- `_lighthouse_ide_helper.php`, `programmatic-types.graphql`, and `schema-directives.graphql`
  are Lighthouse-generated IDE helpers — leave them untouched.
- `public/vendor/graphiql/` holds **pinned** GraphiQL UMD assets (graphiql 2.4.7, react
  17.0.2, plugin-explorer 0.2.0) committed on purpose: the package's unpinned
  `unpkg.com/graphiql/...` CDN URLs 404 since graphiql v4 dropped the UMD bundles, and
  `graphiql:download-assets` dies on the same 404. Do NOT delete or "update" them.

## Project Skills

Development skills live in `.claude/skills/` — check `.claude/skills/README.md` for the catalog
and **follow the relevant skill before writing code**. Notables: `/commit`, `/create-pr`,
`/pre-pr-review`, `/lint-check`, `/claude-transfer`, `/llm-transfer`, `/define-goal`,
`/setup-mcp`, `/test-all-mcp`, `/audit-skills`.

## MCP Servers

Wired via the committed-stub + git-ignored-secret pattern: `.mcp.json.stub` (committed,
placeholders) → `.mcp.json` (git-ignored, real — seeded by `setup.ps1`). Turnkey: `context7`
(library docs — call `resolve-library-id` then `query-docs` instead of recalling APIs),
`playwright` (drive a real browser). Per-dev: `github` (fill the PAT in `.mcp.json`).
Health check: `/test-all-mcp`. Fall back to native tools silently if a server is unavailable.

## Memory

Lightweight, single-developer, file-based project memory at `.claude/memory/`:

- **`MEMORY.md`** is the index (one line per memory: `- [Title](file.md) — hook`), loaded each
  session.
- Each memory is **one fact in its own `*.md` file** with frontmatter (`name`, `description`,
  `metadata.type` = `reference` | `feedback` | `project`). Read the fact file on demand when its
  index hook is relevant.
- After writing a fact file, add its one-line pointer to `MEMORY.md`. Update rather than
  duplicate; delete a memory that turns out wrong. Don't store what the repo already records.
