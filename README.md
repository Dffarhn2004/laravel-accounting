# Laravel Accounting

Modern Laravel 13 accounting system for UMKM and multi-branch business, functionally inspired by the public CatatRapi reference. **Independent implementation**, no proprietary code or branding copied.

## Stack

- **Laravel 13 / PHP >=8.3**, official Laravel React starter kit.
- **React 19 + Inertia 3 + TypeScript + Tailwind CSS 4 + shadcn/ui**.
- **MySQL/MariaDB cPanel-ready**; PostgreSQL supported by Laravel when available.
- **Client-side Inertia rendering** (no Node SSR service in production).
- Starter includes authentication, account settings, testing structure, reusable UI, and accounting screen placeholders.

## Start locally

1. Install compatible PHP (>=8.3), Composer, Node.js, and database driver.
2. `composer install`
3. `cp .env.example .env` and configure `DB_CONNECTION` and credentials.
4. `php artisan key:generate`
5. `php artisan migrate`
6. `npm install && npm run build`
7. `php artisan serve`

Alternatively use `composer run dev` for developer services (see starter scripts). The official starter uses VitePlus; the `npm run build` command compiles assets **before** deploying to shared hosting.

## cPanel deployment

**[Read the cPanel deployment guide](docs/09-DEPLOY-CPANEL.md)** and copy `.env.cpanel.example` to private `.env` (fill in real secrets). PHP >=8.3 plus necessary extensions, Composer/vendor dependencies, and document root pointing at `public/` are mandatory. We do not deploy a long-running Node SSR server.

## Product planning

- [PRD](docs/01-PRD.md)
- [Reference feature matrix](docs/02-FEATURE-MATRIX.md)
- [Posting rules](docs/03-ACCOUNTING-RULES.md)
- [Architecture](docs/04-ARCHITECTURE.md)
- [UI template comparison](docs/05-UI-TEMPLATE.md)
- [Roadmap](docs/06-IMPLEMENTATION-PLAN.md)
- [Acceptance scenarios](docs/07-ACCEPTANCE-TESTS.md)
- [Decisions / open questions](docs/08-DECISIONS.md)

**Implementation status:** Official starter imported and a non-functional financial UI shell added. Journal posting, report calculations, tenants, and accounting features are **not implemented yet**. Do not enter real company data at this stage.

## Source/attribution

Base starter: [Laravel React Starter Kit](https://github.com/laravel/react-starter-kit) (MIT license). See [third-party notice](THIRD_PARTY_NOTICES.md). Functional reference: https://app.profpreneurs.com/ and its public user guide.
