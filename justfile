# Sales Management System justfile — development recipes

set shell := ["powershell.exe", "-NoProfile", "-Command"]

# PHP + Composer extracted by setup.ps1 into the user's LOCALAPPDATA\Programs\php-8.3.
# Absolute paths keep recipes working even in PowerShell sessions opened before
# setup.ps1 updated the User PATH environment variable.
php      := env_var('LOCALAPPDATA') + '\Programs\php-8.3\php.exe'
composer := env_var('LOCALAPPDATA') + '\Programs\php-8.3\composer.bat'
port     := env_var_or_default('PORT', '8111')

# List available recipes
default:
    @just --list

# ─── Guards ───────────────────────────────────────────────
# Fail fast with a "run setup.ps1" message when a required tool is missing, instead of
# letting a recipe explode with a cryptic error deep in its body. Private: hidden from
# `just --list`.

# PHP 8.3 — installed by setup.ps1 to a pinned path; needed by every artisan/composer recipe.
[private]
_require-php:
    @if (-not (Test-Path '{{php}}')) { Write-Error "PHP 8.3 not found at {{php}}`n  -> Run setup.ps1 first:  pwsh ./setup.ps1"; exit 1 }

# Composer — installed by setup.ps1 alongside PHP; needed by bootstrap.
[private]
_require-composer:
    @if (-not (Test-Path '{{composer}}')) { Write-Error "Composer not found at {{composer}}`n  -> Run setup.ps1 first:  pwsh ./setup.ps1"; exit 1 }

# Node/npm — bootstrap runs `npm install` + `npm run build` for the Vite assets.
[private]
_require-node:
    @if (-not (Get-Command npm -ErrorAction SilentlyContinue)) { Write-Error "Node/npm not found on PATH.`n  -> Run setup.ps1 first:  pwsh ./setup.ps1"; exit 1 }

# ─── App lifecycle ───────────────────────────────────────

# .env.example points at the biztory MySQL database (the biztory.sql dump). Local dev uses
# sqlite instead, so the fresh .env gets DB_CONNECTION=sqlite and the mysql-specific DB_*
# lines commented out (sqlite must NOT see DB_DATABASE=biztory). Only the LOCAL .env is
# rewritten — the committed .env.example stays untouched.
# One-time app bootstrap (run after setup.ps1): deps, .env (sqlite), migrate, assets
bootstrap: _require-php _require-composer _require-node
    if (-not (Test-Path .env)) { Copy-Item .env.example .env; (Get-Content .env) -replace '^DB_CONNECTION=mysql', 'DB_CONNECTION=sqlite' -replace '^DB_(HOST|PORT|DATABASE|USERNAME|PASSWORD)=', '# DB_$1=' | Set-Content .env; Write-Host "[INFO] Created .env from .env.example (DB switched to sqlite)" -ForegroundColor Yellow }
    if (-not (Test-Path database\database.sqlite)) { New-Item -Path database\database.sqlite -ItemType File -Force | Out-Null; Write-Host "[INFO] Created empty database\database.sqlite" -ForegroundColor Yellow }
    & '{{composer}}' install
    npm install
    npm run build
    & '{{php}}' artisan key:generate --force
    & '{{php}}' artisan migrate --force
    Write-Host "`nBootstrap complete. Run: just start" -ForegroundColor Green

# Runs `stop` first so a previous run's serve process doesn't linger and double up.
# artisan is launched by ABSOLUTE path so the process's command line carries this repo's
# path — that's how `stop` scopes to THIS project (see its comment).
# Start the dev server on http://127.0.0.1:{{port}} (background window).
start: _require-php stop
    Start-Process powershell -ArgumentList "-NoProfile", "-Command", "& '{{php}}' '{{justfile_directory()}}\artisan' serve --host=127.0.0.1 --port={{port}}"
    Start-Sleep -Seconds 2
    Write-Host "Started: http://127.0.0.1:{{port}}  (stop with: just stop)"

# Serve in the FOREGROUND (Ctrl+C to stop) — handy for watching request logs.
serve: _require-php
    & '{{php}}' '{{justfile_directory()}}\artisan' serve --host=127.0.0.1 --port={{port}}

# Matches php whose command line contains this repo's path (trailing '\' so a sibling
# folder with a shared prefix can't false-match) — another project on the SAME
# php-8.3 binary is left untouched.
# Stop only THIS project's php.exe (serve), not every php on the box.
stop:
    $procs = @(Get-CimInstance Win32_Process -Filter "Name = 'php.exe'" | Where-Object { $_.CommandLine -like '*{{justfile_directory()}}\*' }); $procs | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }; Write-Host "Stopped $($procs.Count) project php.exe process(es)"

# ─── Database ────────────────────────────────────────────

# Run pending migrations.
migrate: _require-php
    & '{{php}}' artisan migrate --force

# Drop everything and re-migrate (+ seed if a seeder exists). IRREVERSIBLE locally.
fresh: _require-php
    & '{{php}}' artisan migrate:fresh --force --seed

# ─── Quality ─────────────────────────────────────────────

# phpunit.xml declares a Feature suite but tests/Feature doesn't exist in git (empty dirs
# aren't tracked), so a bare run aborts with "Test directory not found" — pass
# --testsuite=Unit to run the tests that exist.
# Run the test suite (use: just test --testsuite=Unit). Extra args pass through.
test *flags: _require-php
    & '{{php}}' artisan test {{flags}}

# Check code style with Laravel Pint (read-only; fix with: just lint-fix)
lint: _require-php
    & '{{php}}' vendor\bin\pint --test

# Auto-fix code style with Laravel Pint
lint-fix: _require-php
    & '{{php}}' vendor\bin\pint

# ─── Tools ───────────────────────────────────────────────

# Launch Claude Code with all permissions — Sonnet (latest)
claudex:
    claude --dangerously-skip-permissions --model sonnet

# Launch Claude Code with all permissions — Opus (latest)
claudeo:
    claude --dangerously-skip-permissions --model opus

# Launch Claude Code with all permissions — Haiku (latest)
claudeh:
    claude --dangerously-skip-permissions --model haiku
