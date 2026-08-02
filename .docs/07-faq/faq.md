# FAQ

> **TL;DR** Quick answers: port 8111, sqlite locally (no MySQL needed to boot), the
> `sales` table only exists in `biztory.sql` (tests scaffold their own copy), `just test`
> runs the full suite green, GraphQL dates are strict `Y-m-d`, and no — this is not
> deployed anywhere.

## Running

**Q. What port does it run on?**
8111 — `just start` / `just serve` bind `127.0.0.1:8111`. Override per shell:
`$env:PORT='8200'; just start`. Don't hardcode other ports into scripts or docs.

**Q. Do I need MySQL/XAMPP/Docker to run it?**
No — the app boots on a local sqlite file (`database/database.sqlite`, created by
`just bootstrap`). You only need MySQL if you want the data endpoints to return real
results (import `biztory.sql`, point `.env` at it).

**Q. Why are there two `php.exe` processes when serving?**
`php artisan serve` spawns a worker child. `just stop` kills both (it matches by repo
path, not process name).

**Q. `just start` vs `just serve`?**
`start` = background window, terminal stays free, `just stop` to end.
`serve` = foreground with live request logs, Ctrl+C to end.

**Q. Why PHP 8.3 when composer.json says `^8.1`?**
`composer.lock` pins `nette/schema`/`nette/utils` versions that reject PHP 8.4, so the
newest PHP fails the platform check. 8.3 is the newest that works — `setup.ps1` installs
it side-by-side and the justfile targets it by absolute path.

## Data

**Q. Why does every `/api/*` call return `no such table: sales`?**
The repo's migrations create framework tables only; the app schema lives in the
`biztory.sql` MySQL dump. Expected on sqlite — see
[../06-troubleshooting/common-issues.md](../06-troubleshooting/common-issues.md).

**Q. Should I import `biztory.sql` into sqlite to fix that?**
No. It's a MySQL dump (MySQL-specific DDL). If you want data, import it into a real
MySQL server and point your local `.env` there.

**Q. What is `question.md` at the repo root?**
The original assignment brief this app implements (model + event + listener + GraphQL
endpoint). Kept for context.

**Q. Does `just fresh` seed anything?**
No — `DatabaseSeeder` is the empty stock one. `fresh` just rebuilds the four framework
tables. Demo data comes from `GET /api/store-sale` (SaleFactory) — which needs the
`sales` table, i.e. MySQL.

## Features

**Q. Where does the invoice log go?**
`storage/logs/invoice.log`, via the dedicated `invoice` channel in `config/logging.php`.
`LogInvoiceCreated` writes a line for every `InvoiceCreated` event.

**Q. Is the event really queued/broadcast?**
Declared, yes (`ShouldQueue` + `ShouldBroadcast` on channel `invoice`) — but locally
`QUEUE_CONNECTION=sync` runs it inline and `BROADCAST_DRIVER=log` only logs the
broadcast. Real-time delivery would need Pusher credentials and a queue worker.

**Q. Why do REST and GraphQL both implement daily totals?**
By design — `CountDailySalesController` and the `DailyTotalSales` mutation are
deliberate twins. Change both together; `tests/Feature/RestGraphqlParityTest.php`
asserts they return the same money string for every filter shape. (Two past quirks are
fixed: the REST twin's truthy check dropped `payment_status=0`, and the GraphQL twin
rejected a null/empty `payee_id` that REST treats as "no filter" — its `@rules` now
read `["nullable","exists:users,id"]`.)

**Q. Do soft-deleted sales still count toward the totals?**
No. `Sale` uses `SoftDeletes`, so its global scope hides trashed rows from both twins'
`sum('total')`. `restore()` puts the amount back, `forceDelete()` removes the row —
all pinned by `tests/Feature/SaleSoftDeleteTest.php`.

**Q. I edited `graphql/sale.graphql` and nothing changed. Why?**
The Lighthouse schema is cached to `bootstrap/cache/lighthouse-schema.php` for every
`APP_ENV` except `local`. Delete that file (or keep `APP_ENV=local`). The test suite
sidesteps it with `LIGHTHOUSE_SCHEMA_CACHE_ENABLE=false` in `phpunit.xml`.

**Q. GraphQL rejects my dates with "Trailing data"?**
The `Date` scalar wants strict `Y-m-d` — `"2024-01-01"`, not `"2024-01-01 00:00:00"`.

## Code

**Q. How can the tests pass without MySQL?**
The suite runs on sqlite `:memory:` (`phpunit.xml`), and `tests/TestCase.php` creates
minimal test-only copies of the `sales`/`users` tables per test, cross-checked against
`biztory.sql`. That is test scaffolding, not app schema — migrations stay
framework-only, and the running app still needs MySQL for data endpoints.

**Q. How big is the test suite?**
55 tests / 227 assertions across `tests/Unit/` and `tests/Feature/` — see the table in
[`../01-overview/architecture.md`](../01-overview/architecture.md#testing).

**Q. Why does `just lint` fail when I changed nothing?**
Pre-existing Pint style debt (8 files). See
[../06-troubleshooting/common-issues.md](../06-troubleshooting/common-issues.md).

**Q. Can I edit `_lighthouse_ide_helper.php` / `schema-directives.graphql` / `programmatic-types.graphql`?**
No — they're Lighthouse-generated IDE helper artifacts. Regenerate with
`php artisan lighthouse:ide-helper` if ever needed.

**Q. Is this deployed anywhere?**
No — local only, no CI/CD. See [../04-deployment/deployment.md](../04-deployment/deployment.md).

**Q. Which docs should I read first?**
[`../tldr.md`](../tldr.md), then
[`../02-setup/getting-started.md`](../02-setup/getting-started.md).

## Related docs

| Doc | Why |
| --- | --- |
| [../06-troubleshooting/common-issues.md](../06-troubleshooting/common-issues.md) | Full symptom → cause → fix entries |
| [../01-overview/project-overview.md](../01-overview/project-overview.md) | What the app is |
| [../05-reference/commands.md](../05-reference/commands.md) | Every command mentioned above |
