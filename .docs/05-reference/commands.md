# Commands reference

> **TL;DR** Everything routine is a `just` recipe — run `just` to list them. PHP and Composer
> are resolved by absolute path (`%LOCALAPPDATA%\Programs\php-8.3`), so recipes work even in
> shells opened before `setup.ps1` updated PATH. `just test` runs the full suite, green
> with no MySQL.

## Setup

| Command | What it does |
| --- | --- |
| `pwsh ./setup.ps1` | One-time machine setup: Git, Node, PHP 8.3, Composer, uv/Python, just, gh, Claude CLI, `.mcp.json` from stub. Idempotent. |
| `just bootstrap` | One-time app setup: sqlite `.env`, db file, `composer install`, `npm install`, `npm run build`, `key:generate`, `migrate`. Idempotent. |

## App lifecycle

| Command | What it does |
| --- | --- |
| `just start` | Serve on http://127.0.0.1:8111 in a background window. Runs `stop` first so serves never double up. |
| `just serve` | Serve in the foreground (request logs visible; Ctrl+C to stop). |
| `just stop` | Kill only THIS repo's `php.exe` serve process(es) — matched by repo path in the command line, so other projects' servers survive. |

Port override: `$env:PORT = '8200'; just start` (defaults to 8111).

## Database

| Command | What it does |
| --- | --- |
| `just migrate` | Run pending migrations (`php artisan migrate --force`) — the four framework tables. |
| `just fresh` | Drop all tables, re-migrate, re-seed. The stock `DatabaseSeeder` seeds nothing, so this is effectively a clean re-migrate. **Destroys local data.** |

## Quality

| Command | What it does |
| --- | --- |
| `just test` | Full PHPUnit suite (Unit + Feature) on sqlite `:memory:`; app tables are scaffolded per-test by `tests/TestCase.php`, so it's green without MySQL. Filters pass through: `just test --filter=GraphQLDailyTest`. |
| `just lint` | Laravel Pint style check, read-only (`pint --test`). |
| `just lint-fix` | Laravel Pint auto-fix. |

## Claude Code

| Command | What it does |
| --- | --- |
| `just claudex` | Claude Code, Sonnet, all permissions. |
| `just claudeo` | Claude Code, Opus, all permissions. |
| `just claudeh` | Claude Code, Haiku, all permissions. |

## Useful raw artisan (no recipe on purpose — occasional use)

| Command | What it does |
| --- | --- |
| `php artisan route:list` | Show all registered routes (REST; GraphQL lives at `POST /graphql`). |
| `php artisan tinker` | REPL against the app + database. |
| `php artisan migrate:status` | Which migrations have run. |
| `php artisan lighthouse:validate-schema` | Validate the GraphQL schema after editing `graphql/*.graphql`. |

(In a fresh shell, `php` resolves once setup.ps1's PATH entry lands; otherwise use
`& "$env:LOCALAPPDATA\Programs\php-8.3\php.exe" artisan ...`.)

## Related docs

| Doc | Why |
| --- | --- |
| [project-layout.md](project-layout.md) | Where the files these commands touch live |
| [../02-setup/getting-started.md](../02-setup/getting-started.md) | First-run ordering of these commands |
| [../06-troubleshooting/common-issues.md](../06-troubleshooting/common-issues.md) | When a command fails |
