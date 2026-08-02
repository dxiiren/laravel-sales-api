# Project layout

> **TL;DR** Stock Laravel 10 skeleton plus a small sales domain (one model, two single-action
> controllers, one event + listener, one GraphQL mutation), the Biztory MySQL dump, and the
> onboarding kit (`justfile`, `setup.ps1`, `.docs/`, `.claude/`). About a dozen PHP files
> carry the whole domain.

## Tree

```
laravel-sales-api/
├── app/
│   ├── Events/
│   │   └── InvoiceCreated.php            # ShouldQueue + ShouldBroadcast (channel `invoice`); carries the Sale
│   ├── Listeners/
│   │   └── LogInvoiceCreated.php         # writes date/ref/total to storage/logs/invoice.log
│   ├── GraphQL/Mutations/
│   │   └── DailyTotalSales.php           # resolver twin of CountDailySalesController
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── StoreSaleController.php   # POST /api/sales (single-action)
│   │   │   ├── CountDailySalesController.php # POST /api/daily-sale: whereBetween + filters + sum('total')
│   │   │   └── Controller.php            # base
│   │   └── Requests/
│   │       ├── StoreSaleRequest.php      # validation for storing a sale
│   │       └── CountDailySalesRequest.php# date range required, optional payment_status/payee_id
│   ├── Models/
│   │   ├── Sale.php                      # SoftDeletes; table `sales`; $dispatchesEvents['created']
│   │   └── User.php                      # stock skeleton model (payee_id points at users)
│   └── Providers/
│       └── EventServiceProvider.php      # InvoiceCreated -> LogInvoiceCreated wiring
├── graphql/
│   ├── schema.graphql                    # root schema; imports user + sale
│   ├── sale.graphql                      # DailyTotalSales mutation + @rules validation
│   └── user.graphql                      # User type + queries
├── routes/
│   ├── api.php                           # POST /api/sales, POST /api/daily-sale, GET /api/store-sale
│   └── web.php                           # `/` welcome page only
├── config/                               # stock + lighthouse.php; logging.php adds the `invoice` channel
├── database/
│   ├── migrations/                       # 4 framework tables ONLY (password resets, failed jobs, PATs, jobs)
│   ├── factories/SaleFactory.php         # fake invoice rows (also behind GET /api/store-sale)
│   ├── seeders/DatabaseSeeder.php        # stock, seeds nothing
│   └── database.sqlite                   # local db (git-ignored, created by bootstrap)
├── tests/
│   ├── TestCase.php                      # test-only sales/users schema scaffolding (sqlite :memory:; mirrors biztory.sql)
│   ├── Unit/                             # 4 HTTP-kernel tests: both controllers, factory route, GraphQL
│   └── Feature/                          # store-sale happy path, seeded daily-sale count, omitted-keys regression
├── biztory.sql                           # MySQL dump: the REAL sales/users schema + sample data (do not import into sqlite)
├── question.md                           # the original assignment brief this app implements
├── _lighthouse_ide_helper.php            # generated Lighthouse IDE helper — do not edit
├── programmatic-types.graphql            # generated Lighthouse IDE helper — do not edit
├── schema-directives.graphql             # generated Lighthouse IDE helper — do not edit
├── public/                               # index.php front controller; build/ appears after npm run build
├── resources/                            # welcome.blade.php + stock Vite inputs (css/js)
├── storage/                              # logs (laravel.log, invoice.log), framework cache
├── composer.json / composer.lock         # laravel/framework ^10.10, lighthouse ^6.33, pint, phpunit ^10.1
├── package.json                          # vite 5 + laravel-vite-plugin + axios
├── justfile                              # dev recipes (see commands.md)
├── setup.ps1                             # one-time machine setup
├── CLAUDE.md                             # AI-assistant house rules
├── .mcp.json.stub                        # committed MCP config template (real .mcp.json git-ignored)
├── .claude/                              # settings, statusline hook, skills catalog
└── .docs/                                # this documentation set
```

## Where to make which change

| Change | Touch |
| --- | --- |
| New filter on daily totals | `CountDailySalesController::buildQuery` + `CountDailySalesRequest::rules()` **and** `app/GraphQL/Mutations/DailyTotalSales.php` + `graphql/sale.graphql` (the twins stay in sync) |
| New field on sales | new migration + `biztory.sql` drift decision + `Sale::$fillable` + `StoreSaleRequest::rules()` + `SaleFactory` |
| New invoice-lifecycle side effect | new listener + register in `EventServiceProvider` (the event already fires on every Eloquent create) |
| New REST endpoint | `routes/api.php` + a single-action controller + a FormRequest |
| New GraphQL field/mutation | `graphql/*.graphql` (+ a resolver class in `app/GraphQL/` if not directive-backed) |
| Log format/destination | `app/Listeners/LogInvoiceCreated.php` + the `invoice` channel in `config/logging.php` |

## Related docs

| Doc | Why |
| --- | --- |
| [commands.md](commands.md) | The recipes that operate on this tree |
| [../01-overview/architecture.md](../01-overview/architecture.md) | How these files interact at runtime |
| [../03-development/workflow.md](../03-development/workflow.md) | House patterns when editing |
