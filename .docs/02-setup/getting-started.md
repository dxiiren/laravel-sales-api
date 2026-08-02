# Getting started

> **TL;DR** `pwsh ./setup.ps1` → reopen PowerShell → `just bootstrap` → `just start` →
> http://127.0.0.1:8111. PHP is pinned to **8.3** (the lock file rejects 8.4). The local
> database is sqlite, which boots the app but lacks the `sales` table — that's expected.

## 1. One-time machine setup

```powershell
pwsh ./setup.ps1
```

Idempotent — safe to re-run any time; already-installed tools report `[OK]` and are
skipped. It installs:

| Tool | Notes |
| --- | --- |
| Git, Node.js LTS, GitHub CLI | via winget |
| PHP **8.3** | zip from windows.php.net into `%LOCALAPPDATA%\Programs\php-8.3`, with a php.ini enabling the Laravel extensions (curl, mbstring, openssl, pdo_sqlite, ...) |
| Composer | `composer.phar` + `composer.bat` next to php.exe |
| uv + Python | used by `.claude/` tooling |
| just | the task runner all day-2 commands go through |
| Claude Code CLI | optional, for AI-assisted dev |
| `.mcp.json` | seeded from `.mcp.json.stub` (git-ignored; fill the GitHub PAT by hand) |

**Why PHP 8.3, not 8.4:** `composer.lock` pins `nette/schema v1.3.0` (`php 8.1 - 8.3`)
and `nette/utils v4.0.4` (`php >=8.0 <8.4`) — `composer install` refuses to run on 8.4.
Never `composer update` to work around it; the pinned 8.3 install is the fix.

Then **close and reopen PowerShell** so the PATH changes land.

## 2. Bootstrap the app

```powershell
just bootstrap
```

What it does, in order:

1. Creates `.env` from `.env.example` **switched to sqlite** (`DB_CONNECTION=sqlite`,
   mysql-specific `DB_*` lines commented out — the example's default points at a MySQL
   `biztory` database you don't have locally).
2. Creates an empty `database/database.sqlite`.
3. `composer install` (from the lock file, no updates).
4. `npm install` + `npm run build` (Vite must build once or `/` 500s on a missing
   manifest).
5. `php artisan key:generate`.
6. `php artisan migrate` — the four framework migrations run clean on sqlite.

## 3. Run it

```powershell
just start     # background window; or `just serve` for foreground
```

Verify: http://127.0.0.1:8111/ shows the Laravel welcome page and
http://127.0.0.1:8111/graphiql shows the GraphQL IDE. Stop with `just stop` (kills only
this repo's php processes).

## What works locally, what doesn't

| Works on sqlite | Fails on sqlite (expected) |
| --- | --- |
| `GET /` welcome page (200) | `GET /api/store-sale` → 500 `no such table: sales` |
| `GET /graphiql` IDE (200) | `POST /api/sales` with valid payload → 500 |
| Request validation (bad payloads get proper validation errors) | `POST /api/daily-sale` with valid payload → 500 |
| `just migrate`, `just fresh` | `DailyTotalSales` GraphQL mutation → `no such table: sales` |
| `just lint` / `just lint-fix` | — |
| `just test` (full suite, green — runs on sqlite `:memory:` with the test-only `sales`/`users` scaffolding in `tests/TestCase.php`) | — |

For full data locally you'd need a MySQL server with `biztory.sql` imported and `.env`
pointed at it — optional and not part of the standard setup. Details in
[`../06-troubleshooting/common-issues.md`](../06-troubleshooting/common-issues.md).

## Related docs

| Doc | Why |
| --- | --- |
| [../03-development/workflow.md](../03-development/workflow.md) | The day-2 development loop |
| [../05-reference/commands.md](../05-reference/commands.md) | Every just recipe |
| [../06-troubleshooting/common-issues.md](../06-troubleshooting/common-issues.md) | Every symptom seen during setup, with fixes |
