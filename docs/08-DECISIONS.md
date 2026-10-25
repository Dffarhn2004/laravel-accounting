# Decision Log — confirmed, proposed and unresolved
Date: 25 October 2026.

| ID | Topic | Status | Position |
|---|---|---|---|
| ADR-001 | Framework | CONFIRMED | Use latest stable Laravel major (13 as of Oct 2026) |
| ADR-002 | Repo | CONFIRMED | Dffarhn2004/laravel-accounting |
| ADR-003 | Target UX/functional source | CONFIRMED | CatatRapi as feature reference, independent implementation |
| ADR-004 | Frontend | PROPOSED | Official Laravel React/Inertia/TypeScript kit plus shadcn dashboard |
| ADR-005 | Database | PROPOSED | PostgreSQL, modular monolith, company/branch scoped models |
| ADR-006 | Offline model | OPEN — CRITICAL | True offline-local parity vs cloud SaaS vs hybrid |
| ADR-007 | Authentication and roles | OPEN | Single owner or multiuser per company; memberships and branch access |
| ADR-008 | Tax and accounting compliance | OPEN | Indonesian tax rates/withholding, tax rules, accounting standard and accountant sign-off |
| ADR-009 | Master CoA | PROPOSED | Seed reference-style categories while allowing customization |
| ADR-010 | Posted transaction corrections | PROPOSED | Reversal and repost rather than destructive deletion |
| ADR-011 | AP allocation | OPEN | Reference FIFO per account vs explicit bill matching |
| ADR-012 | Interbranch transfers | OPEN | Clearing accounts and elimination rules |
| ADR-013 | License | OPEN | Reference trial 20 & activation versus subscription, or no license |
| ADR-014 | Exports | PROPOSED | CSV, XLSX and printable PDF using common financial calculations |
| ADR-015 | Visual design | PROPOSED | Modern neutral SaaS dashboard, original brand identity |
| ADR-016 | Historical records | PROPOSED | Immutable entries across fiscal rollover |

Evidence: https://app.profpreneurs.com/ and https://app.profpreneurs.com/#/panduan (read 2026-10-25).
Verification status: user-facing reference screens and guide studied; financial write flows not yet end-to-end tested. The code in the target repository is not implemented just because docs are present.
| ADR-017 | Hosting | CONFIRMED | cPanel compatibility required as an option, not the sole deployment choice; PHP 8.3+, `public/` docroot, static build, no Node SSR. |
| ADR-018 | Database for cPanel | PROPOSED | MySQL/MariaDB preferred on typical cPanel; PostgreSQL may be used where available. Database-neutral migrations and tests. |
| ADR-019 | Starter import | CONFIRMED | Official Laravel 13 React/Inertia/shadcn starter imported, dashboard and module shell placeholders; accounting rules remain unimplemented. |
