# Implementation Roadmap

Statuses on 2026-10-25: all NOT STARTED. Documentation first. Every phase requires feature tests and real fixture verification.

## P0 — Bootstrap & design system

- Scaffold latest Laravel 13 via official React/TypeScript/Inertia starter, confirm php/composer/node prerequisites and exact lockfile packages.
- Configure PostgreSQL and optional Docker dev environment.
- Baseline auth, company/branch/period selectors; install/adapt shadcn sidebar/KPI/table/chart components.
- Add linting, static analysis, migration tests, CI, seed fixtures, production env guidelines.
- Resolve online/offline, tenant and jurisdiction/accounting policy decisions.

## P1 — Accounting foundation

- Company and branch, chart of accounts, fiscal years/periods, opening balances or initial capital.
- Balanced journal engine with posting/reversal, idempotency and authorization.
- General journal + general ledger + account reconciliation.
- Expense, invoice, receipt, treasury/cash/bank and manual journal entries.
- P&L, balance sheet and cash flow; six-KPI dashboard with branch and period filters.
  Gate: balanced trial balance, cash reconciliation, company isolation and P1 acceptance tests pass.

## P2 — Functional parity expansion

- Inventory acquisition/issue, moving average and stock card.
- Receivable/payable aging, part settlements and reconciliations.
- Fixed assets/depreciation, tax liability reports, marketplace import and attached evidence.
- Ratio health scoring (19), monthly budgets and budget achievement.
- Shareholder and dividend estimates; CSV/report exports.
  Gate: data fixtures and subledgers match general ledger.

## P3 — Harden & deploy

- Controlled year-end close, carry-forward, backup restore, licensing if business decision approves.
- Upload security, permissions, audit trail, rate limit, performance baseline, mobile and A4 print QA.
- Database backup/restore rehearsal; accounting review and release checklist.

## Ticket contract

Each feature ticket must define source route, actual user scenario, database schema, journal rules, validation/permission, UI states, automated test, edge cases and traceability ID from 02-FEATURE-MATRIX.md.
