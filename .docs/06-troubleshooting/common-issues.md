# Common issues

> **TL;DR** Every entry below was hit for real while standing this repo up. The big one:
> the app's `sales`/`users` tables exist only in the `biztory.sql` MySQL dump, so on the
> local sqlite database every data endpoint and all four tests fail with
> `no such table: sales` — expected, not a regression. Each entry: symptom → cause → fix.

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
message; all four `tests/Unit/*` tests fail asserting `500 is identical to 200`.
**Cause.** The repo's migrations create framework tables only (password resets, failed
jobs, personal access tokens, jobs). The application schema (`sales`, `users`) lives
solely in the `biztory.sql` MySQL dump. The local `.env` is sqlite.
**Fix.** Nothing to fix locally — the server, welcome page, GraphiQL IDE, and request
validation (422s with proper messages) all work; this is the documented baseline. To
exercise data endpoints for real: install a MySQL server, import `biztory.sql`, point
`.env` at it (`.env.example`'s default shape). Do NOT import the dump into sqlite, edit
migrations, or change `.env.example`.

## `just test` aborts: `Test directory ".../tests/Feature" not found`

**Symptom.** Bare `just test` exits 1 before running anything.
**Cause.** `phpunit.xml` declares a Feature suite but `tests/Feature/` doesn't exist in
git (empty directories aren't tracked).
**Fix.** `just test --testsuite=Unit`. The four Unit tests then fail on the `sales` table
gap above — known baseline. Any OTHER failure is real.

## `artisan migrate` fails: "could not find driver" (mysql)

**Symptom.** `just migrate` / `just bootstrap` dies in PDO before running migrations.
**Cause.** Your `.env` still points at the biztory MySQL database (that is
`.env.example`'s default) and the local PHP has no `pdo_mysql` extension enabled.
**Fix.** `just bootstrap` writes a fresh `.env` switched to sqlite. If you copied `.env`
by hand: set `DB_CONNECTION=sqlite` and comment out the `DB_HOST` / `DB_PORT` /
`DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` lines (sqlite must not see
`DB_DATABASE=biztory`).

## `just lint` fails with style issues you didn't write

**Symptom.** Hit for real on the verify run: Pint `--test` exits 1 — 13 of 63 files
flagged (`app/Events/InvoiceCreated.php`, both controllers, the GraphQL mutation,
`routes/api.php`, the tests, `config/lighthouse.php`, even `_lighthouse_ide_helper.php`;
rules like `single_quote`, `concat_space`, `trailing_comma_in_multiline`,
`no_unused_imports`).
**Cause.** Pre-existing style debt — the app source predates Pint being wired into the
workflow. Not caused by your change.
**Fix.** Scope your check to the files you touched
(`php vendor\bin\pint --test path\to\file.php`), or clean the repo once with
`just lint-fix` in a dedicated `style:` commit. If you do sweep: `_lighthouse_ide_helper.php`
is a **generated** Lighthouse artifact — leave it out of any hand cleanup.

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
