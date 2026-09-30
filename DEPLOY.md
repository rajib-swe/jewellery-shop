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
