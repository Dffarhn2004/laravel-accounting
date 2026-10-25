# Proposed Acceptance & Regression Test Matrix

These are not executed. Each case becomes Pest/PHPUnit tests and UI checks once implementation exists.

| ID     | Steps                                                 | Expected                                                   |
| ------ | ----------------------------------------------------- | ---------------------------------------------------------- |
| ACC-01 | Post journal with equal debit and credit              | Journal posted, totals balanced                            |
| ACC-02 | Post debit 100000 / credit 99000                      | Rejected atomically; no partial rows                       |
| ACC-03 | Retry same invoice/payment idempotency key            | Exactly one source posting                                 |
| ACC-04 | Invoice 1m unpaid, receive 400k                       | AR outstanding 600k, ledger reconciles                     |
| ACC-05 | Expense 100k on credit, pay vendor 60k                | AP remaining 40k                                           |
| ACC-06 | Buy 10 units at 10k and 10 at 20k, issue 5            | Weighted average 15k; issue cost 75k, remaining 15 units   |
| ACC-07 | Issue inventory above stock                           | Reject and leave stock/GL unchanged                        |
| ACC-08 | Buy 2.4m asset with 4-year life                       | Straight-line depreciation 50k/month from following month  |
| ACC-09 | Transfer 1m between bank accounts                     | Total cash unchanged; both account balances updated        |
| ACC-10 | User of company B requests company A invoice/export   | Forbidden, no data leakage                                 |
| ACC-11 | Import file with duplicate marketplace order numbers  | Deduplicated, ledger totals unchanged by duplicates        |
| ACC-12 | Reverse imported batch with no downstream allocations | Traceable reversing entries and consistent balances        |
| ACC-13 | Close year with AR/AP, inventory and assets           | Balanced carry-forward; closed year rejects ordinary posts |
| ACC-14 | Generate indirect cash flow                           | Ending cash equals balance-sheet cash                      |
| ACC-15 | Upload executable disguised as receipt                | Rejected and never executed                                |
| ACC-16 | Compare branch A/B and consolidation                  | Appropriate aggregations and clearing policy               |
| ACC-17 | Submit to closed fiscal period                        | Clear validation error, no rows created                    |
| ACC-18 | Budget revenue 100m, actual 80m                       | Achievement 80%, correct classification                    |
| ACC-19 | 30%/70% shareholders, 50% profit payout               | Sum ownership 100%; proportional distribution              |
| ACC-20 | Ratio calculation with zero denominators              | N/A, never NaN/Infinity                                    |
| ACC-21 | Print multi-item invoice                              | Accurate total, readable printable A4                      |
| ACC-22 | Changing source document after posting                | Controlled reversal/repost, not silent journal editing     |

## UI regression

- Filter branch/period updates every metric, chart and report consistently.
- CRUD form errors and confirmation flows, pagination, search and CSV formula escaping.
- Evidence upload/view/download guarded by permissions.
- Desktop/tablet/mobile layouts, horizontal report scrolling, accessible focus states.
- Empty/loading/error states do not fake financially healthy values.

Definition of done: implementation + automated coverage + populated fixture demo + integration sign-off; not screenshots alone.
