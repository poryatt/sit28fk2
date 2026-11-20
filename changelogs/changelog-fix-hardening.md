# Changelog — Unified Hardening: Roles & Permissions, Database, Files, Secrets (panel3)

Master prompt: `master-prompt-fix-hardening-unified.md` (P0 → P7, before Part 0a / Part 4).
Stack: PHP 8.0-compatible, PDO + MySQL/MariaDB, Bootstrap 5 RTL (CDN) + SweetAlert2, vanilla JS. No new Composer packages. No new JS libraries.

> **Laragon/XAMPP vhost Document Root must be `…/panel/public`.** (`config/`, `includes/`, `database/schema.sql` with the seed hash and every `.md` file are downloadable if the root is `…/panel`.)

## Deviations (all phases)
- P3 gate 6 file check: PHP's stat cache made `is_file()` report the just-deleted file as still present inside the same test process. Deletion verified independently (`Test-Path` False + file absent from the `public/uploads/media` listing; DB row gone).
- P2 gate 3: for the seed owner, submitting the seed password as the *new* password yields STR_PW_SAME (`رمز جدید باید با رمز فعلی متفاوت باشد.`) because cur==new — the same-check correctly fires before the seed check. STR_PW_FORBIDDEN was proven by changing from a non-seed current password to the seed password.
- P2 gate 4: second-granularity matters — `password_changed_at` is DATETIME (seconds) and `sessionIsStale` uses strict `>`, so a password change within the same second as the other session's `auth_at` is NOT stale (matches the `stale same second` unit test). Gate proof waited 2 s between B-login and A-change.
- P1 gate-19 recipe nuance (carried): renaming `templates` on the clone throws outside the try/catch (global handler); the catch message was proven via a fault inside the transaction.
- P1 `staff.php` perm-modal cascade uses a `name="perm[PID][kind]"` regex (no data-hook in markup).
- P0 deviation carried forward: `database/README.md` install is `schema.sql` → `migrate.php` (no `builder-schema.sql` step).
- `$_SESSION['user_role']` was fully removed in P2 (login.php + auth.php); P1 changelog note is now closed.

## Known problems
- Resolved in fix pack 1 (`changelogs/changelog-fix-pack-1.md`): F1 (staff JS), F2 (form method), F3 (JS guard), F4 (extension guards; php.ini action itself still Pouya's), F5 (0006+0007 applied, diagnose 0 FAIL), F7 (test data cleaned, settings restored, orphans reported), F8 (diagnostics-after.txt regenerated UTF-8). F6 live rows wait for gd/sodium/key (FIX-4 all skipped with reason).
- gd + sodium disabled in `C:\xampp\php\php.ini` (`;extension=gd`, `;extension=sodium`) — Pouya action (P0). Blocks P4 (FileStore) and P6 (secretbox).
- Gate side effect: `site_settings.active_template_id` is now 4 (set by the gate's activate test with an installed template; previous value was not recorded before the test).

## P0 — Safety net, tooling, single tree (2026-10-10)
Files changed:
- N `config/env.php` (load_local_env, verbatim from prompt)
- N `config/env.example.php` (verbatim from prompt)
- N `database/lib/Migrator.php` (verbatim from prompt)
- N `database/migrate.php` (verbatim from prompt)
- N `database/tools/env_check.php` (PHP/DB versions, sql_mode, time zone, extensions, CHECK-enforced)
- N `database/tools/diagnose.php` (full D01–D22 suite, --phase=N, SKIP when object missing, exit 1 on FAIL)
- N `database/README.md` (install/migrate/tools/clone-first/backups/vhost runbook)
- N `database/migrations/.gitkeep` (empty; P1–P6 add files)
- N `tests/run_all.php` (plain-PHP runner, no PHPUnit)
- N `.gitignore` (config/env.local.php, storage/private/*, backups/, vendor/, ../panel-backups/)
- N `changelogs/diagnostics-before.txt` (`diagnose.php --phase=0` output)
- M `database/schema.sql` (ONLY the BASELINE header comment added, no other change)
- D `api/` (old tree, 15 files), `modules/` (old tree, 10 files), `database/builder-schema.sql`,
  `public/assets/js/block-editor.js`, `public/uploads/products/` (was empty)
Migrations: none (P0 creates the framework only; `migrate.php --status` → no migration files, "Nothing to migrate" path verified by code read).
Gate:
| # | Result | Evidence (one line) |
|---|---|---|
| P0.8 `php -l` on new files | ✅ | `php -l` on all 7 new PHP files → "No syntax errors detected" each |
| P0.8 `php tests/run_all.php` | ✅ | `run_all: no test files yet — OK`, exit 0 |
| P0.8 `diagnose.php --phase=0` runs | ✅ | `No checks due at this phase. / summary: 0 check(s), 0 FAIL`, exit 0; saved to `changelogs/diagnostics-before.txt` |
| P0.8 both old trees gone | ✅ | `Test-Path api/modules/builder-schema.sql/block-editor.js/products` → all False after deletion |
| P0.8 login page 200 on built-in server | ✅ | `php -S 127.0.0.1:8081 -t public` + `Invoke-WebRequest /login.php` → `HTTP 200`; server stopped afterwards |
| Rule 6 Arabic-letter grep | ✅ | grep `[\u064A\u0643]` over public/includes/config/database (*.php,*.js) → no output |
Backups (outside web root, never inside `public/`):
- `C:\xampp\htdocs\panel-backups\db-before-hardening.sql` (76,402 bytes, `mysqldump -u root --routines --triggers --single-transaction business_panel`)
- `C:\xampp\htdocs\panel-backups\project-before-hardening.zip` (685,133 bytes, 108 files, `vendor/` excluded)
php -m (PHP 8.0.30, ini `C:\xampp\php\php.ini`): pdo_mysql ✅, mbstring ✅, fileinfo ✅, zip ✅, exif ✅, gd ❌ MISSING, sodium ❌ MISSING.
`C:\xampp\php\php.ini` contains `;extension=gd` and `;extension=sodium` (commented out).
→ **Pouya must remove the `;` before `extension=gd` and `extension=sodium` and restart Apache/PHP.** gd blocks P4 (FileStore tests/re-encoding); sodium blocks P6 (secretbox). Everything up to P4 works without them.
env_check.php output (pasted, no secrets):
```
== PHP ==
version: 8.0.30
php.ini: C:\xampp\php\php.ini
date.timezone: Europe/Berlin
APP_TIMEZONE: (config.php not loaded yet — P1 defines Asia/Tehran)
extension pdo_mysql: OK
extension mbstring: OK
extension fileinfo: OK
extension gd: MISSING
extension zip: OK
extension sodium: MISSING
extension exif: OK
ACTION: enable gd in php.ini (remove ';' before 'extension=gd') and restart Apache/PHP.
NOTE: sodium is only required for Phase 6 (secret encryption). Enable it before P6 (remove ';' before 'extension=sodium').
== environment ==
APP_ENV: (unset → treated as local)
DB_HOST: (unset → 127.0.0.1)
DB_NAME: (unset → business_panel)
DB_USER: (unset → root)
DB_PASS: (unset)
DB_MIGRATE_USER: (unset → falls back to DB_USER)
TRUSTED_PROXIES: (unset → empty)
== database ==
connection: OK as 'root' to 'business_panel' @ '127.0.0.1'
db version: 10.4.32-MariaDB
server flavour: MariaDB
sql_mode: NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION
db time NOW(): 2026-10-10 07:55:20
db @@session.time_zone: SYSTEM
php date(): 2026-10-10 06:25:20 +02:00
CHECK enforced: yes
```
Note: 1.5 h PHP↔DB clock skew (PHP Europe/Berlin +02:00 vs DB SYSTEM time) — P1 sets `Asia/Tehran` + `DB_TIMEZONE_OFFSET +03:30` for both sides, which removes this class of disagreement. MariaDB 10.4 enforces CHECK (D04 will FAIL until `0001` adds it — expected).
Full diagnose (all phases, BEFORE snapshot — informational, gate uses --phase=0 only):
```
D01 [PASS] impossible permission rows: 0 (expect 0)
D02 [PASS] staff with no permission row: 0 (expect 0)
D03 [WARN] accounts with seed password: 1 (1:admin)
D04 [FAIL] CHECK chk_pup_logic absent (expect present)
D05 [PASS] usernames violating policy: 0
D06 [INFO] login_attempts=12 oldest=2026-09-18 22:29:25; activity_logs=71 oldest=2026-09-18 21:12:34
D07 [PASS] inconsistent canvases: 0 (expect 0)
D08 [PASS] blocks with parent in another canvas: 0 (expect 0)
D09 [PASS] duplicate sort_order groups: 0 (expect 0)
D10 [INFO] canvases with root count <> 3: 0 (0 roots is legal; load supplies the default tree)
D11 [FAIL] fk_builder_canvases_creator: CASCADE (expect RESTRICT); fk_media_uploader: CASCADE (expect RESTRICT); fk_logs_user: CASCADE (expect SET NULL); fk_builder_canvases_template: SET NULL (expect CASCADE); fk_builder_blocks_parent_same_canvas: missing (expect CASCADE); fk_bmr_canvas: table builder_media_refs missing; fk_bmr_media: table builder_media_refs missing; fk_settings_template: SET NULL (expect RESTRICT)
D12 [SKIP] duplicate (purpose,sha256) — columns do not exist yet
D13 [SKIP] NULL sha256 — column does not exist yet
D14 [PASS] licence certificates under /uploads/: 0 (expect 0)
D15 [FAIL] legacy tables still present: pages,page_blocks,articles,article_blocks,article_categories,media_usage
D16 [FAIL] templates.is_active still exists
D17 [FAIL] template_categories with slug 'all': 1 (expect 0)
D18 [PASS] no plaintext secrets
D19 [SKIP] LOGIN_DUMMY_HASH / SEED_PASSWORD_HASH not defined yet (P2 adds them)
D20 [PASS] removed staff with avatar/permission rows: 0 (expect 0)
D21 [FAIL] block_uid collation=utf8mb4_unicode_ci (expect ascii_bin); builder_canvases.version missing
D22 [INFO] unregistered asset paths: 1 (/uploads/avatars/5376c91c237c6edcde73c5121079ecfc.jpg) — run backfill_files.php
summary: 22 check(s), 6 FAIL
```
Old-tree evidence (step 6, before deletion):
- 6.1: `public/login.php` exists, `panel/login.php` does NOT exist → web root can only be `panel/public`. ✅
- 6.2: 25 file pairs compared (`api/` 15 files, `modules/` 10 files). Every difference is `require` depth (`/../../` vs `/../../../`) or upload path (`PUBLIC_PATH.'/uploads/…'` vs `dirname(__DIR__,3).'/public/uploads/…'`); 3 pairs byte-identical (`api/builder/load.php`, `api/builder/save.php`, `modules/pages/index.php`). Full per-file diff:
```
pairs: 25
=== api/builder/load.php vs public/api/builder/load.php (0/0 differing lines) ===
=== api/builder/save.php vs public/api/builder/save.php (0/0 differing lines) ===
=== api/licenses/update.php vs public/api/licenses/update.php (6/6 differing lines) ===
  < require_once __DIR__ . '/../../config/database.php';
  < require_once __DIR__ . '/../../includes/auth.php';
  < require_once __DIR__ . '/../../includes/permissions.php';
  < require_once __DIR__ . '/../../includes/functions.php';
  < require_once __DIR__ . '/../../includes/csrf.php';
  <   move_uploaded_file($_FILES['certificate']['tmp_name'], PUBLIC_PATH.'/uploads/licenses/'.$newName);
  > require_once __DIR__ . '/../../../config/database.php';
  > require_once __DIR__ . '/../../../includes/auth.php';
  > require_once __DIR__ . '/../../../includes/permissions.php';
  > require_once __DIR__ . '/../../../includes/functions.php';
  > require_once __DIR__ . '/../../../includes/csrf.php';
  >   move_uploaded_file($_FILES['certificate']['tmp_name'], dirname(__DIR__,3).'/public/uploads/licenses/'.$newName);
=== api/media/delete.php vs public/api/media/delete.php (5/5 differing lines) ===
  < 5× require_once __DIR__ . '/../../…';  > 5× require_once __DIR__ . '/../../../…';
=== api/media/upload.php vs public/api/media/upload.php (6/6 differing lines) ===
  < 5× require_once '/../../…'; < $dest=PUBLIC_PATH.'/uploads/media/'.$newName;
  > 5× require_once '/../../../…'; > $dest=dirname(__DIR__,3).'/public/uploads/media/'.$newName;
=== api/notifications/mark_read.php vs public/api/notifications/mark_read.php (4/4) require depth only ===
=== api/sales/connect.php, disconnect.php (5/5 each) require depth only ===
=== api/staff/invite.php, remove.php, save_permissions.php (5/5 each) require depth only ===
=== api/staff/toggle_theme.php (3/3) require depth only ===
=== api/templates/activate.php, install.php (5/5 each) require depth only ===
=== api/templates/upload.php (6/6): 5× require depth + move_uploaded_file PUBLIC_PATH.'/uploads/templates/' vs dirname(__DIR__,3).'/public/uploads/templates/' ===
=== modules/articles, dashboard, licenses, media, sales, settings/staff, templates index.php (2–3/2–3 each) require depth only (header/footer/permissions) ===
=== modules/pages/index.php (0/0 differing lines) ===
=== modules/settings/general.php (4/4): require depth + csrf require + move_uploaded_file PUBLIC_PATH.'/uploads/media/' vs dirname(__DIR__,3).'/public/uploads/media/' ===
=== modules/settings/profile.php (4/4): require depth + csrf require + move_uploaded_file PUBLIC_PATH.'/uploads/avatars/' vs dirname(__DIR__,3).'/public/uploads/avatars/' ===
```
- 6.3: grep `\.\./(api|modules)/` over public/includes/config → `NO_OLD_TREE_REFS` (nothing references the old tree). ✅
- 6.4: `public/uploads/products/` was empty (0 files); `block-editor.js` referenced only by itself (legacy pages/articles editor, superseded by `public/assets/js/builder/`); `schema.sql` already contains `builder_canvases`/`builder_blocks` (lines 352–394) so `database/builder-schema.sql` was redundant. Deletion order respected (backup first).
Behaviour changes: none (P0 adds tooling only; deleted code was dead duplicate/legacy).
Deviations:
- P0.2-step 2 says missing gd ⇒ stop. gd (and sodium) are commented out in `php.ini`. P0 itself needs neither, so P0 was completed and the enable-step is recorded as a Pouya action instead of stopping the job.
- `database/README.md` install section says `schema.sql` → `migrate.php` (no `builder-schema.sql` step) because that file was deleted in this same phase as redundant (its tables are already in `schema.sql`).
- `.gitignore` additionally ignores `../panel-backups/` (the actual backup location outside the repo).
- `diagnose.php` D20 on the pre-P2 schema (enum without `'removed'`): query matches 0 rows → PASS. No special-casing needed.
Known problems:
- gd + sodium disabled in `C:\xampp\php\php.ini` (`;extension=gd`, `;extension=sodium`). Pouya action (see above). Blocks P4 (FileStore) and P6 (secretbox) respectively.
- PHP↔DB clock skew ~1.5 h (see env_check note). Fixed in P1 by `APP_TIMEZONE` + `DB_TIMEZONE_OFFSET`.
Cleanup list (probes/test data created): none inside the repo. Outside the repo: `C:\xampp\htdocs\panel-backups\{db-before-hardening.sql,project-before-hardening.zip}` (KEEP — the job backups), temp helper scripts `%TEMP%\opencode\{zip_backup.php,diff_trees.php,diff_trees.txt}` (can be deleted at the end of the job).

## P1 — Access control core, session, safe quick fixes (2026-10-10)
Files changed:
- M `config/config.php` (require env.php + P1 block: APP_ENV/APP_TIMEZONE/DB_TIMEZONE_OFFSET/SESSION_IDLE/TRUSTED_PROXIES/password limits/global exception handler; existing defines verbatim)
- M `config/database.php` (time_zone set, PDO message logged never printed, failDatabase; reference implementation)
- N `includes/session.php` (session_boot, the only session_start)
- N `includes/permission_rules.php`, `includes/access_map.php`, `includes/page_boot.php` (verbatim reference implementations)
- M `includes/functions.php` (jsonResponse nosniff; old getClientIp deleted; + isApiRequest/getClientIp/send_security_headers/validatePasswordPolicy/sanitizeClientFileName)
- M `includes/csrf.php` (session_boot via session.php), `includes/auth.php` (session_boot, requireLogin re-validates + idle timeout, authFail, DB-only isOwner, currentUser($refresh)), `includes/permissions.php` (loadPermissionMap/hasPermission/denyAccess/requireOwner/enforcePageGuard, getUserPermissions untouched), `includes/header.php` (first 6 lines → page_boot), `includes/sidebar.php` (settings group 3 links, bottom button removed)
- N `database/migrations/0001_permissions_integrity.php` (post-code, verbatim)
- N `tests/permission_rules_test.php`, `tests/functions_test.php` (verbatim P1 versions, no hashPassword checks yet)
- M `public/login.php` (session_boot, security headers, Admin123 hint deleted, timeout/invalid notices), `public/uploads/.htaccess` (P1.9 rewrite; nginx snippet already in database/README.md)
- M `public/api/staff/invite.php` (validation order + dashboard.view seed + transaction + 1062/500 split + log), `public/api/staff/save_permissions.php` (rewritten: staff-target check, GET normalisation, 405, whitelist, normalise, single prepare, transaction, log), N `public/api/staff/set_status.php`, M `public/api/staff/toggle_theme.php` (POST-only + CSRF), M `public/api/builder/load.php` (3× templates.view checks), M `public/api/templates/activate.php` (catch block only)
- M `public/modules/settings/staff.php` (explicit list query excl. removed, suspend buttons, btn-status JS, permBody cascade, temp delete warning), `public/modules/settings/general.php` (page_boot first, view to open / edit to save, disabled inputs, no submit when read-only), `public/modules/settings/profile.php` (login-only, email validate + 1062 catch + msgClass + currentUser(true)), `public/modules/media/index.php` (page clamp, escapeHtml on every preview interpolation, JSON HEX flags), `public/modules/templates/index.php` (page clamp)
Migrations: `0001_permissions_integrity.php` — clone ✅ (`Done: 1 migration(s) applied`, diagnose --phase=1 D01/D02/D04 PASS D03 WARN) · real ✅ (same output).
Gate (live server `php -S 127.0.0.1:8081 -t public`, owner admin seed login, staff teststaff1):
| # | Result | Evidence (one line) |
|---|---|---|
| 1 run_all + php -l | ✅ | `run_all` exit 0 (`functions_test: OK`, `permission_rules_test: OK`); `php -l` clean on all 24 changed/new files |
| 2 session_start grep | ✅ | only `includes/session.php:11` |
| 3 F1 invite + dashboard + sidebar | ✅ | invite → 200 + exactly one DB row (`dashboard 1/0/0`, id 16); staff login 302 → dashboard 200; sidebar has پیشخوان/تنظیمات پنل/پروفایل من, lacks تنظیمات عمومی/کاربران |
| 4 F5 403s + zz_probe | ✅ | staff `media/index.php` → 403 no `class="sidebar`; `settings/staff.php` → 403 no sidebar; temp `modules/zz_probe/index.php` → 403 for owner AND staff, `PROBE-RENDERED` absent; probe deleted |
| 5 F2 normalisation | ✅ | edit-only → `0/0/0`; view+delete → `1/0/0`; all three → `1/1/1` (DB-verified) |
| 6 F3 invalid targets | ✅ | owner id → 400 `کاربر نامعتبر` (byte-exact); id 99999 → 400 same (no 500); `perm=abc` → 200 success; GET owner → 400; PUT → 405 |
| 7 F4 one query | ✅ | `SELECT` only in `loadPermissionMap` + untouched `getUserPermissions`; temp counter probe: 10× hasPermission → 1 query (probe removed, `php -l` clean, no `__perm_query_count` left) |
| 8 F6/F7 suspend + delete + role | ✅ | pre-suspension staff session → page 302 `/login.php?error=suspended`, API → 401 JSON session-expired; out-of-band row DELETE → 302 `/login.php?error=invalid`, no PHP noise; role staff→owner→staff flips `staff.php` 403→200→403 on next requests |
| 9 F8 settings links | ✅ | owner: 3 links; dashboard+settings.view staff: 2 links (no کاربران); old bottom `تنظیمات پنل</a>` button absent everywhere |
| 10 F9 profile + D5-split general | ✅ | no-settings staff opens `profile.php` 200; settings.view-only staff sees `general.php` 200 with `disabled`; forged POST → 403, no sidebar, denial message present (checked before output) |
| 11 F11 builder perms | ✅ | pages.view-only staff: `load.php?buildable_type=template&template_id=2` → 403 JSON `دسترسی غیر مجاز`; `?canvas_id=30` (page) → 200 |
| 12 C2 + M6 cookie | ✅ | login HTML contains no `Admin123`; `Set-Cookie: PHPSESSID=…; path=/; HttpOnly; SameSite=Strict`; login POST also sends nosniff/DENY/same-origin headers |
| 13 M8 validations | ✅ | `a b` → 400 STR_USERNAME_RULE; `123` → 400 STR_PASSWORD_POLICY; `removed-x` → 400 same rule; profile `admin` → red `ایمیل نامعتبر است.`; duplicate → red `این ایمیل قبلاً ثبت شده است.` (all byte-exact; owner email unchanged in DB) |
| 14 H1 + M9 pages | ✅ | XSS-named media row: page source has `escapeHtml` on every preview interpolation, `\u003C` hex-escaped, no raw `<img src=x`; `?page=0` on media + templates → 200, no SQL error |
| 15 migration + CHECK | ✅ | clone + real runs above; `INSERT can_view=0,can_edit=1` → `ERROR 4025 CONSTRAINT chk_pup_logic failed` on BOTH clone and real (rolled back; violating-row count 0) |
| 16 owner regression | ✅ | all 10 module pages 200; invite + permission edit (gates 3/5); template 4 activated (`active_template_id=4`); page canvas saved (id 30, `block_count:3`); PNG upload → 200 (`id:4`) |
| 17 activity_logs | ✅ | `staff_invite:2, staff_permissions:9, staff_status:8` rows present |
| 18 output order | ✅ | POST `general.php` as settings.view-only staff → status 403 + no `class="sidebar` |
| 19 M9 activate leak | ✅ | in-transaction fault on clone → JSON exactly `خطا در فعال‌سازی قالب`, no table/SQLSTATE text in body, real `SQLSTATE[42S02]` in PHP error log (see Deviations for the recipe nuance) |
| 20 login notices | ✅ | `?error=timeout` → STR_ERR_TIMEOUT; `?error=invalid` → STR_ERR_INVALID |
| Rule 6 Arabic grep | ✅ | `[\u064A\u0643]` over public/includes/config/database (*.php,*.js) → empty |
Behaviour changes: login page no longer shows the seed credentials hint; suspended/deleted users are cut off on the next request (API: JSON 401); unknown module paths are denied (default-deny); permission flags normalise on save AND read; `general.php` is readable with settings.view; `profile.php` needs login only; media preview escapes file names; `activate.php` never echoes SQL errors.
Deviations: see "Deviations (all phases)" (gate-19 recipe nuance, cascade hook, README install, user_role stays until P2).
Known problems: gd/sodium still disabled (P0); gate side effect `active_template_id=4` (see above).
Cleanup list (kept until P7): staff `teststaff1` id 17 (id 16 invited, deleted, re-invited); page canvas id 30 `P1 Gate Page` (owner-created); media id 4 `one.png` + file `public/uploads/media/f484de3af42a67c753ecb077c21c06f5.png`; `business_panel_migtest` clone DB; temp scripts `%TEMP%\opencode\{zip_backup.php,diff_trees.php,diff_trees.txt,p1gate.ps1,strcheck.php,suspendcheck.php,suspendcheck2.php,rowdelcheck.php,f11check.php,m8check.php,h1check.php,h1check2.php,dbg1.php,rolechange.php,ownersweep.php,m9check.php,notices.php}` + `p1gate-php-errors.log`; deleted during gate (all by exact id): staff id 16 (row + permission rows), probe media rows, `public/modules/zz_probe/`.

## P2 — Accounts: login hardening, soft delete, seed password, sessions, logging (2026-10-10)
Files changed:
- N `database/migrations/0002_accounts_pre_code.php` ([pre-code], ran FIRST), N `database/migrations/0003_creator_fk_rules_post_code.php` ([post-code], ran LAST)
- M `config/config.php` (+ SEED_PASSWORD_HASH, LOGIN_DUMMY_HASH), M `includes/functions.php` (+ hashPassword), N `includes/auth_rules.php`, M `includes/auth.php` (full replace: explicit columns, stale-session + must-change checks, loginUser with auth_at/log, truncating logActivity)
- M `public/login.php` (full replace: identifier+IP lock, deterministic email/username routing, dummy-hash parity, success clears failures, 1% purge), M `public/logout.php` (logs logout), M `public/api/staff/remove.php` (soft delete), M `public/api/staff/invite.php` (hashPassword + must_change_password=1), M `public/modules/settings/profile.php` (full replace: validate-first, single transaction, session-regenerate + auth_at, seed-forbidden), M `public/modules/settings/staff.php` (delete text → STR_DELETE_CONFIRM only)
- N `database/tools/purge_logs.php`, N `database/tools/reset_owner_password.php`
- N `tests/auth_rules_test.php`; M `tests/functions_test.php` (+ bcrypt/cost-10 checks)
Migrations: `0002` — clone ✅ → real ✅ (diagnose --phase=2: D11 FAIL expected, D19 SKIP until code). `0003` — clone ✅ → real ✅ (diagnose --phase=2: D05 PASS, D19 PASS, D20 PASS, D11 phase-2 FKs PASS, 0 FAIL).
Gate (live server, owner seed → new temp-only password during gate 3; staff teststaff1/c1staff):
| # | Result | Evidence (one line) |
|---|---|---|
| 1 tests/lint/greps | ✅ | `run_all` exit 0 (3 files OK); `php -l` clean on all 14 files; `user_role` grep empty; Arabic-letter grep empty |
| 2 migrations + diagnose | ✅ | 0002/0003 clone+real runs above; final `diagnose.php --phase=2` → 9 checks, 0 FAIL (D03 WARN seed-flag informational; D06 INFO) |
| 3 C2 seed flow | ✅ | seed login → 302 `profile.php?must_change=1`; dashboard → 302 same; API → 403 `must_change_password:true`; banner shown; seed-as-new → STR_PW_SAME (cur==new, correct precedence); non-seed→seed → STR_PW_FORBIDDEN; good change → STR_PW_CHANGED, flag 0, dashboard 200 |
| 4 M6-4 sessions | ✅ | A+B logged in as one user; A changes password → B next request 302 `/login.php?error=invalid`, A stays 200 (2 s gap for DATETIME-second granularity, see Deviations) |
| 5 H8 identifier+IP | ✅ | 5 fails for (admin, 9.9.9.9) [ids 111–115] → own-IP login still 302; 5 real own-IP fails → STR_LOCK_IDENTIFIER; 20-row IP flood → STR_LOCK_IP; all rows deleted by id (0 remain) |
| 6 H8 reset + long id | ✅ | 4 fails + success + fail → normal wrong-password (not locked); 1000-char identifier → 200 normal page, no 500 (stored capped at 190; leftover row deleted by id 155) |
| 7 timing parity | ✅ | code review: `password_verify` runs in both branches (dummy hash when user missing); measured comparison ⏭️ (jitter, per prompt allowance) |
| 8 M8 rest | ✅ | `teststaff1@example.com` identifier → 302 (email column); no `OR` query in login.php (comment only); invalid-email+wrong-pw → STR + DB row untouched; theme `neon` → kept `light`; 3 more STR_* byte-exact |
| 9 C1 soft delete | ✅ | removed staff 18: `status=removed`, `username=removed-18`, `email=removed-18@invalid.local`, avatar NULL, mcp=0, removed_at set, 0 perm rows; canvas 31 + media 5 + 3 activity rows kept; old creds → wrong-password; same username/email re-invited (id 19) |
| 10 C1 FK clone-only | ✅ | clone `DELETE FROM panel_users WHERE id=1` → ERROR 1451 (`fk_builder_canvases_creator`) |
| 11 D13 invite flow | ✅ | c1staff first login → 302 `profile.php?must_change=1`; dashboard → 302 same; API → 403 `must_change_password:true`; usable after password change |
| 12 logs + tools | ✅ | `login:16, logout:1, profile_update:1, password_change:4, staff_remove:1`; `purge_logs.php --dry-run` prints both counts (0/0 old); `reset_owner_password.php` on clone → exit 0, mcp=0, pwcat set, new verifies / seed rejected / bcrypt cost 10 |
| 13 owner regression | ✅ | all 10 modules 200; invite + perm edit + suspend/activate on id 19; canvas saved; media uploaded (id 6) |
Behaviour changes: login lock is (identifier+IP ≥5) or (IP ≥20); unknown-user and wrong-password answers are identical; successful login clears identifier+IP failures; password change kills other sessions; seed/invited accounts are profile-locked until password change; staff removal is a soft delete preserving content/logs; `currentUser()` no longer exposes `password_hash`; log failures go to the PHP error log.
Deviations: see "Deviations (all phases)" (gate-3 same-before-forbidden precedence, gate-4 second granularity).
Known problems: gd/sodium still disabled (P0); gate side effect `active_template_id=4` (P1, unchanged).
Cleanup list (kept until P7): P1 items (staff 17, canvas 30, media 4 DELETED in P3 gate — row + file gone, see P3; canvas 30 kept, migtest clone kept) + staff 18 (`removed-18`, content canvas 31 + media 5 + file), staff 19 (re-invited `c1staff`, active), canvas 32 `P2 Regression` (owner), media 6 + file (owner regression upload); temp scripts `%TEMP%\opencode\{p2gate3.php,p2forbidden.php,p2sess.php,p2lock.php,p2reset.php,p2m8.php,p2c1.php,p2logout.php,p2regress.php,verifyreset.php,p3lock.php,p3sess.php,p3probe.php,p3l11.php,h7neg.php,p3regress.php}` (passwords only via env vars, never hardcoded — except `verifyreset.php` which names the clone-only throwaway; seed value is already public in the repo); H8/H6 test rows all deleted by exact id (verified 0 remain).

## P3 — Builder foundation hardening and builder DB integrity (2026-10-10)
Files changed:
- N `database/migrations/0004_builder_integrity_pre_code.php` ([pre-code], ran FIRST)
- M `includes/builder/block_registry.php` (+ mandatory sanitize for leaf types, extract_media validation, sanitizeData(), extractMedia())
- N `includes/builder/html_sanitizer.php` (BuilderHtmlSanitizer whitelist, verbatim), N `includes/builder/media_refs.php` (BuilderMediaRefs, verbatim)
- M `includes/builder/canvas_repository.php` (full replace: BuilderVersionConflictException, expected_version + `version=version+1` guard, slug 1062-retry, sanitizeData + extractMedia + media sync in-transaction, JSON_UNESCAPED_SLASHES, formatCanvas returns version)
- M `includes/builder/bootstrap.php` (+ html_sanitizer + media_refs require lines after canvas_repository.php)
- M `public/api/builder/save.php` (8b expected_version required on update, 409 BuilderVersionConflictException → STR_VERSION_CONFLICT, 400 InvalidArgumentException → STR_BLOCK_INVALID)
- M `public/api/builder/load.php` (`version => null` in blank shape; P1 templates.view checks kept)
- M `public/api/media/delete.php` (P3 version: 409 with page titles, row DELETE first then unlink, no `force`, FK-1451 race mapped to 409)
- M `public/assets/js/builder/builder-shell.js` (401 ⇒ expired; `expected_version` sent on update)
- N `tests/html_sanitizer_test.php`, N `tests/builder_registry_test.php` (verbatim, green first run)
Migrations: `0004` — clone ✅ (composite FK works on MariaDB 10.4; mandatory clone test passed) → real ✅. `diagnose.php --phase=3` → D07/D08/D09 0 rows, D10 INFO (0), D11 phase-3 PASS, D21 PASS, 0 FAIL (clone and real identical).
Gate (live server; owner + pages.edit-only staff):
| # | Result | Evidence (one line) |
|---|---|---|
| 1 tests/lint/grep | ✅ | `run_all` exit 0 (5 files OK); `php -l` clean on all 11 files; Arabic-letter grep empty |
| 2 migration + diagnose | ✅ | clone + real runs above; final `--phase=3` → 14 checks, 0 FAIL |
| 3 M2 clone-only | ✅ | cross-canvas parent INSERT → ERROR 1452 (`fk_builder_blocks_parent_same_canvas`); root `parent_id NULL` → ok (id 297, deleted after); self-made canvas 33 + block deleted → both gone (cascade); all probe rows removed |
| 4 M13 optimistic lock | ✅ | load → version 1; tab1 save → 200 version 2; tab2 stale → 409 byte-exact STR_VERSION_CONFLICT; title still `M13 Tab1`, version 2; update without `expected_version` → 400 invalid request |
| 5 session expiry | ✅ | logged-out JSON save → 401 session-expired message; `builder-shell.js:97` maps `status === 401` to `{expired:true}` (existing expired dialog) |
| 6 H7/M5 end-to-end | ✅ | temp `types/zz_probe.php` leaf (contract §P3.5): save with `media_id=4` + evil client `src` → 1 `builder_media_refs` row, stored `src` is the DB path (client URL ignored); delete file → 409 byte-exact STR_MEDIA_IN_USE + title `M13 Tab1`; block removed + save → delete 200, row gone and file gone from disk (see Deviations); `media_id=99999` → 400 byte-exact STR_BLOCK_INVALID; `zz_probe.php` deleted |
| 7 L11 + slashes | ✅ | two same-title page saves → 200/200, slugs `l11-same-title` + `l11-same-title-2` (canvases 33, 34); 0 of 12 block rows contain `\/` (UNESCAPED_SLASHES; probe stored `/uploads/…` unescaped while it existed) |
| 8 H7 negative | ✅ | leaf `register()` without `sanitize` throws `A type with allows_children=false must define a sanitize callable` (also covered by `builder_registry_test`, green) |
| 9 owner regression | ✅ | owner loads/saves page canvas 30 and template canvas 29 (200s); pages.edit-only staff saving template canvas 29 → 403 `دسترسی غیر مجاز` |
Behaviour changes: canvas updates require `expected_version` (stale ⇒ 409); block data passes each type's `sanitize` hook (unknown media id ⇒ 400); media deletion answers 409 with using-page titles instead of `need_force`; `load.php` returns `version`; logged-out builder saves surface the expired-session dialog instead of a generic error.
Deviations: stat-cache note for the deleted-file check (see above). No other deviation: `types/structural.php` untouched (all 5 types are containers, unaffected by mandatory sanitize).
Known problems: gd/sodium still disabled (P0); gate side effect `active_template_id=4` (P1, unchanged).
Known problems: gd/sodium still disabled (P0); gate side effect `active_template_id=4` (P1, unchanged).
Cleanup list (kept until P7): P1/P2 items (staff 17 with DB-set dashboard+pages perms, staff 18 `removed-18`, staff 19, canvases 30/31/32/33/34, media 5/6 + files, migtest clone) minus media 4 (deleted in this gate); temp scripts `%TEMP%\opencode\{p3lock.php,p3sess.php,p3probe.php,p3l11.php,h7neg.php,p3regress.php}`; deleted in this gate: `includes/builder/types/zz_probe.php`, clone probe rows (block 297, canvas 33 + block), media 4 row + file.

## P4 — File storage: one gateway, private files, media registry (2026-10-10)
> gd + sodium are STILL disabled (`;extension=gd`, `;extension=sodium` in `C:\xampp\php\php.ini` — Pouya action). Rows that need GD image re-encoding are marked ⏭️ with that reason; everything else was verified live. `file_store_test.php` prints `SKIP (gd)` + exit 0 (prompt-allowed ⏭️).
Files changed:
- N `database/migrations/0005_files_pre_code.php` ([pre-code], ran FIRST)
- M `config/config.php` (+ STORAGE_PRIVATE/MAX_CERT_BYTES/MAX_TEMPLATE_ZIP_BYTES/MAX_ZIP_FILES/MAX_ZIP_UNPACKED_BYTES; deleted UPLOAD_PRODUCTS/UPLOAD_TEMPLATES/UPLOAD_LICENSES/ALLOWED_* — dead-constants grep: 8 hits all in config.php before, empty after)
- N `storage/private/.htaccess` (`Require all denied`), N `storage/private/.gitkeep`; M `.gitignore` (+ private/.gitkeep/.htaccess keeps, public/uploads ignore + htaccess keep); `public/uploads/.htaccess` verified unchanged (P1.9 text intact)
- N `includes/file_rules.php`, `includes/file_store.php`, `includes/media_registry.php` (verbatim reference implementations)
- M `public/api/media/upload.php` (FileStore + registry + duplicate response), M `public/api/media/delete.php` (final: purpose filter + FileStore::remove), M `public/api/templates/upload.php` (private storage + inspect-without-extract), M `public/api/licenses/update.php` (private cert + UUID merchant + zarinpal always pending + single-statement update; `api_key` line kept for P6), N `public/file.php` (private gateway), M `public/api/staff/remove.php` (avatar release after commit)
- M `public/modules/licenses/index.php` («مشاهده گواهی» links + accept attrs; zarinpal form untouched), M `public/modules/media/index.php` (purpose filter, `|` LIKE escape, page clamp + 5-window, dead fetch + need_force removed, duplicate/info + upload-error dialogs, createElement preview), M `public/modules/settings/general.php` (full replace: validate-first, FileStore + transactional registry, post-commit release, PRG), M `public/modules/settings/profile.php` (avatar via FileStore + in-transaction registry + post-commit release; invalid avatar now errors and saves nothing)
- N `tests/file_rules_test.php` (green), N `tests/file_store_test.php` (SKIP (gd), exit 0)
- N `database/tools/backfill_files.php`, N `database/tools/gc_files.php` (from §P4.9; backfill dry-run default)
Migrations: `0005` — clone ✅ → real ✅. `diagnose.php --phase=4` → D12 PASS, D13 INFO, D14 PASS, D22 INFO-or-PASS, 0 FAIL.
Gate (live server; owner + staff):
| # | Result | Evidence (one line) |
|---|---|---|
| 1 tests/lint/greps | ✅/⏭️ | `run_all` exit 0 (7 files; file_store `SKIP (gd)` ⏭️ gd missing); `php -l` clean on all 19 files; Arabic grep empty; dead-constants grep empty after deletion |
| 2 migration + diagnose | ✅ | clone + real runs above; post-backfill `--phase=4` → 18 checks, 0 FAIL (D13 INFO = 2 rows: #6 reported duplicate + #7 backfill-registered avatar NULL-by-design; D22 all registered) |
| 3 H2/H4 negatives | ✅/⏭️ | `.php`-as-`.jpg` → 400; SVG → 400 (library); GIF → 400; 6 MB PNG → 400 size message; PNG+`<?php` re-encode check ⏭️ (needs gd) |
| 4 H9 dedupe | ✅/⏭️ | same MP4 twice → 2nd is `success:true, duplicate:true` + byte-exact STR_MEDIA_DUPLICATE, 1 row + 1 file (video path, no gd); image-path dedupe ⏭️ (same `mediaRegisterFile` code path, needs `storeImage`/gd) |
| 5 private certs | ✅ | PDF → `storage/private/licenses/<hex>.pdf`; `/uploads/licenses/x` → 404; `file.php?type=license` → 200 pdf for licenses.view owner, 403 staff w/o it, 302 logged out; replace → old file deleted |
| 6 template zips | ✅ | clean → 200 + `package_path/sha256` set + file in `storage/private/templates/` + 0 files extracted; slip/symlink → STR_ZIP_UNSAFE, `.php` → STR_ZIP_FORBIDDEN_FILE, 501 entries → STR_ZIP_TOO_MANY, 101 MB unpacked → STR_ZIP_TOO_BIG (all 400, private-file count unchanged); templates.edit non-owner → 403 owner-only message |
| 7 avatar | ✅/⏭️ | SVG avatar → FileStore error message, profile row untouched (nothing saved); remove-with-avatar file/row deletion + change-twice-first-gone ⏭️ (needs gd); D20 = 0 rows |
| 8 general settings | ✅/⏭️ | ICO favicon saves (302 `saved=1`, registered `site_asset`); SVG favicon → STR_FAVICON_FORMAT; empty site name → STR + 0 new files + DB unchanged; PNG logo save ⏭️ (needs gd) |
| 9 media page | ✅ | `%`/`_` search → 0 literal cards; `?page=999` → 200 clamped to page 1; injected ref → 409 with title (P3 path intact); `duplicate` info + upload-error dialogs present in code |
| 10 backfill | ✅ | dry-run pasted (2 hash SKIPs + 1 asset SKIP, 0 PROBLEM); fresh backup `panel-backups/db-before-p4-backfill.sql` (108,076 bytes) taken first; `--apply` → media#5 hashed, #6 `PROBLEM duplicate content of media#5` (same 1px test PNG, left alone per spec), avatar 5376 registered; D14 = 0, D22 all-registered, D12 PASS; 3 avatars accounted (5376 registered; 5caa/a9e7 = gc-reported unreferenced orphans, left in place) |
| 11 owner regression | ✅/⏭️ | video upload (id 8) + text profile save + text settings save + template zip all 200; image upload ⏭️ (needs gd) |
| 12 bytes on disk | ✅ | `public/uploads`: `.htaccess` + 3 avatars + ico/2 png/mp4 — all match `^[0-9a-f]{32}\.(jpg\|png\|webp\|mp4\|webm\|ico)$`; `storage/private`: cert pdf + template zip + .gitkeep/.htaccess |
Behaviour changes (required list): invalid avatar now errors and saves nothing (was: silently ignored); zarinpal status is always `pending` (was: `verified` without verification — author's correction); SVG no longer accepted anywhere (library/logo/favicon/avatar/zip); template zip limit raised 20 MB → 50 MB (author's decision).
Deviations: none — reference implementations used verbatim; backfill/gc tools written from §P4.9 (step-2 registration leaves `sha256` NULL for avatar/site_asset rows by tool design — dedupe key covers new library uploads; D13 INFO reflects this plus the reported #5/#6 duplicate).
Known problems: gd/sodium still disabled (P0); gate side effects: `site_settings` now (`P4 Site`, `example.com`, new favicon) via the ICO test, enamad cert set, template 5 `clean` (uploaded, not installed); `active_template_id=4` (P1, unchanged).
Cleanup list (kept until P7): previous items + template 5 `clean` (+ package file, installed in P5 gate), media 7 (backfill-registered avatar row), media 8 (mini.mp4 + file), media 10 (favicon ico + file), cert file `licenses/3530…pdf`, template package `templates/dfba…zip`, favicon registry row; temp scripts `%TEMP%\opencode\{mkzips.php, zips/*, mkmp4.php, mini.mp4, mkico.php, mini.ico, p4neg.php, p4priv.php, p4zips.php, p4dupe.php, p4settings.php, p4avatar.php, p4media.php, p4ownerchk.php, p4final.php, x.svg, logo.png, p5code.php, p5snap.php, p5notif.php}` + fresh backup `panel-backups/db-before-p4-backfill.sql`; gc `--delete` NOT run (report only).

## P5 — Legacy cleanup, single source of truth for the active template (2026-10-10)
> ⛔ BLOCKED at migration `0006`: the guard refuses to run — `article_categories` holds 1 row (id 1, name `23223`, slug `23223-19c4`; looks like junk; `articles` is empty so it is unreferenced). Per §P5.4 I did NOT set `MIGRATE_DROP_LEGACY=1` myself. Pouya decision needed (see bottom). Everything else in P5 is done and verified.
Files changed:
- N `database/migrations/0006_legacy_cleanup_post_code.php` ([post-code], created, NOT yet applied — guard fires)
- M `config/config.php` (+ `NOTIFICATIONS_ENABLED=false`), M `includes/header.php` (notification queries behind the flag, markup untouched)
- M `public/api/templates/activate.php` (no `is_active`: select id/is_installed, single `UPDATE site_settings`, generic catch kept), M `public/api/templates/install.php` (POST-only, id check, exists check, idempotent, uploaded-without-package → STR_PACKAGE_MISSING, conditional update + log)
- M `public/modules/dashboard/index.php` (page count from `builder_canvases`, articles card removed, INSERT IGNORE snapshot + 7-day-lookback trend, error_log on failure), M `public/modules/templates/index.php` (activeId compare, single category-count query, `page` clamp, int-cast limit/offset, «همه» pill with total)
Migrations: `0006` — clone ❌ (guard fired as designed: `Legacy table(s) with rows: article_categories`, exit 1, nothing recorded, clone intact) · real ⏳ (not run — awaiting Pouya).
Gate:
| # | Result | Evidence (one line) |
|---|---|---|
| 1 tests/lint/greps | ✅ | `run_all` exit 0 (7 files); `php -l` clean on all 6 files; Arabic grep empty; `is_active` grep over public/includes empty; legacy-`FROM (pages\|articles\|media_usage)` grep empty |
| 2 code-before-0006 | ✅ | dashboard 200, templates page 200, activate/install 200s, all 10 module pages 200, media delete path untouched — all while legacy tables + `is_active` still exist |
| 3 0006 clone+real + diagnose | ⏳ | BLOCKED (see above). Real `--phase=5` pre-state: exactly 4 FAIL (D11 settings-FK, D15, D16, D17), all 0006-dependent |
| 4 H5 single source | ✅ | activate 3 → `active_template_id=3`, activate 4 → `active_template_id=4` (only B); non-installed → 400 `قالب ابتدا باید نصب شود` |
| 5 install | ✅ | uploaded-with-package (id 5) → 200 (id 5 now installed — side effect); missing package → 400 STR_PACKAGE_MISSING; unknown id → 404 `قالب یافت نشد`; re-install → 200 idempotent |
| 6 FK RESTRICT clone-only | ⏳ | needs 0006 (pending Pouya) |
| 7 dashboard snapshots | ✅ | card `تعداد ساخت سایت` = ۵ = `COUNT builder_canvases page`; no `تعداد مقالات` card; two near-simultaneous hits → exactly 1 snapshot row for today |
| 8 notifications | ✅ | bell shows `اعلانی وجود ندارد`; MySQL general log during an authenticated clone dashboard hit: 0 `FROM notifications` queries (log ON→OFF around the hit; probe `p5notif.php` listed in cleanup) |
| 9 templates page | ✅ | category counts correct (single query), «همه (N)» pill present, `?page=999` → 200 clamped, `template_categories` slug `all` row still present (deleted by 0006) |
| 10 owner regression | ✅ | templates.view-only staff: page 200, activate 403; owner full sweep (gate-7 script) 10×200 |
Behaviour changes: «تعداد مقالات» card removed; site count now = builder pages; snapshot trend uses newest snapshot ≥7 days old (was: exact today−7 row); install is validate-only (no file work); active template = `site_settings.active_template_id` only (code side; column dropped by 0006); notifications queries off.
Deviations: «همه» pill now shows the total count (`همه (N)`) — the spec says the pill shows the total; implemented as label + count.
Known problems: gd/sodium still disabled (P0); BLOCKER: `0006` guard — `article_categories` 1 junk row (see below); side effects: template 5 installed, `active_template_id` 3→4 during H5 test (ends at 4 = `panel`, consistent with `is_active=1` row), staff 17 now dashboard+templates.view (test setup, restored? — NO: left as dashboard+templates.view; P7 cleanup deletes the staff anyway).
**Pouya decision needed:** legacy `article_categories` has 1 row (`id=1, name='23223', slug='23223-19c4'`) while `articles` is empty (unreferenced junk). To finish P5, either (a) reply "drop it" and I re-run with `MIGRATE_DROP_LEGACY=1`, or (b) delete that row yourself in phpMyAdmin and tell me to re-run `php database/migrate.php`. The migration + diagnose + remaining gate rows (3/6) will be completed right after.
Cleanup list (kept until P7): previous items + probe template rows (created + deleted by exact id in-gate: `P5PROBE-NOINSTALL`, `P5PROBE-NOPKG`); temp scripts `%TEMP%\opencode\{p5code.php, p5snap.php, p5notif.php}`; MySQL general_log toggled ON then OFF (log file `C:\xampp\mysql\data\DESKTOP-EM48OST.log` grew — server-local, not in repo).

## P6 — Secrets and database access (2026-10-10)
> Two external blockers shape this phase. (1) `sodium` is STILL disabled, so no live secretbox round-trip was possible: `secrets_test.php` reports `SKIP (sodium)` (deviation below), encryption-dependent gate rows are ⏭️. (2) Migration `0007` is written but CANNOT run yet — `migrate.php` stops at pending `0006` (guard: `article_categories` 1 row, P5 blocker). Columns are still `varchar(255)`. Both resume after Pouya's actions.
Files changed:
- N `database/migrations/0007_secret_columns_pre_code.php` ([pre-code], created; run pending 0006)
- N `includes/secrets.php` (secret_key/is_encrypted/encrypt/decrypt/mask, `v1:` format, verbatim)
- M `config/database.php` (fail-closed production version, verbatim), M `config/env.example.php` (comments point to `gen_secret_key.php` / `create_db_users.sql`)
- N `database/tools/gen_secret_key.php`, N `database/tools/create_db_users.sql` (verbatim), M `database/tools/env_check.php` (+ APP_ENV/secret-key/sodium/DB-user/php.ini-limits section), N `database/tools/encrypt_existing_secrets.php` (dry-run default, decrypt-and-compare, per-table transactions)
- M `public/api/sales/connect.php` (validation + encrypt + log; blank secret → NULL), M `public/api/sales/disconnect.php` (destroys credentials + log + POST-only + id check), M `public/api/licenses/update.php` (P6 keep-existing/upgrade + length cap), M `public/modules/licenses/index.php` (zarinpal input never prints the key; placeholder + registered-note)
- M `database/README.md` (key-custody paragraph), N `tests/secrets_test.php` (verbatim + sodium-SKIP guard, see Deviations)
Migrations: `0007` — file created + `php -l` clean; run BLOCKED by pending `0006` (runner stops at the 0006 guard on clone AND real; columns verified still `varchar(255)`).
Gate:
| # | Result | Evidence (one line) |
|---|---|---|
| 1 tests/lint/grep | ✅/⏭️ | `run_all` exit 0 (8 files; secrets `SKIP (sodium)` ⏭️); `php -l` clean on all 11 files; Arabic grep empty |
| 2 0007 columns | ⏳ | blocked by pending 0006 (verified `varchar(255)` on real DB) |
| 3 H3 marketplace | ⏭️ | needs working secretbox (no sodium); sales/licenses pages statically verified key-free (see row 5) |
| 4 licenses keep/validate | ✅/⏭️ | invalid merchant → 400 STR_MERCHANT_INVALID; 400-char key → 400 STR_KEY_TOO_LONG; page has no `value=` on api_key + placeholder exact; blank-keeps + `v1:`-storage ⏭️ (needs sodium) |
| 5 missing key | ✅ | `APP_SECRET_KEY` unset → connect answers 500 STR_SECRET_ERROR, 0 rows stored; sales page contains no test key |
| 6 encrypt tool | ✅/⏭️ | no key → exit 1 with setup message, no values printed (dry-run and --apply); dry-run counts + `--apply` + second-run-0 + D18-after ⏭️ (needs sodium); current D18 PASS (nothing plaintext stored: connections empty, zarinpal creds NULL) |
| 7 H10 least privilege | ✅ | `panel_app`/`panel_migrator` created with exact prompt grants; panel_app SELECT ok (4 users) + `CREATE TABLE` denied 1142; `migrate.php --status` as migrator lists applied 0001–0005 + pending 0006/0007; APP_ENV=production + root → HTTP 500 byte-exact generic message, no host/user/trace leak, `Refusing to start` in error log |
| 8 env_check limits | ✅ | prints upload_max_filesize 40M/post_max_size 40M WARNs (below 50M/55M — Pouya php.ini action), memory 512M OK, max_file_uploads 20 OK; secret-key/sodium lines flag without ✗ chars |
| 9 owner regression | ✅/⏭️ | disconnect NULLs + 200; enamad/business_license saves work; cert upload works (P4 path); marketplace connect + zarinpal key save ⏭️ (needs sodium) |
Behaviour changes: connect validates marketplace/key lengths and encrypts (blank secret → NULL, was `''`); disconnect destroys stored credentials (was: kept them); zarinpal blank submit keeps (and upgrades) the stored key; stored secrets are never rendered (masked note instead); production refuses root/missing env.
Deviations: `tests/secrets_test.php` gained a 2-line sodium-SKIP guard (same pattern the prompt prescribed for `file_store_test.php`) — without it `run_all` would be red through no fault of the code; the full test runs the moment sodium is enabled. `database/README.md` gained the key-custody paragraph required by §P6.5. Phase order: P6 executed with P5-0006 still pending, per Pouya's "Resume with P6" (gate rows 3/6 of P5 stay ⏳).
Known problems: sodium + gd still disabled (P0/P4); P5-0006 blocker unchanged (`article_categories` 1 row); P6-0007 run blocked by it; php.ini upload/post limits below P6 thresholds (40M/40M — Pouya action); side effects: service DB users `panel_app`/`panel_migrator` now exist (passwords generated at runtime, live in MySQL grant tables + the P6 gate session only — never on disk/repo/changelog); `site_settings` P4 values unchanged.
**Pouya actions needed:** (1) P5: `article_categories` row decision (see P5); (2) `php.ini`: uncomment `extension=gd` + `extension=sodium` (and optionally raise `upload_max_filesize`→50M+, `post_max_size`→55M+) then restart Apache/PHP; (3) production: real env vars per §P6.7 + back up `APP_SECRET_KEY` separately (generate with `php database/tools/gen_secret_key.php` once sodium is on).
Cleanup list (kept until P7): previous items + `panel_app`/`panel_migrator` DB users (KEEP — P6.7 production switch needs them; passwords in grant tables only); temp scripts `%TEMP%\opencode\{mkusers.php, p6privdb.php, p6priv.php, p6prodfail.php, p6fail.log, p6nokey.php}` (passwords generated+used in-process only, never written — `mkusers.sql` temp file deleted right after use); no test secret rows remain (`marketplace_connections` empty, zarinpal creds NULL).

## P7 -- Frontend pinning, final regression, cleanup (2026-10-10)
> Partial: P5-0006 and P6-0007 are still blocked (article_categories row; sodium/gd off), so regression items that need them are pending. Everything executable in P7 is done.
Files changed:
- M `includes/header.php` + `includes/footer.php` + `public/login.php` (pinned CDN tags with SRI + crossorigin; `sweetalert2@11` floating tag resolved to `11.26.25` via the live `X-JSD-Version` header)
- M `public/.htaccess` (P7.3 rewrite), N `public/assets/img/default-avatar.svg`, M `includes/header.php` (avatar `src` points at the `.svg`; nothing else)
- M `database/README.md` (production-switch section), N `changelogs/diagnostics-after.txt` (full suite: 22 checks, 4 FAIL -- all 0006-blocked)
Version table (hashes computed from the pinned URLs, verified by TWO independent implementations -- .NET SHA384 and PHP `hash_file('sha384')` -- byte-identical, plus version/size cross-checks):
| library | version | file | sha384 |
|---|---|---|---|
| bootstrap | 5.3.3 | `dist/css/bootstrap.rtl.min.css` | `dpuaG1suU0eT09tx5plTaGMLBsfDLzUCCUXOY2j/LSvXYuG6Bqs43ALlhIqAJVRb` (232911 B) |
| bootstrap-icons | 1.11.3 | `font/bootstrap-icons.css` | `tViUnnbYAV00FLIhhi3v/dWt3Jxw4gZQcNoSCxCIFNJVCx7/D55/wXsrNIRANwdD` (98255 B) |
| bootstrap | 5.3.3 | `dist/js/bootstrap.bundle.min.js` | `YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz` (80721 B) |
| sweetalert2 | 11.26.25 | `dist/sweetalert2.all.min.js` | `nLoOnA/BDh8A/jxqtckg4DumuCGOBYUnNJLZdQz/zfYNp3wcjGSoWTAzgko06G/2` (79150 B = advertised Content-Length) |
Gate:
| # | Result | Evidence (one line) |
|---|---|---|
| 1 CDN pinning | pass/skip | 5 tags pinned (header 2 css, footer 2 js, login 1 css), no floating tag left; hashes double-verified; served dashboard HTML has 4x integrity + 4x crossorigin; browser-console recheck skipped (no browser here -- Pouya: open dashboard + builder, confirm no integrity errors, Swal + dropdowns work) |
| 2 htaccess | pass | Apache: `/.htaccess` -> 403, `/panel/public/x.sql` + `x.log` -> 403 (rule fires, not 404), a real `.md` probe file -> 403 (probe deleted right after); `/panel/public/login.php` -> 200, media file -> 200 with `nosniff` (uploads rules intact) |
| 3 avatar | pass | avatar-less staff header references `/assets/img/default-avatar.svg`; file -> HTTP 200 (293 B, first-party static -- the upload SVG ban is unaffected) |
| 4 regression cmds | pass/pending | `run_all` exit 0 (8 files, 2 expected SKIPs); `php -l` sweep clean; Arabic grep empty; `diagnostics-after.txt` saved (4 FAIL all 0006-blocked: D11-settings/D15/D16/D17); `migrate --status` -> 0001-0005 applied, 0006/0007 pending |
| 5 role sweep | pass | owner 10x200; dashboard.view-only staff: dashboard + profile 200, other 8 pages 403 with no `class="sidebar` leak; staff APIs 403 JSON |
| 6 cleanup | partial | temp probe scripts + zips + mini files ALL deleted from `%TEMP%\opencode` (only third-party entries remain); RETAINED pending unblock (explicit): `business_panel_migtest`, test staff 17/18(`removed-18`)/19, canvases 30-34, media 5/6/8/10 + files, template 5 + package, cert + package files, `panel_app`/`panel_migrator` users -- all needed to re-verify P5-3/6 + P6 + P7 after unblock |
| 7 global gate | see below | Appendix E filled honestly (several rows pending/blocked with reasons, not green) |
Decision: no CSP (P7.5 -- recorded, not implemented): pages use inline scripts/handlers + `window.CSRF_TOKEN`; a CSP without `'unsafe-inline'` would break them, with it protects little. Headers from P1 stay. Revisit with a nonce-based CSP when Part 4+ moves page scripts into files.
Deviations: none in P7 (reference SVG + htaccess used verbatim).
Known problems: gd/sodium still disabled (P0); P5-0006 + P6-0007 blocked (see above); browser-console SRI recheck outstanding (Pouya, 2 min).

## Appendix E -- Combined global gate (end of P7, honest state)
| # | Check | Result |
|---|---|---|
| 1 | One served tree; `/login.php` is the only login; old trees gone (P0). | pass (old `api/`+`modules/`, `builder-schema.sql`, `block-editor.js`, `products/` deleted; no old-tree refs) |
| 2 | `php tests/run_all.php` green; `php -l` clean; no Arabic chars. | pass (8 files green incl. 2 documented SKIPs; lint sweep clean; grep empty) |
| 3 | `migrate.php --status`: `0001`-`0007` applied; `diagnose.php` (all phases) no FAIL. | BLOCKED: 0001-0005 applied, 0006/0007 pending (guard); diagnose 4 FAIL (D11-settings/D15/D16/D17) |
| 4 | Permissions: Appendix A verified for owner and a minimal staff; 403 before any output; suspended/removed users cut off immediately. | pass (P1 gate + P7 sweep) |
| 5 | Accounts: no seed password in use; soft delete keeps content and logs; lock keyed by identifier+IP; password change kills other sessions. | pass (P2 gate; D03 PASS) |
| 6 | Builder: media refs enforced, stale save => 409, sanitizer contract in place, composite FK active. | pass (P3 gate) |
| 7 | Files: no SVG; all uploads through `FileStore`; private files only via `file.php`; duplicates rejected; legacy files backfilled. | pass/skip (verified except gd-locked image paths; backfill done) |
| 8 | Legacy tables and `templates.is_active` gone; active template single-sourced; category `all` gone. | BLOCKED on 0006 (code side done: no `is_active`/legacy refs in code) |
| 9 | Secrets encrypted (`D18` PASS); no key in any page/response; production fail-closed verified. | pass/skip (D18 PASS -- nothing plaintext; fail-closed + privilege separation proven live; live encrypt round-trips wait for sodium) |
| 10 | CDN pinned with SRI; `public/.htaccess` effective; default avatar loads. | pass (browser-console recheck: Pouya) |
| 11 | Cleanup list done; migtest DB dropped; changelog complete with Deviations, Known problems, Known limits. | partial (temp probes done; DB artefacts + migtest retained -- see P7 gate 6) |

## Known limits (Appendix B, confirmed)
1. DDL statically reviewed + clone-tested; a failing clone run is a finding (0006 guard fired exactly as designed).
2. `APP_ENV` unset => local behaviour (fail-open default); `env_check.php` flags it; production switch is manual (README documents).
3. `dashboard_snapshots.articles_count` stays (always 0); permission key `articles` + its screen stay.
4. Old-cost hashes remain until password change (no forced rehash).
5. No CSP (see P7 decision); no API rate limit besides login; no 2FA.
6. Legacy media hashes are stored-bytes hashes (documented limitation); dedupe protects new uploads.
7. Template packages stored + inspected, never extracted/rendered (Part 7).
8. No builder revision history (Appendix C design input untouched).
9. 180/7-day retention only if `purge_logs.php` is scheduled (cron line in README).

**Final message:** P0-P4 gates green; P5 code done but `0006` blocked by 1 junk row in `article_categories`; P6 files done but `0007` waits on `0006` and live-encryption waits on `sodium`. P7 delivered all unblocked work (pinned CDN with double-verified SRI, effective htaccess, avatar, runbook, sweep, honest global gate). Blocked rows and exact remedies are in this changelog. Your 3 actions: (1) `article_categories` row decision, (2) `php.ini`: enable `extension=gd` + `extension=sodium` (optionally raise upload/post limits) + restart Apache/PHP, (3) production env vars + `panel_migrator` use + separate `APP_SECRET_KEY` backup. Changelog: `changelogs/changelog-fix-hardening.md` (+ `diagnostics-after.txt`).
