# Modern Dashboard Starter & UI Specification

Audit baseline: 25 Oct 2026. Before installation, reconfirm package release notes and compatibility.

## Candidates

| Option                                   | Advantages                                                           | Caveats                                                                        | Decision                |
| ---------------------------------------- | -------------------------------------------------------------------- | ------------------------------------------------------------------------------ | ----------------------- |
| **Official Laravel React starter kit**   | Laravel 13 aligned auth, Inertia, TS, reusable layouts and shadcn/ui | Not a completed accounting design                                              | **FOUNDATION**          |
| **shadcn/ui dashboard-01**               | Modern dashboard layout, charts, sidebar and tables                  | React page/router code may assume Next.js; adapt to Inertia                    | **VISUAL BLUEPRINT**    |
| Official Laravel Livewire starter + Flux | PHP-first UI workflows, official starter                             | Different stack from React; some components may be paid                        | Alternative             |
| Filament admin panel                     | Quick internal CRUD and management                                   | Not ideal as sole highly custom finance dashboard, check version compatibility | Optional internal admin |

Sources:

- https://laravel.com/starter-kits
- https://laravel.com/framework/docs/releases
- https://ui.shadcn.com/blocks
- https://ui.shadcn.com/docs

## Target appearance

Modern B2B SaaS, clean neutral palette, non-cluttered cards, clear typography, 8px spacing, subtle borders, responsive grid, properly aligned Rupiah. A distinct identity rather than tracing Prof.Preneur's logo/color/branding.

### Sidebar

Collapsible navigation groups: Dashboard; Master/System; Input Transactions; Transaction Records; Financial Reports; Budgets; Dividends; Settings. Desktop persistent sidebar, tablet compact and mobile drawer. Header includes active company, branch and accounting period.

### Dashboard layout

1. Six KPI tiles: revenue, net income, cash, total assets, AR, AP.
2. Revenue/expenses/net-profit trend chart and financial-health score.
3. Integrity status with understandable discrepancy explanations.
4. Branch comparison and budget achievement; asset/expense/cash breakdowns.
5. Core ratios, monthly P&L and AR/AP aging top-five.

### Financial screens

- Tables: sticky header/first column, right-aligned monetary values with tabular digits, search + branch/month/status filters, page size and export.
- Forms: keyboard-friendly, inline server validation, invoice item lines, live tax/discount/receivable totals, evidence attachment and print preview.
- Errors: clear unbalanced debit/credit indicator, invalid stock/closed fiscal period warning; no misleading all-green mock data.
- Print: A4/PDF-friendly invoice, reports with proper pagination and hidden navigation.
- States: empty, loading, error, success and data-heavy.
- Mobile: sidebar drawer, card KPI stacking and horizontal financial table scroll.

## Suggested component strategy

Use official starter's layouts and auth, shadcn Card/Button/Sidebar/Dialog/Tabs, TanStack Table for large registers, Recharts (if compatible) for trends, independent Money/date formatting and advanced forms (React Hook Form + Zod optional). Keep Laravel validation authoritative.

## Acceptance UI checks

1440px desktop, 768px tablet, 390px mobile; dark/light contrast, keyboard navigation, invoice with 15 products, 20-column report scrolling, authorized export and print output.

## Implemented UI foundation (2026-10-25)

The official Laravel 13 React starter's authentication/layout components have been imported. `resources/js/pages/dashboard.tsx` presents six **explicitly empty** financial KPI cards, trend/health/integrity areas, branch/budget empty states and shortcuts. `resources/js/components/app-sidebar.tsx` routes to authenticated module placeholder pages for the six PRD categories. No financial calculator or accounting backend is implemented yet. The shell is intended for later adaptation with shadcn components and real ledger-backed data. See `docs/09-DEPLOY-CPANEL.md` for runtime target.
