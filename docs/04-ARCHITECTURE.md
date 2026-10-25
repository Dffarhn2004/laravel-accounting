# Proposed Laravel Accounting Architecture

## Stack decision baseline

- Laravel **13** (`laravel/framework: ^13.0`), PHP **8.3+**, Composer lockfile.
- Official Laravel React starter kit: **React 19**, **TypeScript**, **Inertia 3**, **Tailwind CSS 4**, shadcn/ui components. Confirm scaffold package versions at installation.
- **MySQL/MariaDB as the shared cPanel deployment baseline**; PostgreSQL remains an option for VPS/managed hosting. Migrations and queries should stay compatible with both databases. Laravel migrations, Policies, Form Requests, queues/jobs, Filesystem; Redis optional.
- Modular monolith (Laravel framework conventions), not microservices.

## Domain responsibilities

- Identity/Access: authentication, company membership and branch policies.
- Organization: companies, branches, account configuration.
- Accounting: chart of accounts, periods, postings, journals, ledger, reconciliations.
- Sales & AR: customers, invoices, invoice lines, receipts/allocations, import batches.
- Expenses & AP: vendors, bills/expenses and vendor settlements.
- Treasury: cash/bank accounts, transfers, tax settlement, financing and equity.
- Inventory: items, movement log, branch stock, cost valuation.
- Fixed Assets: asset register, useful life, schedule, monthly postings.
- Budgeting: monthly targets and budget-vs-actual.
- Reporting: general ledger, P&L, BS, cash flow, 19 ratios and dashboard.
- Audit/Documents: evidence upload and protected immutable activity history.

## Logical tables (not yet migrated)

companies, branches, users, company_memberships, accounts, fiscal_periods, journal_entries, journal_lines, customers, products, invoices, invoice_lines, receipts, receipt_allocations, vendors, bills, bill_payments, cash_accounts, cash_transfers, inventory_items, inventory_movements, inventory_cost_layers, fixed_assets, depreciation_postings, budgets, budget_monthly_lines, shareholders, import_batches, attachments, audit_events.

Use company_id on every company-owned table and branch_id wherever data is branch-specific. Scoped composite unique constraints on invoice numbers, posting/idempotency keys; non-cross-tenant FKs where possible. Monetary type numeric(20,2), tax rate decimals with explicit rounding/versioning.

## Posting transaction architecture

Controller/FormRequest -> authorize (company/branch) -> use case service -> DB transaction (source record + balanced ledger + subledger + audit marker) -> afterCommit notification/job -> reporting query. Reversals follow controlled paths; no balance mutation from frontend.

## Reporting

Ledger is source of truth. Indexed SQL queries with company/branch/period filters. Materialized summaries only after demonstrated performance need. One shared calculation service for UI and CSV/PDF exports.

## SaaS vs offline fork

The reference stores all transactions locally in browser and supports manual backup; **this stack is cloud-oriented** unless an offline sync subsystem is explicitly designed. Simply enabling a PWA does NOT reproduce offline writes. If offline parity becomes required: IndexedDB outbox, sync conflict handling, durable IDs and reconciled ledger posting must be designed first.

## Engineering standards

PSR-12/Laravel Pint, static analysis, integration tests, CI; protected env secrets; validation and malware/size controls for evidence; authorization on object fetch and export; performance profiling; backups/restore drills; disaster recovery policy.

## Hosting decision (2026-10-25)

Shared **cPanel is a supported deployment target**, provided PHP >=8.3 and required extensions, Composer/vendor installation, safe `public/` document root and writable storage are available. Default Inertia SSR is disabled via `config/inertia.php` and `INERTIA_SSR_ENABLED=false`. Frontend assets are built off-server and uploaded from `public/build`; no Node process on shared hosting. Use `DB_CONNECTION=mysql`, file cache/sessions and sync queue at the beginning. See `docs/09-DEPLOY-CPANEL.md`.
