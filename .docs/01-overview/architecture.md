# Architecture

> **TL;DR** Two thin single-action controllers and one GraphQL resolver sit over a single
> `Sale` Eloquent model. Sale creation triggers an automatic `InvoiceCreated` event
> (queue + broadcast + log listener). The daily-total aggregation is deliberately
> implemented twice — REST and GraphQL — with mirrored filter logic.

## Request flows

### Store a sale (REST)

```
POST /api/sales
  └─ StoreSaleController::__invoke          (single-action)
       └─ StoreSaleRequest                  (validation rules)
            └─ Sale::create($validated)
                 └─ Eloquent `created` event
                      └─ InvoiceCreated     (ShouldQueue + ShouldBroadcast, channel `invoice`)
                           └─ LogInvoiceCreated listener
                                └─ Log::channel('invoice') → storage/logs/invoice.log
```

Key detail: the event is wired declaratively via `Sale::$dispatchesEvents['created']`,
not dispatched by the controller. Anything that creates a `Sale` through Eloquent —
including `GET /api/store-sale`'s factory call — fires the whole chain. Raw
`DB::insert()` / bulk `insert()` would silently skip it.

### Daily totals (REST + GraphQL twins)

```
POST /api/daily-sale                         POST /graphql (mutation DailyTotalSales)
  └─ CountDailySalesController                 └─ App\GraphQL\Mutations\DailyTotalSales
       └─ CountDailySalesRequest                    └─ @rules directives in graphql/sale.graphql
       └─ whereBetween(created_at) + optional       └─ same whereBetween + optional filters
          payment_status / payee_id filters
       └─ sum('total') → "RM x,xxx.xx"              └─ sum('total') → "RM x,xxx.xx"
```

The two implementations intentionally mirror each other. When changing filter or
aggregation logic, change BOTH (the `pre-pr-review` skill checks this). The twins are
now reconciled and, more importantly, **the parity is asserted**: both `buildQuery`
methods use the identical `isset($x) && $x !== ''` guard, so a legitimate
`payment_status=0` filter is honoured and an omitted / null / empty value means "no
filter" on both endpoints.

Their history — the old truthy check dropped `payment_status=0`, before that reading
validated() keys directly 500'd on omitted keys, and the GraphQL twin used to reject a
null/empty `payee_id` its REST twin accepted — is regression-tested in
`tests/Feature/CountDailySalesTest.php` and
`tests/Feature/RestGraphqlParityTest.php`. The parity test seeds one fixed ledger,
drives both endpoints with the same filters, and `assertSame`s the two money strings
for every filter shape (including the ranges that match nothing, guarded against the
empty-set tautology).

## GraphQL layer

- **nuwave/lighthouse 6** serves `POST /graphql`; schema files live in `graphql/`
  (`schema.graphql` imports `user.graphql` + `sale.graphql`).
- `DailyTotalSales` is declared as a **mutation** returning `TotalSales`
  (`amount`, `payment_status`, `payee_id`), with `@rules` validation on the arguments
  (`end_date` must be `after_or_equal:start_date`, `payee_id` is
  `["nullable","exists:users,id"]` — `nullable` matters, since Lighthouse coerces an
  explicit `null`/empty `payee_id` into the validator and `exists` alone rejected it,
  diverging from the REST twin's `nullable|int|exists:users,id`).
- **The schema is cached on disk** (`bootstrap/cache/lighthouse-schema.php`) for every
  `APP_ENV` but `local`. Tests therefore set `LIGHTHOUSE_SCHEMA_CACHE_ENABLE=false` in
  `phpunit.xml`; without it a schema edit is invisible to the suite.
- **mll-lab/laravel-graphiql** serves the in-browser IDE at `/graphiql`.
- `_lighthouse_ide_helper.php`, `programmatic-types.graphql`, and
  `schema-directives.graphql` at the root are Lighthouse-generated IDE helper artifacts —
  leave them untouched.

## Persistence

- `Sale` → table `sales` with `SoftDeletes` (`deleted_at`), ~20 mass-assignable invoice
  columns (`ref_num`, `invoice_date`, `total`, `paid`, `due`, `payment_status`, ...).
- The `sales`/`users` DDL exists ONLY in `biztory.sql` (MySQL dump). Repo migrations
  create framework tables: `password_reset_tokens`, `failed_jobs`,
  `personal_access_tokens`, `jobs`.
- Local dev runs sqlite (`database/database.sqlite`, git-ignored); the framework
  migrations run clean there, the `sales` queries do not — the documented gap.
- Queue: `.env.example` ships `QUEUE_CONNECTION=sync`, so the "queued" event actually
  runs inline in local dev; the `jobs` table exists for a `database` driver switch.

## Testing

PHPUnit 10, two suites, **55 tests / 227 assertions**, all green.

| Suite | File | What it pins |
| --- | --- | --- |
| Unit | `CountDailySalesControllerTest` | Exact REST totals per filter combination over a seeded ledger |
| Unit | `GraphQLDailyTest` | Exact GraphQL totals via the relative `/graphql` route |
| Unit | `FactoryStoreSalesTest` | `GET /api/store-sale` factory route |
| Unit | `StoreSaleControllerTest` | `POST /api/sales` persists the payload |
| Unit | `LogInvoiceCreatedTest` | The listener writes to the `invoice` log channel and is wired to the event |
| Feature | `RestGraphqlParityTest` | REST ⇄ GraphQL twin parity across every filter shape |
| Feature | `SaleSoftDeleteTest` | Soft-deleted sales leave both money totals; `restore()`/`forceDelete()` |
| Feature | `InvoiceCreatedEventTest` | `InvoiceCreated` dispatch, the `invoice` broadcast channel, no dispatch on 422 |
| Feature | `DailySaleValidationTest` | The bare `{"errors": …}` 422 contract + the GraphQL `@rules` twin |
| Feature | `StoreSaleValidationTest` | Every `StoreSaleRequest` rule rejects and persists nothing |
| Feature | `CountDailySalesTest`, `StoreSaleTest` | The original happy paths + filter regressions |

`phpunit.xml` pins the suite to sqlite `:memory:` and disables the Lighthouse schema
cache, and `tests/TestCase.php` scaffolds test-only copies of `sales`/`users` per test
(columns cross-checked against `biztory.sql`, user id 506 seeded for the GraphQL
`exists:users,id` rule) — so `just test` is green with no MySQL, while the running app
still needs the dump for data. The scaffolding is test infrastructure only; never
promote it to a migration. Shared GraphQL/REST call helpers live in
`tests/Concerns/QueriesDailyTotals.php`.

## Related docs

| Doc | Why |
| --- | --- |
| [project-overview.md](project-overview.md) | Feature list and endpoint table |
| [../03-development/workflow.md](../03-development/workflow.md) | Day-2 loop: recipes, style, tests |
| [../05-reference/project-layout.md](../05-reference/project-layout.md) | Full annotated file tree |
