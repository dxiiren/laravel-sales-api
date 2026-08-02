# Common issues

> **TL;DR** Every entry below was hit for real while standing this repo up. The big one:
> the app's `sales`/`users` tables exist only in the `biztory.sql` MySQL dump, so on the
> local sqlite database every data endpoint fails with `no such table: sales` — expected,
> not a regression. (The PHPUnit suite is exempt: it runs on sqlite `:memory:` and
> scaffolds test-only copies of those tables in `tests/TestCase.php`.) Each entry:
> symptom → cause → fix.

## `composer install` fails: "nette/schema ... requires php 8.1 - 8.3"

**Symptom.** On PHP 8.4, `composer install` aborts its platform check citing
`nette/schema v1.3.0` (`php: 8.1 - 8.3`) and `nette/utils v4.0.4` (`php >=8.0 <8.4`).
**Cause.** The committed `composer.lock` pins those versions; they reject newer PHP.
**Fix.** Use the PHP **8.3** that `setup.ps1` installs to
`%LOCALAPPDATA%\Programs\php-8.3` — the justfile targets it by absolute path, so recipes
do the right thing automatically. Do NOT `composer update` to "fix" it (lock regeneration
is prohibited).

## Anything touching `sales` returns 500: `no such table: sales`

**Symptom.** Hit for real on the verify run:
`GET /api/store-sale` → 500; `POST /api/daily-sale` with a valid payload → 500
`SQLSTATE[HY000]: General error: 1 no such table: sales (Connection: sqlite, ...)`;
the `DailyTotalSales` GraphQL mutation → HTTP 200 but an `errors[]` entry with the same
message. (Historically the four `tests/Unit/*` tests failed the same way — they now pass
via the test-only schema scaffolding in `tests/TestCase.php`.)
**Cause.** The repo's migrations create framework tables only (password resets, failed
jobs, personal access tokens, jobs). The application schema (`sales`, `users`) lives
solely in the `biztory.sql` MySQL dump. The local `.env` is sqlite.
**Fix.** Nothing to fix locally — the server, welcome page, GraphiQL IDE, and request
validation (422s with proper messages) all work; this is the documented baseline. To
exercise data endpoints for real: install a MySQL server, import `biztory.sql`, point
`.env` at it (`.env.example`'s default shape). Do NOT import the dump into sqlite, edit
migrations, or change `.env.example`.

## `just test` fails with `no such table: sales` (or aborts on `tests/Feature`)

**Symptom.** Test failures citing `no such table: sales`, or (older checkouts) bare
`just test` aborting with `Test directory ".../tests/Feature" not found`.
**Cause.** You are on a checkout that predates the test scaffolding. Current `main` pins
the suite to sqlite `:memory:` in `phpunit.xml`, creates test-only `sales`/`users`
tables per test in `tests/TestCase.php`, and ships a `tests/Feature/` suite.
**Fix.** Update to `main`; bare `just test` then runs the full suite green with no
MySQL. Any failure on current `main` is real.

## `artisan migrate` fails: "could not find driver" (mysql)

**Symptom.** `just migrate` / `just bootstrap` dies in PDO before running migrations.
**Cause.** Your `.env` still points at the biztory MySQL database (that is
`.env.example`'s default) and the local PHP has no `pdo_mysql` extension enabled.
**Fix.** `just bootstrap` writes a fresh `.env` switched to sqlite. If you copied `.env`
by hand: set `DB_CONNECTION=sqlite` and comment out the `DB_HOST` / `DB_PORT` /
`DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` lines (sqlite must not see
`DB_DATABASE=biztory`).

## `just lint` fails with style issues you didn't write

**Symptom.** Pint `--test` exits 1 — 8 of 72 files flagged
(`app/Events/InvoiceCreated.php`, `StoreSaleController`, `CountDailySalesRequest`,
`app/Listeners/LogInvoiceCreated.php`, `app/Models/Sale.php`, `routes/api.php`,
`config/lighthouse.php`, and `_lighthouse_ide_helper.php`; rules like `single_quote`,
`concat_space`, `trailing_comma_in_multiline`, `no_unused_imports`).
**Cause.** Pre-existing style debt — the app source predates Pint being wired into the
workflow. Not caused by your change.
**Fix.** Scope your check to the files you touched
(`php vendor\bin\pint --test path\to\file.php`), or clean the repo once with
`just lint-fix` in a dedicated `style:` commit. If you do sweep: `_lighthouse_ide_helper.php`
is a **generated** Lighthouse artifact — leave it out of any hand cleanup.

## A `graphql/*.graphql` edit has no effect (stale Lighthouse schema cache)

**Symptom.** Hit for real while fixing the `payee_id` `@rules`: the schema file changed,
the app (or the test suite) kept serving the old directives — a `nullable` added to
`@rules(apply: [...])` was simply ignored.
**Cause.** `config/lighthouse.php` sets
`'schema_cache.enable' => env('LIGHTHOUSE_SCHEMA_CACHE_ENABLE', env('APP_ENV') !== 'local')`
and writes `bootstrap/cache/lighthouse-schema.php` (git-ignored). Anything running under
a non-`local` `APP_ENV` — including PHPUnit, where `phpunit.xml` sets `APP_ENV=testing` —
resolves the schema from that file, not from `graphql/`.
**Fix.** The suite now pins `LIGHTHOUSE_SCHEMA_CACHE_ENABLE=false` in `phpunit.xml`, so
tests always build the schema fresh — leave that in. Outside tests, delete
`bootstrap/cache/lighthouse-schema.php` after a schema edit, or keep `APP_ENV=local`.

## GraphQL mutation errors with "Trailing data" from `DateScalar.php`

**Symptom.** Hit for real: `DailyTotalSales(start_date: "2024-01-01 00:00:00", ...)`
returns `errors[]: "Trailing data"` pointing at Lighthouse's `DateScalar`.
**Cause.** The schema types `start_date`/`end_date` as `Date` — Lighthouse's `Date`
scalar parses strict `Y-m-d` only; a trailing time component is rejected.
**Fix.** Pass plain dates: `start_date: "2024-01-01"`. (The REST endpoint's
`date` validation rule is looser and accepts both.)

## `GET /` returns 500 with a Vite "manifest not found" error

**Symptom.** The welcome page 500s complaining about `public/build/manifest.json`.
**Cause.** The Blade view calls `@vite(...)`; the built assets don't exist until
`npm run build` has run once.
**Fix.** `just bootstrap` (runs the build). Confirmed after build: `GET /` → 200 and
`GET /graphiql` → 200.

## `/graphiql` returns 200 but the page is stuck at "Loading..."

**Symptom.** Hit for real: `GET /graphiql` → 200, but the browser shows only "Loading..."
forever; the console logs 404s for `unpkg.com/graphiql/graphiql.min.js` and
`graphiql.min.css`.
**Cause.** The vendor view (mll-lab/laravel-graphiql v3.1.0) loads **unpinned**
`unpkg.com/graphiql/...` UMD bundles. graphiql v4+ deleted those files, so the CDN
"latest" URLs now 404 — `php artisan graphiql:download-assets` dies on the same 404.
**Fix.** Already fixed in-repo: era-correct pinned assets (graphiql 2.4.7, react 17.0.2,
plugin-explorer 0.2.0) are committed under `public/vendor/graphiql/`, and the package
serves local copies in preference to the CDN — the IDE works even offline. If the page
regresses, check those files still exist; do not delete or "update" them. A console 404
for `favicon.ico` is harmless (the upstream icon URL is gone too).

## `PHP 8.3 not found at ...` / vendor missing

**Symptom.** A recipe fails immediately with the `_require-php` guard message, or PHP
fatals about `vendor/autoload.php` / `vendor\bin\pint`.
**Cause.** `setup.ps1` hasn't run on this machine, or `composer install` never ran on
this clone.
**Fix.** `pwsh ./setup.ps1`, close and reopen PowerShell, then `just bootstrap`.

## Port 8111 already in use / server won't die

**Symptom.** `just start` runs but the page errors, or the port stays bound.
**Cause.** A lingering serve process — `php artisan serve` spawns a worker child, so
**two** `php.exe` processes per serve is normal (verified: `just stop` reported
"Stopped 2 project php.exe process(es)").
**Fix.** `just stop` — it kills every `php.exe` whose command line carries this repo's
path (other projects' servers survive). If the port is held by a non-project process:
`netstat -ano | findstr :8111` and investigate that PID. Or serve elsewhere:
`$env:PORT='8200'; just start`.

## Related docs

| Doc | Why |
| --- | --- |
| [../02-setup/getting-started.md](../02-setup/getting-started.md) | The happy path these issues deviate from |
| [../01-overview/architecture.md](../01-overview/architecture.md) | Why the schema lives in the dump |
| [../07-faq/faq.md](../07-faq/faq.md) | Shorter Q&A versions of some of these |
