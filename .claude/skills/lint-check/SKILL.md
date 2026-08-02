---
name: lint-check
description: Use when the developer says 'lint check', 'run lint', 'check lint', 'run the quality suite', or 'lint everything' — runs the full quality suite for this repo (Laravel Pint style check + the PHPUnit test suite) and reports pass/fail per layer, with the Pint auto-fix path.
model: sonnet
---

# lint-check — Full quality suite (Pint · PHPUnit)

Run the two quality layers this repo has and report pass/fail per layer. There is
no CI — this suite is the whole quality gate, run it before every commit/PR.

## Trigger

When the developer says any of: "lint check", "run lint", "check lint",
"run the quality suite", "lint everything".

---

## What to Do

Run each layer and record its result. Run them independently so one failure doesn't
hide the others.

### 1 — Pint (code style)

```powershell
just lint          # php vendor\bin\pint --test  (read-only)
```

Pass = exit 0, "PASS" summary, no style issues. If it lists offending files,
**auto-fix** and re-check:

```powershell
just lint-fix      # php vendor\bin\pint  (writes fixes)
just lint          # confirm green
```

### 2 — Test suite (PHPUnit via artisan)

```powershell
just test          # php artisan test
```

Pass = all tests green, exit 0. Filter a single test with
`just test --filter=SomethingTest`. The full suite (Unit + Feature) runs on sqlite
`:memory:` (`phpunit.xml`) with test-only `sales`/`users` scaffolding created per test
by `tests/TestCase.php` — green with no MySQL. Any failure is real; the old
`no such table: sales` baseline and the `tests/Feature` abort only exist on
pre-scaffolding checkouts. See `.docs/06-troubleshooting/common-issues.md`.

---

## Reporting back

Report a per-layer table, then an overall verdict:

```
LAYER   TOOL             STATUS
style   pint --test      PASS | FAIL (N files)  [auto-fixed → re-checked green]
test    php artisan test PASS | FAIL (N failures)
OVERALL: PASS | FAIL
```

- **style** is the only layer safe to auto-fix mechanically (`just lint-fix`).
  After auto-fixing, always re-run `just lint` and report the green result.
- **test** — never weaken an assertion to force green; fix the root cause in the
  source. A `no such table: sales` failure is the documented sqlite schema gap
  (see above) — report it as such; don't "fix" it by editing migrations or
  importing `biztory.sql` into sqlite.

---

## Notes

- Run from the **repo root** — `just` recipes resolve PHP by absolute path
  (`%LOCALAPPDATA%\Programs\php-8.3\php.exe`), so they work even in stale shells.
- Pint needs `vendor/` — if it's missing, run `just bootstrap` first.
- There are no JS/CSS lint layers: the only view is the stock `welcome.blade.php`
  and `resources/js` is the stock Laravel bootstrap — nothing to lint there.
- Don't add ESLint/Prettier/typecheck layers here — this is a PHP API app
  (REST + Lighthouse GraphQL).
