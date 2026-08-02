# Skills Catalog — `laravel-sales-api`

Project development skills for this Laravel 10 sales-management API (REST + Lighthouse GraphQL, MySQL biztory.sql schema, sqlite local dev; GitHub, local-only). Each lives in its own directory with a `SKILL.md`. **Follow the relevant skill before writing code.** Run `/audit-skills` to verify every skill here is registered and that `CLAUDE.md` references only existing skills.

Model tiers: `sonnet` (floor) · `opus` (deep reasoning / generation).

## Git

| Skill                           | What it does                                                                                                                                                                       | Model  |
| ------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------ |
| [commit](commit/SKILL.md)       | Conventional-Commits stage + message flow (stale-lock preflight, `git add -A` fast path, scoped stage-by-name). Never auto-commits, never pipes `git commit` in an `&&` chain, no attribution footer. | sonnet |
| [create-pr](create-pr/SKILL.md) | Push the current branch and open a **GitHub** PR into `main` via `gh` / GitHub MCP, with a clean Summary/Changes/Testing body and no attribution footer.                            | opus   |

## Quality & Review

| Skill                                   | What it does                                                                                                                      | Model  |
| --------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------- | ------ |
| [pre-pr-review](pre-pr-review/SKILL.md) | Self-review the branch diff against a Laravel / Eloquent / API / GraphQL / security checklist + the quality suite; report to `workspace/reports/pr/`. | opus   |
| [lint-check](lint-check/SKILL.md)       | Run `just lint` (Pint) + `just test` (PHPUnit); report pass/fail per layer with the Pint auto-fix path.                            | sonnet |

## MCP tooling

| Skill                                 | What it does                                                                                                  | Model  |
| ------------------------------------- | ------------------------------------------------------------------------------------------------------------- | ------ |
| [setup-mcp](setup-mcp/SKILL.md)       | Registry-driven MCP setup / onboarding (reads `setup-mcp/registry.json`; wires stub + secret + enable tiers). | opus   |
| [test-all-mcp](test-all-mcp/SKILL.md) | Live per-server smoke-test sweep → PASS/FAIL/SKIP table (prompts in `test-all-mcp/checks/`).                  | sonnet |

## Maintenance

| Skill                                 | What it does                                                                                                     | Model  |
| ------------------------------------- | ----------------------------------------------------------------------------------------------------------------- | ------ |
| [audit-skills](audit-skills/SKILL.md) | Verify every skill has a valid, registered `SKILL.md` (no BOM, valid model, no hardcoded secret) via `audit.py`. | sonnet |

## Planning & handoff

| Skill                                       | What it does                                                                                                        | Model  |
| ------------------------------------------- | ------------------------------------------------------------------------------------------------------------------- | ------ |
| [define-goal](define-goal/SKILL.md)         | Interrogate until a goal is unambiguous, then write a stop-proof `{topic}-goal.md` for the built-in `/goal` runner. | opus   |
| [claude-transfer](claude-transfer/SKILL.md) | Pointer-based session-handoff brief to `workspace/reports/transfers/claude/`.                                       | sonnet |
| [llm-transfer](llm-transfer/SKILL.md)       | Self-contained master prompt for an external LLM → `workspace/reports/transfers/{tool}/`.                           | sonnet |

## Not included (and why)

Stack add-on skills whose prerequisite this repo doesn't have:

| Skill | Skipped because |
| --- | --- |
| `fix-typecheck` | No typecheck script or `tsconfig.json` — the JS layer is the stock Laravel Vite bootstrap. |
| `fix-phpstan` | `phpstan` is not in `composer.json` (Pint is the only committed quality tool → `lint-check`). |
| `generate-playwright-tests` | No Playwright dependency or config in the repo. |
| `monitor-ci` | No CI workflow files — the project runs locally only. |
