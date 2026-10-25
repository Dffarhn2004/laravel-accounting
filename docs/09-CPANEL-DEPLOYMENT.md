# cPanel deployment requirements

Status: planned, not yet deployed. Applies to Laravel 13 + official React/Inertia starter.

## Hosting preflight
- PHP >=8.3 and required Laravel extensions, Composer-compatible dependencies, sufficient memory, mod_rewrite/LiteSpeed equivalent.
- MySQL/MariaDB is preferred for broad shared cPanel compatibility; PostgreSQL only when provider supports it. Database decision pending provider confirmation.
- Ability to set document root to Laravel `public/`, or arrange a secure supported layout. Never expose `.env`, `vendor/`, `storage/`, or project root as web document root.
- SSH/Terminal is recommended. Confirm scheduled cron jobs, writable `storage/` and `bootstrap/cache/`, HTTPS, file limits and backups.

## Frontend
- Build Vite React/TypeScript/Tailwind assets in CI/local (`npm ci && npm run build`); deploy compiled `public/build` and `manifest.json` with PHP app. No persistent Node.js runtime is needed for standard client-side Inertia pages. Do not require Inertia SSR on shared hosting.

## Runtime constraints
- Use standard HTTP request/response; avoid requiring Octane, RoadRunner, Horizon, WebSocket daemons, Redis, Docker or root access.
- Sessions/cache/queue: database or file drivers compatible with provider. Background queue processing requires a working cron-triggered `queue:work --stop-when-empty` or hosting-supported supervisor; do not claim a cron fallback is reliable until tested. Scheduler via cron `php artisan schedule:run` once/minute when supported.
- Exports and heavy imports should be batched or scheduled with memory/time limits; cap upload size and validate files.

## Deploy checklist
1. Confirm PHP/extension/DB support and available terminal; create database and credentials in cPanel.
2. Build assets in CI/local; install production PHP dependencies with `composer install --no-dev --optimize-autoloader` in suitable environment.
3. Configure production `.env` privately (`APP_ENV=production`, `APP_DEBUG=false`, APP_KEY, APP_URL, DB, SESSION, CACHE, QUEUE).
4. Ensure document root is `public/`; HTTPS enabled; verify web server rewrites.
5. Run migrations with reviewed backup and `php artisan migrate --force`; run `php artisan optimize`, `storage:link` only when supported/needed.
6. Configure scheduler and queue worker mechanism if features need them; verify email, exports, attachment storage, permission, backup/restore.
7. Test login, routes, Vite assets, file access, accounting journal transaction and rollback, finance report, backup and error logs.

## Explicit non-goals for shared hosting MVP
Long-running realtime server, Inertia SSR process, Redis requirement, mandatory Docker, persistent queue daemon without host support.

## Hosting acceptance
An actual deployment must pass on the selected cPanel account. Compatibility is an architectural target, not a guarantee for every shared-hosting plan.
