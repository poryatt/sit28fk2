# Database — install, migrate, tools

## First install
1. Import the frozen baseline: `database/schema.sql` in phpMyAdmin (or `mysql -u root business_panel < database/schema.sql`).
2. Run every migration in order: `php database/migrate.php`.
3. Create/check the owner account: `php database/tools/reset_owner_password.php <username>` (password on STDIN; added in P2).

> `schema.sql` is **BASELINE — frozen; every later change is a migration**. Never edit it except the header comment.

## How to add a migration
- Create `database/migrations/NNNN_name.php` returning `['description' => string, 'up' => function (PDO $pdo, Migrator $m): void]`.
- Every step must be idempotent: guard with `Migrator` (`tableExists`, `columnExists`, `indexExists`, `fkExists`, `checkExists`, `columnType`, `isNullable`, `collation`, `ensureIndex`, `replaceForeignKey`).
- A migration that throws is **not** recorded in `schema_migrations` — fix the cause and re-run the same command.
- Clone-first rule: test every migration on `business_panel_migtest` before `business_panel` (see below).

## Tools (one line each)
- `php database/migrate.php [--status]` — apply pending migrations (or list applied/pending). Uses `DB_MIGRATE_USER`/`DB_MIGRATE_PASS` when set.
- `php database/tools/diagnose.php [--phase=N]` — read-only diagnostic checks D01–D26; exit code 1 on any FAIL.
- `php database/tools/env_check.php` — PHP/DB versions, sql_mode, time zone, extensions, whether CHECK is enforced.
- `php database/tools/purge_logs.php [--dry-run]` — retention: `login_attempts` 7 days, `activity_logs` 180 days (P2; schedule daily).
- `php database/tools/backfill_files.php` — one-off file registry backfill, dry-run by default (P4).
- `php database/tools/gc_files.php` — orphan-file report (`--delete` removes; P4; weekly, report only).
- `php database/tools/encrypt_existing_secrets.php` — one-off secret encryption, dry-run by default (P6).
- `php database/tools/gen_secret_key.php` — prints a fresh `APP_SECRET_KEY`; writes nothing (P6).
- `database/tools/create_db_users.sql` — run once as DB admin to create `panel_app` / `panel_migrator` (P6).

## Frontend checks
1. `php database/tools/check_inline_js.php` must print `syntax errors: 0` before every release (checks every inline `<script>` in `*.php` plus every `public/assets/js/*.js` with `node --check`; prints `SKIP` when node is missing).
2. **Manual console check** (needed when `node` is missing, and always once after any JS change): open DevTools → Console, reload each of: dashboard, pages, templates, media, licenses, settings/general, settings/profile, settings/staff, login — **no red errors** (also none about `integrity`/SRI).
3. On the staff page: click «ویرایش دسترسی» (a modal with 7 modules opens), click «تعلیق» (a confirmation dialog opens), click «افزودن همکار جدید» (a modal opens).

## Clone-first procedure (mandatory before every migration)
```
mysql -u root -e "DROP DATABASE IF EXISTS business_panel_migtest; CREATE DATABASE business_panel_migtest CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysqldump -u root --routines --triggers --single-transaction business_panel | mysql -u root business_panel_migtest
DB_NAME=business_panel_migtest php database/migrate.php
DB_NAME=business_panel_migtest php database/tools/diagnose.php --phase=<N>
```
(On Windows use the full path `C:\xampp\mysql\bin\mysql.exe` / `mysqldump.exe` when not on PATH.
On Linux/macOS prefix env vars per command: `DB_NAME=... php ...`.)

## Backups
- Database dump **plus** `public/uploads/` **plus** `storage/private/` (P4+). The `APP_SECRET_KEY` is backed up **separately** from database dumps, never in chat/e-mail/changelog.
- Key custody: losing `APP_SECRET_KEY` makes every stored secret unrecoverable (the owner re-enters the API keys; nothing else breaks). Generate with `php database/tools/gen_secret_key.php` (prints only, writes nothing).

## Web server notes
- Vhost **Document Root must be `.../panel/public`** (never `.../panel`: `config/`, `includes/`, `database/schema.sql` and every `.md` file would be downloadable).
- Apache: `public/uploads/.htaccess` blocks script execution. Nginx ignores `.htaccess`, so add:
  `location ~* ^/uploads/.*\.php$ { deny all; }`
- Scheduled: `purge_logs.php` daily, `gc_files.php` weekly (report only).

## Troubleshooting: "پردازش تصویر در سرور فعال نیست (افزونه GD)"
Image uploads need the PHP **GD** extension in the PHP that Apache/PHP-FPM actually loads (the CLI `php.ini` can differ, so `php -m` in a terminal does not prove what the web server has).
- **Laragon:** tray icon → right-click → **PHP → Extensions** → tick `gd`, `exif`, `fileinfo`, `mbstring` → Laragon restarts Apache.
- **XAMPP:** open `C:\xampp\php\php.ini`; remove the leading `;` from `extension=gd`, `extension=mbstring`, `extension=exif` (exif **after** mbstring) and `extension=fileinfo`; restart Apache from the XAMPP control panel.
- **Linux:** `sudo apt install php-gd php-mbstring` then restart Apache/php-fpm.
- **Check:** `php database/tools/env_check.php` prints `image processing: gd | none (fallback: passthrough | refuse)`. The media page also shows a warning while GD is missing and it disappears once GD is active.

**Fallback policy.** When GD is missing the panel keeps working per `IMAGE_PROCESSING_FALLBACK` (env var, also settable in `config/env.local.php`):
- `passthrough` — validated jpg/png/webp are stored **without re-encoding** (magic-byte and script-marker checks still apply). Good for local development.
- `refuse` — image upload is refused with the Persian message above. Safer for production.
- Default: `refuse` when `APP_ENV=production`, otherwise `passthrough`. GD re-encoding strips metadata and rejects polyglots, so enabling GD remains the recommended setting.

## Production switch (P6.7)
1. Run `database/tools/create_db_users.sql` as DB admin (replace both `<generate>` placeholders with generated passwords; never commit them).
2. Set real environment variables: `APP_ENV=production`, `DB_HOST`, `DB_NAME`, `DB_USER=panel_app`, `DB_PASS`, `DB_MIGRATE_USER=panel_migrator`, `DB_MIGRATE_PASS`, `APP_SECRET_KEY` (from `gen_secret_key.php`), and `TRUSTED_PROXIES` when behind a proxy/CDN.
3. Remove `config/env.local.php` from the server.
4. `php database/tools/env_check.php` must show no missing/invalid lines. Local development keeps `APP_ENV=local` and `root` — that stays allowed.
