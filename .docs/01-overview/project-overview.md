# Project overview

> **TL;DR** A Laravel 10 demo API for managing sales data from the Biztory invoicing
> dataset: soft-deleted sales, a queued + broadcast `InvoiceCreated` event with an
> `invoice.log` audit trail, REST endpoints for storing/totalling sales, and a Lighthouse
> GraphQL mutation mirroring the daily-total query. Educational/demonstration project;
> runs locally only.

## What it is

The Sales Management System implements the assignment brief in [`question.md`](../../question.md)
(kept at the repo root): build a small but complete sales pipeline on Laravel. The four
deliverables, as shipped:

| Feature | Where it lives |
| --- | --- |
| `Sale` model with soft-delete trait | `app/Models/Sale.php` — `SoftDeletes`, table `sales`, mass-assignable invoice fields |
| `InvoiceCreated` event, broadcast + queued | `app/Events/InvoiceCreated.php` — `ShouldQueue` + `ShouldBroadcast` on channel `invoice`, dispatched automatically via `Sale::$dispatchesEvents['created']` |
| `InvoiceCreated` logging subscriber | `app/Listeners/LogInvoiceCreated.php` — writes `date, ref, total` to `storage/logs/invoice.log` through the dedicated `invoice` log channel (`config/logging.php`) |
| GraphQL daily-total endpoint | `app/GraphQL/Mutations/DailyTotalSales.php` + `graphql/sale.graphql` — date range required, optional `payment_status` / `payee_id` filters |

## The endpoints

| Method + path | Purpose |
| --- | --- |
| `POST /api/sales` | Store a sale (validated by `StoreSaleRequest`) |
| `POST /api/daily-sale` | Total of `sales.total` between `start_date` and `end_date`, optional `payment_status` / `payee_id` filters; returns `RM x,xxx.xx` |
| `GET /api/store-sale` | Create a sale from `SaleFactory` (demo helper) |
| `POST /graphql` | Lighthouse endpoint — `DailyTotalSales` mutation mirrors `/api/daily-sale` |
| `GET /graphiql` | In-browser GraphQL IDE (mll-lab/laravel-graphiql) |
| `GET /` | Stock Laravel welcome page |

## The dataset

`biztory.sql` at the repo root is a ~650 KB MySQL dump holding the REAL application schema
(`sales`, `users`) plus sample data. The repo's migrations create framework tables only
(password resets, failed jobs, personal access tokens, jobs queue). Consequences:

- On the local sqlite database, everything boots and validates, but any query touching
  `sales` fails with `no such table: sales` — see
  [`../06-troubleshooting/common-issues.md`](../06-troubleshooting/common-issues.md).
  (The PHPUnit suite is the exception: it runs on sqlite `:memory:` and scaffolds
  test-only copies of `sales`/`users` in `tests/TestCase.php`, so `just test` is green.)
- To exercise the endpoints with data, import `biztory.sql` into a MySQL server and point
  `.env` at it (that is `.env.example`'s default shape). Do NOT import the dump into sqlite.

## Intent and status

From the original README: the project is intended for educational and demonstration
purposes and may require further customization for real business use; ensure proper
security measures before handling sensitive sales data. There is no auth on the sales
endpoints, no CI, and no deployment target — it runs locally.

## Related docs

| Doc | Why |
| --- | --- |
| [architecture.md](architecture.md) | How the request, event, and GraphQL flows fit together |
| [../02-setup/getting-started.md](../02-setup/getting-started.md) | Get it running from a fresh PC |
| [../06-troubleshooting/common-issues.md](../06-troubleshooting/common-issues.md) | The sqlite schema gap and other known symptoms |
