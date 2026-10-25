# Accounting Engine — Invariants & Posting Policies
Draft rules: must be validated by an accounting professional before a production release.

## Authoritative model
- One financial business operation -> source document -> one or more journal entries and lines.
- Each entry has company, branch, fiscal date/period, source type/id, immutable posting reference, idempotency key, created/posting actor, reversal reference and status.
- Each line has account, debit, credit. For each line, debit >= 0, credit >= 0 and exactly one is positive. Every posted entry has at least two lines.
- For each posted entry (and accounting scope), SUM(debit) = SUM(credit). Reject unbalanced entries; rollback source and journals together.
- Use PostgreSQL NUMERIC(20,2) for IDR amounts or a rigorously tested value object; do not use binary floating point for financial values.
- Transactional posting via DB::transaction; use unique keys and locks where needed for concurrent payments/imports.
- No physical deletion of posted accounting history. Corrections via reversing entry + replacement with explicit source and actor audit.

## Illustrative postings (before tax/discount variants)
| Event | Debit | Credit |
|---|---|---|
| Cash invoice Rp1m | Cash/Bank 1m | Revenue 1m |
| Credit invoice Rp1m | AR 1m | Revenue 1m |
| Partial customer receipt Rp400k | Cash/Bank 400k | AR 400k |
| Cash expense Rp100k | Expense 100k | Cash/Bank 100k |
| AP expense Rp100k | Expense 100k | AP 100k |
| AP payment Rp60k | AP 60k | Cash/Bank 60k |
| Inventory purchase Rp500k | Inventory 500k | Cash/Bank or AP 500k |
| Inventory issue at Rp180k cost | COGS 180k | Inventory 180k |
| Asset acquisition Rp2m | Fixed Asset 2m | Cash/Bank or AP 2m |
| Depreciation Rp50k | Depreciation Expense 50k | Accumulated Depreciation 50k |
| Bank A to Bank B Rp1m | Bank B 1m | Bank A 1m |

## Business policies
### Inventory
- Track by branch x item. Stock issue cost uses moving weighted average; no negative quantity by default.
- Stock quantity/cost changes must be coordinated with journal posting; backdated edits require cost recalculation strategy.
### Receivables/payables
- Keep open documents and applied settlements separately, support partial payments, aging by as-of date.
- The reference allocates payables FIFO per account; decide whether explicit bill allocation is preferable.
### Fixed assets
- Straight line over assigned useful life; start depreciating in the month following acquisition. Zero life denotes a non-depreciable asset where appropriate.
- Asset/month depreciation posting must be idempotent and match the fixed asset register.
### Taxes
- Rates, tax base, rounding, PPN and withholding posting policy MUST be independently checked against current Indonesian law. UI example rates are not binding legal guidance.
### Branches
- Each entry has valid branch allocation. Interbranch transfers need clearing accounts and an explicit consolidation elimination policy.
### Period close
- Prevent postings to closed periods without controlled adjustments. Retain historical journals and carry forward balance accounts, open AR/AP, stock and fixed assets; do not destroy prior-year audit trails for an online deployment.

## Required reconciliations
1. Per-entry debit = credit, and whole-journal debit = credit.
2. Total assets = total liabilities + equity (including current earnings).
3. Indirect cash-flow ending cash = cash/equivalents on balance sheet.
4. AR/AP, inventory and fixed-asset subledgers reconcile to control accounts.
5. Branch sums and company consolidation reconcile after any required eliminations.

## Testing
Property and unit tests for balance/precision, rollback, duplicate submission, reversals, stock cost, AR/AP payments, depreciation, branch isolation and fiscal close. Detailed fixtures in 07-ACCEPTANCE-TESTS.md.