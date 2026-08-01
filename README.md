# Sales Management System

A Laravel 10 sales-management demo API built around the Biztory invoicing dataset. It stores
sales (with soft deletes), totals daily sales over a date range through both a REST endpoint
and a Lighthouse GraphQL mutation, and fires a queued + broadcast `InvoiceCreated` event on
every sale creation that is logged to `storage/logs/invoice.log`.

> **New developer? Start with [`.docs/tldr.md`](.docs/tldr.md)** — every doc summarised on one
> page. The full guide lives in [`.docs/`](.docs/README.md).

## Prerequisites

| Tool | Version | Installed by |
| --- | --- | --- |
| PowerShell + winget | Windows 10/11 stock | — (the only true prerequisites) |
| Git | any recent | `setup.ps1` |
| PHP | 8.3 (pinned — `composer.lock` rejects 8.4) | `setup.ps1` |
| Composer | 2.x | `setup.ps1` |
| Node.js | LTS (Vite asset build) | `setup.ps1` |
| uv | latest | `setup.ps1` |
| just | any recent | `setup.ps1` |
| Claude Code CLI | latest | `setup.ps1` (optional, for AI-assisted dev) |

## Quick start

```powershell
# 1. One-time machine setup (idempotent — safe to re-run)
pwsh ./setup.ps1

# 2. Close and reopen PowerShell so PATH updates land

# 3. One-time app bootstrap: composer + npm deps, sqlite .env, migrate, Vite build
just bootstrap

# 4. Start the dev server
just start
```

The app is now at **http://127.0.0.1:8111**. Stop it with `just stop`.

## Commands

Run `just` with no arguments to list every recipe. The ones you'll use daily:

| Command | What it does |
| --- | --- |
| `just bootstrap` | One-time app bootstrap: deps, sqlite `.env`, migrate, asset build |
| `just start` | Serve on http://127.0.0.1:8111 in a background window |
| `just serve` | Serve in the foreground (Ctrl+C to stop) |
| `just stop` | Stop only THIS repo's `php.exe` processes |
| `just migrate` | Run pending migrations |
| `just fresh` | Drop everything and re-migrate + seed (irreversible locally) |
| `just test --testsuite=Unit` | Run the PHPUnit suite (bare `just test` aborts — see below) |
| `just lint` | Check code style with Laravel Pint (read-only) |
| `just lint-fix` | Auto-fix code style with Laravel Pint |
| `just claudex` | Launch Claude Code (Sonnet, all permissions) |

## Troubleshooting

### `composer install` fails: "nette/schema ... requires php 8.1 - 8.3"

The lock file pins `nette/schema v1.3.0` and `nette/utils v4.0.4`, both of which cap PHP
below 8.4. Use the PHP 8.3 that `setup.ps1` installs to
`%LOCALAPPDATA%\Programs\php-8.3` — the justfile already targets it by absolute path. Do
not run `composer update` to "fix" it.

### `/api/*` or `/graphql` returns 500: "no such table: sales"

Expected on the local sqlite database. The repo's migrations create framework tables only —
the app's `sales`/`users` tables exist solely in the MySQL dump `biztory.sql`. The server,
welcome page, GraphiQL IDE, and request validation all still work. To exercise the data
endpoints you need a MySQL server with the dump imported and `.env` pointed at it. See
[`.docs/06-troubleshooting/common-issues.md`](.docs/06-troubleshooting/common-issues.md).

### `just test` aborts: `Test directory ".../tests/Feature" not found`

`phpunit.xml` declares a Feature suite but the folder doesn't exist in git (empty
directories aren't tracked). Run `just test --testsuite=Unit` instead. The four Unit tests
then fail on the `sales` table gap above — that is the known local baseline, not a
regression.

### `artisan migrate` fails: "could not find driver" (mysql)

Your `.env` points at the biztory MySQL database (that is `.env.example`'s default) and the
local PHP has no `pdo_mysql`. `just bootstrap` writes a fresh `.env` switched to sqlite; if
you copied `.env` by hand, set `DB_CONNECTION=sqlite` and comment out the `DB_HOST` /
`DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` lines.

More in [`.docs/06-troubleshooting/common-issues.md`](.docs/06-troubleshooting/common-issues.md).

## Project layout

```
Sales-Management-System/
├── app/
│   ├── Events/InvoiceCreated.php          # queued + broadcast on sale creation
│   ├── Listeners/LogInvoiceCreated.php    # audit line into storage/logs/invoice.log
│   ├── GraphQL/Mutations/DailyTotalSales.php  # GraphQL twin of CountDailySalesController
│   ├── Http/Controllers/                  # StoreSale + CountDailySales (single-action)
│   ├── Http/Requests/                     # StoreSaleRequest, CountDailySalesRequest
│   └── Models/Sale.php                    # SoftDeletes; table `sales`
├── graphql/                               # Lighthouse schema (schema/sale/user.graphql)
├── routes/api.php                         # POST /api/sales, POST /api/daily-sale, GET /api/store-sale
├── database/                              # framework migrations, SaleFactory, database.sqlite (local)
├── biztory.sql                            # MySQL dump: real sales/users schema + sample data
├── tests/Unit/                            # PHPUnit endpoint + GraphQL tests
├── question.md                            # the original assignment brief this app implements
├── justfile / setup.ps1                   # dev recipes / one-time machine bootstrap
└── .docs/                                 # developer documentation (start at tldr.md)
```
