---
name: pre-pr-review
description: Use when the developer says 'pre-pr review', 'review my branch', 'audit my work', or 'self review' — self-reviews the current branch's diff against a Laravel / Eloquent / API / GraphQL / security checklist before opening a PR, then saves a report to .claude/workspace/reports/pr/.
model: opus
---

# Pre-PR Review (Self-Audit)

Self-review your feature-branch diff **before** opening a PR. This is a Laravel 10
API app — REST endpoints in `routes/api.php` plus a Lighthouse GraphQL schema; the only
Blade view is the stock welcome page. The goal is to catch query, validation, event, and
security problems early, not to nitpick style Pint already handles.

## Trigger

- `"pre-pr review"` / `"self review"`
- `"review my branch"` / `"review my work"` / `"review my code"`
- `"audit my work"` / `"audit my branch"`

## Do NOT flag (owned elsewhere)

- **Formatting / code style** — Laravel Pint owns it (`just lint`). Run it; don't hand-review it.
- **Pre-existing patterns** the developer copied from the codebase — not this branch's problem.

## Step 1 — Branch & base

```bash
git branch --show-current
```

If on `main`: **STOP** — "You're on `main`; switch to your feature branch first."

```bash
git fetch origin main
git diff origin/main...HEAD --name-only
```

If no files changed: **STOP** — "No changes vs `main`."

Scope the review to reviewable source: `app/**/*.php`, `routes/*.php`,
`database/migrations/`, `database/factories/`, `database/seeders/`,
`graphql/*.graphql`, `config/*.php`, `tests/**/*.php`. **Exclude** `composer.lock`,
`package-lock.json`, and `.claude/`. If only excluded files changed: **STOP** —
"No reviewable source changed."

Report: "Branch `{name}` changed {N} source files ({php} .php, {graphql} .graphql). Running review."

## Step 2 — Fetch the diff

```bash
git diff origin/main...HEAD -- 'app' 'routes' 'database' 'graphql' 'config' 'tests'
```

For context-dependent checks (cache invalidation, scope correctness, route binding), read
the **full file**, not just the hunk. If the diff exceeds ~4000 lines, prioritise the
highest-change files and note "focused review on largest files".

## Step 3 — Run the checklist

Verify each finding against the actual code before reporting it (grep how existing code does
the same thing; don't invent a rule the codebase doesn't follow).

| #   | Check                       | Label      | What to look for                                                                                                                                                                                                                                                  |
| --- | --------------------------- | ---------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1   | **Validation**              | issue      | Controller reading `$request->input()` and persisting without a FormRequest (`StoreSaleRequest` / `CountDailySalesRequest` are the house pattern); missing `rules()` for a new field; GraphQL args without `@rules` directives where the REST twin validates (`sale.graphql` uses `@rules(apply:[...])`).                             |
| 2   | **Mass assignment**         | issue      | New `sales` columns not in `Sale::$fillable`; `create($request->all())` instead of `create($request->validated())`.                                                                                                                                                |
| 3   | **Query efficiency**        | issue      | Unbounded `->get()` where an aggregate (`sum`/`count`) would do (the house pattern: `CountDailySalesController::buildQuery` + `sum('total')`); a query in a loop; date-range filters not using `whereBetween`.                                                      |
| 4   | **Soft-delete correctness** | issue      | Queries that must respect `deleted_at` using raw DB calls that bypass the `SoftDeletes` trait; a hard `delete()`/`forceDelete()` where soft delete is the contract; restoring without checking `trashed()`.                                                          |
| 5   | **Events & queue**          | issue      | Bypassing Eloquent (`DB::insert`, mass `insert()`) for sale creation — that skips the model's `created` event, so no `InvoiceCreated` broadcast and no `invoice.log` entry; a new listener not registered in `EventServiceProvider`; queued event payloads carrying whole models where an id would do.                              |
| 6   | **REST/GraphQL parity**     | issue      | Changing filter/aggregation logic in `CountDailySalesController` but not the `DailyTotalSales` GraphQL mutation (or vice versa) — they intentionally mirror each other; a schema change in `graphql/sale.graphql` with no matching resolver change in `app/GraphQL/Mutations/`.                                                    |
| 7   | **API responses**           | issue      | A new endpoint returning a Blade view or bare string instead of `response()->json()`; inconsistent JSON shape (`message` + payload is the house pattern); a write endpoint added as `GET`.                                                                          |
| 8   | **Migrations**              | issue      | Editing an already-committed migration instead of adding a new one; missing `down()`; a new `sales`/`users` column added ONLY to `biztory.sql` (or only to a migration) — schema lives in the MySQL dump, so flag any drift and make the developer decide.           |
| 9   | **Secrets / config**        | issue      | Hardcoded credentials/API keys; reading `env()` outside `config/`; committing `.env` or `database/database.sqlite`.                                                                                                                                                |
| 10  | **Tests**                   | issue      | New/changed behavior with no test in `tests/Unit/`; a changed assertion watered down to pass; a new test assuming the `sales` table exists on sqlite (it lives in the MySQL dump — see lint-check).                                                                 |
| 11  | **No debug leftovers**      | issue      | `dd()` / `dump()` / `ray()` / `Log::debug` spam / commented-out dead blocks / `TODO` without a follow-up.                                                                                                                                                          |
| 12  | **Eloquent design**         | suggestion | Query logic inline in a controller that belongs in a model scope; duplicated date-filter logic between the REST controller and the GraphQL resolver that could share a scope on `Sale`; a scope that silently changes global behavior.                              |
| 13  | **Logging**                 | suggestion | New invoice-lifecycle logging not using the dedicated `invoice` channel (`config/logging.php` → `storage/logs/invoice.log`); log lines missing ref_num/total context the audit trail needs.                                                                         |
| 14  | **Naming / conventions**    | nitpick    | Non-RESTful controller method names (single-action controllers here use `__invoke`); a GraphQL field that breaks the schema's naming; migration filename not matching its table.                                                                                    |

## Step 4 — Run the quality suite

```powershell
just lint
just test --testsuite=Unit
```

(Bare `just test` aborts on the missing `tests/Feature` directory — always pass
`--testsuite=Unit` here.)

Pint must be green. For the test run: a `no such table: sales` failure is the documented
sqlite schema gap (tests need the MySQL `biztory.sql` schema — see
`.docs/06-troubleshooting/common-issues.md`), not a blocking finding; any OTHER test
failure is an **issue** (blocking) — paste the failing output line.

## Step 5 — Finding labels & caps

- **issue** (blocking) — fix before opening the PR.
- **suggestion** (non-blocking) — recommended.
- **nitpick** (non-blocking) — minor/optional.

Every finding must carry: the label, the `file:line`, and **WHY** it matters (not just what).
Issues: uncapped. Suggestions + nitpicks: cap at 15 total; note "{X} more non-blocking findings
omitted" if over.

## Step 6 — Present

```
## Pre-PR Review: {branch}
Branch: {branch} -> main   |   Files: {N} ({php} .php, {graphql} .graphql)
Quality suite: {pint pass/fail} · {test pass/fail}

### Issues (fix before PR)
1. [path:line] Finding — why it matters

### Suggestions
2. [path:line] Finding

### Nitpicks
3. [path:line] Finding

---
{Total} findings: {issues} issues, {suggestions} suggestions, {nitpicks} nitpicks
```

Zero findings → "No issues found — branch looks clean. Ready to open the PR."

## Step 7 — Save the report

Path: `.claude/workspace/reports/pr/{branch}-{YYYY-MM-DD}.md` (replace `/` in the branch name
with `-`; overwrite on a same-day re-run; create the folder if missing). Frontmatter then the
same body as the terminal output:

```yaml
---
branch: { branch }
base: main
date: { YYYY-MM-DD }
files_changed: { N }
issues: { count }
suggestions: { count }
nitpicks: { count }
---
```

Confirm: "Report saved to `{path}`".

## Tone

Self-improvement, not a verdict from a lead. "Consider extracting…", not "You must fix…". Never
directive, never judgmental.
