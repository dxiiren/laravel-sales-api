# Deployment

> **TL;DR** There is no deployment. This app has no CI/CD pipeline, no hosting target, and no
> production environment — it runs locally via `just start` on http://127.0.0.1:8111. This
> page records that honestly and sketches what a real deploy would need.

## Current state

| Aspect | State |
| --- | --- |
| CI/CD | None — no workflow files, no pipeline |
| Hosting | None — local `php artisan serve` only (via `just start`) |
| Database | Local sqlite for framework tables; the real schema/data is the `biztory.sql` MySQL dump |
| Secrets | Local `.env` only (git-ignored; `.env.example` is the template) |
| Asset build | `npm run build` locally (run by `just bootstrap`) |
| Auth | None on the sales endpoints (Sanctum is installed but only guards the stock `/api/user` route) |

The local "gate" is `just lint` + `just test --testsuite=Unit`, run by hand before
committing.

## If you ever deploy this

Not prescriptive — a checklist of what would have to change:

1. **Environment** — a real `.env` per environment: `APP_ENV=production`,
   `APP_DEBUG=false`, a generated `APP_KEY`, an `APP_URL`.
2. **Database** — a MySQL server with `biztory.sql` imported and `.env` pointed at it
   (`.env.example`'s default shape). The repo's migrations add the framework tables on top.
   Without the dump, every `sales` query fails — sqlite is not an option in production.
3. **Auth** — the sales endpoints are open. Put them behind `auth:sanctum` (already
   installed) or an equivalent before exposing them; the original README itself warns the
   project needs security hardening for real business use.
4. **Web server** — serve `public/` via a real web server (nginx + php-fpm, or
   Forge/Vapor); `php artisan serve` is a dev server only.
5. **Build** — `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`,
   `php artisan config:cache route:cache view:cache` (+ consider
   `php artisan lighthouse:cache` for the GraphQL schema).
6. **Queue** — locally `QUEUE_CONNECTION=sync` runs the "queued" `InvoiceCreated` event
   inline. A real deploy that switches to `database`/`redis` needs a worker
   (`php artisan queue:work`) or invoice logging silently stops.
7. **Broadcasting** — `BROADCAST_DRIVER=log` locally. Real-time invoice broadcasts on the
   `invoice` channel need Pusher (or compatible) credentials — the `PUSHER_*` keys in
   `.env.example` are empty placeholders.

## Related docs

| Doc | Why |
| --- | --- |
| [../02-setup/getting-started.md](../02-setup/getting-started.md) | The local "deployment" that does exist |
| [../01-overview/architecture.md](../01-overview/architecture.md) | What the app needs at runtime |
| [../06-troubleshooting/common-issues.md](../06-troubleshooting/common-issues.md) | Local serving issues |
