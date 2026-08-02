# TL;DR — every doc in 30 seconds

One paragraph per document. Read this page, then jump to what you need.

## [01-overview/project-overview.md](01-overview/project-overview.md)

Sales Management System is a Laravel 10 demo API over the Biztory invoicing dataset,
implementing the brief in `question.md`: a soft-deleting `Sale` model, an automatic
queued + broadcast `InvoiceCreated` event logged to `storage/logs/invoice.log`, REST
endpoints to store sales and total daily sales over a date range, and a Lighthouse
GraphQL mutation mirroring that total. Educational/demo project; local-only on
http://127.0.0.1:8111.

## [01-overview/architecture.md](01-overview/architecture.md)

Two thin single-action controllers and one GraphQL resolver over one Eloquent model.
`Sale::$dispatchesEvents` fires `InvoiceCreated` on every Eloquent create (queue +
broadcast + log listener). The daily-total aggregation is deliberately implemented twice —
`CountDailySalesController` and the `DailyTotalSales` mutation are twins that must change
together. The real schema (`sales`, `users`) lives in the `biztory.sql` MySQL dump; repo
migrations cover framework tables only.

## [02-setup/getting-started.md](02-setup/getting-started.md)

Four steps on a stock Windows machine: `pwsh ./setup.ps1` (installs Git, Node, PHP
**8.3** — the lock file rejects 8.4 — Composer, just, uv; idempotent), reopen PowerShell,
`just bootstrap` (deps + sqlite `.env` + migrate + Vite build), `just start` →
http://127.0.0.1:8111. The welcome page and GraphiQL work; anything touching the `sales`
table 500s on sqlite — expected.

## [03-development/workflow.md](03-development/workflow.md)

Branch off `main`, `just serve` while editing (no restarts needed), new migrations only,
gate with `just lint` + `just test` (full suite on sqlite `:memory:`; no CI — this is
the only gate),
Conventional Commits, PR via `gh`. House patterns: single-action controllers,
FormRequests, model-event dispatch, the `invoice` log channel, REST/GraphQL parity.
Watch for: the sqlite schema gap, Eloquent-bypassing writes skipping the event chain,
generated Lighthouse files.

## [04-deployment/deployment.md](04-deployment/deployment.md)

There is no deployment — no CI/CD, no hosting, local `just start` only; this doc says so
honestly. It also lists what a real deploy would need: production `.env`, a MySQL with
`biztory.sql` imported, auth on the open sales endpoints, a real web server, optimized
builds, a queue worker, and Pusher credentials for real broadcasts.

## [05-reference/commands.md](05-reference/commands.md)

The `just` recipe table: `bootstrap`, `start`/`serve`/`stop` (project-scoped kill),
`migrate`/`fresh`, `test` (full suite, sqlite `:memory:`, green without MySQL),
`lint`/`lint-fix`,
`claudex/o/h` — plus occasional raw artisan commands (`route:list`, `tinker`,
`lighthouse:validate-schema`). PHP/Composer resolve by absolute path so recipes work in
stale shells.

## [05-reference/project-layout.md](05-reference/project-layout.md)

Annotated tree: about a dozen domain PHP files (2 controllers, 2 FormRequests, 1 model,
1 event + 1 listener, 1 GraphQL resolver) on the stock Laravel 10 skeleton, the
`graphql/` schema, the `biztory.sql` dump, generated Lighthouse IDE helpers, and the
onboarding kit (`justfile`, `setup.ps1`, `.docs/`, `.claude/`). Ends with a "where to
make which change" table.

## [06-troubleshooting/common-issues.md](06-troubleshooting/common-issues.md)

Real symptom → cause → fix entries from the verify run: `composer install` refusing PHP
8.4 (nette pins → use the 8.3 install), `no such table: sales` everywhere data is touched
(schema lives in the dump; the test suite scaffolds its own copies), the mysql
"could not find driver" from a hand-copied `.env`, Pint failing on 13 files of
pre-existing debt, GraphQL's strict `Y-m-d` Date scalar, the Vite manifest 500, and
port-8111/stop behavior (two `php.exe` per serve).

## [07-faq/faq.md](07-faq/faq.md)

Quick answers: port 8111 (override with `$env:PORT`), no MySQL needed to boot, don't
import the dump into sqlite, `just fresh` seeds nothing, where the invoice log goes, the
queued/broadcast event runs inline locally, the REST/GraphQL twin rule (and the fixed
`payment_status=0` quirk), strict GraphQL dates, generated files to leave alone, and
"is this deployed?" (no).
