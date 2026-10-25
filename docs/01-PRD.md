# Product Requirements Document — Laravel Accounting
Version: 0.1 (draft) | Baseline: 25 October 2026 | Status: documentation only

## Product objective
Build an independent financial accounting app for SMEs with functional parity to the publicly accessible Prof.Preneur CatatRapi demo. Aim for one transaction entry to flow consistently to journal, general ledger, financial statements and dashboard. Recreate functional workflows, not the source code, artwork, brand or visual assets of the reference.

## Users
- Owner: view consolidated performance, cash, liabilities, profitability, budgeting and dividends.
- Accounting/finance: record daily transactions, validate debit/credit and reconcile books.
- Branch operator: enter business transactions only for authorized branches.
- Company administrator: maintain accounts, branches, fiscal periods, settings and user access.
Roles and multiuser functionality are proposed additions, NOT confirmed as original features.

## Functional requirements
### Configuration
Company identity, bank and cash accounts, customizable Chart of Accounts, branches/brands, products and prices, inventory items and minimum stock, depreciation rules, tax defaults, journal templates, backup/restore and fiscal year close.
### Transactions
Opening balances or initial capital at cut-off; invoice with product lines, discounts, taxes, ancillary income and partial payment; POS/marketplace Excel/CSV import with preview and dedupe; expenses; inventory purchase/consumption; AR settlements; assets; cash and bank movements, borrowings, debt repayments, taxes, owner draws, capital changes; balanced manual journals. Keep supporting documents (source reference: up to five files per transaction).
### Data management
Searchable/filterable transaction registers for sales, expenses, stock, receivables, assets, cash/bank, capital and manual journals. CSV export, evidence viewing, and controlled correction history.
### Reports
General journal; account ledger; monthly and branch-level P&L; balance sheet; indirect cash flow; inventory and stock card; AR/AP aging; tax liability/settlement; fixed assets/depreciation; 19 financial ratios and financial health score; dividend forecast.
### Budgeting
Monthly budgets by branch/account/activity, recap and budget-versus-actual achievement.
### Dashboard
Six core KPIs (revenue, net income, cash, total assets, AR, AP); trends; integrity checks; budget; cash flow and asset/expense composition; top AR/AP; per-branch performance and dividends.

## Product acceptance principles
1. Post every financial event as a balanced double-entry journal, atomically and exactly once.
2. General ledger is authoritative for statement totals; subsidiary ledgers must reconcile.
3. A user cannot view, mutate or export records outside their permitted company and branch.
4. Period filters and branch filters have predictable, reproducible semantics.
5. Tax rules and accounting framework must be professionally reviewed before production.
6. Never claim feature parity until workflows with populated data are tested.

## Non-functional
- Exact money calculations (decimal/numeric), atomic PostgreSQL transactions, idempotency.
- Immutable posted ledger with reversal-and-repost, audit trail and protected fiscal close.
- Responsive, accessible, keyboard-operable UI, desktop-first financial reports and print views.
- Authorized attachments, content-type verification, secure storage, antivirus strategy if exposed publicly.
- Performance targets and backup retention to be determined from intended deployment size.
- Automated unit, integration, reconciliation and scenario acceptance tests.

## Explicit open decisions
SaaS/multi-user vs offline/local-first; tenant membership and permissions; licensing/paid activation; inventory cost adjustments on backdated changes; how interbranch eliminations work; compliant Indonesian tax calculation and report standards. See 08-DECISIONS.md.

## Source
Reference screens: https://app.profpreneurs.com/ ; reference user guide: https://app.profpreneurs.com/#/panduan . Descriptions reflect public demo and guide on 25 Oct 2026, not live transaction verification.