# UI Sitemap & Page Definition — Laravel Accounting

**Version:** UX spec 1.0 (27 October 2026)  
**Status:** proposed information architecture, not claim of feature completeness  
**Reference:** feature matrix docs/02-FEATURE-MATRIX.md, PRD docs/01-PRD.md, CatatRapi public demo  
**Technical constraints:** Laravel 13 / React / Inertia / shadcn/ui, cPanel-compatible deployment (no SSR/daemon requirement).

## 0. Product UX principles

1. **Task-oriented navigation:** masters, business transactions, accounting, reports, budgets, dividends, settings. Avoid duplicating "Input Transaksi" and "Data Transaksi" as separate navigation sections: each transaction module owns its listing, create and detail views.
2. **One source of financial truth:** never calculate authoritative journal balances only in React. UI displays journal and report aggregates from Laravel services; frontend totals are previews only.
3. **Global context:** selected company, branch (all or one), fiscal period and as-of date. Filters must be reflected in URL queries, server-side data and exports. Restrict branch selection to authorized scope.
4. **Accounting visibility:** after posting, show journal entry reference, debit/credit status, period and clickable originating document. Posted transactions cannot be silently edited/deleted; offer "reverse and correct" with clear reason.
5. **Progressive disclosure:** primary everyday flows simple; account codes, tax policy, cost valuation and advanced options behind clear advanced sections.
6. **Empty is honest:** no invented KPI/chart figures. Distinguish missing data, 0, unavailable, calculation error and accounting imbalance.
7. **Financial table usability:** fixed headers, right-aligned tabular Rupiah, date filters, CSV export, server-side search/pagination, mobile horizontal scrolling; visible subtotals and reconciliation indicator.

## 1. Shared application shell

### Sidebar
- Dashboard
- Master Data (expand)
- Transaksi (expand)
- Akuntansi (expand)
- Laporan (expand)
- Anggaran (expand)
- Bagi Hasil & Dividen (expand)
- Pengaturan (expand)

Sidebar groups can collapse and remember selection. No menu points to the same generic placeholder when a dedicated page exists.

### Header
- Breadcrumb with current module and detail entity.
- Company switcher (only if multi-company enabled), branch selector, date/period selector.
- Global search (invoice no., customer, reference number) [phase P2].
- Quick-create dropdown: Invoice, Pengeluaran, Kas/Bank, Jurnal Manual.
- Notification/status: fiscal period open/closed, import and integrity warning.
- User menu and accessible mobile hamburger.

### Content patterns
- **Index pattern:** title+description, primary CTA, KPI strip if useful, search and chips, data table, pagination, export.
- **Create/edit pattern:** field groups and sticky totals/summary panel; validation and draft/save/post controls; on mobile move summary below form and sticky bottom actions.
- **Detail pattern:** source information, timeline/status, monetary summary, attachments, linked journal lines, reversal and print/export actions.
- **Report pattern:** title, period/branch filters, data-as-of timestamp, totals, hierarchical financial table, reconciliation warnings, export/print.
- **Settings pattern:** grouped tabs, clear save/cancel, confirmation on risky changes.

### Shared states
loading skeleton; empty with action; error+retry; forbidden; fiscal period closed; unbalanced preview; disabled actions explained; import partial errors; posting success with journal reference.

### Permission levels (design assumption, pending ADR-007)
Owner/admin full company settings; accountant posts and corrects; branch operator enters and views permitted branch; viewer/report access only. Enforce on Laravel Policies, never via hidden UI alone.

## 2. Page count & phase map

**41 main navigation-level pages:** 1 dashboard + 7 master + 9 transactions + 5 accounting + 10 reports + 3 budgets + 2 dividends + 4 settings. Detail/create/print/drawer routes are additional screens but NOT sidebar items. Auth screens inherited from Laravel starter and not counted.

P1 = accounting MVP, P2 = extended functional parity, P3 = management/operational hardening. Scope can be refined before implementation.

## 3. Dashboard (1 page)

### 01. Ringkasan Bisnis — `/dashboard` [P1 then P2]
**Purpose:** daily landing and financial situation as of selected company/branch/period.
- **Topbar:** period (month/year/custom), branch/all branches; quick create invoice and expense.
- **6 KPI:** Pendapatan; Laba/Rugi Bersih; Kas & Setara Kas; Total Aset; Piutang Outstanding; Hutang Outstanding. KPIs display currency, context date, change indicator only when historical comparison exists.
- **Main chart:** revenue vs expense vs net profit over months, with legend/tooltips.
- **Secondary:** 19-ratio health score and top six metrics, accounting integrity alerts, branch P&L table, budget achievement, cash-flow summary, asset/expense composition, top 5 overdue receivables/payables.
- **Navigation:** click KPI to source report/ledger with filters preserved. Click branch to branch report.
- **Empty:** no transactions → setup wizard CTA and neutral placeholders, not fake financial data.

## 4. Master Data (7 pages)

### 02. Chart of Accounts — `/master/accounts` [P1]
**List:** account code, name, classification (asset/liability/equity/income/expense), normal balance, posting/parent status, usage, active state.
**Tools:** searchable tree/table, type tabs, create account modal, edit descriptive fields, deactivate unused code, CSV export; no deletion of referenced account.
**Forms:** code, name, type, parent, normal balance inferred, use-for and optional flags. Validation uniqueness company-wide.
**Detail:** account history link to general ledger.

### 03. Cabang / Brand — `/master/branches` [P1]
**List:** branch code, name, city/brand, status, activation date; branch KPI optional.
**Tools:** add/edit/deactivate; set default; navigate to branch financials.
**Form:** identifier, name, location, brand, contact; cannot deactivate when policy disallows open obligations.

### 04. Pelanggan — `/master/customers` [P1]
**List:** code, name, contact, outstanding AR, last invoice, active.
**Tools:** create/edit, view invoices/aging, import customer master optional.
**Detail:** profile + invoice list + settlements; per-company scope.

### 05. Pemasok — `/master/vendors` [P2]
**List:** name/code, contact, outstanding AP, latest purchase/expense, active.
**Tools:** add/edit, open AP aging, contact.
**Detail:** open bills, history and payments.

### 06. Produk & Harga — `/master/products` [P1/P2]
**List:** SKU, product/service, category, unit, sale price, revenue mapping, active; inventory-tracked flag.
**Tools:** create/edit, duplicate/disable, branch pricing if business policy requires.
**Form:** SKU, name, unit, price, income COA, tax/default discount, stock linkage.
**Detail:** price history optional; shortcut new invoice.

### 07. Rekening Kas & Bank — `/master/accounts-cash` [P1]
**List:** payment method, bank account label, masked account number, mapped GL account, branch scope, active, current cash balance.
**Tools:** add/change metadata, deactivate if safe; link to cash bank journal and reconciliation.
**Form:** name, account type, bank, last digits, GL mapping; avoid storing unnecessary secrets.

### 08. Pengaturan Pajak & Template Akun — `/master/tax-and-templates` [P2]
**Tabs:** tax codes/rate policy; journal account mapping; transaction category templates; fiscal defaults.
**Tools:** create/activate policies; impact warnings; show effective date and tax legality review notice.
**Form:** tax name/code, rate and applicability/versioning, assigned GL accounts.
**Note:** rules require Indonesian regulatory/accounting verification, not direct copying reference rates.

## 5. Transaksi (9 pages)

All transaction pages use **list → create → detail → correction** inside their module. Data transaction routes from CatatRapi are folded into the appropriate module index, eliminating the previous duplicate "Input vs Data Transaksi" navigation.

### 09. Penjualan & Invoice — `/transactions/invoices` [P1]
**List:** number, date, branch, customer, item summary, gross/discount/tax, total, amount paid, outstanding, status (draft/unpaid/partial/paid/void), journal link.
**Filters:** date range, branch, customer, status, number, amount; export and batch actions (authorized).
**Create `/transactions/invoices/create`:** invoice metadata; dynamic product rows (reference supports 15); qty, price, line discount; up to 3 ancillary income rows from reference; PPN/PPh options; payment account; paid amount/receivable; attachment uploader (reference up to 5); sticky total and preview.
**Detail `/transactions/invoices/{id}`:** itemized document, financial/payment timeline, linked receipt/AR, journal entry, download/print, reverse/correct.

### 10. Pengeluaran & Beban — `/transactions/expenses` [P1]
**List:** reference, date, branch, expense category/COA, vendor, total, payment method/status, proof.
**Create:** category, account, date, description, amount, bank/cash or payable, vendor optional, attachment.
**Detail:** posting status, journal link, payment allocation, correction/reversal.

### 11. Persediaan Masuk/Keluar — `/transactions/inventory` [P2]
**Tabs:** Pembelian/Stock In; Pemakaian/Stock Out; Riwayat Mutasi.
**List:** date, branch, item, movement, qty, unit cost/average, total valuation, stock after, evidence.
**Create:** item, qty, date, supplier/payment mode for in; expense/COGS mapping for out; valuation preview and negative-stock guard.
**Detail:** inventory movement → stock card and journal.

### 12. Penerimaan Piutang — `/transactions/receivables` [P2]
**List:** unpaid/partial invoices, customer, due date, original/paid/outstanding, aging bucket.
**Action:** choose invoice(s), receive amount <= outstanding, date, cash/bank account, receipt evidence.
**Detail:** payment application/timeline and AR control account; no overpayment without dedicated credit policy.

### 13. Pembayaran Hutang — `/transactions/payables` [P2]
**List:** open bills and payable obligations, vendor, due date, outstanding, aging status.
**Action:** choose bill or FIFO allocation according to ADR, settlement date/amount/account and proof.
**Detail:** allocations, subledger-vs-GL reconciliation status.

### 14. Pembelian Aset Tetap — `/transactions/fixed-assets` [P2]
**List:** asset id, class, name, acquisition date, cost, useful life, accumulated depreciation, book value, branch.
**Create:** asset code/name/class, initial cost, date, residual value if policy permits, useful life, payment source cash/bank/AP, evidence.
**Detail:** asset register, schedule, posted depreciation, link report, retirement/disposal scoped to later phase.

### 15. Kas & Bank — `/transactions/cash-bank` [P1]
**Tabs:** Semua; Transfer; Penerimaan; Pengeluaran; Utang/Modal/Pajak.
**List:** date, reference, transaction type, branch, source/destination account, amount, description, ledger.
**Create with type-specific form:** internal transfer, deposit/withdrawal, borrowing, debt repayment, interest/income, owner draw, equity contribution, tax payment, advance/prepayment/refund. Balance preview, avoid ambiguous posting.
**Detail:** debit-credit visualization and bank balances before/after (when well-defined).

### 16. Impor Penjualan — `/transactions/imports` [P2]
**Wizard:** (1) download template/select marketplace channel; (2) upload CSV/XLSX; (3) map columns/branch/accounts; (4) review preview warnings, duplicates, totals; (5) post batch.
**History:** time, source/channel, rows valid/skipped/error, import status, resulting invoices, permitted undo/reversal.
**Constraints:** limit file sizes and timeouts for cPanel; idempotent import; full report of rejected rows.

### 17. Modal & Prive — `/transactions/equity` [P1]
**List:** date, owner/investor, branch, deposit/draw, asset/money amount, linked GL.
**Create:** source owner, type: additional capital or prive, cash/bank/asset mapping, date, description, evidence.
**Detail:** ledger and resulting equity account; **initial cut-off capital entry stays in Accounting → Saldo Awal** to avoid duplicate opening methods.

## 6. Akuntansi (5 pages)

### 18. Saldo Awal & Cut-off — `/accounting/opening-balances` [P1]
**Onboarding wizard:** choose fiscal start/cut-off date and branch; choose method **Saldo Awal** OR **Setoran Modal Awal** for each branch, not both.
**Form:** account-grid debit/credit columns, total difference panel always visible; imports optional.
**Review:** opening BS + receivables/payables/stock/asset opening schedules, preview posted entries; block if unbalanced.
**Lock:** clear irreversible/adjustment policy after initial posting.

### 19. Jurnal Manual — `/accounting/manual-journal` [P1]
**Form:** date/period, branch, memo, source reference, dynamic journal lines (reference up to six), accounts, debit/credit, evidence.
**Footer:** SUM Debit / SUM Credit / Selisih; Post button disabled when imbalance; postings cannot edit in place.
**History:** recent manual journals and correction/reversal actions.

### 20. Jurnal Umum — `/accounting/journals` [P1]
**List:** posting date, journal number, source type, branch, description, total debit/credit, status, actor.
**Filters:** account/source/date/branch and posting state; export.
**Detail:** expanded lines, source document, reversal chain, audit trail. This page is **read/reversal** not uncontrolled editing.

### 21. Buku Besar — `/accounting/ledger` [P1]
**Controls:** account single/all, branch, date range, opening balance toggle.
**Table:** date, journal link, description, debit, credit, running balance, subtotal.
**Actions:** export and drilldown to journal/source; control account detail.

### 22. Tutup Buku & Periode — `/accounting/closing` [P3]
**Tabs:** Period status; pre-close checklist; year-end close; history.
**Checks:** balanced ledger/BS/cash reconciliation, stock/AR/AP/asset match, missing branch and unposted draft warnings.
**Actions:** simulate, preview carry-forward, approve, lock period, archive and download evidence. Permission-gated and double-confirmed; never destructive deletion.

## 7. Laporan (10 pages)

All reports share branch/period/account filters, report generation time, source method, export CSV/print/PDF, consolidated vs branch options, and visible reconciliation warnings.

### 23. Laba Rugi — `/reports/profit-loss` [P1]
**View:** revenue → COGS → gross profit → operating expenses → EBITDA/EBIT → before-tax → net income; monthly Jan-Dec matrix and comparative period.
**Interactions:** expand COA groups, drill account to ledger, annual or month filter.
### 24. Laba Rugi per Cabang — `/reports/branch-profit-loss` [P1]
**View:** side-by-side branch columns + consolidated, row hierarchy same as P&L; totals, branch margin percentages.
**Interactions:** branch comparison, period, reconcile interbranch clearing policy.
### 25. Neraca — `/reports/balance-sheet` [P1]
**View:** Assets; Liabilities; Equity and current-year retained profit, opening and ending columns.
**Integrity:** prominent A = L + E reconciliation, mismatch diagnostic.
### 26. Arus Kas — `/reports/cash-flow` [P1]
**View:** indirect method operating/investing/financing, opening+ending cash and reconciliation to cash GL/BS.
**Interaction:** expand component detail/source journal.
### 27. Persediaan & Kartu Stok — `/reports/inventory` [P2]
**Tabs:** Stock summary, valuation, stock-card drilldown; item/branch, in/out qty, cost, book value and low-stock alerts.
### 28. Umur Piutang — `/reports/receivables-aging` [P2]
**View:** by customer and invoice, total outstanding, aging buckets (policy-configurable), overdue days, due date and collection CTA.
### 29. Umur Hutang — `/reports/payables-aging` [P2]
**View:** by vendor and bill, overdue buckets, outstanding total, GL reconciliation and settlement CTA.
### 30. Aset & Penyusutan — `/reports/fixed-assets` [P2]
**View:** register by class/branch, acquisition, useful life, monthly depreciation, accumulated, book value, schedule/simulation, GL tie-out.
### 31. Laporan Pajak — `/reports/tax` [P2]
**View:** tabs PPN, PPh 21, PPh 23, corporate tax (only applicable types); liability opening/current/settlements/outstanding, due period and postings.
**Warning:** informational ledger report, not a guarantee of statutory correctness.
### 32. Rasio & Kesehatan Keuangan — `/reports/ratios` [P2]
**View:** 19 ratios across liquidity (3), solvency (3), profitability (6), activity (5), cash-flow (2). Ratio formula/explanation, threshold, status, multi-period trend and written suggestion.
**States:** zero denominator → N/A; financial health score source and calculation shown.

## 8. Anggaran (3 pages)

### 33. Rencana Anggaran — `/budgets/plans` [P2]
**List/form:** fiscal year, branch, P&L account, activities, volume/unit/rate, 12 monthly allocations, annual total.
**Actions:** new budget, copy previous year, edit/delete draft, validate totals; import spreadsheet later.
### 34. Rekap Anggaran — `/budgets/summary` [P2]
**View:** consolidated or branch, income vs expense annual targets, month matrix, margin forecast and segment breakdown; export.
### 35. Realisasi Anggaran — `/budgets/performance` [P2]
**View:** target vs journal-backed actual, absolute difference, achievement %, status, trend; drill-down from variance to ledger and transactions.

## 9. Bagi Hasil & Dividen (2 pages)

### 36. Pemegang Saham — `/dividends/shareholders` [P2]
**List:** name, ownership %, effective date, status and entitlement basis.
**Actions:** create/edit with allocation validation SUM ownership = 100%; history and changes effective date.
### 37. Simulasi & Distribusi Dividen — `/dividends/distributions` [P2]
**Form:** select period and approved profit base, chosen payout ratio, ownership split, gross payout, retained profit.
**Actions:** calculate/preview, save proposal; formal declaration and payment only once approval/accounting policy established.
**Detail:** status proposed/approved/declared/paid; generated payable and cash payment journals when implemented.

## 10. Pengaturan (4 pages)

### 38. Profil Perusahaan — `/settings/company` [P1]
Company identity, logo (original business-owned), address/contact, currency IDR, locale, fiscal defaults, document numbering and invoice print settings.
### 39. Pengguna & Hak Akses — `/settings/team` [P2]
User list, role, branch scope, invitation status, deactivate member; audit and permission explanation; only if multi-user ADR approved.
### 40. Preferensi Aplikasi — `/settings/preferences` [P1]
Theme light/dark, date/number display, notification preferences, optional default branch/period; distinguish user-specific from company policy.
### 41. Backup, Restore & Aktivitas — `/settings/data` [P3]
Tabs: backup snapshots/download, restore dry-run/conflict warning, audit trail, data retention/exports. Protect sensitive exports and report archive.
**License activation/trial-20:** do not add permanent sidebar item until business model ADR-013 confirmed; can add `/settings/billing` later.

## 11. Screens that are NOT sidebar items

- Auth from Laravel starter: login, register (if enabled), forgot/reset password, verification, 2FA, profile/security.
- Dedicated transactional subroutes: `/transactions/invoices/create`, `/transactions/invoices/{id}`, `/transactions/expenses/create`, `/transactions/expenses/{id}`, etc.
- Print views: `/transactions/invoices/{id}/print` and `/reports/.../print` or server-generated PDF links.
- Shared quick-create modal, journal line drilldown drawer, attachment preview, confirmation for correction/closing.
- Future onboarding wizard before first real company posting.
- Not authorized and empty states are not separate sidebar items.

## 12. Core navigation and data-flow examples

**Sell on credit:** Master Customer/Product → Transaction Invoice Create → Invoice Detail (open balance and source JE) → Receive AR → Journal → GL → P&L, Balance Sheet, Cash Flow → Dashboard.

**Purchase inventory:** Master Inventory Item → Stock In → Stock Card/Valuation → Stock Out/COGS posting → Inventory Report + P&L + BS.

**Expense with payable:** Expense input → AP aging report → Payment AP → AP report cleared + cash flow; accounting GL always reconciles.

**Opening fiscal year:** company/branches/CoA → opening balance wizard → balanced journal → dashboard and reports; lock cut-off once accepted.

## 13. Existing code mapping (inspected 27 October 2026)

| Existing file/route | Proposed change |
|---|---|
| `/dashboard` | Keep; upgrade KPIs, persistent filters and drilldowns |
| `/accounting/master-data` | Split to seven master pages with redirect for backward compatibility |
| `/accounting/transactions` | Replace generic input/list mixing with per-module transaction indexes |
| `/accounting/invoices` POST | Keep controller use case, add GET list/create/detail route hierarchy |
| `/accounting/expenses` POST | Keep posting service, add index/create/detail screens |
| `/accounting/treasury` | Redirect to `/transactions/cash-bank` once new UI functional |
| `/accounting/journal` and `/accounting/journals` | Canonicalize to `/accounting/journals`; remove duplicate navigation |
| `/accounting/ledger` | Keep existing, upgrade controls and reporting UX |
| `/accounting/manual-journal` | Keep existing, enhance journal line composer |
| `/accounting/reports` | Split 10 dedicated financial reports |
| `/accounting/aging` | Split AR/AP aging report; keep redirect when safe |
| `/accounting/budgets` | Split plans, summary, performance |
| `/modules/{section}` | Gradually retire placeholders after routes exist |
| `app-sidebar.tsx` | New nested module-specific navigation; no duplicate destination links |

**Do not rename existing production routes blindly:** preserve route aliases/redirects, update controller authorization, tests and Wayfinder route generation. Existing repo contains scaffold and some financial route/controller code; per-feature completeness needs separate audit.

## 14. UI component inventory

AppSidebarNavigation; CompanySwitcher; BranchSelector; FiscalPeriodPicker; PageHeader; AccountingDataTable; DataTableFilters; Money; MoneyInput; StatusBadge; AccountingIntegrityIndicator; JournalEntryPreview; JournalLinesEditor; AttachmentUploader/Viewer; EntitySearchCombobox; SourceDocumentLink; FinanceReportTable; FinanceChart; KPIStatCard; AgingBucketTable; InvoiceLinesEditor; TransactionCorrectionDialog; FiscalPeriodBanner; ExportMenu; PrintView.

Reusable patterns should be created before duplicating several CRUD pages.

## 15. UX milestone order

- **UX-0:** app shell, navigation, global filters, typography/colors and reusable components.
- **UX-1:** seven core screens Dashboard, CoA, Branches, Invoice, Expense, Journal, Ledger (plus journal manual / opening cut-off as functional dependencies).
- **UX-2:** P&L, balance sheet, cash flow, cash/bank, master items and subledgers.
- **UX-3:** inventory/AR/AP/assets/import/budget/ratios/dividend.
- **UX-4:** setup, fiscal close, backup/restore, cPanel deployment QA.

Every major page must define: title/description, filter props, data contract, permissions, actions, empty/error/loading states, journal/GL drilldowns, acceptance tests, mobile overflow and print behavior where relevant.

## 16. Reference routes to proposed UI mapping

Old single-purpose CatatRapi menu pages map into modules:
- `#/master`, `#/template`, `#/setting` → Masters and Settings.
- `#/invoice`, `#/beban`, `#/persediaan`, `#/piutang`, `#/asset`, `#/kasbank`, `#/impor`, `#/modal` → Transactions.
- `#/saldo`, `#/jm`, `#/ju`, `#/bb`, `#/tutup` → Accounting.
- All `#/d-*` pages → relevant Transaction Index instead of duplicate side navigation.
- `#/lr`, `#/lr-cabang`, `#/neraca`, `#/cashflow`, `#/lap-*`, `#/rasio`, `#/simulasi` → Reporting.
- `#/ang-*` → Budgeting.
- `#/dividen` → Shareholders + Dividend Distribution.
- `#/aktivasi` → Later optional license/billing setup; `#/panduan` → help center/docs.

## 17. Pending design decisions before implementation
- Full offline parity vs Laravel online app (ADR-006).
- Multi-tenant/team roles (ADR-007).
- Cash vs accrual accounting policy and statutory tax rules (ADR-008).
- cPanel hosting DB provider choice; migrations should remain MySQL/PostgreSQL portable until locked.
- User-facing dashboard interaction and visual design can be adapted with shadcn; functionality/ledger source of truth cannot be compromised for appearance.

**Approved page architecture requires an explicit user decision or acceptance after review; this document is a UX proposal.**
