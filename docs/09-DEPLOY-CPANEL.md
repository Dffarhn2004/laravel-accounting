# Deploy Laravel 13 + Inertia/React to cPanel (shared hosting)
Status: **documented strategy, deployment NOT tested on target hosting**. The target stack is Laravel 13, PHP >=8.3, React 19 and Inertia 3. No long-running Node server is required when SSR is not used.

## 0. Hosting requirements to confirm
1. **PHP 8.3+** for the selected site/domain, with Ctype, cURL, DOM, Fileinfo, Filter, Hash, Mbstring, OpenSSL, PCRE, PDO, Session, Tokenizer, XML; also enable `pdo_mysql` or `pdo_pgsql` for your database. `intl`, `gd`/`imagick` and `zip` may be needed depending on app/Excel/image packages.
2. Document root must point to the Laravel **public/** directory. Prefer a dedicated subdomain and set its document root directly to `/home/<cpanel-user>/laravel-accounting/public`. **Never** expose project root, `.env`, `vendor`, `storage` or `composer.json` under public web root. If your plan cannot set the document root safely, ask the host for support or change the hosting plan.
3. CLI/SSH or Terminal and Composer access recommended. Alternatively deploy a compatible production vendor bundle built in CI. Never copy a development `.env`.
4. MySQL/MariaDB database and user (prefixes commonly applied by cPanel). PostgreSQL only if your hosting plan supports it.
5. HTTPS certificate, writable `storage/` and `bootstrap/cache/`, enough disk space and backups.
6. Cron access for scheduled tasks or periodic jobs; not needed for initial synchronous queue.

## 1. Build locally or in CI
```bash
composer install
cp .env.example .env
php artisan key:generate
# Configure DB and run local migrations
php artisan migrate
npm install
npm run build
```
The current official starter uses **VitePlus (`vp build` via npm script)**, which emits production asset files under `public/build`. **Do not** deploy `node_modules`; deploy the compiled `public/build` manifest and assets. SSR build and `php artisan inertia:start-ssr` are intentionally **not used** in shared hosting mode. On shared cPanel, React executes in the visitor's browser; PHP handles Inertia requests.

## 2. Deploy code and production dependencies
```bash
# In cPanel terminal, from project directory outside public_html:
composer install --no-dev --prefer-dist --optimize-autoloader
cp .env.cpanel.example .env
# EDIT .env with actual APP_URL, DB, APP_KEY and secure settings.
php artisan key:generate
php artisan migrate --force
php artisan optimize
```
Generate `APP_KEY` only once per installation; preserve it during upgrades. If this is a redeploy, **do not overwrite the original .env**. Run `npm run build` on your workstation/CI, not on the production cPanel host. If no Composer is available, transfer the vendor directory from a matching PHP/OS build pipeline and test carefully; avoid unsafe PHP platform overrides.

## 3. Domain and web root
Example:
```text
/home/cpuser/laravel-accounting/
  artisan
  app/
  bootstrap/
  config/
  vendor/
  storage/
  .env                  # private
  public/               # ONLY this directory is web-visible
    index.php
    .htaccess
    build/              # compiled JS/CSS
```
In cPanel Domains / Subdomains set document root to `laravel-accounting/public` when the provider exposes that option. Laravel's included `public/.htaccess` handles Apache rewrite to `public/index.php`. No rewriting Laravel's public index to point to `../` manually. Check that secrets cannot be downloaded over HTTP.

## 4. Important .env settings
Use `.env.cpanel.example` as a reference:
`APP_ENV=production`; `APP_DEBUG=false`; HTTPS `APP_URL`; `DB_CONNECTION=mysql`; `SESSION_DRIVER=file`; `CACHE_STORE=file`; `QUEUE_CONNECTION=sync`; `INERTIA_SSR_ENABLED=false`.
On a host with database queues, change to `QUEUE_CONNECTION=database` only after setting up the migrations, cron-driven workers or supervisor process. Default file sessions work without a persistent worker.

## 5. Operations
- Run `php scripts/check-cpanel.php` using the same CLI PHP version as your domain. In cPanel the correct CLI binary may be `/opt/cpanel/ea-php83/root/usr/bin/php`, not the default `php`.
- If cPanel cron is enabled, e.g. `* * * * * cd /home/cpuser/laravel-accounting && /path/to/php artisan schedule:run >/dev/null 2>&1` after scheduled tasks exist.
- For queued jobs on plans without always-on workers: if permitted, use cron + `queue:work --stop-when-empty`, with careful overlap/concurrency control and sufficient execution time. Start with sync jobs otherwise.
- If serving public files, `php artisan storage:link` may be useful; **transaction receipts and financial evidence should remain private** and be downloaded through authorized controllers.
- Test SSL, account login, asset manifest, DB writes, file storage, 404 redirects, backup/restore and production error logs.

## 6. Upgrade & recovery checklist
1. Back up DB + `.env` + private file uploads; never store backup in public root.
2. Put app into maintenance mode if migrations are breaking; deploy vendor and new `public/build` together.
3. Run `composer install --no-dev --optimize-autoloader`, `php artisan migrate --force`, `php artisan optimize:clear && php artisan optimize`.
4. Validate health routes and accounting integrity. Restore DB/data if rollback is required.
5. For large workloads, multiuser realtime sync, bank integrations, heavy queue processing or high concurrency, upgrade from shared cPanel to managed VPS/Laravel hosting.

## References
- Laravel deployment: https://laravel.com/framework/docs/deployment
- Laravel 13 starter kits: https://laravel.com/framework/docs/starter-kits
- Laravel Vite: https://laravel.com/framework/docs/13.x/vite
- cPanel install guide: https://support.cpanel.net/hc/en-us/articles/360060775934-How-to-install-Laravel-on-cPanel
- cPanel CLI PHP: https://support.cpanel.net/hc/en-us/articles/360050224413-How-to-run-PHP-commands-as-a-cPanel-user-via-Terminal
