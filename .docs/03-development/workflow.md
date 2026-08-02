# Development workflow

> **TL;DR** Branch off `main` → edit → `just serve` to watch request logs → `just lint` +
> `just test` before committing → Conventional Commits → PR via `gh`. All
> routine commands are `just` recipes; Claude Code skills in `.claude/skills/` automate the
> git parts. Change the REST controller and its GraphQL twin together.

## The loop

1. **Branch** — `git checkout -b feat/<topic>` off `main` for non-trivial work.
2. **Run** — `just serve` (foreground, request logs) or `just start` (background).
   PHP's built-in server picks up PHP changes per request — no restart needed for
   controller/model/schema edits. GraphQL schema changes in `graphql/*.graphql` are also
   read per request (no schema cache is configured locally).
3. **Migrate** — schema change? Add a **new** migration (`php artisan make:migration ...`),
   never edit a committed one. Remember the app tables (`sales`, `users`) live in
   `biztory.sql`, not in migrations — if you add a column the MySQL dump and your migration
   drift apart; flag that in the PR rather than silently choosing one.
4. **Quality gate** — `just lint` (Pint style check; `just lint-fix` to auto-fix) and
   `just test` (full Unit + Feature suite, green on sqlite `:memory:` — the app tables the
   tests need are scaffolded per-test by `tests/TestCase.php`, no MySQL required). Any
   failure is yours. No CI exists — this local gate is the only gate. Keep the scaffolding
   in `tests/` in sync with `biztory.sql` if you ever touch the dump's schema.
5. **Commit** — Conventional Commits (`feat(sales): ...`). With Claude Code, `/commit`
   drives it. Never commit `.env`, `.mcp.json`, or `database/database.sqlite`.
6. **PR** — `gh pr create` into `main` (or `/create-pr`). Optional `/pre-pr-review` first.

## House patterns (follow these, the codebase already does)

| Concern | Pattern | Example |
| --- | --- | --- |
| Endpoints | Single-action controllers (`__invoke`) | `StoreSaleController`, `CountDailySalesController` |
| Write validation | FormRequest classes | `StoreSaleRequest`, `CountDailySalesRequest` |
| GraphQL validation | `@rules` directives in the schema | `graphql/sale.graphql` (`after_or_equal:start_date`, `exists:users,id`) |
| Domain events | Declarative model dispatch, not controller dispatch | `Sale::$dispatchesEvents['created'] => InvoiceCreated` |
| Audit logging | Dedicated log channel | `Log::channel('invoice')` → `storage/logs/invoice.log` |
| Aggregates | Query `sum()`, not stored totals | `CountDailySalesController::calculateTotalSale` |
| Test data | Factories | `SaleFactory` (also behind `GET /api/store-sale`) |
| API responses | `response()->json(['message' => ..., ...])` | both controllers |

## Things that will bite you

- **The REST/GraphQL twins must stay in sync** — `CountDailySalesController` and
  `app/GraphQL/Mutations/DailyTotalSales.php` intentionally mirror each other. Change the
  filter/aggregation logic in both or the endpoints diverge (`/pre-pr-review` checks this).
  They are reconciled today — both use the `isset()` pattern (the REST twin's old
  truthy check that dropped a legitimate `payment_status=0` filter is fixed and
  regression-tested in `tests/Feature/CountDailySalesTest.php`) — keep them that way.
- **Creating sales outside Eloquent skips the event chain** — `DB::insert()` or bulk
  `insert()` never fires `InvoiceCreated`, so no broadcast and no `invoice.log` line.
  Go through `Sale::create()` / instances.
- **The sqlite schema gap** — anything touching `sales` 500s locally
  (see [`../06-troubleshooting/common-issues.md`](../06-troubleshooting/common-issues.md)).
  Don't "fix" it by editing migrations or importing `biztory.sql` into sqlite.
- **Lighthouse IDE artifacts are generated** — leave `_lighthouse_ide_helper.php`,
  `programmatic-types.graphql`, and `schema-directives.graphql` untouched; they are
  refreshed by `php artisan lighthouse:ide-helper`, not edited by hand.
- **`vendor/` missing after a fresh clone** — recipes that need it (`just lint`,
  `just test`) fail until `just bootstrap` runs.

## Claude Code

- `claude` (or `just claudex`) starts a session; `CLAUDE.md` + `.claude/skills/` give it the
  house rules. Skill catalog: `.claude/skills/README.md`.
- MCP servers: `context7` (docs) + `playwright` (browser) work out of the box; `github`
  needs a PAT — see `/setup-mcp`. Health check: `/test-all-mcp`.

## Related docs

| Doc | Why |
| --- | --- |
| [../01-overview/architecture.md](../01-overview/architecture.md) | The flows and twins this workflow protects |
| [../05-reference/commands.md](../05-reference/commands.md) | Full recipe reference |
| [../06-troubleshooting/common-issues.md](../06-troubleshooting/common-issues.md) | When the gate fails |
