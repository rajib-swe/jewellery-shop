# Deploying

The shop runs as a Laravel API plus a Vue single page app, both served by the
same origin. This file covers what has to be true in production that is not
true in a local checkout.

## 1. Environment

Copy `.env.example` to `.env` and set at minimum:

| Key | Value | Why |
| --- | --- | --- |
| `APP_ENV` | `production` | Enables the cached config and the `local` seeders stay off. |
| `APP_DEBUG` | `false` | A stack trace on a sale screen exposes the schema and the file layout. |
| `APP_URL` | `https://shop.example.com` | Used for the PWA service worker scope and absolute links. |
| `APP_KEY` | `php artisan key:generate` | Without it every session cookie and encrypted column is unreadable. |
| `ADMIN_PASSWORD` | 12+ characters | `DatabaseSeeder` refuses to run outside `local` with a shorter or default value. |
| `SESSION_SECURE_COOKIE` | `true` | Keeps the session cookie off plain HTTP. |
| `SESSION_HTTP_ONLY` | `true` | Keeps the cookie away from JavaScript. |
| `SESSION_SAME_SITE` | `lax` | Default; lower it only if a payment gateway redirect needs it. |
| `BACKUP_KEEP_DAYS` | `14` | How long a nightly dump is kept. |

`SESSION_DRIVER`, `QUEUE_CONNECTION` and `CACHE_STORE` all default to
`database`, so no extra services are needed. If the host has Redis, switching
those three to it is the single biggest speed win.

## 2. Deploy

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize
```

`npm run build` is what emits the service worker to the web root. Without it the
app works but installs as a plain web page rather than a PWA.

## 3. Scheduler and queue

Two things have to run on a timer. Both are defined in `routes/console.php` and
need one cron entry per minute:

```cron
* * * * * cd /path/to/jewellery-shop && php artisan schedule:run >> /dev/null 2>&1
```

The schedule runs, each night:

| Time | Command | What it does |
| --- | --- | --- |
| 02:00 | `backup:database` | Writes a gzipped dump of every table, then deletes copies older than `BACKUP_KEEP_DAYS`. |
| 06:00 | `pawns:mark-overdue` | Flags active pawns past their due date. |
| 06:15 | `pawns:send-reminders` | Queues an SMS for pawns due within 7 days and for overdue ones. |

`pawns:send-reminders` only queues jobs, so a queue worker is also needed or the
messages will sit in the `jobs` table:

```bash
php artisan queue:work --stop-when-empty   # for cron-based hosts
php artisan queue:work --sleep=3           # for a long-running supervisor
```

Check both with `php artisan schedule:list`.

## 4. Backups

`php artisan backup:database` writes
`storage/app/private/backups/backup-<database>_<timestamp>.sql.gz` and prunes
anything past the retention window. The directory is outside the public web
root and gitignored, so a dump is only reachable by an authenticated user with
`manage backups`.

The dump is written in PHP, not by `mysqldump`, so it runs on a host with no
MySQL client. It contains `DROP TABLE IF EXISTS` and wraps itself in
`SET FOREIGN_KEY_CHECKS=0`, so restoring is:

```bash
gunzip -c backup.sql.gz | mysql -u USER -p DATABASE
```

**A backup on the same disk is not a backup.** Copy the directory off the host
nightly, and confirm the restore works — an untested dump is the failure mode
that costs a shop its ledger.

To restore the uploaded customer photos and logo as well, take
`storage/app/public` at the same time.

## 5. SMS

`SMS_DRIVER` defaults to `log`, which writes each message to the application log
and sends nothing. That is deliberate: a fresh install or a test run cannot post
to a paid gateway by accident. To deliver messages, set `SMS_DRIVER=http` and
fill in `SMS_ENDPOINT`, `SMS_API_KEY`, `SMS_API_SECRET` and `SMS_SENDER_ID`.
See `config/sms.php` for the shape the gateway is called with.

## 6. Hardening already in place

- `POST /api/v1/login` is limited to 5 attempts per minute per IP and email, and
  a successful sign-in clears the allowance.
- Every authenticated API route is limited to 180 requests a minute per user.
- Taking a backup is limited to 6 a minute, because it dumps the whole database.
- Every route behind the login carries a `spatie/laravel-permission` guard, and
  the permission matrix is editable from the Roles screen.
- Money, stock and settings changes are written to `activity_log` by
  `spatie/laravel-activitylog`; the Activity Log screen reads it back.

Terminate TLS at the web server and redirect HTTP to HTTPS. The app does not
force it itself, because a forced redirect behind some reverse proxies turns
into a redirect loop.

## 7. cPanel / shared hosting

Deploy with the application kept outside the web root and the domain pointed
straight at `public/`:

```text
/home/USER/goldapp/          <- application, not reachable over HTTP
/home/USER/goldapp/public/   <- document root for the domain
```

This layout keeps `.env`, `vendor/`, `storage/` and the database dumps off the
web. Do not upload the application into `public_html`, and do not use a root
rewrite as a substitute for a correct document root.

### 7.1 Domain and document root

cPanel -> **Domains** (or **Subdomains**) -> select the domain -> set
**Document Root** to `/home/USER/goldapp/public`. Replace `USER` with the
cPanel account name.

### 7.2 PHP version and extensions

cPanel -> **MultiPHP Manager** -> set the domain to PHP 8.3. Then open
**Select PHP Version -> Extensions** and confirm these are enabled (most are on
by default):

`fileinfo`, `gd`, `mbstring`, `openssl`, `pdo_mysql`, `zip`, `dom`, `xml`,
`xmlwriter`, `xmlreader`, `simplexml`, `curl`, `tokenizer`, `ctype`, `json`,
`zlib`.

`gd` is needed by the PDF packages, `zip` by the Excel export, and `dom`/`xml`
by the Swagger generator. A missing extension fails when the feature is first
used, not at upload time.

If large exports, backups or photo uploads fail, raise the limits in
**MultiPHP INI Editor**: `memory_limit=256M`, `upload_max_filesize=20M`,
`post_max_size=20M` and `max_execution_time=120`.

### 7.3 Database

cPanel -> **MySQL Databases**: create a database and a user, then add the user
to the database with **All Privileges**. cPanel prefixes both names with the
account name, for example database `shop_goldapp` and user `shop_goldapp`.

### 7.4 Upload the files

Upload an archive of the project into `/home/USER/goldapp` with **File Manager**
and extract it, or `git clone` when Terminal is available.

`vendor/` is not portable and must be produced on the server: run
`composer install` (below). If the host has no Composer or Terminal, upload a
`vendor/` built locally on the same PHP 8.3 version. A stale or partial
`vendor/` makes every `artisan` command fail.

The compiled frontend is tracked in git (`public/build/`, `public/sw.js`,
`public/workbox-*.js`), so a normal `git pull` brings it. After changing
frontend code, run `npm ci && npm run build` locally and commit the rebuilt
files. Without that manifest the live site throws
`ViteManifestNotFoundException`.

Do not upload `node_modules/`, `.git/`, or a local `.env`.

### 7.5 Environment

Copy `.env.example` to `.env` with File Manager and set:

| Key | Value |
| --- | --- |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://shop.example.com` |
| `APP_KEY` | generated in 7.6 |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | from 7.3 |
| `DB_HOST` | usually `localhost` on cPanel |
| `SESSION_SECURE_COOKIE` | `true` (after HTTPS works) |
| `SESSION_HTTP_ONLY` | `true` |
| `SESSION_SAME_SITE` | `lax` |
| `SESSION_DOMAIN` | leave `null` for a single domain |
| `LOG_LEVEL` | `error` |
| `ADMIN_NAME` / `ADMIN_EMAIL` / `ADMIN_PASSWORD` | first admin; password 12+ characters |

`ADMIN_PASSWORD` must be 12 characters or more or the seeder refuses to run
outside `local`. Quote values that contain `#`, spaces or `$`.

`SESSION_DRIVER`, `CACHE_STORE` and `QUEUE_CONNECTION` stay on `database`, so no
Redis or other service is required. If the host offers Redis, moving all three
to it is the biggest speed win.

### 7.6 Install, migrate, seed

Open cPanel -> **Terminal** (or use a one-off cron job when Terminal is
unavailable) and run from `/home/USER/goldapp`:

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan optimize
```

- `key:generate` writes `APP_KEY`. Skip it when moving an existing install that
  already has a key, or existing encrypted data becomes unreadable.
- `db:seed` is safe to re-run; roles, permissions, settings and the admin user
  are created with `firstOrNew` / `findOrCreate`.
- `storage:link` creates `public/storage` -> `../storage/app/public`. If
  `symlink()` is disabled on the host, create the link in File Manager or ask
  support to enable it; customer photos and the logo are served through it.

### 7.7 Permissions

```bash
find storage bootstrap/cache -type d -exec chmod 775 {} \;
find storage bootstrap/cache -type f -exec chmod 664 {} \;
```

The files should be owned by the cPanel user. Never use `777`.

### 7.8 Cron jobs

cPanel -> **Cron Jobs** -> add both, each **once per minute**:

```cron
* * * * * /usr/local/bin/php /home/USER/goldapp/artisan schedule:run >> /dev/null 2>&1
* * * * * /usr/local/bin/php /home/USER/goldapp/artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1
```

The first runs the nightly backup and pawn jobs from `routes/console.php`; the
second drains the SMS jobs that `pawns:send-reminders` queues. `--max-time=55`
stops a run before the next minute so jobs cannot pile up.

Find the correct PHP binary with `which php` or `php -v`. On EasyApache hosts it
is often versioned, for example `/opt/cpanel/ea-php83/root/usr/bin/php`. Using
the wrong binary runs the app on a different PHP version than the website.

### 7.9 HTTPS

cPanel -> **SSL/TLS Status** -> **Run AutoSSL** for the domain. Then enable
**Force HTTPS Redirect** under Domains. Only after the certificate is active,
set `APP_URL` to `https://...` and `SESSION_SECURE_COOKIE=true`; otherwise
logins break because the browser refuses to send the secure cookie over HTTP.

Pick one canonical host (`www` or the bare domain) and redirect the other. Mixing
them makes the session cookie and Sanctum's stateful check fail.

### 7.10 Behind Cloudflare or another proxy

If traffic passes through Cloudflare, a load balancer or a reverse proxy,
Laravel may see the request as HTTP and generate `http://` links. In
`bootstrap/app.php`, inside the `withMiddleware` closure, add:

```php
$middleware->trustProxies(at: '*');
```

Only do this when every request really arrives through your proxy.

### 7.11 Verify

- `https://shop.example.com/up` returns HTTP 200 (the Laravel health endpoint).
- Log in at `/login`, upload an item photo and open it (checks `storage:link`).
- Create a sale and print/download the invoice and PDF.
- `php artisan schedule:list` shows the three scheduled commands.
- After changing `.env` on a cached install, run `php artisan optimize:clear`
  then `php artisan optimize`; cached config ignores the new values.
- Check `storage/logs/laravel.log` after the first requests. It must be
  writable. Keep `LOG_LEVEL=error` in production.

### 7.12 Updating a running install

```bash
cd /home/USER/goldapp
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
```
