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
aggregation logic, change BOTH (the `pre-pr-review` skill checks this). Note the twins
currently differ subtly: the REST controller uses a null-coalesced truthy check
(`if ($validatedData['payment_status'] ?? null)` — the coalesce fixed the old
omitted-key `Undefined array key` 500, regression-tested in
`tests/Feature/CountDailySalesTest.php`) which still drops a legitimate
`payment_status=0` filter, while the GraphQL resolver uses `isset()`. Treat the GraphQL
behavior as the correct one if you ever reconcile them.

## GraphQL layer

- **nuwave/lighthouse 6** serves `POST /graphql`; schema files live in `graphql/`
  (`schema.graphql` imports `user.graphql` + `sale.graphql`).
- `DailyTotalSales` is declared as a **mutation** returning `TotalSales`
  (`amount`, `payment_status`, `payee_id`), with `@rules` validation on the arguments
  (`end_date` must be `after_or_equal:start_date`, `payee_id` must exist in `users`).
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

PHPUnit 10, two suites. `tests/Unit/` holds the four original tests exercising the two
controllers, the factory route, and the GraphQL mutation via HTTP kernel calls;
`tests/Feature/` adds the store-sale happy path, a seeded daily-sale count, and the
omitted-nullable-keys regression test. `phpunit.xml` pins the suite to sqlite
`:memory:`, and `tests/TestCase.php` scaffolds test-only copies of `sales`/`users` per
test (columns cross-checked against `biztory.sql`, user id 506 seeded for the GraphQL
`exists:users,id` rule) — so `just test` is green with no MySQL, while the running app
still needs the dump for data. The scaffolding is test infrastructure only; never
promote it to a migration.

## Related docs

| Doc | Why |
| --- | --- |
| [project-overview.md](project-overview.md) | Feature list and endpoint table |
| [../03-development/workflow.md](../03-development/workflow.md) | Day-2 loop: recipes, style, tests |
| [../05-reference/project-layout.md](../05-reference/project-layout.md) | Full annotated file tree |
