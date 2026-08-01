# Sales Management System — documentation

Developer documentation for the Sales Management System (Laravel 10 REST + GraphQL API
over the Biztory invoicing dataset, local-only on port 8111).

> **New here? Start with [`tldr.md`](tldr.md)** — every document below summarised in 30
> seconds each. Then follow the reading order for your role.

## Who is this for?

| Reader | Start here |
| --- | --- |
| Brand-new developer setting up a machine | [`02-setup/getting-started.md`](02-setup/getting-started.md) |
| Developer about to change code | [`03-development/workflow.md`](03-development/workflow.md) + [`01-overview/architecture.md`](01-overview/architecture.md) |
| Someone who just wants to know what this is | [`01-overview/project-overview.md`](01-overview/project-overview.md) |
| Someone whose command just failed | [`06-troubleshooting/common-issues.md`](06-troubleshooting/common-issues.md) |
| Anyone with a quick question | [`07-faq/faq.md`](07-faq/faq.md) |

## Recommended reading order

1. [`tldr.md`](tldr.md) — the whole set at a glance
2. [`01-overview/project-overview.md`](01-overview/project-overview.md) — what and why
3. [`02-setup/getting-started.md`](02-setup/getting-started.md) — get it running
4. [`01-overview/architecture.md`](01-overview/architecture.md) — how it works
5. [`03-development/workflow.md`](03-development/workflow.md) — how to work on it
6. [`05-reference/commands.md`](05-reference/commands.md) — keep open while working
7. Everything else on demand.

## 01-overview

| Document | What it covers |
| --- | --- |
| [`project-overview.md`](01-overview/project-overview.md) | What the app is, the four assignment deliverables, the endpoints, the biztory.sql dataset, intent and status |
| [`architecture.md`](01-overview/architecture.md) | Request flows (store-sale event chain, REST/GraphQL daily-total twins), the GraphQL layer, persistence, testing |

## 02-setup

| Document | What it covers |
| --- | --- |
| [`getting-started.md`](02-setup/getting-started.md) | Fresh-PC setup: `setup.ps1` → `just bootstrap` → `just start` → verify; why PHP 8.3; what works on sqlite and what can't |

## 03-development

| Document | What it covers |
| --- | --- |
| [`workflow.md`](03-development/workflow.md) | The day-2 loop, house patterns table, the REST/GraphQL twin rule, sharp edges, Claude Code usage |

## 04-deployment

| Document | What it covers |
| --- | --- |
| [`deployment.md`](04-deployment/deployment.md) | Honest state: no CI/CD, runs locally — plus a checklist for a hypothetical future deploy (MySQL, auth, queue worker, broadcasting) |

## 05-reference

| Document | What it covers |
| --- | --- |
| [`commands.md`](05-reference/commands.md) | Every `just` recipe + occasional raw artisan commands |
| [`project-layout.md`](05-reference/project-layout.md) | Annotated tree + a "where to make which change" table |

## 06-troubleshooting

| Document | What it covers |
| --- | --- |
| [`common-issues.md`](06-troubleshooting/common-issues.md) | Real symptom → cause → fix entries (PHP 8.4 platform check, the sales-table gap, the Feature-suite abort, mysql driver, Pint debt, GraphQL date format, Vite manifest, port/stop) |

## 07-faq

| Document | What it covers |
| --- | --- |
| [`faq.md`](07-faq/faq.md) | Quick Q&A: port, database, the dump, events/broadcast reality, the twins, test suite, generated files, deployment |
