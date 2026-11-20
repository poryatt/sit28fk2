# Master Prompt — Unified Hardening: Roles & Permissions, Database, Files, Secrets (panel3)

> **Type:** Multi-phase fix prompt (P0 → P7). It runs **before** Part 0a / Part 4 of the Visual Page Builder roadmap.
> **Replaces:** `master-prompt-fix-roles-permissions.md` (fully merged into P0/P1/P2) and the proposed fix-A/B/C/D + `0b` prompts of the DB audit `db-audit-panel3-v2.md` (merged into P1–P7).
> **Executor:** OpenCode AI agent. **Language of this file:** English. Persian UI strings are exact and must stay byte-identical (§2.3 lists every new one).
> **Stack (decided, do not re-ask):** PHP **8.0-compatible** (`composer.json` says `>=8.0`), PDO + MySQL 5.7+/8.x or MariaDB 10.4+, Bootstrap 5 RTL (CDN) + SweetAlert2 already loaded, vanilla JS. **No new Composer packages. No new JS libraries.**

---

## 0. How to run this prompt

1. Read §0–§4 completely, then execute the phases **in order**: P0 → P1 → P2 → P3 → P4 → P5 → P6 → P7. A phase starts only when the previous phase's **Gate** is green.
2. Each phase ends with a **Gate** (commands + checklist). Evidence goes into the changelog **at the end of that phase** (append a section to `changelogs/changelog-fix-hardening.md`; create the file in P0).
3. **Resume protocol.** This is a long job. If you cannot finish every phase in one session, **stop right after a completed Gate**, make sure the changelog contains that phase, and tell Pouya in one line: "Completed through P<N>. Resume with P<N+1>." Never stop in the middle of a phase without saying so. Pouya may run later phases in a new session by giving you §0–§4 plus the next phase and the changelog so far.
4. **Migration run protocol** (important — migrations are created *during* phases, not all at once):
   - A migration file is created only at the step that names it, and `php database/migrate.php` is run only at that step. `migrate.php` applies **every pending file**, so never leave a file for a later step in `database/migrations/`.
   - Each migration is marked **[pre-code]** (additive, safe before the new code exists: run it *first* in its phase) or **[post-code]** (destructive or rule-tightening: run it *last*, after the code that depends on it is deployed and tested).
   - Every migration is run **first on a clone** (`business_panel_migtest`, see §1 Rule 12), and only if that succeeds, on `business_panel`.
5. When this prompt is wrong, incomplete, or conflicts with the real code, do the smallest safe thing, and record it under "Deviations" in the changelog. Do not stop to ask unless the data could be destroyed.

---

## 1. Rules of engagement

1. **Scope.** Do exactly what this file says — nothing more, nothing less. Anything not listed in a phase's file map or specification is forbidden. Each phase has a **Must NOT do** table; it is binding.
2. **One source tree (decided in P0).** The project has two copies of every API/module file (`panel/api` + `panel/modules` = dead old tree; `panel/public/api` + `panel/public/modules` = served tree). P0 proves the old tree is dead, backs everything up, and deletes it. From P0 on, **only the served tree exists**. All paths in P1–P7 are relative to `panel/` and use the served tree (`public/api/...`, `public/modules/...`). This **supersedes** the old rule "edit both trees".
3. **Stack discipline.** PHP 8.0-compatible only: no enums, no `readonly`, no `never`, no first-class callable syntax (`strlen(...)`), no `new` in initializers, no pure intersection types, no fibers, no `array_is_list()`. No new Composer package, no new JS library. Vanilla JS. Prepared statements with placeholders everywhere (the only allowed interpolations are integers that were cast with `(int)`).
4. **Database unreachable.** If you cannot reach the database (no `mysql`/`mysqldump` on PATH, connection refused), ask Pouya to export/run the step in phpMyAdmin once. If that is impossible too: create the files, **do not run any migration**, mark every DB-dependent check ⏭️ with the reason, and write that plainly in the changelog. Never fake evidence.
5. **No destructive SQL except what is written here.** No `DROP`, `TRUNCATE`, or `DELETE` against the database unless the statement appears literally in this file (e.g. `DROP TABLE` of the dead tables in P5, `DELETE FROM template_categories WHERE slug = 'all'`, the `DELETE`s inside application code that this file specifies). Exceptions you may use freely: (a) the clone database `business_panel_migtest` (create/drop it as you like), (b) rows **you yourself** inserted for testing, deleted by exact `id` right after the test.
6. **Persian text.** Every Persian string in this file is exact. Do not translate, reword, re-encode, or "fix" them. Persian text uses **Persian letters only**: ی (U+06CC) and ک (U+06A9) — never Arabic ي (U+064A) or ك (U+0643). After finishing a phase, run: `grep -rnP "[\x{064A}\x{0643}]" public includes config database --include=*.php --include=*.js` — it must print nothing for files you touched.
7. **Secrets.** Never print, log, commit, or write into the changelog any password, API key, secret key, or hash that is not already in the repo. The DB password and `APP_SECRET_KEY` live only in `config/env.local.php` or real environment variables.
8. **Evidence honesty.** Every checklist row gets ✅ / ❌ / ⏭️ plus evidence (command output excerpt, SQL result, or a one-line manual observation). ⏭️ needs a reason. A ❌ that you cannot fix goes into "Known problems" and blocks the Gate unless Pouya says otherwise.
9. **Phase isolation.** Never start a later phase's work early (e.g. do not drop a column in P3 "because it is easy"). Never change a file that is not in the current phase's file map.
10. **Cleanup.** Temporary probe files, test users, test rows, test uploads, and the `business_panel_migtest` clone are removed/dropped at the end of the job (P7). List them as you create them.
11. **Idempotent migrations.** Every migration step is guarded (`information_schema` checks through `Migrator`), so a failed run can simply be re-run. A migration that throws is **not** recorded in `schema_migrations` and stops the runner with exit code 1.
12. **Clone first.** Before running a migration on `business_panel`: recreate the clone from a fresh dump and run it there:
    ```
    mysql -u root -e "DROP DATABASE IF EXISTS business_panel_migtest; CREATE DATABASE business_panel_migtest CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    mysqldump -u root --routines --triggers --single-transaction business_panel | mysql -u root business_panel_migtest
    DB_NAME=business_panel_migtest php database/migrate.php
    DB_NAME=business_panel_migtest php database/tools/diagnose.php --phase=<N>
    ```
    (Use the real DB user/password from `env.local.php`; on Windows use the full path of `mysql.exe`/`mysqldump.exe` from Laragon/XAMPP if they are not on PATH.)

---

## 2. Context and decisions (made by the prompt author — do not reopen)

### 2.1 What exists today (verified in the code)

- **Roles:** exactly two, ENUM `panel_users.role ∈ {'owner','staff'}`. **Permissions:** 8 module keys in `permissions` (`dashboard, pages, articles, templates, sales, media, licenses, settings`); staff access = per-user matrix `panel_user_permissions(can_view, can_edit, can_delete)`; owner bypasses everything; default is deny.
- **Schema:** 22 tables, 22 FKs (all with an explicit delete rule — the *choice* of rules is the problem). Builder tables `builder_canvases` / `builder_blocks` are the Part 0 foundation.
- **Zip evidence used for this prompt:** `/login.php` exists only in `public/`, so the web root can only be `panel/public`; every old-tree API file differs from its served twin only in `require` depth and upload paths (`diff` verified for all 25 files).
- **Not verified here:** no SQL was executed by the author (no MySQL available). All DDL is statically reviewed. This is why every migration is clone-tested first (Rule 12).

### 2.2 Decisions

| ID | Decision | Why |
|---|---|---|
| D1 | **No `roles` table.** Keep owner/staff ENUM + per-user matrix. | One owner and a handful of staff. A `roles` table becomes worth it only when many staff share identical access; it can be added later as a pure addition without breaking this design. |
| D2 | **Permission hierarchy `delete ⇒ edit ⇒ view`** enforced three times: when saving, when reading in `hasPermission()`, and by DB `CHECK chk_pup_logic`. | Code is the real guarantee (MySQL 5.7 ignores `CHECK`); the constraint is a safety net; read-time normalisation protects rows written by hand in phpMyAdmin. |
| D3 | **New staff get `dashboard.view` only.** Existing staff with **no rows at all** are backfilled the same way. Staff who already have rows (even all zeros) are left alone. | The dashboard is the post-login landing page; without it the first request is a 403. All-zero rows are a deliberate owner decision. |
| D4 | **Page guards live in one central map** (`includes/access_map.php`) enforced inside `header.php` **before any output**; a module page missing from the map is **denied**. | Fixes "403 after the whole panel frame was printed" without editing 9 module files; one place to audit; a future module must register itself or it stays closed (Lego rule). |
| D5 | `settings.view` = open «تنظیمات عمومی» read-only; `settings.edit` = save it. Own profile = **login only**. Staff management = **owner only**. | Once the profile page stops using `settings.view`, `view` would otherwise protect nothing. |
| D6 | Add suspend/activate (`set_status.php`). | Gives the owner a non-destructive way to cut access immediately. |
| D7 | `requireLogin()` re-validates the user **on every request** (row exists, `status === 'active'`); API requests get JSON 401, not a redirect. | Suspension/removal take effect immediately, not at the next login. |
| D8 | `load.php` also requires `templates.view` for template canvases (mirror of `save.php`). | Closes an information leak. |
| D9 | Username/password policy are enforced at **invite** (P1) and **profile** (P2). | Audit M8. |
| D10 | `profile.php` gets server-side email validation + duplicate (1062) catch in P1. | Making the profile reachable by every staff widens the "email accepted unvalidated" bug. |
| D11 | **Single served tree**; the old tree and 3 dead files are deleted in P0 after a backup and an evidence check. | Two copies guarantee drift; the audit proved one is dead code. |
| D12 | **Soft delete for staff** (`status='removed'`, anonymised username/email, unusable password hash, permissions rows removed) instead of `DELETE`. Creator FKs become `RESTRICT`; `activity_logs.panel_user_id` becomes `SET NULL`. | `created_by` must say "who made it", not "who owns its lifetime". Today deleting a staff member cascades to every canvas/block/media/log row they created (audit C1). |
| D13 | Seed password: column `must_change_password`; every account that still carries the seed hash is flagged by migration `0002`; until changed, only the profile page and logout are reachable. CLI `reset_owner_password.php` for fresh installs/servers. | Audit C2. Flag-based (not an interactive installer) = simplest thing that closes the hole. |
| D14 | Login lock is keyed by **(identifier + IP)** ≥ 5 failures **or** IP ≥ 20 failures in 15 minutes; a successful login clears that identifier+IP's failures; `getClientIp()` trusts forwarded headers **only** from configured `TRUSTED_PROXIES`. | Stops "5 wrong tries for `admin` lock the owner out for 15 minutes" (audit H8). |
| D15 | **Password change invalidates other sessions** via `panel_users.password_changed_at` + `$_SESSION['auth_at']` (pure rule `sessionIsStale()`). *Author's addition to audit M6-4, which named the problem without a mechanism.* | Cheapest mechanism that needs no session table. |
| D16 | `media_library` becomes the single registry of **public** files: new columns `sha256`, `purpose ENUM('library','site_asset','avatar')`, `UNIQUE(purpose, sha256)`. No new `files` table. `sha256` = hash of the **original uploaded bytes** (stored image is re-encoded, so its hash would differ). | Fewer tables = simpler Lego; dedupe key must be stable across GD versions. |
| D17 | **Avatars are registered** in `media_library` (`purpose='avatar'`), `panel_users.avatar_path` stays as the denormalised path. *Author's choice* (audit left the enum value `avatar` without saying who writes it). | Uniform GC, dedupe (the zip really contains the same avatar twice), clean removal on staff removal. |
| D18 | **Private files** (licence certificates, template zips) live in `storage/private/` (outside `public/`), tracked by their own table column (`licenses.certificate_file`, `templates.package_path`), served only by `public/file.php`. | Business documents must not be world-readable; no extra column `visibility` needed. |
| D19 | **Block-data contract** for Parts 4–6: every leaf block type **must** define `sanitize(stdClass $data, PDO $pdo): stdClass`; media is referenced by integer `media_id` and listed by optional `extract_media(stdClass $data): int[]`; the server (not the client) writes any media URL. Rich text goes through `BuilderHtmlSanitizer` (whitelist). | Part 4 is the first place users enter real HTML/URLs; this is the last moment to make it safe. |
| D20 | **Optimistic locking** for canvases: `builder_canvases.version`; `save.php` requires `expected_version` on update and answers 409 on a stale save. | Today two tabs silently overwrite each other (audit M13). |
| D21 | **Secrets encrypted at application level** with libsodium `secretbox`, key from env `APP_SECRET_KEY`, format `v1:base64(nonce‖cipher)`. UI never shows stored keys. | Audit H3. `v1:` prefix leaves room for key rotation later. |
| D22 | **Two DB users** in production: `panel_app` (DML only) and `panel_migrator` (DDL, used by `migrate.php` only). In `APP_ENV=production` the app **refuses to start** on missing env or on `root`. | Audit H10; fits the DevOps path. |
| D23 | Notifications queries in `header.php` are switched off behind `NOTIFICATIONS_ENABLED=false` until something produces notifications. | Table has no producer; two queries per page for nothing (audit §7). |
| D24 | Dashboard: the card «تعداد ساخت سایت» counts `builder_canvases` of type `page`; the card «تعداد مقالات» is **removed**. *Author's choice* (audit said "remove or redefine"). | There is no article concept any more; inventing a new metric is scope creep. |
| D25 | CDN assets are pinned to exact versions with SRI hashes computed at execution time. No CSP now (see P7.7). | Audit L8. |
| D26 | Migrations: `0001` P1 · `0002` + `0003` P2 · `0004` P3 · `0005` P4 · `0006` P5 · `0007` P6. | Matches the "since" phases of the diagnostic suite. |

### 2.3 Exact Persian strings introduced by this prompt

Use these byte-for-byte wherever the phases refer to them by name.

| Name | Text |
|---|---|
| `STR_DELETE_WARNING_TEMP` (P1 only) | `با حذف همکار، تمام صفحات ساخته‌شده، رسانه‌ها و لاگ فعالیت او نیز حذف می‌شود. برای جلوگیری از این اتفاق از «تعلیق» استفاده کنید. ادامه می‌دهید؟` |
| `STR_DELETE_CONFIRM` (P2, replaces the temp text) | `همکار حذف شود؟ دسترسی او فوراً قطع می‌شود و نام کاربری و ایمیلش آزاد می‌شود، اما صفحات و رسانه‌های ساخته‌شده توسط او و لاگ فعالیتش حفظ می‌شود. اگر فقط می‌خواهید دسترسی را موقتاً قطع کنید از «تعلیق» استفاده کنید.` |
| `STR_USERNAME_RULE` | `نام کاربری باید ۳ تا ۳۰ کاراکتر و فقط شامل حروف انگلیسی، عدد و . _ - باشد` |
| `STR_PASSWORD_POLICY` | `رمز عبور باید بین ۸ تا ۷۲ کاراکتر باشد` |
| `STR_LOCK_IDENTIFIER` (existing text, keep) | `حساب شما به دلیل تلاش‌های ناموفق به مدت ۱۵ دقیقه قفل شده است.` |
| `STR_LOCK_IP` | `تعداد تلاش‌های ناموفق از این شبکه بیش از حد مجاز است. ۱۵ دقیقه دیگر دوباره تلاش کنید.` |
| `STR_LOGIN_FAIL` (existing, keep) | `نام کاربری یا رمز عبور اشتباه است.` |
| `STR_LOGIN_SUSPENDED` (existing, keep) | `حساب شما مسدود شده است.` |
| `STR_ERR_TIMEOUT` | `نشست شما به دلیل عدم فعالیت منقضی شد. دوباره وارد شوید.` |
| `STR_ERR_INVALID` | `نشست شما معتبر نیست. دوباره وارد شوید.` |
| `STR_SESSION_EXPIRED_API` | `نشست شما منقضی شده است. دوباره وارد شوید.` |
| `STR_MUST_CHANGE_API` | `ابتدا باید رمز عبور خود را تغییر دهید.` |
| `STR_MUST_CHANGE_BANNER` | `برای ادامه کار با پنل، رمز عبور جدید تعیین کنید.` |
| `STR_MUST_CHANGE_NEEDED` | `برای ادامه باید رمز عبور جدید تعیین کنید.` |
| `STR_PW_CURRENT_REQUIRED` | `برای تغییر رمز، رمز فعلی را وارد کنید.` |
| `STR_PW_SAME` | `رمز جدید باید با رمز فعلی متفاوت باشد.` |
| `STR_PW_FORBIDDEN` | `این رمز عبور مجاز نیست. رمز دیگری انتخاب کنید.` |
| `STR_PW_WRONG_CURRENT` (existing, keep) | `رمز فعلی اشتباه است.` |
| `STR_PW_CHANGED` (existing, keep) | `رمز عبور با موفقیت تغییر کرد.` |
| `STR_PROFILE_SAVED` (existing, keep) | `اطلاعات با موفقیت ذخیره شد.` |
| `STR_NAME_INVALID` | `نام و نام خانوادگی باید بین ۱ تا ۱۵۰ کاراکتر باشد.` |
| `STR_PHONE_INVALID` | `شماره تلفن حداکثر ۲۰ کاراکتر است.` |
| `STR_EMAIL_INVALID` | `ایمیل نامعتبر است.` |
| `STR_EMAIL_DUPLICATE` | `این ایمیل قبلاً ثبت شده است.` |
| `STR_SAVE_ERROR` | `خطا در ذخیره‌سازی.` |
| `STR_VERSION_CONFLICT` | `این صفحه توسط شخص دیگری تغییر کرده است. صفحه را دوباره بارگذاری کنید.` |
| `STR_BLOCK_INVALID` | `داده‌ی یکی از بلاک‌ها نامعتبر است` |
| `STR_MEDIA_IN_USE` (prefix, then titles joined by `، `) | `این فایل در صفحه‌های زیر استفاده شده و قابل حذف نیست: ` |
| `STR_MEDIA_IN_USE_MORE` (suffix, `N` = Persian digits) | ` و N مورد دیگر` |
| `STR_MEDIA_DUPLICATE` | `این فایل قبلاً آپلود شده است` |
| `STR_SITE_NAME_INVALID` | `نام سایت باید بین ۱ تا ۱۵۰ کاراکتر باشد.` |
| `STR_DOMAIN_TOO_LONG` | `دامنه حداکثر ۱۹۰ کاراکتر است.` |
| `STR_CERT_FORMAT` | `فرمت مجاز: PDF/JPG/PNG/WEBP` |
| `STR_TEMPLATE_NAME_LONG` | `نام قالب بیش از ۱۵۰ کاراکتر است` |
| `STR_ZIP_UNSAFE` | `ساختار فایل ZIP امن نیست` |
| `STR_ZIP_FORBIDDEN_FILE` | `فایل ZIP شامل فایل غیرمجاز است` |
| `STR_ZIP_TOO_MANY` | `تعداد فایل‌های ZIP بیش از حد مجاز است` |
| `STR_ZIP_TOO_BIG` | `حجم بازشده‌ی ZIP بیش از حد مجاز است` |
| `STR_MERCHANT_INVALID` | `مرچنت آیدی نامعتبر است` |
| `STR_KEY_TOO_LONG` | `کلید API یا رمز API بیش از حد طولانی است` |
| `STR_MARKET_INVALID` | `مارکت‌پلیس نامعتبر` |
| `STR_SECRET_ERROR` | `خطا در رمزنگاری اطلاعات` |
| `STR_KEY_PLACEHOLDER` | `برای تغییر، کلید جدید را وارد کنید (در غیر این صورت خالی بگذارید)` |
| `STR_VIEW_CERT` | `مشاهده گواهی` |
| `STR_ACTIVATE_ERROR` | `خطا در فعال‌سازی قالب` |
| `STR_FAVICON_FORMAT` | `فاوآیکون باید PNG یا ICO باشد` |
| `STR_PACKAGE_MISSING` | `فایل بسته‌ی قالب پیدا نشد` |

Every Persian string that appears inside a code block of this file is exact as well, even when it is not listed here.

Already-existing strings that this file refers to but does not redefine (`دسترسی غیر مجاز`, `فقط مالک`, `کاربر نامعتبر`, `متد مجاز نیست`, `درخواست نامعتبر`, `توکن نامعتبر`, `قالب یافت نشد`, `خطا در ایجاد همکار` …) stay exactly as they are in the code or as written in the phase.

---

## 3. Phase map

| Phase | Theme | Migration | Audit / fix IDs closed |
|---|---|---|---|
| **P0** | Safety net, tooling, single tree | — (creates the migration framework) | M3, L1, L7 (part) |
| **P1** | Access-control core, session, safe quick fixes | `0001` [post-code] | F1–F9, F11/M14, M6-1/2/3/5, M7, M8 (invite + profile email), M9 (offset, global handler, activate leak), M15, L4, L9, L12, L8 (headers), C2 (text) |
| **P2** | Accounts: login hardening, soft delete, seed password, sessions, logging | `0002` [pre-code], `0003` [post-code] | C1 (code + FK), C2, H8, M6-4, M8 (rest), L5, M10 (indexes, coverage, retention) |
| **P3** | Builder foundation hardening + builder DB integrity ("0b") | `0004` [pre-code] | H7, M1, M2, M5, M13, L3, L11 |
| **P4** | File storage: one gateway, private files, media registry | `0005` [pre-code] | H1 (server), H2, H4, H9, M11 (zip), M12, L6, L7, L10 |
| **P5** | Legacy cleanup, single source of truth for the active template | `0006` [post-code] | H5, M4, M10 (snapshots, notifications), M11 (install/activate/categories) |
| **P6** | Secrets & database access | `0007` [pre-code] | H3, H10 |
| **P7** | Frontend pinning, final regression, cleanup | — | L1 (htaccess), L7 (avatar), L8 |

Order inside a phase is always: **[pre-code] migration → code → tests → [post-code] migration → gate.**

## 4. Audit coverage (nothing may fall between the phases)

| ID | Phase | ID | Phase | ID | Phase |
|---|---|---|---|---|---|
| C1 | P2 (+P3 trio moved to P2/`0003`) | M5 | P3 | M15 | P1 |
| C2 | P1 + P2 | M6 | P1 + P2 | L1 | P0 + P7 |
| H1 | P1 (client) + P4 (server) | M7 | P1 | L3 | P3 |
| H2 | P4 | M8 | P1 + P2 | L4 | P1 |
| H3 | P6 | M9 | P1 | L5 | P2 |
| H4 | P4 | M10 | P2 + P5 | L6 | P4 |
| H5 | P5 | M11 | P4 + P5 | L7 | P0 + P4 + P5 + P7 |
| H7 | P3 | M12 | P4 | L8 | P1 + P7 |
| H8 | P2 | M13 | P3 | L9 | P1 |
| H9 | P4 | M14 | P1 | L10 | P4 |
| H10 | P6 | M1, M2 | P3 | L11, L12 | P3, P1 |
| M3 | P0 | M4 | P5 | audit §12–§13 | Appendix C (design inputs for Part 7, not implemented) |

Permission-prompt defects F1–F12 map to P1 (F10 = suspend button; F12 = activity logs).

---


# PHASE 0 — Safety net, tooling, single tree

**Goal:** backups, environment probe, migration framework, diagnostics, one served tree. No migration is created in this phase.

> **Reference implementation.** Code blocks marked *reference implementation* were syntax-checked (and, where a test is named, executed) by the prompt author. Use them as written; if the real project forces a change, record it under "Deviations".

## P0.1 File map

```
panel/
├── config/
│   ├── env.php                        N  load_local_env() — §P0.3
│   └── env.example.php                N  Example of env.local.php (committed); never real secrets
├── database/
│   ├── README.md                      N  Install = schema.sql then `php database/migrate.php`; how to add a migration
│   ├── schema.sql                     M  ONLY a header comment: "BASELINE — frozen; every later change is a migration"
│   ├── lib/Migrator.php               N  Guard helpers over information_schema (tableExists, columnExists, indexExists, fkExists, fkRule, checkExists, columnType, isNullable, replaceForeignKey, ensureIndex)
│   ├── migrate.php                    N  CLI runner (filename order; records in schema_migrations; `--status`; advisory lock `GET_LOCK` so two runners cannot interleave DDL)
│   ├── migrations/                    N  (empty now; P1..P6 add files)
│   └── tools/
│       ├── env_check.php              N  Prints PHP/DB versions, sql_mode, time zone, extensions, whether CHECK is enforced
│       └── diagnose.php               N  Runs the diagnostic checks of §P0.5; `--phase=N`; exit code 1 on any FAIL
├── tests/                             N  (created empty; later phases add tests) + tests/run_all.php
├── .gitignore                         N  config/env.local.php, storage/private/*, backups/, vendor/ (if not present already, merge don't overwrite)
├── api/            ← DELETED (old tree)
├── modules/        ← DELETED (old tree)
├── database/builder-schema.sql        ← DELETED
├── public/assets/js/block-editor.js   ← DELETED (referenced by nothing)
└── public/uploads/products/           ← DELETED (empty; shop module is gone)
```

## P0.2 Steps (in this order)

1. **Backups** (outside the web root, never inside `public/`):
   - `mysqldump --routines --triggers --single-transaction business_panel > ../panel-backups/db-before-hardening.sql` (create the folder; if `mysqldump` is not on PATH, ask Pouya to export from phpMyAdmin; if neither is possible follow Rule 4).
   - Zip the whole project **excluding `vendor/`** to `../panel-backups/project-before-hardening.zip` (there is no git history in this project).
2. Run `php -m` and record which of `pdo_mysql, mbstring, fileinfo, gd, zip, sodium, exif` are present. Missing `pdo_mysql/mbstring/fileinfo/gd/zip` ⇒ stop and tell Pouya which line to enable in `php.ini`. Missing `sodium` only blocks Phase 6.
3. Create `changelogs/changelog-fix-hardening.md` (header + "Deviations" + "Known problems" sections). Create `config/env.php`, `config/env.example.php`, `.gitignore`, `database/lib/Migrator.php`, `database/migrate.php`, `database/tools/env_check.php`, `database/tools/diagnose.php`, `database/README.md`, empty `database/migrations/`, `tests/run_all.php` (§P0.3–P0.6).
4. Run `php database/tools/env_check.php` and paste its output into the changelog.
5. Run `php database/tools/diagnose.php --phase=0` → save to `changelogs/diagnostics-before.txt` (this is the "before" snapshot).
6. **Prove the old tree is dead, then delete it (single tree, D11):**
   1. `test -f public/login.php && ! test -f login.php` — the login page exists only in `public/`, so the web root can only be `panel/public`.
   2. `diff -rq api public/api; diff -rq modules public/modules` — the only differences must be `require` depth and upload paths. Paste the output into the changelog.
   3. `grep -rnE "(require|include)(_once)?" public includes config | grep -E "\.\./(api|modules)/"` must print nothing.
   4. Only then delete the items marked ← DELETED in §P0.1. Add the header comment to `database/schema.sql` (no other change to that file).
7. Smoke test (needs DB): `php -S 127.0.0.1:8081 -t public` in the background; `curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8081/login.php` → 200; stop the server. (The built-in server ignores `.htaccess`; that is expected.)
8. Tell Pouya in the changelog: **"Laragon/XAMPP vhost Document Root must be `…/panel/public`."** (`config/`, `includes/`, `database/schema.sql` with the seed hash and every `.md` file are downloadable if the root is `…/panel`.)

## P0.3 `config/env.php` and `config/env.example.php`

Reference implementation:

```php
<?php
// Loads config/env.local.php (git-ignored) into the process environment.
// Real environment variables (Apache SetEnv, CI, systemd) always win.
function load_local_env(): void {
    static $done = false;
    if ($done) { return; }
    $done = true;
    $file = __DIR__ . '/env.local.php';
    if (!is_file($file)) { return; }
    $vars = require $file;
    if (!is_array($vars)) { return; }
    foreach ($vars as $k => $v) {
        if (!is_string($k) || !preg_match('/^[A-Z][A-Z0-9_]*$/', $k)) { continue; }
        if (getenv($k) === false) {
            putenv($k . '=' . (string)$v);
            $_ENV[$k] = (string)$v;
        }
    }
}
```

`config/env.example.php` (committed; never real secrets):

```php
<?php
// Copy to config/env.local.php (git-ignored) and edit. Production values come from REAL environment
// variables (Apache SetEnv / PHP-FPM env[] / systemd / CI secrets), never from a committed file.
return [
    'APP_ENV'  => 'local',            // anything other than 'local' = production rules (fail closed)
    'DB_HOST'  => '127.0.0.1',
    'DB_NAME'  => 'business_panel',
    'DB_USER'  => 'root',             // local only; production must use a dedicated DML-only user
    'DB_PASS'  => '',
    // 'DB_MIGRATE_USER' => 'panel_migrator',   // used by database/migrate.php only (DDL rights)
    // 'DB_MIGRATE_PASS' => '',
    // 'TRUSTED_PROXIES' => '203.0.113.10',      // comma-separated; only these may set X-Forwarded-For / CF-Connecting-IP
    // 'APP_SECRET_KEY'  => '',                  // P6: php database/tools/gen_secret_key.php
];
```
## P0.4 `database/lib/Migrator.php` and `database/migrate.php`

`Migrator` (final class, constructor takes `PDO`; schema = `SELECT DATABASE()`). All methods query `information_schema` with `TABLE_SCHEMA = ?`:

| Method | Returns |
|---|---|
| `tableExists($t)`, `columnExists($t,$c)`, `indexExists($t,$index)` (`STATISTICS`) | bool |
| `fkExists($t,$name)` / `checkExists($t,$name)` | bool (`TABLE_CONSTRAINTS`, type `FOREIGN KEY` / `CHECK`) |
| `fkRule($t,$name)` | `DELETE_RULE` from `REFERENTIAL_CONSTRAINTS` or null |
| `columnType($t,$c)` | `COLUMN_TYPE` string or null (e.g. `enum('active','suspended')`) |
| `isNullable($t,$c)` | bool |
| `collation($t,$c)` | `COLLATION_NAME` or null |
| `count($sql, $params=[])` | int (first column of first row) |
| `ensureIndex($t,$name,$colsSql, $unique=false)` | adds `ALTER TABLE … ADD [UNIQUE] INDEX` only if missing |
| `replaceForeignKey($t,$name,$col,$refTable,$refCol,$onDelete)` | if exists with same rule ⇒ nothing; if exists with another rule ⇒ `DROP FOREIGN KEY` then add; if missing ⇒ add (always `ON DELETE <rule>`) |

`migrate.php` behaviour: CLI only; builds its **own** PDO from `DB_HOST`, `DB_NAME`, `DB_MIGRATE_USER ?: DB_USER ?: 'root'`, `DB_MIGRATE_PASS ?: DB_PASS ?: ''`; takes `GET_LOCK('panel_migrate', 5)`; creates `schema_migrations`; runs pending files in filename order; each file `return`s `['description' => string, 'up' => function (PDO $pdo, Migrator $m): void]`; a migration that throws is **not** recorded and the runner exits 1 (re-running is safe because every step is guarded); `--status` lists applied/pending.

Reference implementation of `database/lib/Migrator.php`:

```php
<?php
// Guard helpers over information_schema. Every method is read-only except ensureIndex() and replaceForeignKey().
// Identifiers are validated, never trusted: only [A-Za-z0-9_] is accepted.
final class Migrator
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function tableExists(string $table): bool
    {
        return $this->scalar("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?", [$table]) > 0;
    }

    public function columnExists(string $table, string $column): bool
    {
        return $this->scalar("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?", [$table, $column]) > 0;
    }

    public function indexExists(string $table, string $index): bool
    {
        return $this->scalar("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?", [$table, $index]) > 0;
    }

    public function fkExists(string $table, string $name): bool
    {
        return $this->scalar("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'", [$table, $name]) > 0;
    }

    public function checkExists(string $table, string $name): bool
    {
        return $this->scalar("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'CHECK'", [$table, $name]) > 0;
    }

    /** DELETE_RULE ('CASCADE', 'SET NULL', 'RESTRICT', 'NO ACTION') or null when the FK does not exist. */
    public function fkRule(string $table, string $name): ?string
    {
        $v = $this->scalar("SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?", [$table, $name]);
        return $v === null || $v === false ? null : (string)$v;
    }

    /** COLUMN_TYPE such as "enum('active','suspended')" or "varchar(512)", or null. */
    public function columnType(string $table, string $column): ?string
    {
        $v = $this->scalar("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?", [$table, $column]);
        return $v === null || $v === false ? null : strtolower((string)$v);
    }

    public function isNullable(string $table, string $column): bool
    {
        return $this->scalar("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?", [$table, $column]) === 'YES';
    }

    public function collation(string $table, string $column): ?string
    {
        $v = $this->scalar("SELECT COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?", [$table, $column]);
        return $v === null || $v === false ? null : (string)$v;
    }

    /** First column of the first row, as int (for COUNT(*) preflights). */
    public function count(string $sql, array $params = []): int
    {
        return (int)$this->scalar($sql, $params);
    }

    /** $colsSql example: "(ip_address, attempted_at)". Adds the index only when no index with that name exists. */
    public function ensureIndex(string $table, string $name, string $colsSql, bool $unique = false): void
    {
        $this->ident($table);
        $this->ident($name);
        if ($this->indexExists($table, $name)) {
            return;
        }
        $this->pdo->exec('ALTER TABLE `' . $table . '` ADD ' . ($unique ? 'UNIQUE ' : '') . 'INDEX `' . $name . '` ' . $colsSql);
    }

    /** Single-column FK. Same rule ⇒ nothing; other rule ⇒ drop + add; missing ⇒ add. */
    public function replaceForeignKey(string $table, string $name, string $col, string $refTable, string $refCol, string $onDelete): void
    {
        foreach ([$table, $name, $col, $refTable, $refCol] as $id) {
            $this->ident($id);
        }
        $rule = strtoupper($onDelete);
        if (!in_array($rule, ['RESTRICT', 'CASCADE', 'SET NULL', 'NO ACTION'], true)) {
            throw new InvalidArgumentException('Unsupported ON DELETE rule: ' . $onDelete);
        }
        $current = $this->fkRule($table, $name);
        if ($current === $rule) {
            return;
        }
        if ($current !== null) {
            $this->pdo->exec('ALTER TABLE `' . $table . '` DROP FOREIGN KEY `' . $name . '`');
        }
        $this->pdo->exec('ALTER TABLE `' . $table . '` ADD CONSTRAINT `' . $name . '` FOREIGN KEY (`' . $col . '`) REFERENCES `' . $refTable . '` (`' . $refCol . '`) ON DELETE ' . $rule);
    }

    private function scalar(string $sql, array $params = [])
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $v = $stmt->fetchColumn();
        return $v === false ? null : $v;
    }

    private function ident(string $id): void
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $id)) {
            throw new InvalidArgumentException('Bad identifier: ' . $id);
        }
    }
}
```

Reference implementation of `database/migrate.php`:

```php
<?php
// Applies pending migrations in filename order.   php database/migrate.php [--status]
// Uses DB_MIGRATE_USER / DB_MIGRATE_PASS (DDL rights) when set, otherwise DB_USER / DB_PASS.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/lib/Migrator.php';
load_local_env();

$host = getenv('DB_HOST') ?: '127.0.0.1';
$name = getenv('DB_NAME') ?: 'business_panel';
$user = getenv('DB_MIGRATE_USER') ?: (getenv('DB_USER') ?: 'root');
$pass = getenv('DB_MIGRATE_PASS') !== false ? getenv('DB_MIGRATE_PASS') : (getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("SET time_zone = '+03:30'");
} catch (PDOException $e) {
    fwrite(STDERR, "Cannot connect to database '$name' as '$user': " . $e->getMessage() . "\n");
    exit(1);
}

// Two runners at once (two terminals, CI + a human) must not interleave DDL.
if ((int)$pdo->query("SELECT GET_LOCK('panel_migrate', 5)")->fetchColumn() !== 1) {
    fwrite(STDERR, "Another migration run holds the lock. Try again.\n");
    exit(1);
}

$pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(100) NOT NULL PRIMARY KEY,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=ascii");

$applied = $pdo->query("SELECT version FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN);
$files = glob(__DIR__ . '/migrations/*.php') ?: [];
sort($files, SORT_STRING);

if (in_array('--status', $argv, true)) {
    foreach ($files as $file) {
        $v = basename($file, '.php');
        echo (in_array($v, $applied, true) ? '[applied] ' : '[pending] ') . $v . "\n";
    }
    exit(0);
}

$migrator = new Migrator($pdo);
$ran = 0;
foreach ($files as $file) {
    $version = basename($file, '.php');
    if (in_array($version, $applied, true)) { continue; }
    $def = require $file;
    if (!is_array($def) || !isset($def['up']) || !is_callable($def['up'])) {
        fwrite(STDERR, "Migration $version is malformed.\n");
        exit(1);
    }
    echo '→ ' . $version . ': ' . ($def['description'] ?? '') . "\n";
    try {
        $def['up']($pdo, $migrator);
        $pdo->prepare("INSERT INTO schema_migrations (version) VALUES (?)")->execute([$version]);
        $ran++;
    } catch (Throwable $e) {
        // Not recorded: every step is guarded, so the same command can simply be run again after the cause is fixed.
        fwrite(STDERR, "FAILED $version: " . $e->getMessage() . "\n");
        exit(1);
    }
}
echo $ran === 0 ? "Nothing to migrate.\n" : "Done: $ran migration(s) applied.\n";
```
## P0.5 `database/tools/diagnose.php` — the diagnostic suite

CLI. Connects with the **app** credentials (`getPDO()` from `config/database.php`; read-only queries only). Each check has `id`, `since` (phase), `title`, `sql`, and an expectation. Output one line per check: `PASS | FAIL | WARN | INFO | SKIP`. `--phase=N` runs only checks with `since <= N`; a check whose table/column does not exist yet is `SKIP`, not FAIL. Exit code 1 if any FAIL.

| ID | since | Check (expect) | SQL / rule |
|---|---|---|---|
| D01 | 1 | impossible permission rows → **0 rows** | `SELECT * FROM panel_user_permissions WHERE (can_edit=1 AND can_view=0) OR (can_delete=1 AND can_edit=0)` |
| D02 | 1 | staff with no permission row (excluding removed) → **0 rows** | `SELECT u.id,u.username FROM panel_users u LEFT JOIN panel_user_permissions p ON p.panel_user_id=u.id WHERE u.role='staff' AND u.status<>'removed' GROUP BY u.id HAVING COUNT(p.id)=0` |
| D03 | 1 | seed password still in use → **WARN** if rows | `SELECT id,username FROM panel_users WHERE password_hash='$2y$10$cnvXc0McHVEK5Nml0ZvMwepHQ8jERISPDuFgn9BuwBsO6CZ5utTO.'` |
| D04 | 1 | CHECK `chk_pup_logic` present → PASS; absent → WARN on MySQL 5.7, FAIL otherwise | `Migrator::checkExists` |
| D05 | 2 | usernames violating `^[A-Za-z0-9_.-]{3,30}$` (excluding removed) → **WARN** with ids (do not auto-fix) | PHP regex over `SELECT id,username FROM panel_users WHERE status<>'removed'` |
| D06 | 2 | `login_attempts` / `activity_logs` size + oldest row → **INFO** | `SELECT COUNT(*), MIN(attempted_at) …` |
| D07 | 3 | canvases with inconsistent type/template → **0 rows** | `SELECT id,buildable_type,template_id FROM builder_canvases WHERE (buildable_type='template' AND template_id IS NULL) OR (buildable_type='page' AND template_id IS NOT NULL)` |
| D08 | 3 | block whose parent is in another canvas → **0 rows** | `SELECT b.id,b.canvas_id,p.canvas_id AS parent_canvas FROM builder_blocks b JOIN builder_blocks p ON p.id=b.parent_id WHERE p.canvas_id<>b.canvas_id` |
| D09 | 3 | duplicate `sort_order` among siblings → **0 rows** | `SELECT canvas_id,parent_id,sort_order,COUNT(*) n FROM builder_blocks GROUP BY canvas_id,parent_id,sort_order HAVING n>1` |
| D10 | 3 | canvases whose root count ≠ 3 → **INFO** (0 roots is legal; load supplies the default tree) | `SELECT c.id,COUNT(b.id) roots FROM builder_canvases c LEFT JOIN builder_blocks b ON b.canvas_id=c.id AND b.parent_id IS NULL GROUP BY c.id HAVING roots<>3` |
| D11 | 2 / 3 / 5 | FK delete rules equal the expected map → **PASS only if every FK whose phase has been reached matches** | map (phase): `fk_builder_canvases_creator=RESTRICT` (2), `fk_media_uploader=RESTRICT` (2), `fk_logs_user=SET NULL` (2), `fk_builder_canvases_template=CASCADE` (3), `fk_builder_blocks_parent_same_canvas=CASCADE` (3), `fk_bmr_canvas=CASCADE` (3), `fk_bmr_media=RESTRICT` (3), `fk_settings_template=RESTRICT` (5). A check whose FK does not exist before its phase is `SKIP`. |
| D12 | 4 | duplicate `(purpose,sha256)` impossible → **0 rows** | `SELECT purpose,sha256,COUNT(*) n FROM media_library WHERE sha256 IS NOT NULL GROUP BY purpose,sha256 HAVING n>1` |
| D13 | 4 | `media_library` rows with NULL `sha256` → **INFO** count (run the backfill tool to reduce) | — |
| D14 | 4 | licence certificates still stored under `/uploads/` → **0** | `SELECT COUNT(*) FROM licenses WHERE certificate_file LIKE '/uploads/%'` |
| D15 | 5 | legacy tables gone → **0 of** `pages,page_blocks,articles,article_blocks,article_categories,media_usage` | `tableExists` |
| D16 | 5 | `templates.is_active` absent; `site_settings.active_template_id` NULL or points to an existing installed template | — |
| D17 | 5 | `template_categories` has no slug `all` | — |
| D18 | 6 | plaintext secrets → **0**: `marketplace_connections.api_key/api_secret` not NULL and not `LIKE 'v1:%'`; `licenses.credentials` JSON key `api_key` present and not `v1:`-prefixed | PHP check |
| D19 | 2 | `LOGIN_DUMMY_HASH` (config.php) is a valid bcrypt hash → `password_get_info(LOGIN_DUMMY_HASH)['algoName']==='bcrypt'` and cost 10; `SEED_PASSWORD_HASH` likewise | `config.php` constants |
| D20 | 2 | removed staff keep no permission rows and no avatar → **0 rows** | `SELECT u.id FROM panel_users u WHERE u.status='removed' AND (u.avatar_path IS NOT NULL OR EXISTS (SELECT 1 FROM panel_user_permissions p WHERE p.panel_user_id=u.id))` |
| D21 | 3 | `builder_blocks.block_uid` collation is `ascii_bin`; `builder_canvases.version` exists → **PASS** | `Migrator::collation`, `columnExists` |
| D22 | 4 | `site_settings.logo_path` / `favicon_path` / every `panel_users.avatar_path` that points to `/uploads/` is registered in `media_library` → **INFO** with the unregistered paths (run `backfill_files.php`) | PHP set difference |

## P0.6 `tests/run_all.php`

CLI runner: executes every `tests/*_test.php` with `PHP_BINARY`, prints one line per file, exit code 1 if any file exits non-zero. Each test file is plain PHP that `exit(1)` on failure (no PHPUnit).

## P0.7 Must NOT do (Phase 0)

| Forbidden |
|---|
| Running any migration other than via `migrate.php`; creating migrations in this phase. |
| Editing `schema.sql` beyond the header comment. |
| Deleting anything not listed in §P0.1, or deleting before the backup in step 1 exists. |
| Reading `.env` style files from inside `public/`. |

## P0.8 Gate

`php -l` on new files ✔ · `php tests/run_all.php` ✔ (nothing to run yet is fine) · `diagnose.php --phase=0` runs · both old trees gone · login page returns 200 on the built-in server.

---

# PHASE 1 — Access control core, session, safe quick fixes (code + migration `0001`)

**Goal:** fix F1–F9, F11, M6-1/2/3/5, M7, M8 (invite + profile email), M9 (offset + global handler + activate leak), M14, M15, L4, L9, L12, L8 (headers only), C2 (text removal). No schema change except migration `0001` (CHECK + data normalisation), run **last** in this phase.

## P1.1 File map

```
panel/
├── config/
│   ├── config.php                     M  env + APP_ENV + timezone + constants + global exception handler (§P1.2)
│   └── database.php                   M  SET time_zone; never prints the PDO message (§P1.2)
├── includes/
│   ├── session.php                    N  session_boot() — the ONLY place that starts a session
│   ├── permission_rules.php           N  Pure functions (no DB/session): normalizePermissionFlags, permissionFlagsAllow, normalizeScriptPath, resolvePageGuard
│   ├── access_map.php                 N  Central page-guard map (default-deny)
│   ├── page_boot.php                  N  session + requireLogin + enforcePageGuard + security headers, BEFORE any output
│   ├── functions.php                  M  + isApiRequest(), getClientIp() (trusted proxies), send_security_headers(), validatePasswordPolicy(), sanitizeClientFileName(); jsonResponse adds nosniff
│   ├── csrf.php                       M  session_start() → session_boot()
│   ├── auth.php                       M  uses session_boot(); requireLogin() re-validates + idle timeout; isOwner() DB-only; authFail(); currentUser($refresh)
│   ├── permissions.php                M  loadPermissionMap() cached; hasPermission(); denyAccess(); requireOwner(); enforcePageGuard()
│   ├── header.php                     M  requires page_boot.php instead of its own requireLogin()
│   └── sidebar.php                    M  settings group (3 links); removes bottom «تنظیمات پنل» button
├── database/migrations/0001_permissions_integrity.php   N
├── tests/
│   ├── permission_rules_test.php      N
│   └── functions_test.php             N
└── public/
    ├── login.php                      M  session_boot(); remove the password hint; show timeout/invalid notices; send_security_headers()
    ├── uploads/.htaccess              M  M15 rewrite (§P1.9)
    ├── api/staff/invite.php           M  seeds dashboard.view; validation; precise errors; log
    ├── api/staff/save_permissions.php M  rewritten body (§P1.7)
    ├── api/staff/set_status.php       N  suspend / activate
    ├── api/staff/toggle_theme.php     M  POST-only + CSRF
    ├── api/builder/load.php           M  templates.view for template canvases
    ├── api/templates/activate.php     M  stops leaking $e->getMessage() (M9)
    ├── modules/settings/staff.php     M  suspend button, cascade JS, temp delete text, list excludes removed + explicit columns
    ├── modules/settings/general.php   M  read-only for settings.view; 403 before any output (upload code untouched)
    ├── modules/settings/profile.php   M  login-only; email validation; duplicate catch; alert class
    ├── modules/media/index.php        M  client escaping; page>=1; JSON hex flags (upload logic untouched)
    └── modules/templates/index.php    M  page>=1 only
```

**Change to the original P1 plan (author's completion):** `includes/page_boot.php` is new. The original plan put `enforcePageGuard()` inside `header.php`, but `header.php` prints HTML, so a page that must answer a POST with a redirect, a 403 or `session_regenerate_id()` could only do it *after* output had started (headers-already-sent). `page_boot.php` runs the same checks before output; `header.php` simply requires it, so every existing page keeps working and the five-line probe in the gate still works.

## P1.2 `config/config.php` and `config/database.php`

`config/config.php` after this phase (reference implementation — the existing `define`s are kept **verbatim**; P4/P5 clean them later; the new block is at the bottom, `env.php` is required at the very top):

```php
<?php
require_once __DIR__ . '/env.php';
load_local_env();

// مسیرها
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/vendor/autoload.php';
define('PUBLIC_PATH', BASE_PATH . '/public');
define('UPLOAD_BASE', PUBLIC_PATH . '/uploads');
define('UPLOAD_MEDIA', UPLOAD_BASE . '/media');
define('UPLOAD_PRODUCTS', UPLOAD_BASE . '/products');
define('UPLOAD_TEMPLATES', UPLOAD_BASE . '/templates');
define('UPLOAD_AVATARS', UPLOAD_BASE . '/avatars');
define('UPLOAD_LICENSES', UPLOAD_BASE . '/licenses');

// محدودیت آپلود
define('MAX_IMAGE_SIZE', 5 * 1024 * 1024); // 5MB
define('MAX_VIDEO_SIZE', 50 * 1024 * 1024); // 50MB
define('ALLOWED_IMAGE_EXTS', ['jpg','jpeg','png','webp']);
define('ALLOWED_IMAGE_MIMES', ['image/jpeg','image/png','image/webp']);
define('ALLOWED_VIDEO_EXTS', ['mp4','webm']);
define('ALLOWED_VIDEO_MIMES', ['video/mp4','video/webm']);
define('ALLOWED_TEMPLATE_EXTS', ['zip']);

// تنظیمات امنیتی
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCK_MINUTES', 15);
define('CSRF_TOKEN_NAME', 'csrf_token');

// تنظیمات سایت
define('SITE_CHARSET', 'utf8mb4');

// ===== P1: environment, time zone, session, error handling =====
// Any APP_ENV value other than exactly 'local' is treated as production (fail safe).
define('APP_ENV', (getenv('APP_ENV') ?: 'local') === 'local' ? 'local' : 'production');
define('APP_TIMEZONE', 'Asia/Tehran');
define('DB_TIMEZONE_OFFSET', '+03:30');
date_default_timezone_set(APP_TIMEZONE);
define('SESSION_IDLE_SECONDS', 3600);
define('LOGIN_MAX_ATTEMPTS_PER_IP', 20);
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_MAX_BYTES', 72);
define('TRUSTED_PROXIES', array_values(array_filter(array_map('trim', explode(',', (string)(getenv('TRUSTED_PROXIES') ?: ''))))));
ini_set('log_errors', '1');
if (APP_ENV === 'production') { ini_set('display_errors', '0'); }
set_exception_handler(function (Throwable $e): void {
    error_log('Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) { http_response_code(500); }
    if (function_exists('isApiRequest') && isApiRequest()) {
        if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); }
        echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور'], JSON_UNESCAPED_UNICODE);
    } else {
        if (!headers_sent()) { header('Content-Type: text/html; charset=utf-8'); }
        echo '<h1 style="text-align:center;margin-top:50px;font-family:tahoma">خطای داخلی سرور</h1>';
        if (APP_ENV === 'local') { echo '<pre dir="ltr">' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>'; }
    }
});
```

Notes: any `APP_ENV` other than exactly `local` is treated as production (fail safe). `LOGIN_MAX_ATTEMPTS` (5) and `LOGIN_LOCK_MINUTES` (15) already exist and become used in P2.

`config/database.php` after this phase (reference implementation; P6 replaces it again with the fail-closed version):

```php
<?php
require_once __DIR__ . '/config.php';

function getPDO(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $db   = getenv('DB_NAME') ?: 'business_panel';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';
    $charset = 'utf8mb4';
    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("SET time_zone = '" . DB_TIMEZONE_OFFSET . "'");
    } catch (PDOException $e) {
        // The PDO message can contain host/user names: log it, never print it.
        error_log('DB connection failed: ' . $e->getMessage());
        failDatabase();
    }
    return $pdo;
}

/** Never returns. Generic message only. */
function failDatabase(): void {
    if (!headers_sent()) { http_response_code(500); }
    if (function_exists('isApiRequest') && isApiRequest()) {
        if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); }
        echo json_encode(['success' => false, 'message' => 'خطا در اتصال به پایگاه داده'], JSON_UNESCAPED_UNICODE);
    } else {
        if (!headers_sent()) { header('Content-Type: text/plain; charset=utf-8'); }
        echo 'خطا در اتصال به پایگاه داده';
    }
    exit;
}
```

## P1.3 `includes/session.php`

```php
<?php
function session_boot(): void {
    if (session_status() !== PHP_SESSION_NONE) { return; }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => $secure,
        'httponly' => true, 'samesite' => 'Strict',
    ]);
    session_start();
}
```
`grep -rn "session_start" public includes` must afterwards show **only** `includes/session.php`.

## P1.4 `includes/functions.php` additions

Do **not** change any existing function except (a) `jsonResponse()` — add `header('X-Content-Type-Options: nosniff');` before the `echo`; (b) `getClientIp()` — **delete the old one-line version**, the new one below replaces it. Append the following (reference implementation, unit-tested; the file also contains the P2 function `hashPassword` which you add in P2, not now):

```php
<?php
// ---- P1 additions to includes/functions.php (append; shown here as a standalone file for testing) ----

function isApiRequest(): bool {
    return (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false)
        || (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false)
        || (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false);
}

function getClientIp(): string {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    $ip = filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
    $trusted = defined('TRUSTED_PROXIES') ? TRUSTED_PROXIES : [];
    if ($trusted && in_array($ip, $trusted, true)) {
        $cf = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
        if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP)) { return $cf; }
        $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($xff !== '') {
            foreach (array_reverse(array_map('trim', explode(',', $xff))) as $cand) {
                if (filter_var($cand, FILTER_VALIDATE_IP) && !in_array($cand, $trusted, true)) { return $cand; }
            }
        }
    }
    return $ip;
}

function send_security_headers(): void {
    if (headers_sent()) { return; }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
}

/** Returns a Persian error message, or null when the password satisfies the policy. Length is measured in bytes (bcrypt limit). */
function validatePasswordPolicy(string $pw): ?string {
    $min = defined('PASSWORD_MIN_LENGTH') ? PASSWORD_MIN_LENGTH : 8;
    $max = defined('PASSWORD_MAX_BYTES') ? PASSWORD_MAX_BYTES : 72;
    $len = strlen($pw);
    if ($len < $min || $len > $max) {
        return 'رمز عبور باید بین ۸ تا ۷۲ کاراکتر باشد';
    }
    return null;
}

/** Display-only file name from the client: no directory part, no control or markup characters, max 255 chars. */
function sanitizeClientFileName(string $name): string {
    $name = preg_replace('~^.*[\\\\/]~u', '', $name);
    if ($name === null) { return 'file'; }
    $name = preg_replace('~[\x00-\x1F\x7F<>"\'`\\\\/]~u', '', $name);
    if ($name === null) { return 'file'; }
    $name = mb_substr(trim($name), 0, 255, 'UTF-8');
    return $name === '' ? 'file' : $name;
}
```

`X-Forwarded-For` is honoured **only** when `REMOTE_ADDR` is a configured trusted proxy (env `TRUSTED_PROXIES`, comma-separated, empty by default), and the rightmost non-proxy address wins. `validatePasswordPolicy()` measures **bytes** because bcrypt stops at 72 bytes.

## P1.5 `includes/auth.php`, `permission_rules.php`, `access_map.php`, `permissions.php`

**`permission_rules.php`** (pure — no `require`, no DB, no `$_SESSION`):
Reference implementation (unit-tested):

```php
<?php
// Pure rules for panel roles & permissions. No side effects. Unit-tested in tests/permission_rules_test.php.

/** Hierarchy: delete ⇒ edit ⇒ view. Anything below a missing level is cleared. */
function normalizePermissionFlags(bool $view, bool $edit, bool $delete): array {
    $v = $view;
    $e = $v && $edit;
    $d = $e && $delete;
    return ['view' => $v, 'edit' => $e, 'delete' => $d];
}

/** $flags is the result of normalizePermissionFlags() (or []). Unknown action ⇒ false. */
function permissionFlagsAllow(array $flags, string $action): bool {
    switch ($action) {
        case 'view':   return !empty($flags['view']);
        case 'edit':   return !empty($flags['edit']);
        case 'delete': return !empty($flags['delete']);
        default:       return false;
    }
}

/** '/x/modules/media/index.php' → '/modules/media/index.php'; anything not under /modules/ → null. */
function normalizeScriptPath(string $scriptName): ?string {
    $p = str_replace('\\', '/', $scriptName);
    if (preg_match('#(/modules/[A-Za-z0-9_\-]+(?:/[A-Za-z0-9_\-]+)*\.php)$#', $p, $m)) {
        return $m[1];
    }
    return null;
}

/** Returns the rule array for a path, or ['deny' => true] when the path is null or not in the map. */
function resolvePageGuard(?string $path, array $map): array {
    if ($path === null || !isset($map[$path]) || !is_array($map[$path])) {
        return ['deny' => true];
    }
    return $map[$path];
}
```

**`access_map.php`** — `return [...]`, loaded with `require`:
```php
<?php
return [
    '/modules/dashboard/index.php'  => ['module' => 'dashboard', 'action' => 'view'],
    '/modules/pages/index.php'      => ['module' => 'pages',     'action' => 'view'],
    '/modules/articles/index.php'   => ['module' => 'articles',  'action' => 'view'],
    '/modules/templates/index.php'  => ['module' => 'templates', 'action' => 'view'],
    '/modules/sales/index.php'      => ['module' => 'sales',     'action' => 'view'],
    '/modules/media/index.php'      => ['module' => 'media',     'action' => 'view'],
    '/modules/licenses/index.php'   => ['module' => 'licenses',  'action' => 'view'],
    '/modules/settings/general.php' => ['module' => 'settings',  'action' => 'view'],
    '/modules/settings/profile.php' => ['login_only' => true],
    '/modules/settings/staff.php'   => ['owner_only' => true],
];
```

**`auth.php`** — replace the inline session block with `require_once` of `session.php` and `permission_rules.php`, then `session_boot();`. Keep `isLoggedIn()`, `loginUser()`, `logoutUser()`, `logActivity()` unchanged in this phase (P2 edits them). Change:

```php
function requireLogin(): void {
    if (!isLoggedIn()) { authFail('expired'); }
    $u = currentUser();
    if (!$u || ($u['status'] ?? '') !== 'active') {
        $reason = ($u && ($u['status'] ?? '') === 'suspended') ? 'suspended' : 'invalid';
        logoutUser();
        authFail($reason);
    }
    if (time() - (int)($_SESSION['last_seen'] ?? time()) > SESSION_IDLE_SECONDS) {
        logoutUser();
        authFail('timeout');
    }
    $_SESSION['last_seen'] = time();
}
/** Never returns. API → JSON 401; page → redirect to /login.php[?error=…]. */
function authFail(string $reason): void {
    if (isApiRequest()) {
        jsonResponse(['success' => false, 'message' => 'نشست شما منقضی شده است. دوباره وارد شوید.'], 401);
    }
    header('Location: /login.php' . ($reason === 'expired' ? '' : '?error=' . $reason));
    exit;
}
function isOwner(): bool {
    $u = currentUser();
    return $u !== null && ($u['role'] ?? '') === 'owner' && ($u['status'] ?? '') === 'active';
}
```
`currentUser()` gains an optional parameter: `function currentUser(bool $refresh = false): ?array` — when `$refresh` is true it discards the static cache and re-reads (the old profile page "refresh" silently returned the stale cached row). Keep `SELECT *` until P2.

**`permissions.php`** — keep `require_once auth.php`, add `require_once permission_rules.php`; keep names/signatures of `hasPermission`, `requirePermission`, `getUserPermissions` (the last is untouched). Add:

- `loadPermissionMap(int $userId): array` — one query, `static $cache = []` keyed by user id:
  ```sql
  SELECT p.module_key, pup.can_view, pup.can_edit, pup.can_delete
  FROM permissions p
  LEFT JOIN panel_user_permissions pup ON pup.permission_id = p.id AND pup.panel_user_id = ?
  ```
  Each value = `normalizePermissionFlags((bool)can_view, (bool)can_edit, (bool)can_delete)` (NULL from the LEFT JOIN ⇒ false).
- `hasPermission($moduleKey,$action='view')`: no user ⇒ false; `status !== 'active'` ⇒ false; `role === 'owner'` ⇒ true; else `permissionFlagsAllow(loadPermissionMap((int)$user['id'])[$moduleKey] ?? [], $action)`. Remove the `match` and the two old queries.
- `denyAccess(): void` — JSON 403 `{"success":false,"message":"دسترسی غیر مجاز"}` when `isApiRequest()`; otherwise `http_response_code(403)` + the **same HTML body** now in `requirePermission()` (byte-identical), then `exit`. `requirePermission()` becomes `if (!hasPermission($moduleKey,$action)) { denyAccess(); }`.
- `requireOwner(): void` — `if (!isOwner()) { denyAccess(); }`.
- `enforcePageGuard(): void`:
  ```php
  function enforcePageGuard(): void {
      static $map = null;
      if ($map === null) { $map = require __DIR__ . '/access_map.php'; }
      $rule = resolvePageGuard(normalizeScriptPath($_SERVER['SCRIPT_NAME'] ?? ''), $map);
      if (!empty($rule['deny']))       { denyAccess(); }
      if (!empty($rule['login_only'])) { return; }
      if (!empty($rule['owner_only'])) { requireOwner(); return; }
      requirePermission((string)($rule['module'] ?? ''), (string)($rule['action'] ?? 'view'));
  }
  ```
**`page_boot.php`** (new; reference implementation — see the file map note):

```php
<?php
// Everything a module page needs BEFORE it prints a single byte:
// session, login check, central page guard, security headers.
// header.php requires this file; a page that must handle a POST (redirect, session_regenerate_id,
// 403) before output requires it first. Safe to require twice.
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/permissions.php';

if (!defined('PAGE_BOOT_DONE')) {
    define('PAGE_BOOT_DONE', true);
    send_security_headers();
    requireLogin();
    enforcePageGuard();
}
```

`header.php`: replace its first six lines (the four `require_once` lines, `requireLogin();` and nothing else) with `require_once __DIR__ . '/page_boot.php';` and keep `$user = currentUser();` and everything after it unchanged. `csrf.php`: replace its `session_start()` line with `require_once __DIR__ . '/session.php'; session_boot();`. Existing inline `requirePermission()` calls in modules stay (harmless redundancy), except where this phase says otherwise.

## P1.6 `sidebar.php`

Main `$menuItems` loop unchanged (now cheap). After the `foreach`, still inside `<nav>`, render the settings group: heading `<div class="text-white-50 small px-3 mt-3 mb-1">تنظیمات پنل</div>` (exact string; Persian ی only) — then, with the same link markup/classes as menu items (active when `$_SERVER['SCRIPT_NAME']` ends with the URL):

| Label | URL | Icon | Shown when |
|---|---|---|---|
| `پروفایل من` | `/modules/settings/profile.php` | `bi-person-circle` | always |
| `تنظیمات عمومی` | `/modules/settings/general.php` | `bi-sliders` | `hasPermission('settings','view')` |
| `کاربران و دسترسی‌ها` | `/modules/settings/staff.php` | `bi-people` | `isOwner()` |

Remove the bottom `<a … profile.php … تنظیمات پنل</a>` button; keep the plan box and «خروج از حساب».

## P1.7 Staff APIs (served tree only: `public/api/staff/*`, `require` depth `__DIR__ . '/../../../…'`)

**`invite.php`** — keep the existing guard lines and CSRF line and the existing `password_hash($password, PASSWORD_BCRYPT)` line (P2 switches it to `hashPassword`). Validation order: all fields required (existing message `تمام فیلدها الزامی است`); email filter (existing message `ایمیل نامعتبر`); `username` must match `/^[A-Za-z0-9_.-]{3,30}$/D` **and must not start with `removed-`** (that prefix is reserved for anonymised users, P2) else 400 with `STR_USERNAME_RULE`; `validatePasswordPolicy($password)` else 400 with its message; `mb_strlen($full_name) > 150` or `strlen($email) > 190` ⇒ 400 `درخواست نامعتبر`. Then:
```php
try {
  $pdo->beginTransaction();
  $pdo->prepare("INSERT INTO panel_users (full_name, username, email, password_hash, role) VALUES (?,?,?,?,'staff')")
      ->execute([$full_name, $username, $email, $hash]);
  $newId = (int)$pdo->lastInsertId();
  $pdo->prepare("INSERT INTO panel_user_permissions (panel_user_id, permission_id, can_view, can_edit, can_delete)
                 SELECT ?, id, 1, 0, 0 FROM permissions WHERE module_key = 'dashboard'")->execute([$newId]);
  $pdo->commit();
} catch (PDOException $e) {
  if ($pdo->inTransaction()) { $pdo->rollBack(); }
  if ((int)($e->errorInfo[1] ?? 0) === 1062) { jsonResponse(['success'=>false,'message'=>'نام کاربری یا ایمیل تکراری است'], 400); }
  error_log('staff/invite.php: ' . $e->getMessage());
  jsonResponse(['success'=>false,'message'=>'خطا در ایجاد همکار'], 500);
}
logActivity('staff_invite', 'settings', 'افزودن همکار ' . $newId);
jsonResponse(['success'=>true,'message'=>'همکار با موفقیت ایجاد شد']);
```

**`save_permissions.php`** — keep the first six `require` lines. Behaviour:
1. `requireLogin();` owner-only (`'فقط مالک'`, 403).
2. *Staff target* = row in `panel_users` with `id=?`, `role='staff'`, `status<>'removed'`. Invalid/missing/owner ⇒ 400 `'کاربر نامعتبر'` (same message for all).
3. **GET** `?user_id=N`: validate target; same JSON shape as today (`data` rows `module_key, can_view, can_edit, can_delete`, `COALESCE(...,0)`), each triple passed through `normalizePermissionFlags` and cast to int.
4. Other methods ⇒ 405 `'متد مجاز نیست'`.
5. **POST**: CSRF (as today) → validate target → whitelist = `SELECT id FROM permissions` (ignore every other posted key) → `$posted = $_POST['perm'] ?? []; if (!is_array($posted)) $posted = [];`.
6. Per permission id: `$in = (isset($posted[$pid]) && is_array($posted[$pid])) ? $posted[$pid] : [];` `$f = normalizePermissionFlags(isset($in['view']), isset($in['edit']), isset($in['delete']));` and upsert with the existing `INSERT … ON DUPLICATE KEY UPDATE` (prepare **once** outside the loop).
7. `try { beginTransaction … commit } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error_log(…); jsonResponse(['success'=>false,'message'=>'خطا در ذخیره‌سازی دسترسی‌ها'], 500); }`
8. After commit: `logActivity('staff_permissions','settings','تغییر دسترسی‌های همکار ' . $uid);` then the existing success response.

**`set_status.php`** (new) — same lines/pattern as `invite.php`. Owner-only; POST only (405); CSRF; input `id` (int), `status` ∈ `['active','suspended']` (strict `in_array`) else 400 `'درخواست نامعتبر'`. `SELECT id, role, status FROM panel_users WHERE id=?` → missing, `role!=='staff'` or `status==='removed'` ⇒ 400 `'کاربر نامعتبر'` (SELECT first: `UPDATE` reports 0 rows when unchanged). `UPDATE panel_users SET status=? WHERE id=? AND role='staff'`; `logActivity('staff_status','settings','تغییر وضعیت همکار ' . $id . ' به ' . $status)`; success `'وضعیت همکار تغییر کرد'`; `try/catch Throwable` ⇒ 500 `'خطا در تغییر وضعیت'`.

**`toggle_theme.php`** — add: non-POST ⇒ 405 `'متد مجاز نیست'`; CSRF check using the same line as the other endpoints (`admin.js` already sends `X-CSRF-TOKEN`); require `csrf.php`.

**`builder/load.php`** (walk-up idiom stays; P3 later adds `version` to this same file and shows the whole file) — after `requirePermission('pages','view');` add `templates.view` enforcement: Form 1 (by `canvas_id`): after `$result` is non-null, `if (($result['canvas']['buildable_type'] ?? '') === 'template') { requirePermission('templates','view'); }` before `jsonResponse`; Form 2 (`new=1`): when `$buildableType === 'template'` ⇒ `requirePermission('templates','view')` before responding; Form 3 (template entry): `requirePermission('templates','view');` at the start of the branch. Response shapes unchanged.

**`templates/activate.php`** — change **only** the `catch` block: `if ($pdo->inTransaction()) { $pdo->rollBack(); } error_log('templates/activate.php: ' . $e->getMessage()); jsonResponse(['success'=>false,'message'=>'خطا در فعال‌سازی قالب'], 500);` (the old code sent `$e->getMessage()` — table and FK names — to the browser). The `is_active` statements stay until P5.

## P1.8 Pages / modules

**`staff.php`** — keep everything not listed.
1. List query: `SELECT id, full_name, email, status FROM panel_users WHERE role='staff' AND status<>'removed' ORDER BY id`.
2. Next to the delete button add per row: active ⇒ `<button class="btn btn-sm btn-outline-warning btn-status" data-id="…" data-status="suspended">تعلیق</button>`; otherwise `<button class="btn btn-sm btn-outline-success btn-status" data-id="…" data-status="active">فعال‌سازی</button>`.
3. JS for `.btn-status`: `Swal.fire({title: (target==='suspended' ? 'همکار تعلیق شود؟' : 'همکار فعال شود؟'), icon:'warning', showCancelButton:true, confirmButtonText:'بله', cancelButtonText:'انصراف'})`, then POST `FormData` (`id`, `status`, `csrf_token`) + header `X-CSRF-TOKEN` to `/api/staff/set_status.php`; result via the file's existing `Swal.fire(...).then(()=> j.success&&location.reload())` pattern.
4. **Checkbox cascade** (event delegation on `#permBody`, `change`): checking *delete* ⇒ check *edit* and *view*; checking *edit* ⇒ check *view*; unchecking *view* ⇒ uncheck *edit* and *delete*; unchecking *edit* ⇒ uncheck *delete*.
5. Delete confirm text: replace `'همکار حذف شود؟'` with the **temp delete warning** from §2.3 (P2 replaces it again).
6. Inline `if(!isOwner())…die()` at line 4 stays.

**`general.php`** — the 403 for a forged POST must be sent **before** `header.php` prints anything (after output `http_response_code(403)` is ignored). Replace the first lines of the file with:
```php
<?php
$currentModule='settings'; $pageTitle='تنظیمات عمومی'; $breadcrumb='تنظیمات پنل ‹ عمومی';
require_once __DIR__ . '/../../../includes/page_boot.php';
requirePermission('settings','view');
$canEdit = hasPermission('settings','edit');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$canEdit) { denyAccess(); }
require_once __DIR__ . '/../../../includes/header.php';
```
(the old `requirePermission('settings','edit');` line is deleted). When `!$canEdit`: `disabled` on the four inputs (`site_name`, `site_domain`, `logo`, `favicon`) and no submit button. Do **not** touch upload handling (P4 rewrites this page).

**`profile.php`** — delete line 4 (`requirePermission('settings','view');`; the map marks the page `login_only`). Before the first `UPDATE panel_users`: `if(!filter_var($email, FILTER_VALIDATE_EMAIL)){ $msg='ایمیل نامعتبر است.'; $msgClass='danger'; }` and skip all write work when invalid. Wrap the profile `UPDATE` in `try/catch PDOException`: code 1062 ⇒ `'این ایمیل قبلاً ثبت شده است.'` (danger); others ⇒ `error_log` + `'خطا در ذخیره‌سازی.'` (danger). Introduce `$msgClass='success'` default and render `<div class="alert alert-<?= $msgClass ?>">`; set `'danger'` for `'رمز فعلی اشتباه است.'`. Use `currentUser(true)` for the final refresh. Avatar block, theme handling and password logic are otherwise untouched (P2/P4).

**`media/index.php`** — (a) `$page=max(1,(int)($_GET['page']??1));` (b) add a JS helper `function escapeHtml(s){return String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}` and wrap **every** interpolated value in the `openPreview` template literal (`file_path` in `src=`, `file_name`, `file_size_kb`, `width`, `height`, `created_at`); (c) `json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)`. Nothing else (upload/delete logic is P4).
**`templates/index.php`** — only `$page=max(1,(int)($_GET['page']??1));`.
**`login.php`** — (1) replace the `session_start` guard with `require_once __DIR__ . '/../includes/session.php'; session_boot();`; (2) call `send_security_headers();` before output; (3) **delete the `<p …>نام کاربری: admin — رمز: Admin123!</p>` line**; (4) next to the existing `suspended` notice add two more notices for the redirects `requireLogin()` now produces: `?error=timeout` ⇒ `STR_ERR_TIMEOUT`, `?error=invalid` ⇒ `STR_ERR_INVALID` (same `alert alert-warning` markup). The lock/auth logic is untouched until P2.

## P1.9 `public/uploads/.htaccess` (replace content)

```apache
# Nothing in this folder may ever execute.
<IfModule mod_php.c>
  php_flag engine off
</IfModule>
<IfModule mod_mime.c>
  RemoveHandler .php .phtml .php3
</IfModule>
<FilesMatch "\.(php|phtml|php3|php4|php5|php7|phps|phar)$">
  Require all denied
</FilesMatch>
Options -Indexes -ExecCGI
<IfModule mod_headers.c>
  Header set X-Content-Type-Options "nosniff"
  Header set Content-Security-Policy "default-src 'none'; img-src 'self' data:; media-src 'self'; style-src 'unsafe-inline'; sandbox"
</IfModule>
```
(Nginx: document in `database/README.md` that `.htaccess` is ignored there and `location ~* ^/uploads/.*\.php$ { deny all; }` is required.)

## P1.10 Migration `database/migrations/0001_permissions_integrity.php`

Description: *Permissions: normalise rows, backfill dashboard.view for never-configured staff, add chk_pup_logic.* `up()` (all idempotent):
1. `UPDATE panel_user_permissions SET can_edit=0 WHERE can_view=0 AND can_edit=1;` then `UPDATE panel_user_permissions SET can_delete=0 WHERE can_edit=0 AND can_delete=1;` (order matters).
2. `INSERT INTO panel_user_permissions (panel_user_id, permission_id, can_view, can_edit, can_delete) SELECT u.id, p.id, 1, 0, 0 FROM panel_users u JOIN permissions p ON p.module_key='dashboard' WHERE u.role='staff' AND NOT EXISTS (SELECT 1 FROM panel_user_permissions x WHERE x.panel_user_id=u.id);` — staff who already have rows (even all-zero) are left alone: all-zero rows are a deliberate owner decision.
3. `if (!$m->checkExists('panel_user_permissions','chk_pup_logic')) { ALTER TABLE panel_user_permissions ADD CONSTRAINT chk_pup_logic CHECK ((can_edit = 0 OR can_view = 1) AND (can_delete = 0 OR can_edit = 1)); }` — enforced on MariaDB ≥ 10.2.1 and MySQL ≥ 8.0.16; MySQL 5.7 parses and ignores it (code-level normalisation is the real guarantee).

Reference implementation:

```php
<?php
// Phase 1 — [post-code]: run LAST in P1, after save_permissions.php normalises flags.
return [
    'description' => 'Permissions: normalise rows, backfill dashboard.view for never-configured staff, add chk_pup_logic',
    'up' => function (PDO $pdo, Migrator $m): void {
        // 1) order matters: edit depends on view, delete depends on edit
        $pdo->exec("UPDATE panel_user_permissions SET can_edit = 0 WHERE can_view = 0 AND can_edit = 1");
        $pdo->exec("UPDATE panel_user_permissions SET can_delete = 0 WHERE can_edit = 0 AND can_delete = 1");

        // 2) staff that never had a single row get dashboard.view (staff with rows, even all-zero, are left alone)
        $pdo->exec("INSERT INTO panel_user_permissions (panel_user_id, permission_id, can_view, can_edit, can_delete)
                    SELECT u.id, p.id, 1, 0, 0
                    FROM panel_users u
                    JOIN permissions p ON p.module_key = 'dashboard'
                    WHERE u.role = 'staff'
                      AND NOT EXISTS (SELECT 1 FROM panel_user_permissions x WHERE x.panel_user_id = u.id)");

        // 3) safety net. Enforced on MariaDB >= 10.2.1 and MySQL >= 8.0.16; MySQL 5.7 parses and ignores it.
        if (!$m->checkExists('panel_user_permissions', 'chk_pup_logic')) {
            $pdo->exec("ALTER TABLE panel_user_permissions
                        ADD CONSTRAINT chk_pup_logic CHECK ((can_edit = 0 OR can_view = 1) AND (can_delete = 0 OR can_edit = 1))");
        }
    },
];
```

Run it **after** all P1 code is in place (before the code, the permission modal could still post impossible combinations and the CHECK would turn them into a 500).

## P1.11 Tests

`tests/permission_rules_test.php` (reference implementation, executed by the author):

```php
<?php
require __DIR__ . '/../includes/permission_rules.php';
$fail = 0;
function check(string $label, $actual, $expected): void {
    global $fail;
    if ($actual !== $expected) { $fail++; fwrite(STDERR, "FAIL: $label\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n"); }
}
$all = ['view' => true, 'edit' => true, 'delete' => true];
$none = ['view' => false, 'edit' => false, 'delete' => false];
check('F,T,T', normalizePermissionFlags(false, true, true), $none);
check('T,F,T', normalizePermissionFlags(true, false, true), ['view' => true, 'edit' => false, 'delete' => false]);
check('T,T,T', normalizePermissionFlags(true, true, true), $all);
check('F,F,F', normalizePermissionFlags(false, false, false), $none);
check('T,T,F', normalizePermissionFlags(true, true, false), ['view' => true, 'edit' => true, 'delete' => false]);
check('empty flags view', permissionFlagsAllow([], 'view'), false);
check('unknown action', permissionFlagsAllow($all, 'publish'), false);
check('view allowed', permissionFlagsAllow($all, 'view'), true);
check('edit denied', permissionFlagsAllow(['view' => true, 'edit' => false, 'delete' => false], 'edit'), false);
check('path 1', normalizeScriptPath('/modules/media/index.php'), '/modules/media/index.php');
check('path 2', normalizeScriptPath('/panel/public/modules/settings/staff.php'), '/modules/settings/staff.php');
check('path 3', normalizeScriptPath('C:\\x\\modules\\media\\index.php'), '/modules/media/index.php');
check('path api', normalizeScriptPath('/api/staff/invite.php'), null);
check('path traversal', normalizeScriptPath('/modules/../index.php'), null);
check('path no php', normalizeScriptPath('/modules/media/index.txt'), null);
$map = ['/modules/media/index.php' => ['module' => 'media', 'action' => 'view']];
check('guard null', resolvePageGuard(null, $map), ['deny' => true]);
check('guard unknown', resolvePageGuard('/modules/zz_probe/index.php', $map), ['deny' => true]);
check('guard known', resolvePageGuard('/modules/media/index.php', $map), ['module' => 'media', 'action' => 'view']);
if ($fail > 0) { fwrite(STDERR, "$fail failure(s)\n"); exit(1); }
echo "permission_rules_test: OK\n";
```

`tests/functions_test.php` — reference implementation. The `getClientIp` cases run in child processes because `TRUSTED_PROXIES` is a constant. In the real project `$file` must point at `includes/functions.php` (the sample points at a standalone copy); the two `hashPassword` checks at the end are added in P2.

```php
<?php
// Runs the getClientIp cases in child processes because TRUSTED_PROXIES is a constant.
$fail = 0;
function check(string $label, $actual, $expected): void {
    global $fail;
    if ($actual !== $expected) { $fail++; fwrite(STDERR, "FAIL: $label\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n"); }
}
$file = realpath(__DIR__ . '/../includes/functions.php');   // real project: includes/functions.php
function child(string $file, string $definePrefix, string $server): string {
    $code = $definePrefix . '$_SERVER = ' . $server . '; require ' . var_export($file, true) . '; echo getClientIp();';
    return trim((string)shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code)));
}
check('no trusted proxies ignores XFF', child($file, '', "['REMOTE_ADDR'=>'1.2.3.4','HTTP_X_FORWARDED_FOR'=>'9.9.9.9']"), '1.2.3.4');
check('trusted proxy honours XFF', child($file, "define('TRUSTED_PROXIES',['1.2.3.4']);", "['REMOTE_ADDR'=>'1.2.3.4','HTTP_X_FORWARDED_FOR'=>'9.9.9.9']"), '9.9.9.9');
check('trusted proxy prefers CF header', child($file, "define('TRUSTED_PROXIES',['1.2.3.4']);", "['REMOTE_ADDR'=>'1.2.3.4','HTTP_CF_CONNECTING_IP'=>'8.8.8.8','HTTP_X_FORWARDED_FOR'=>'9.9.9.9']"), '8.8.8.8');
check('spoofed XFF leftmost ignored', child($file, "define('TRUSTED_PROXIES',['1.2.3.4']);", "['REMOTE_ADDR'=>'1.2.3.4','HTTP_X_FORWARDED_FOR'=>'6.6.6.6, 9.9.9.9']"), '9.9.9.9');
check('untrusted remote ignores CF', child($file, "define('TRUSTED_PROXIES',['1.2.3.4']);", "['REMOTE_ADDR'=>'5.5.5.5','HTTP_CF_CONNECTING_IP'=>'8.8.8.8']"), '5.5.5.5');
check('garbage remote', child($file, '', "['REMOTE_ADDR'=>'nonsense']"), '0.0.0.0');

require $file;
check('sanitize: markup removed', strpbrk(sanitizeClientFileName('<img src=x onerror=alert(1)>.jpg'), '<>"\'') === false, true);
check('sanitize: windows path', sanitizeClientFileName('C:\\fake\\path\\a.png'), 'a.png');
check('sanitize: unix path', sanitizeClientFileName('../../etc/passwd'), 'passwd');
check('sanitize: empty', sanitizeClientFileName(''), 'file');
check('sanitize: only markup', sanitizeClientFileName('<>"'), 'file');
check('sanitize: invalid utf8', sanitizeClientFileName("\xff\xfe.jpg"), 'file');
check('sanitize: persian kept', sanitizeClientFileName('عکس تست.jpg'), 'عکس تست.jpg');
check('sanitize: length', mb_strlen(sanitizeClientFileName(str_repeat('a', 400)), 'UTF-8'), 255);
check('policy short', validatePasswordPolicy('short') !== null, true);
check('policy 73 bytes', validatePasswordPolicy(str_repeat('a', 73)) !== null, true);
check('policy ok', validatePasswordPolicy('goodpass1'), null);
check('policy 72 ok', validatePasswordPolicy(str_repeat('a', 72)), null);
if ($fail > 0) { fwrite(STDERR, "$fail failure(s)\n"); exit(1); }
echo "functions_test: OK\n";
```

## P1.12 Must NOT do (Phase 1)

| Area | Forbidden |
|---|---|
| Permissions model | No new/renamed/removed rows in `permissions` (key `articles` labelled «صفحات ساخته شده» is historical — keep). No `roles`/`members` tables. |
| Deletion | `remove.php` is **not** changed in P1 (P2 does it). |
| Uploads | No upload logic edits (avatar, logo, favicon, media, zip, licences) even inside files you edit. |
| Login | Only the three edits listed for `login.php`; no lock logic yet. |
| Secrets | Do not touch `licenses/*`, `sales/*`. |
| Builder | Only `api/builder/load.php`. No JS/CSS. |
| DB | No DDL except migration `0001`. |
| Dashboard | Do not touch. |
| Syntax | No PHP 8.1+. |

## P1.13 Gate / checklist

| # | Check |
|---|---|
| 1 | `php tests/run_all.php` exit 0; `php -l` on every changed file. |
| 2 | `grep -rn "session_start" public includes` ⇒ only `includes/session.php`. |
| 3 | **F1:** invite a staff member ⇒ exactly one new row (`dashboard`, 1/0/0); they log in, see the dashboard (200), sidebar shows «پیشخوان» + the settings group with only «پروفایل من». |
| 4 | **F5:** as that staff member `curl -i` `/modules/media/index.php` ⇒ **403** and body has no `class="sidebar`; same for `/modules/settings/staff.php`. A temporary `modules/zz_probe/index.php` that includes `header.php` ⇒ 403 (delete it afterwards). |
| 5 | **F2:** POST `perm[<media id>][edit]=1` only ⇒ stored `0/0/0`; `view`+`delete` ⇒ `1/0/0`; all three ⇒ `1/1/1`; modal cascade works. |
| 6 | **F3:** `user_id`=owner ⇒ 400 `کاربر نامعتبر`; non-existent id ⇒ 400 (no 500); `perm=abc` ⇒ success with all-zero rows, no PHP warnings. |
| 7 | **F4:** `hasPermission()` contains no `SELECT`; `loadPermissionMap()` runs once per request per user (temporary query counter, removed afterwards). |
| 8 | **F6/F7:** suspend a logged-in staff via the new button ⇒ next request redirects to `/login.php?error=suspended` (API ⇒ JSON 401). Manually deleting that row ⇒ `/login.php?error=invalid`, **no PHP notices**. Changing `role` in the DB takes effect next request. |
| 9 | **F8:** owner sees 3 settings links; staff with `settings.view` sees 2; without `settings` sees 1; old bottom button gone. |
| 10 | **F9:** staff without `settings` can open `profile.php`. **D5-split:** staff with only `settings.view`: `general.php` renders disabled, forged POST ⇒ 403. |
| 11 | **F11:** staff with `pages.view` but no `templates.view`: `GET /api/builder/load.php?buildable_type=template&template_id=N` ⇒ 403 JSON; `?canvas_id=<page canvas>` ⇒ 200. |
| 12 | **C2:** login page HTML contains no `Admin123`. **M6:** after login `Set-Cookie: PHPSESSID` has `HttpOnly` and `SameSite=Strict`. |
| 13 | **M8:** invite with username `a b`, or password `123` ⇒ 400 with the exact messages; profile email `admin` ⇒ red `ایمیل نامعتبر است.`; duplicate email ⇒ red `این ایمیل قبلاً ثبت شده است.`. |
| 14 | **H1 client:** upload (or insert via SQL) a media row named `<img src=x onerror=alert(1)>.jpg`, open the preview ⇒ no alert, text shown literally. **M9:** `?page=0` on media and templates ⇒ page 1, no SQL error. |
| 15 | **Migration 0001** ran; `diagnose.php --phase=1` ⇒ D01/D02/D04 PASS, D03 WARN or PASS. After it, `INSERT … can_view=0, can_edit=1` fails on MariaDB ≥10.2.1 / MySQL ≥8.0.16 (rolled back test). |
| 16 | **Owner regression:** owner still opens every module, invites staff, edits permissions, activates a template, saves a canvas, uploads media. |
| 17 | `activity_logs` gets `staff_invite`, `staff_permissions`, `staff_status` rows. |
| 18 | **Output order:** `curl -si -X POST` to `general.php` as a staff with only `settings.view` ⇒ status line `403` and the body contains no `class="sidebar`. |
| 19 | **M9:** force an exception in `activate.php` (temporarily rename the `templates` table in the **clone**) ⇒ JSON message is exactly `خطا در فعال‌سازی قالب`, the PHP error log has the real message. Never do this on `business_panel`. |
| 20 | **Login notices:** `/login.php?error=timeout` and `?error=invalid` show `STR_ERR_TIMEOUT` / `STR_ERR_INVALID`. |

Append the P1 section to `changelogs/changelog-fix-hardening.md` (files changed, checklist with evidence, deviations) before starting P2.

---

# PHASE 2 — Accounts: login hardening, soft delete, seed password, sessions, logging (migrations `0002` + `0003`)

**Goal:** close C1 (code + FK), C2, H8, M6-4, M8 (rest), L5 and M10 (indexes, coverage, retention). Order inside the phase: **`0002` [pre-code] → code → tests → `0003` [post-code] → gate.**

## P2.1 File map

```
panel/
├── config/config.php                                  M  + SEED_PASSWORD_HASH, LOGIN_DUMMY_HASH
├── database/
│   ├── migrations/0002_accounts_pre_code.php          N  [pre-code]
│   ├── migrations/0003_creator_fk_rules_post_code.php N  [post-code]
│   └── tools/
│       ├── purge_logs.php                             N  retention for login_attempts (7 d) and activity_logs (180 d)
│       └── reset_owner_password.php                   N  CLI: new owner password via STDIN
├── includes/
│   ├── auth_rules.php                                 N  Pure: loginLockReason, sessionIsStale, mustChangeAllowedScript
│   ├── functions.php                                  M  + hashPassword()
│   └── auth.php                                       M  explicit user columns, stale-session + must-change checks, loginUser(), logActivity()
├── tests/
│   ├── auth_rules_test.php                            N
│   └── functions_test.php                             M  + 2 hashPassword checks
└── public/
    ├── login.php                                      M  full rewrite (identifier+IP lock, constant-time, success clears failures)
    ├── logout.php                                     M  logs the logout
    ├── api/staff/remove.php                           M  soft delete
    ├── api/staff/invite.php                           M  hashPassword + must_change_password=1
    └── modules/settings/
        ├── profile.php                                M  full rewrite (validate first, POST handled before output)
        └── staff.php                                  M  delete confirmation text only
```

## P2.2 Migration `0002` [pre-code] — run FIRST

Purely additive, safe before any new code exists: `status` gains `'removed'`; new columns `removed_at`, `must_change_password`, `password_changed_at`; every account that still carries the published seed hash gets `must_change_password = 1` (D13); the indexes `idx_la_ip`, `idx_la_time`, `idx_la_ident_ip` (login lock queries) and `idx_logs_created`, `idx_logs_user_time` (retention + "recent activity").

```php
<?php
// Phase 2 — [pre-code]: purely additive, safe before the new account code exists. Run FIRST in P2.
return [
    'description' => 'Accounts: removed status, removed_at, must_change_password, password_changed_at, login/log indexes',
    'up' => function (PDO $pdo, Migrator $m): void {
        if ($m->columnType('panel_users', 'status') !== "enum('active','suspended','removed')") {
            $pdo->exec("ALTER TABLE panel_users MODIFY status ENUM('active','suspended','removed') NOT NULL DEFAULT 'active'");
        }
        if (!$m->columnExists('panel_users', 'removed_at')) {
            $pdo->exec("ALTER TABLE panel_users ADD COLUMN removed_at DATETIME DEFAULT NULL AFTER last_login_at");
        }
        if (!$m->columnExists('panel_users', 'must_change_password')) {
            $pdo->exec("ALTER TABLE panel_users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER password_hash");
        }
        if (!$m->columnExists('panel_users', 'password_changed_at')) {
            $pdo->exec("ALTER TABLE panel_users ADD COLUMN password_changed_at DATETIME DEFAULT NULL AFTER must_change_password");
        }

        // Every account that still carries the published seed hash must change its password (D13).
        $seed = '$2y$10$cnvXc0McHVEK5Nml0ZvMwepHQ8jERISPDuFgn9BuwBsO6CZ5utTO.';
        $pdo->prepare("UPDATE panel_users SET must_change_password = 1 WHERE password_hash = ?")->execute([$seed]);

        $m->ensureIndex('login_attempts', 'idx_la_ip', '(ip_address, attempted_at)');
        $m->ensureIndex('login_attempts', 'idx_la_time', '(attempted_at)');
        $m->ensureIndex('login_attempts', 'idx_la_ident_ip', '(identifier, ip_address, attempted_at)');
        $m->ensureIndex('activity_logs', 'idx_logs_created', '(created_at)');
        $m->ensureIndex('activity_logs', 'idx_logs_user_time', '(panel_user_id, created_at)');
    },
];
```

Run on the clone first (Rule 12), then on `business_panel`. Afterwards `diagnose.php --phase=2` must run (D03 may WARN: that is the seed hash being flagged, not an error).

## P2.3 Code

**`config/config.php`** — append (after the P1 block):
```php
// ===== P2: accounts =====
// The published seed hash (schema.sql). Any account whose password is this (or whose NEW password verifies against it) is refused.
define('SEED_PASSWORD_HASH', '$2y$10$cnvXc0McHVEK5Nml0ZvMwepHQ8jERISPDuFgn9BuwBsO6CZ5utTO.');
// bcrypt cost-10 hash of a random discarded string: password_verify() runs against it when the user does not exist (timing parity).
define('LOGIN_DUMMY_HASH', '$2y$10$gabpQVj0WCB74y5TAOIHO.tqD/ssMH/LR4sSRomD2k8Kr/v5nvlsq');
```
(You may regenerate the dummy with `php -r 'echo password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT, ["cost"=>10]);'`; diagnostic D19 verifies it is bcrypt cost 10.)

**`includes/functions.php`** — append `hashPassword()` (this is the only way passwords are hashed from now on; cost is explicit because PHP 8.4 changed the default):
```php
<?php
// ---- P2 addition ----

/** The only way passwords are hashed in this project (explicit cost: PHP 8.4 changed the default). */
function hashPassword(string $plain): string {
    return password_hash($plain, PASSWORD_BCRYPT, ['cost' => 10]);
}
```

**`includes/auth_rules.php`** (new, pure, unit-tested):
```php
<?php
// Pure rules for authentication. No DB, no session, no headers. Unit-tested in tests/auth_rules_test.php.

/**
 * Decides whether a login attempt must be refused because of too many recent failures.
 * $failsIdentifierIp = failed attempts for this identifier from this IP in the lock window.
 * $failsIp           = failed attempts from this IP (any identifier) in the lock window.
 * Returns 'identifier' | 'ip' | null (null = allowed to try).
 */
function loginLockReason(int $failsIdentifierIp, int $failsIp, int $maxIdentifierIp = 5, int $maxIp = 20): ?string {
    if ($failsIdentifierIp >= $maxIdentifierIp) { return 'identifier'; }
    if ($failsIp >= $maxIp) { return 'ip'; }
    return null;
}

/**
 * A session is stale when the password was changed after that session was authenticated.
 * $passwordChangedAt is the DATETIME string from panel_users (or null); $authAt is $_SESSION['auth_at'] (unix time, 0 if missing).
 * An unparsable date fails closed (stale).
 */
function sessionIsStale(?string $passwordChangedAt, int $authAt): bool {
    if ($passwordChangedAt === null || $passwordChangedAt === '') { return false; }
    $ts = strtotime($passwordChangedAt);
    if ($ts === false) { return true; }
    return $ts > $authAt;
}

/** While must_change_password = 1 only the profile page and logout are reachable. */
function mustChangeAllowedScript(string $scriptName): bool {
    $p = str_replace('\\', '/', $scriptName);
    foreach (['/modules/settings/profile.php', '/logout.php'] as $allowed) {
        $len = strlen($allowed);
        if (strlen($p) >= $len && substr($p, -$len) === $allowed) { return true; }
    }
    return false;
}
```

**`includes/auth.php`** — replace the whole file (reference implementation). Differences from P1: `currentUser()` selects **explicit columns** (no `password_hash`; L5); `requireLogin()` also rejects stale sessions and enforces `must_change_password`; `loginUser()` sets `auth_at`/`last_seen` and logs; `logActivity()` truncates to column sizes and logs failures to the PHP error log instead of swallowing them; `$_SESSION['user_role']` no longer exists (nothing reads it after P1 — run `grep -rn "user_role" public includes` ⇒ must print nothing).
```php
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/permission_rules.php';
require_once __DIR__ . '/auth_rules.php';

session_boot();

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

/** Explicit column list: password_hash must never travel into templates. */
function currentUser(bool $refresh = false): ?array {
    if (!isLoggedIn()) return null;
    static $cached = null;
    static $cachedId = 0;
    $id = (int)$_SESSION['user_id'];
    if (!$refresh && $cached !== null && $cachedId === $id) return $cached;
    $stmt = getPDO()->prepare(
        "SELECT id, full_name, username, email, phone, avatar_path, role, status, theme_preference,
                must_change_password, password_changed_at, last_login_at
         FROM panel_users WHERE id = ? LIMIT 1"
    );
    $stmt->execute([$id]);
    $cached = $stmt->fetch() ?: null;
    $cachedId = $id;
    return $cached;
}

function requireLogin(): void {
    if (!isLoggedIn()) { authFail('expired'); }
    $u = currentUser();
    if (!$u || ($u['status'] ?? '') !== 'active') {
        $reason = ($u && ($u['status'] ?? '') === 'suspended') ? 'suspended' : 'invalid';
        logoutUser();
        authFail($reason);
    }
    // A password change (anywhere) kills every session that was authenticated before it.
    if (sessionIsStale($u['password_changed_at'] ?? null, (int)($_SESSION['auth_at'] ?? 0))) {
        logoutUser();
        authFail('invalid');
    }
    if (time() - (int)($_SESSION['last_seen'] ?? time()) > SESSION_IDLE_SECONDS) {
        logoutUser();
        authFail('timeout');
    }
    $_SESSION['last_seen'] = time();
    // Seed / invited accounts: only the profile page and logout are reachable until the password is changed.
    if (!empty($u['must_change_password']) && !mustChangeAllowedScript($_SERVER['SCRIPT_NAME'] ?? '')) {
        if (isApiRequest()) {
            jsonResponse(['success' => false, 'message' => 'ابتدا باید رمز عبور خود را تغییر دهید.', 'must_change_password' => true], 403);
        }
        header('Location: /modules/settings/profile.php?must_change=1');
        exit;
    }
}

/** Never returns. API → JSON 401; page → redirect to /login.php[?error=…]. */
function authFail(string $reason): void {
    if (isApiRequest()) {
        jsonResponse(['success' => false, 'message' => 'نشست شما منقضی شده است. دوباره وارد شوید.'], 401);
    }
    header('Location: /login.php' . ($reason === 'expired' ? '' : '?error=' . $reason));
    exit;
}

/** Call only after the credentials were verified and the account is active. */
function loginUser(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['auth_at'] = time();
    $_SESSION['last_seen'] = time();
    getPDO()->prepare("UPDATE panel_users SET last_login_at = NOW() WHERE id = ?")->execute([(int)$user['id']]);
    logActivity('login', 'auth', 'ورود به پنل');
}

function logoutUser(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
}

function isOwner(): bool {
    $u = currentUser();
    return $u !== null && ($u['role'] ?? '') === 'owner' && ($u['status'] ?? '') === 'active';
}

function logActivity(string $action_type, string $module_key, ?string $description = null): void {
    if (!isLoggedIn()) return;
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare("INSERT INTO activity_logs (panel_user_id, action_type, module_key, description, ip_address) VALUES (?,?,?,?,?)");
        $stmt->execute([
            (int)$_SESSION['user_id'],
            mb_substr($action_type, 0, 60, 'UTF-8'),
            mb_substr($module_key, 0, 50, 'UTF-8'),
            $description === null ? null : mb_substr($description, 0, 500, 'UTF-8'),
            getClientIp(),
        ]);
    } catch (Throwable $e) {
        // A logging failure must never break the real operation, but it must not be silent either.
        error_log('logActivity failed: ' . $e->getMessage());
    }
}
```

**`public/login.php`** — replace the whole file (reference implementation; keeps the page markup, adds `maxlength`, drops the password hint). Behaviour: identifier capped at 190 chars; lock = ≥5 failures for **identifier+IP** *or* ≥20 failures for the **IP** inside `LOGIN_LOCK_MINUTES`; `@` ⇒ look up by e-mail, otherwise by username (never "email OR username", never a `removed` user); `password_verify` always runs (dummy hash when the user is missing); a good login deletes that identifier+IP's failed rows; 1 % of requests purge attempts older than 7 days.
```php
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

send_security_headers();

if (!empty($_SESSION['user_id'])) { header('Location: /modules/dashboard/index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'توکن امنیتی نامعتبر است.';
    } else {
        $identifier = mb_substr(trim((string)($_POST['identifier'] ?? '')), 0, 190, 'UTF-8');
        $password = (string)($_POST['password'] ?? '');
        $ip = getClientIp();
        $pdo = getPDO();
        $window = "attempted_at > (NOW() - INTERVAL " . (int)LOGIN_LOCK_MINUTES . " MINUTE)";

        $st = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND ip_address = ? AND is_successful = 0 AND $window");
        $st->execute([$identifier, $ip]);
        $failsIdentifierIp = (int)$st->fetchColumn();
        $st = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND is_successful = 0 AND $window");
        $st->execute([$ip]);
        $failsIp = (int)$st->fetchColumn();

        $lock = loginLockReason($failsIdentifierIp, $failsIp, LOGIN_MAX_ATTEMPTS, LOGIN_MAX_ATTEMPTS_PER_IP);
        if ($lock === 'identifier') {
            $error = 'حساب شما به دلیل تلاش‌های ناموفق به مدت ۱۵ دقیقه قفل شده است.';
        } elseif ($lock === 'ip') {
            $error = 'تعداد تلاش‌های ناموفق از این شبکه بیش از حد مجاز است. ۱۵ دقیقه دیگر دوباره تلاش کنید.';
        } else {
            // '@' → e-mail, otherwise username: one deterministic row, never "email OR username".
            $col = (strpos($identifier, '@') !== false) ? 'email' : 'username';
            $st = $pdo->prepare("SELECT id, role, status, password_hash, must_change_password FROM panel_users WHERE $col = ? AND status <> 'removed' LIMIT 1");
            $st->execute([$identifier]);
            $user = $st->fetch();

            // Constant work whether or not the user exists (timing parity).
            $verified = password_verify($password, $user ? $user['password_hash'] : LOGIN_DUMMY_HASH);
            $ok = $user && $verified;
            $suspended = $ok && $user['status'] !== 'active';

            $pdo->prepare("INSERT INTO login_attempts (identifier, ip_address, is_successful) VALUES (?,?,?)")
                ->execute([$identifier, $ip, ($ok && !$suspended) ? 1 : 0]);
            if (random_int(1, 100) === 1) {
                $pdo->exec("DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 7 DAY)");
            }

            if ($suspended) {
                $error = 'حساب شما مسدود شده است.';
            } elseif ($ok) {
                // A good login clears this identifier+IP's failures (4 fails + 1 success + 1 fail must not lock).
                $pdo->prepare("DELETE FROM login_attempts WHERE identifier = ? AND ip_address = ? AND is_successful = 0")
                    ->execute([$identifier, $ip]);
                loginUser($user);
                header('Location: ' . (!empty($user['must_change_password']) ? '/modules/settings/profile.php?must_change=1' : '/modules/dashboard/index.php'));
                exit;
            } else {
                $error = 'نام کاربری یا رمز عبور اشتباه است.';
            }
        }
    }
}
$_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$notice = '';
$errCode = $_GET['error'] ?? '';
if ($errCode === 'suspended') { $notice = 'حساب شما مسدود است.'; }
elseif ($errCode === 'timeout') { $notice = 'نشست شما به دلیل عدم فعالیت منقضی شد. دوباره وارد شوید.'; }
elseif ($errCode === 'invalid') { $notice = 'نشست شما معتبر نیست. دوباره وارد شوید.'; }
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ورود به پنل مدیریت</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
<style>body{font-family:'Vazirmatn',tahoma;background:#f3f0ff; display:flex;align-items:center;justify-content:center;min-height:100vh}</style>
</head>
<body>
<div class="card shadow p-4" style="width:400px;max-width:95%">
<h4 class="text-center mb-3">ورود به پنل مدیریت</h4>
<?php if($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<?php if($notice): ?><div class="alert alert-warning"><?= e($notice) ?></div><?php endif; ?>
<form method="post">
<input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
<div class="mb-3"><label class="form-label">ایمیل یا نام کاربری</label><input name="identifier" class="form-control" maxlength="190" required></div>
<div class="mb-3"><label class="form-label">رمز عبور</label><input type="password" name="password" class="form-control" required></div>
<button class="btn btn-primary w-100" style="background:#6f42c1;border-color:#6f42c1">ورود</button>
</form>
</div>
</body>
</html>
```

**`public/logout.php`** — replace with:
```php
<?php
require_once __DIR__ . '/../includes/auth.php';
logActivity('logout', 'auth', 'خروج از پنل');
logoutUser();
header('Location: /login.php'); exit;
```

**`public/api/staff/remove.php`** — replace the whole file: soft delete (D12). The row stays so `created_by`, `uploaded_by` and `activity_logs` keep their meaning; username and e-mail are anonymised (the UNIQUE keys become free, the same address can be invited again); the password hash becomes unusable; permission rows are deleted; `password_changed_at` + `status='removed'` kill any live session. The avatar **file** is released in P4 (until then only the column is cleared).
```php
<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/csrf.php';
requireLogin(); if(!isOwner()) jsonResponse(['success'=>false,'message'=>'فقط مالک'],403);
if($_SERVER['REQUEST_METHOD']!=='POST') jsonResponse(['success'=>false,'message'=>'متد مجاز نیست'],405);
if(!csrf_verify($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')){ jsonResponse(['success'=>false,'message'=>'توکن نامعتبر'],403); }
$id=(int)($_POST['id']??0);
$pdo=getPDO();
$st=$pdo->prepare("SELECT id, role, status FROM panel_users WHERE id=? LIMIT 1");
$st->execute([$id]);
$target=$st->fetch();
if(!$target || $target['role']!=='staff' || $target['status']==='removed'){
  jsonResponse(['success'=>false,'message'=>'کاربر نامعتبر'],400);
}
// Soft delete (D12): the row stays so created_by / uploaded_by / activity_logs keep meaning.
// Username and e-mail are anonymised so the UNIQUE keys are free again; the hash can never verify.
$unusableHash='!removed!'.bin2hex(random_bytes(16));
try{
  $pdo->beginTransaction();
  $pdo->prepare("UPDATE panel_users
                    SET status='removed', removed_at=NOW(),
                        username=CONCAT('removed-', id), email=CONCAT('removed-', id, '@invalid.local'),
                        phone=NULL, avatar_path=NULL, password_hash=?, must_change_password=0, password_changed_at=?
                  WHERE id=? AND role='staff'")
      ->execute([$unusableHash,date('Y-m-d H:i:s'),$id]);
  $pdo->prepare("DELETE FROM panel_user_permissions WHERE panel_user_id=?")->execute([$id]);
  $pdo->commit();
}catch(Throwable $e){
  if($pdo->inTransaction()) $pdo->rollBack();
  error_log('staff/remove.php: '.$e->getMessage());
  jsonResponse(['success'=>false,'message'=>'خطا در حذف همکار'],500);
}
logActivity('staff_remove','settings','حذف همکار '.$id);
jsonResponse(['success'=>true,'message'=>'همکار حذف شد']);
```

**`public/api/staff/invite.php`** — replace the whole file. Changes versus P1: `hashPassword()`, `must_change_password = 1` (the owner knows the first password, so the colleague must replace it — *author's addition to D13*). Everything else is the P1 behaviour.
```php
<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/csrf.php';
requireLogin(); if(!isOwner()) jsonResponse(['success'=>false,'message'=>'فقط مالک'],403);
if(!csrf_verify($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')){ jsonResponse(['success'=>false,'message'=>'توکن نامعتبر'],403); }
$full_name=trim($_POST['full_name']??''); $username=trim($_POST['username']??''); $email=trim($_POST['email']??''); $password=$_POST['password']??'';
if($full_name===''||$username===''||$email===''||$password==='') jsonResponse(['success'=>false,'message'=>'تمام فیلدها الزامی است'],400);
if(!filter_var($email,FILTER_VALIDATE_EMAIL)) jsonResponse(['success'=>false,'message'=>'ایمیل نامعتبر'],400);
if(!preg_match('/^[A-Za-z0-9_.-]{3,30}$/D',$username) || stripos($username,'removed-')===0) jsonResponse(['success'=>false,'message'=>'نام کاربری باید ۳ تا ۳۰ کاراکتر و فقط شامل حروف انگلیسی، عدد و . _ - باشد'],400);
if(($pwErr=validatePasswordPolicy($password))!==null) jsonResponse(['success'=>false,'message'=>$pwErr],400);
if(mb_strlen($full_name,'UTF-8')>150 || strlen($email)>190) jsonResponse(['success'=>false,'message'=>'درخواست نامعتبر'],400);
$hash=hashPassword($password);
$pdo=getPDO();
try{
  $pdo->beginTransaction();
  // must_change_password=1: the owner knows the first password, so the new colleague must replace it (D13).
  $pdo->prepare("INSERT INTO panel_users (full_name, username, email, password_hash, must_change_password, role) VALUES (?,?,?,?,1,'staff')")
      ->execute([$full_name,$username,$email,$hash]);
  $newId=(int)$pdo->lastInsertId();
  $pdo->prepare("INSERT INTO panel_user_permissions (panel_user_id, permission_id, can_view, can_edit, can_delete)
                 SELECT ?, id, 1, 0, 0 FROM permissions WHERE module_key = 'dashboard'")->execute([$newId]);
  $pdo->commit();
}catch(PDOException $e){
  if($pdo->inTransaction()) $pdo->rollBack();
  if((int)($e->errorInfo[1]??0)===1062){ jsonResponse(['success'=>false,'message'=>'نام کاربری یا ایمیل تکراری است'],400); }
  error_log('staff/invite.php: '.$e->getMessage());
  jsonResponse(['success'=>false,'message'=>'خطا در ایجاد همکار'],500);
}
logActivity('staff_invite','settings','افزودن همکار '.$newId);
jsonResponse(['success'=>true,'message'=>'همکار با موفقیت ایجاد شد']);
```

**`public/modules/settings/profile.php`** — replace the whole file (reference implementation). Key points: (1) `page_boot.php` first, **POST handled before any output** (needed for `session_regenerate_id()`); (2) validate **everything** before touching disk or DB (audit M8: today a wrong current password still saved the e-mail); (3) theme is an enum (invalid ⇒ keep the old value); (4) password: current password required, policy, wrong-current, same-as-current, seed password forbidden; (5) one transaction for profile + password; on success `session_regenerate_id(true)` and `$_SESSION['auth_at'] = $now` so **this** session survives and every older one becomes stale (D15); (6) 1062 ⇒ `STR_EMAIL_DUPLICATE`; (7) a banner `STR_MUST_CHANGE_BANNER` while `must_change_password` is set. The avatar block is intentionally unchanged (P4 replaces it).
```php
<?php
$currentModule='settings'; $pageTitle='پروفایل من'; $breadcrumb='تنظیمات پنل ‹ پروفایل';
require_once __DIR__ . '/../../../includes/page_boot.php';   // login + guard, nothing printed yet
$pdo=getPDO();
$user=currentUser();
$msg=''; $msgClass='success';

if($_SERVER['REQUEST_METHOD']==='POST'){
  requireCsrf();
  $full_name=trim((string)($_POST['full_name']??''));
  $email=trim((string)($_POST['email']??''));
  $phone=trim((string)($_POST['phone']??''));
  $themeIn=(string)($_POST['theme_preference']??'');
  $theme=in_array($themeIn,['light','dark'],true) ? $themeIn : ($user['theme_preference']??'light');
  $cur=(string)($_POST['current_password']??'');
  $new=(string)($_POST['new_password']??'');
  $wantsPw=($cur!=='' || $new!=='');
  $mustChange=!empty($user['must_change_password']);

  // 1) validate everything BEFORE touching the disk or the database
  $err=null;
  if(mb_strlen($full_name,'UTF-8')<1 || mb_strlen($full_name,'UTF-8')>150) $err='نام و نام خانوادگی باید بین ۱ تا ۱۵۰ کاراکتر باشد.';
  elseif(mb_strlen($phone,'UTF-8')>20) $err='شماره تلفن حداکثر ۲۰ کاراکتر است.';
  elseif(!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>190) $err='ایمیل نامعتبر است.';
  elseif($mustChange && $new==='') $err='برای ادامه باید رمز عبور جدید تعیین کنید.';
  elseif($wantsPw){
    $row=$pdo->prepare("SELECT password_hash FROM panel_users WHERE id=?"); $row->execute([(int)$user['id']]);
    $hash=(string)$row->fetchColumn();
    if($cur==='') $err='برای تغییر رمز، رمز فعلی را وارد کنید.';
    elseif(($p=validatePasswordPolicy($new))!==null) $err=$p;
    elseif(!password_verify($cur,$hash)) $err='رمز فعلی اشتباه است.';
    elseif(hash_equals($cur,$new)) $err='رمز جدید باید با رمز فعلی متفاوت باشد.';
    elseif(password_verify($new,SEED_PASSWORD_HASH)) $err='این رمز عبور مجاز نیست. رمز دیگری انتخاب کنید.';
  }

  if($err!==null){
    $msg=$err; $msgClass='danger';
  } else {
    // 2) avatar: unchanged in P2 (P4 replaces this block with FileStore)
    $avatarPath=$user['avatar_path'];
    if(!empty($_FILES['avatar']['tmp_name'])){
      $ext=strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
      $finfo=finfo_open(FILEINFO_MIME_TYPE); $mime=finfo_file($finfo, $_FILES['avatar']['tmp_name']);
      if(in_array($ext, ['jpg','jpeg','png','webp']) && in_array($mime, ['image/jpeg','image/png','image/webp']) && $_FILES['avatar']['size']<=5*1024*1024){
        $newName=bin2hex(random_bytes(16)).'.'.$ext;
        move_uploaded_file($_FILES['avatar']['tmp_name'], dirname(__DIR__,3).'/public/uploads/avatars/'.$newName);
        $avatarPath='/uploads/avatars/'.$newName;
      }
    }
    // 3) one transaction for profile + password
    try{
      $pdo->beginTransaction();
      $pdo->prepare("UPDATE panel_users SET full_name=?, email=?, phone=?, avatar_path=?, theme_preference=? WHERE id=?")
          ->execute([$full_name,$email,$phone,$avatarPath,$theme,(int)$user['id']]);
      $now=time();
      if($wantsPw){
        $pdo->prepare("UPDATE panel_users SET password_hash=?, must_change_password=0, password_changed_at=? WHERE id=?")
            ->execute([hashPassword($new),date('Y-m-d H:i:s',$now),(int)$user['id']]);
      }
      $pdo->commit();
      if($wantsPw){
        session_regenerate_id(true);
        $_SESSION['auth_at']=$now;                 // this session survives, every older one is stale (sessionIsStale)
        logActivity('password_change','settings','تغییر رمز عبور');
        $msg='رمز عبور با موفقیت تغییر کرد.';
      } else {
        logActivity('profile_update','settings','ویرایش پروفایل');
        $msg='اطلاعات با موفقیت ذخیره شد.';
      }
    }catch(PDOException $e){
      if($pdo->inTransaction()) $pdo->rollBack();
      if((int)($e->errorInfo[1]??0)===1062){ $msg='این ایمیل قبلاً ثبت شده است.'; }
      else { error_log('profile.php: '.$e->getMessage()); $msg='خطا در ذخیره‌سازی.'; }
      $msgClass='danger';
    }
  }
  $user=currentUser(true);
}
require_once __DIR__ . '/../../../includes/header.php';
?>
<?php if(!empty($user['must_change_password'])): ?><div class="alert alert-warning">برای ادامه کار با پنل، رمز عبور جدید تعیین کنید.</div><?php endif; ?>
<?php if($msg): ?><div class="alert alert-<?= e($msgClass) ?>"><?= e($msg) ?></div><?php endif; ?>
<div class="card p-4">
<form method="post" enctype="multipart/form-data">
<?= csrf_field() ?>
<div class="mb-3"><label class="form-label">نام و نام خانوادگی</label><input name="full_name" value="<?= e($user['full_name']) ?>" class="form-control" maxlength="150"></div>
<div class="mb-3"><label class="form-label">ایمیل</label><input name="email" type="email" value="<?= e($user['email']) ?>" class="form-control" maxlength="190"></div>
<div class="mb-3"><label class="form-label">تلفن</label><input name="phone" value="<?= e($user['phone']) ?>" class="form-control" maxlength="20"></div>
<div class="mb-3"><label class="form-label">آواتار</label><input type="file" name="avatar" accept="image/*" class="form-control"><br><?php if($user['avatar_path']): ?><img src="<?= e($user['avatar_path']) ?>" width="80" height="80" style="border-radius:50%"><?php endif; ?></div>
<div class="mb-3"><label class="form-label">تم</label><select name="theme_preference" class="form-select"><option value="light" <?= $user['theme_preference']==='light'?'selected':'' ?>>روشن</option><option value="dark" <?= $user['theme_preference']==='dark'?'selected':'' ?>>تیره</option></select></div>
<hr>
<h6>تغییر رمز عبور</h6>
<div class="mb-3"><label class="form-label">رمز فعلی</label><input type="password" name="current_password" class="form-control" autocomplete="current-password"></div>
<div class="mb-3"><label class="form-label">رمز جدید</label><input type="password" name="new_password" class="form-control" autocomplete="new-password"></div>
<button class="btn btn-primary">ذخیره تغییرات</button>
</form>
</div>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
```

**`public/modules/settings/staff.php`** — replace the temporary delete warning text (`STR_DELETE_WARNING_TEMP`) with `STR_DELETE_CONFIRM` (§2.3). Nothing else.

**`database/tools/purge_logs.php`** (new; run daily from cron / Task Scheduler; `--dry-run` first):
```php
<?php
// Retention for the two tables that grow forever. CLI only. Run from cron / Task Scheduler (e.g. daily).
//   php database/tools/purge_logs.php            → deletes and prints counts
//   php database/tools/purge_logs.php --dry-run  → only prints what would be deleted
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../config/database.php';   // app credentials: DML only is enough

const LOGIN_ATTEMPTS_KEEP_DAYS = 7;
const ACTIVITY_LOGS_KEEP_DAYS = 180;
const BATCH = 5000;

$dry = in_array('--dry-run', $argv, true);
$pdo = getPDO();
$jobs = [
    ['login_attempts', 'attempted_at', LOGIN_ATTEMPTS_KEEP_DAYS],
    ['activity_logs',  'created_at',   ACTIVITY_LOGS_KEEP_DAYS],
];
foreach ($jobs as [$table, $col, $days]) {
    $days = (int)$days;
    if ($dry) {
        $n = (int)$pdo->query("SELECT COUNT(*) FROM $table WHERE $col < (NOW() - INTERVAL $days DAY)")->fetchColumn();
        echo "$table: $n row(s) older than $days days (dry run)\n";
        continue;
    }
    $total = 0;
    do {
        $affected = $pdo->exec("DELETE FROM $table WHERE $col < (NOW() - INTERVAL $days DAY) LIMIT " . BATCH);
        $total += (int)$affected;
    } while ($affected === BATCH);
    echo "$table: deleted $total row(s) older than $days days\n";
}
```

**`database/tools/reset_owner_password.php`** (new; for fresh installs, a forgotten password, or a leaked seed password; the password comes from STDIN, never argv):
```php
<?php
// Sets a new owner password from the command line (fresh installs, forgotten password, leaked seed password).
//   php database/tools/reset_owner_password.php <username>
// The password is read as one line from STDIN (never from argv: shell history and the process list would leak it):
//   Linux/macOS:  read -s -p "New password: " P; echo; printf '%s\n' "$P" | php database/tools/reset_owner_password.php admin
//   Windows:      php database/tools/reset_owner_password.php admin     (type the password, press Enter; it is echoed on screen)
// The script never prints the password.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$username = $argv[1] ?? '';
if ($username === '') { fwrite(STDERR, "Usage: php reset_owner_password.php <username>   (password on STDIN)\n"); exit(2); }
$pw = rtrim((string)fgets(STDIN), "\r\n");
if (($err = validatePasswordPolicy($pw)) !== null) { fwrite(STDERR, "Password rejected by policy (8-72 bytes).\n"); exit(2); }
if (password_verify($pw, SEED_PASSWORD_HASH)) { fwrite(STDERR, "That password is the published seed password and is forbidden.\n"); exit(2); }

$pdo = getPDO();
$st = $pdo->prepare("SELECT id FROM panel_users WHERE username = ? AND role = 'owner' AND status <> 'removed' LIMIT 1");
$st->execute([$username]);
$id = $st->fetchColumn();
if (!$id) { fwrite(STDERR, "No active owner with that username.\n"); exit(1); }
$pdo->prepare("UPDATE panel_users SET password_hash = ?, must_change_password = 0, password_changed_at = ?, status = 'active' WHERE id = ?")
    ->execute([hashPassword($pw), date('Y-m-d H:i:s'), (int)$id]);
$pdo->prepare("DELETE FROM login_attempts WHERE identifier = ?")->execute([$username]);
echo "Owner password updated; every existing session of that user is now invalid.\n";
```

### Activity-log coverage (M10)

`logActivity($action_type, $module_key, $description)` must be called **after** the operation succeeded. This phase covers the accounts rows; later phases add theirs (the full list is in Appendix D).

| action_type | module_key | Where | Phase |
|---|---|---|---|
| `staff_invite`, `staff_permissions`, `staff_status` | settings | invite / save_permissions / set_status | P1 |
| `staff_remove` | settings | remove.php | P2 |
| `login`, `logout` | auth | `loginUser()`, logout.php | P2 |
| `profile_update`, `password_change` | settings | profile.php | P2 |

## P2.4 Tests

`tests/auth_rules_test.php` (reference implementation, executed by the author):
```php
<?php
date_default_timezone_set('Asia/Tehran');
require __DIR__ . '/../includes/auth_rules.php';
$fail = 0;
function check(string $label, $actual, $expected): void {
    global $fail;
    if ($actual !== $expected) { $fail++; fwrite(STDERR, "FAIL: $label\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n"); }
}
check('lock none', loginLockReason(0, 0), null);
check('lock 4/19', loginLockReason(4, 19), null);
check('lock identifier at 5', loginLockReason(5, 5), 'identifier');
check('lock ip at 20', loginLockReason(1, 20), 'ip');
check('identifier wins over ip', loginLockReason(9, 99), 'identifier');
check('custom limits', loginLockReason(2, 0, 2, 20), 'identifier');
$now = time();
check('stale null', sessionIsStale(null, $now), false);
check('stale empty', sessionIsStale('', $now), false);
check('stale changed after auth', sessionIsStale(date('Y-m-d H:i:s', $now + 60), $now), true);
check('stale changed before auth', sessionIsStale(date('Y-m-d H:i:s', $now - 60), $now), false);
check('stale same second', sessionIsStale(date('Y-m-d H:i:s', $now), $now), false);
check('stale garbage fails closed', sessionIsStale('not-a-date', $now), true);
check('stale missing auth_at', sessionIsStale('2026-01-01 00:00:00', 0), true);
check('allowed profile', mustChangeAllowedScript('/modules/settings/profile.php'), true);
check('allowed logout', mustChangeAllowedScript('/logout.php'), true);
check('allowed nested profile', mustChangeAllowedScript('/panel/public/modules/settings/profile.php'), true);
check('windows path', mustChangeAllowedScript('C:\\x\\public\\logout.php'), true);
check('denied dashboard', mustChangeAllowedScript('/modules/dashboard/index.php'), false);
check('denied api', mustChangeAllowedScript('/api/staff/invite.php'), false);
check('denied lookalike', mustChangeAllowedScript('/modules/settings/profile.php.bak'), false);
if ($fail > 0) { fwrite(STDERR, "$fail failure(s)\n"); exit(1); }
echo "auth_rules_test: OK\n";
```

`tests/functions_test.php` — append these two checks before the final `if ($fail > 0)` line:
```php
check('hash is bcrypt', password_get_info(hashPassword('x'))['algoName'], 'bcrypt');
check('hash cost 10', password_get_info(hashPassword('x'))['options']['cost'], 10);
```

## P2.5 Migration `0003` [post-code] — run LAST

Only after `remove.php` is the soft delete above (a real `DELETE` of a user who owns content now fails by design — that is the safety net). `creator` and `uploader` FKs become `RESTRICT`; `activity_logs.panel_user_id` becomes nullable with `SET NULL`. The migration drops the old `fk_logs_user` **before** widening the column (MySQL refuses to alter a column an FK still guards) and re-adds it; a failed run is simply re-run.

```php
<?php
// Phase 2 — [post-code]: run LAST in P2, after remove.php is a soft delete (a real DELETE of a user with content now fails by design).
return [
    'description' => 'FK rules: creators/uploaders RESTRICT, activity_logs.panel_user_id nullable + SET NULL',
    'up' => function (PDO $pdo, Migrator $m): void {
        $m->replaceForeignKey('builder_canvases', 'fk_builder_canvases_creator', 'created_by', 'panel_users', 'id', 'RESTRICT');
        $m->replaceForeignKey('media_library', 'fk_media_uploader', 'uploaded_by', 'panel_users', 'id', 'RESTRICT');

        // SET NULL needs a nullable column. MySQL refuses to alter a column that an FK still guards,
        // so: drop the old FK first, widen the column, then re-add the FK with the new rule.
        if ($m->fkExists('activity_logs', 'fk_logs_user') && $m->fkRule('activity_logs', 'fk_logs_user') !== 'SET NULL') {
            $pdo->exec("ALTER TABLE activity_logs DROP FOREIGN KEY fk_logs_user");
        }
        if (!$m->isNullable('activity_logs', 'panel_user_id')) {
            $pdo->exec("ALTER TABLE activity_logs MODIFY panel_user_id INT UNSIGNED DEFAULT NULL");
        }
        $m->replaceForeignKey('activity_logs', 'fk_logs_user', 'panel_user_id', 'panel_users', 'id', 'SET NULL');
    },
];
```

## P2.6 Must NOT do (Phase 2)

| Forbidden |
|---|
| Touching the avatar upload block of `profile.php`, any other upload code, `licenses/*`, `sales/*`, the builder, the dashboard. |
| `DELETE FROM panel_users` anywhere in application code (soft delete only). Dropping the legacy `pages`/`articles` tables (P5). |
| Changing `permissions` rows or the permission model. Adding a `roles` table. |
| Hashing passwords with anything but `hashPassword()`; storing or logging a password, hash or session id. |
| Running `0003` before `remove.php` is replaced; running either migration without the clone test first. |
| Showing different messages for "unknown user" and "wrong password"; revealing which of the two locks applied beyond `STR_LOCK_IDENTIFIER` / `STR_LOCK_IP`. |
| Executing the CHECK/FK experiments of the gate on `business_panel` (clone only). |

## P2.7 Gate / checklist

| # | Check |
|---|---|
| 1 | `php tests/run_all.php` exit 0; `php -l` on every changed file; `grep -rn "user_role" public includes` prints nothing; Arabic-letter grep (Rule 6) clean. |
| 2 | `0002` ran on the clone and on `business_panel`; `diagnose.php --phase=2`: D05 (WARN allowed), D19, D20 PASS. After `0003`: D11 (phase-2 FKs) PASS. |
| 3 | **C2:** log in with the seed owner (`admin` / the seed password, if it is still in use) ⇒ redirected to `/modules/settings/profile.php?must_change=1`; `/modules/dashboard/index.php` ⇒ 302 to the profile; an `/api/…` call ⇒ JSON 403 with `must_change_password: true`; changing to `Admin123!` ⇒ `STR_PW_FORBIDDEN`; changing to a good password ⇒ `STR_PW_CHANGED`, flag becomes 0, dashboard opens. |
| 4 | **M6-4:** two browsers logged in as the same user; change the password in browser A ⇒ browser B's next request redirects to `/login.php?error=invalid`; A stays logged in. |
| 5 | **H8 (identifier+IP):** insert 5 failed rows for identifier `admin` with ip `9.9.9.9` (note their ids) ⇒ logging in as `admin` with the right password from your own IP **succeeds**; 5 real failures from your IP ⇒ `STR_LOCK_IDENTIFIER`; 20 failed rows (any identifiers) for one IP ⇒ `STR_LOCK_IP` from that IP. Delete your test rows by id. |
| 6 | **H8 (reset):** 4 failures, 1 success, 1 failure ⇒ not locked. A 1 000-character identifier ⇒ normal "wrong password" page, no 500. |
| 7 | **H8 (timing parity):** code review shows `password_verify` runs in both branches. ⏭️ is acceptable for a measured comparison (jitter), with that reason. |
| 8 | **M8:** `a@b` identifier looks up e-mail only; profile with invalid e-mail **and** wrong current password saves nothing; invalid theme value keeps the old theme; each profile message equals its `STR_*` text. |
| 9 | **C1:** remove a staff member who owns a canvas and a media row ⇒ row has `status='removed'`, `username='removed-<id>'`, no permission rows; canvas, media and their `activity_logs` rows still exist; the old credentials no longer log in; inviting the **same** username/e-mail again works. |
| 10 | **C1 FK (clone only):** after `0003`, `DELETE FROM panel_users WHERE id=<staff with content>` fails with error 1451. |
| 11 | **D13:** a newly invited staff member's first login lands on the profile page and cannot reach anything else until the password is changed. |
| 12 | `activity_logs` has `login`, `logout`, `profile_update`, `password_change`, `staff_remove` rows; `purge_logs.php --dry-run` prints two counts; `reset_owner_password.php` works on the clone (and the user's old sessions are then stale). |
| 13 | **Owner regression:** owner opens every module, invites, edits permissions, suspends/activates, saves a canvas, uploads media. |

Append the P2 section to the changelog before starting P3.

---

# PHASE 3 — Builder foundation hardening and builder DB integrity (migration `0004`)

**Goal:** make the builder safe **before Part 4** puts real HTML and URLs into it: mandatory sanitize hook (H7), media references with a real FK (M5), optimistic locking (M13), same-canvas parent FK (M2), template FK (M1), exact `block_uid` (L3), slug race (L11), JSON slashes. Order: **`0004` [pre-code] → code → tests → gate.** There is no post-code migration in P3.

## P3.1 File map

```
panel/
├── database/migrations/0004_builder_integrity_pre_code.php   N  [pre-code]
├── includes/builder/
│   ├── block_registry.php         M  sanitize mandatory for leaf types; extract_media hook; sanitizeData(); extractMedia()
│   ├── canvas_repository.php      M  version + conflict exception; slug retry; sanitize + media refs; JSON_UNESCAPED_SLASHES
│   ├── media_refs.php             N  BuilderMediaRefs (existingIds, sync, canvasesUsing, countUsing)
│   ├── html_sanitizer.php         N  BuilderHtmlSanitizer (whitelist, DOM based)
│   └── bootstrap.php              M  + two require_once lines
├── public/api/
│   ├── builder/save.php           M  expected_version, 409, 400 on bad block data
│   ├── builder/load.php           M  returns version (P1's templates.view change is included — the file below is the whole file)
│   └── media/delete.php           M  409 with page titles, DELETE row first then unlink, no "force"
├── public/assets/js/builder/builder-shell.js   M  401 = expired; send expected_version
└── tests/
    ├── html_sanitizer_test.php    N
    └── builder_registry_test.php  N
```

## P3.2 Migration `0004` [pre-code] — run FIRST

Preflight refuses to run when a block's parent lives in another canvas (diagnostic D08 — fix by hand, re-run). Then: `block_uid` → `CHAR(32) ascii_bin` (exact match); `builder_canvases.version`; template FK → `CASCADE` (a template's canvas must not outlive it); composite FK `fk_builder_blocks_parent_same_canvas (canvas_id, parent_id) → (canvas_id, id) ON DELETE CASCADE` replacing the single-column parent FK (root blocks have `parent_id NULL` and are exempt); table `builder_media_refs` with `media_id … ON DELETE RESTRICT` (the database itself refuses to delete a file that a page uses). The index that MySQL auto-created for the old parent FK stays — harmless.

```php
<?php
// Phase 3 — [pre-code]: additive or rule-tightening with a preflight; run FIRST in P3.
return [
    'description' => 'Builder: block_uid ascii_bin, canvases.version, template FK CASCADE, composite parent FK, builder_media_refs',
    'up' => function (PDO $pdo, Migrator $m): void {
        // Preflight (diagnostics D07/D08): refuse to tighten constraints over inconsistent data. Fix the rows by hand, re-run.
        $badCross = $m->count("SELECT COUNT(*) FROM builder_blocks b JOIN builder_blocks p ON p.id = b.parent_id WHERE p.canvas_id <> b.canvas_id");
        if ($badCross > 0) {
            throw new RuntimeException("Preflight failed: $badCross builder_blocks row(s) have a parent in another canvas (diagnostic D08). Fix them, then re-run.");
        }

        // 1) L3: exact, case-sensitive, ASCII-only uid
        if ($m->collation('builder_blocks', 'block_uid') !== 'ascii_bin') {
            $pdo->exec("ALTER TABLE builder_blocks MODIFY block_uid CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL");
        }

        // 2) M13: optimistic-lock counter
        if (!$m->columnExists('builder_canvases', 'version')) {
            $pdo->exec("ALTER TABLE builder_canvases ADD COLUMN version INT UNSIGNED NOT NULL DEFAULT 1 AFTER status");
        }

        // 3) M1: a template's canvas must not outlive the template
        $m->replaceForeignKey('builder_canvases', 'fk_builder_canvases_template', 'template_id', 'templates', 'id', 'CASCADE');

        // 4) M2: a block's parent must live in the same canvas (composite FK; NULL parent = root is exempt)
        $m->ensureIndex('builder_blocks', 'uq_blocks_canvas_id', '(canvas_id, id)', true);
        if (!$m->fkExists('builder_blocks', 'fk_builder_blocks_parent_same_canvas')) {
            if ($m->fkExists('builder_blocks', 'fk_builder_blocks_parent')) {
                $pdo->exec("ALTER TABLE builder_blocks DROP FOREIGN KEY fk_builder_blocks_parent");
            }
            $pdo->exec("ALTER TABLE builder_blocks
                        ADD CONSTRAINT fk_builder_blocks_parent_same_canvas
                        FOREIGN KEY (canvas_id, parent_id) REFERENCES builder_blocks (canvas_id, id) ON DELETE CASCADE");
        }

        // 5) M5: which media files a canvas uses; the DB itself refuses to delete a file that is in use
        if (!$m->tableExists('builder_media_refs')) {
            $pdo->exec("CREATE TABLE builder_media_refs (
                canvas_id INT UNSIGNED NOT NULL,
                media_id  INT UNSIGNED NOT NULL,
                PRIMARY KEY (canvas_id, media_id),
                KEY idx_bmr_media (media_id),
                CONSTRAINT fk_bmr_canvas FOREIGN KEY (canvas_id) REFERENCES builder_canvases(id) ON DELETE CASCADE,
                CONSTRAINT fk_bmr_media  FOREIGN KEY (media_id)  REFERENCES media_library(id)    ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
    },
];
```

Clone test (Rule 12) is **mandatory** here: the composite FK is the only DDL of this prompt whose behaviour differs between MySQL and MariaDB versions. If the clone run fails on it, stop, record the error text in the changelog, and ask Pouya (the rest of `0004` is independent: you may ship the migration without that one step only with his approval).

## P3.3 Code

**`includes/builder/block_registry.php`** — replace the whole file (reference implementation, unit-tested). New: `register()` **throws** for a leaf type (`allows_children=false`) without a `sanitize` callable and validates `extract_media`; `sanitizeData(string $type, stdClass $data, PDO $pdo): stdClass` and `extractMedia(string $type, stdClass $data): array`.
```php
<?php
// Server-side block type registry for the visual page builder (Part 0).

final class BuilderBlockRegistry
{
    /** @var array<string, array> */
    private static array $types = [];

    /**
     * @param array $definition Registry definition per master prompt §8.3.
     * @throws InvalidArgumentException
     */
    public static function register(array $definition): void
    {
        $required = [
            'type' => 'string',
            'label' => 'string',
            'icon' => 'string',
            'category' => 'string',
            'allows_children' => 'boolean',
            'min_children' => 'integer',
            'max_children' => 'integer',
            'supports_text_formatting' => 'boolean',
            'allowed_child_types' => 'array',
            'allowed_parent_types' => 'array',
            'default_data' => 'array',
        ];

        foreach ($required as $key => $phpType) {
            if (!array_key_exists($key, $definition)) {
                throw new InvalidArgumentException('Missing required registry key: ' . $key);
            }
            $actual = gettype($definition[$key]);
            if ($actual !== $phpType) {
                throw new InvalidArgumentException('Registry key "' . $key . '" must be of type ' . $phpType);
            }
        }

        $type = $definition['type'];
        if (!is_string($type) || !preg_match('/^[a-z][a-z0-9_]{1,49}$/', $type)) {
            throw new InvalidArgumentException('Invalid block type name');
        }

        if (isset(self::$types[$type])) {
            throw new InvalidArgumentException('Block type already registered: ' . $type);
        }

        if ($definition['category'] !== 'structure' && $definition['category'] !== 'content') {
            throw new InvalidArgumentException('Invalid block category');
        }

        $allowsChildren = $definition['allows_children'];
        $minChildren = $definition['min_children'];
        $maxChildren = $definition['max_children'];

        if ($minChildren < 0 || $maxChildren < 0) {
            throw new InvalidArgumentException('min_children and max_children must be >= 0');
        }

        if ($allowsChildren === false && ($minChildren !== 0 || $maxChildren !== 0)) {
            throw new InvalidArgumentException('A type with allows_children=false must have min_children=0 and max_children=0');
        }

        if ($maxChildren !== 0 && $maxChildren < $minChildren) {
            throw new InvalidArgumentException('max_children must not be smaller than min_children');
        }

        foreach (['allowed_child_types', 'allowed_parent_types'] as $listKey) {
            $list = $definition[$listKey];
            foreach ($list as $entry) {
                if (!is_string($entry) || !preg_match('/^[a-z][a-z0-9_]{1,49}$/', $entry)) {
                    throw new InvalidArgumentException('Invalid entry in ' . $listKey);
                }
            }
        }

        if ($allowsChildren === false && count($definition['allowed_child_types']) > 0) {
            throw new InvalidArgumentException('A type with allows_children=false must have an empty allowed_child_types');
        }

        if (array_key_exists('sanitize', $definition) && $definition['sanitize'] !== null && !is_callable($definition['sanitize'])) {
            throw new InvalidArgumentException('sanitize must be callable or null');
        }

        if (array_key_exists('extract_media', $definition) && $definition['extract_media'] !== null && !is_callable($definition['extract_media'])) {
            throw new InvalidArgumentException('extract_media must be callable or null');
        }

        // H7: every leaf type (allows_children=false) holds user content and MUST define a sanitize hook.
        if ($allowsChildren === false && (!array_key_exists('sanitize', $definition) || $definition['sanitize'] === null)) {
            throw new InvalidArgumentException('A type with allows_children=false must define a sanitize callable');
        }

        // Referenced types in allowed_child_types / allowed_parent_types are
        // intentionally NOT checked here: types load from a glob in
        // alphabetical order, so a definition may reference a type that
        // registers later in the same bootstrap.

        self::$types[$type] = $definition;
    }

    public static function get(string $type): ?array
    {
        return self::$types[$type] ?? null;
    }

    /** @return array<string, array> */
    public static function all(): array
    {
        return self::$types;
    }

    /** @return array<string, array> */
    public static function insertable(): array
    {
        $out = [];
        foreach (self::$types as $type => $definition) {
            if ($definition['category'] === 'content') {
                $out[$type] = $definition;
            }
        }
        return $out;
    }

    public static function exists(string $type): bool
    {
        return isset(self::$types[$type]);
    }

    /**
     * Runs the type's sanitize hook. Hook signature: function (stdClass $data, PDO $pdo): stdClass.
     * The hook may throw InvalidArgumentException to reject the data (save.php answers 400).
     * @throws InvalidArgumentException
     */
    public static function sanitizeData(string $type, stdClass $data, PDO $pdo): stdClass
    {
        $definition = self::$types[$type] ?? null;
        if ($definition === null || empty($definition['sanitize'])) {
            return $data;
        }
        $result = $definition['sanitize']($data, $pdo);
        if (!($result instanceof stdClass)) {
            throw new InvalidArgumentException('sanitize must return a stdClass');
        }
        return $result;
    }

    /**
     * Runs the type's extract_media hook. Hook signature: function (stdClass $data): int[] (media_library ids).
     * @return int[] unique positive ids
     * @throws InvalidArgumentException
     */
    public static function extractMedia(string $type, stdClass $data): array
    {
        $definition = self::$types[$type] ?? null;
        if ($definition === null || empty($definition['extract_media'])) {
            return [];
        }
        $ids = $definition['extract_media']($data);
        if (!is_array($ids)) {
            throw new InvalidArgumentException('extract_media must return an array');
        }
        $unique = [];
        foreach ($ids as $id) {
            if (!is_int($id) || $id < 1) {
                throw new InvalidArgumentException('extract_media must return positive integers');
            }
            $unique[$id] = $id;
        }
        return array_values($unique);
    }

    public static function defaultData(string $type): stdClass
    {
        if (!isset(self::$types[$type])) {
            return new stdClass();
        }
        $default = self::$types[$type]['default_data'];
        if (empty($default)) {
            return new stdClass();
        }
        return (object) $default;
    }
}
```

**`includes/builder/html_sanitizer.php`** (new; whitelist sanitizer for rich text — tags `b strong i em u a br p ul ol li h2 h3`, `href` only `http/https/mailto/tel`, links get `rel="noopener noreferrer"`, everything else is dropped; `safeUrl()` is reusable for button links):
```php
<?php
// Whitelist HTML sanitizer for builder text blocks (rich text from contenteditable).
// Pure: no DB, no session. Output is re-serialised from a whitelisted DOM walk, never copied from the input.

final class BuilderHtmlSanitizer
{
    public const DEFAULT_TAGS = ['b', 'strong', 'i', 'em', 'u', 'a', 'br', 'p', 'ul', 'ol', 'li', 'h2', 'h3'];
    public const DEFAULT_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /** Removed together with everything inside them. */
    private const DROP_WITH_CONTENT = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'svg', 'math',
        'template', 'noscript', 'textarea', 'select', 'option', 'form', 'input', 'button',
        'link', 'meta', 'base', 'title', 'head', 'audio', 'video', 'canvas',
    ];

    private const MAX_DEPTH = 30;

    /**
     * @param string[] $allowedTags lower-case tag names
     */
    public static function clean(string $html, array $allowedTags = self::DEFAULT_TAGS, int $maxBytes = 20000): string
    {
        $html = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $html);
        if ($html === null || trim($html) === '') {
            return '';
        }
        if (strlen($html) > $maxBytes) {
            $html = mb_strcut($html, 0, $maxBytes, 'UTF-8');
        }

        $previous = libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->loadHTML(
            '<?xml encoding="UTF-8"><div id="sanitizer-root">' . $html . '</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($doc);
        $rootList = $xpath->query('//div[@id="sanitizer-root"]');
        if ($rootList === false || $rootList->length !== 1) {
            return '';
        }

        return trim(self::walk($rootList->item(0), array_flip($allowedTags), 0));
    }

    /** Returns the URL when it is safe for an href, otherwise null. */
    public static function safeUrl(string $url, array $schemes = self::DEFAULT_SCHEMES): ?string
    {
        // Remove every whitespace/control character so "java\tscript:" cannot hide a scheme.
        $u = preg_replace('/[\x00-\x20\x7F]+/', '', $url);
        if ($u === null || $u === '' || strlen($u) > 2000) {
            return null;
        }
        if (strpos($u, '\\') !== false) {
            return null; // "/\evil.com" is treated as "//evil.com" by browsers
        }
        if (preg_match('/^([A-Za-z][A-Za-z0-9+.\-]*):/', $u, $m)) {
            return in_array(strtolower($m[1]), $schemes, true) ? $u : null;
        }
        if (strpos($u, '//') === 0) {
            return null; // protocol-relative
        }
        if ($u[0] === '/' || $u[0] === '#') {
            return $u; // site-relative path or in-page anchor
        }
        return null;
    }

    private static function walk(DOMNode $parent, array $allowed, int $depth): string
    {
        $out = '';
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMText) {
                $out .= htmlspecialchars($child->nodeValue ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                continue;
            }
            if (!($child instanceof DOMElement)) {
                continue; // comments, CDATA, processing instructions
            }
            $name = strtolower($child->nodeName);
            if (in_array($name, self::DROP_WITH_CONTENT, true)) {
                continue;
            }
            if ($depth >= self::MAX_DEPTH) {
                $out .= htmlspecialchars($child->textContent ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                continue;
            }
            if ($name === 'div' && isset($allowed['p'])) {
                $name = 'p'; // contenteditable wraps lines in <div>
            }
            if (!isset($allowed[$name])) {
                $out .= self::walk($child, $allowed, $depth + 1); // unwrap unknown tags, keep their text
                continue;
            }
            if ($name === 'br') {
                $out .= '<br>';
                continue;
            }
            $inner = self::walk($child, $allowed, $depth + 1);
            if ($name === 'a') {
                $href = self::safeUrl($child->getAttribute('href'));
                if ($href === null) {
                    $out .= $inner;
                } else {
                    $out .= '<a href="' . htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" rel="noopener noreferrer">' . $inner . '</a>';
                }
                continue;
            }
            $out .= '<' . $name . '>' . $inner . '</' . $name . '>';
        }
        return $out;
    }
}
```

**`includes/builder/media_refs.php`** (new):
```php
<?php
// Tracks which media_library rows a canvas uses (table builder_media_refs, FK media_id ... ON DELETE RESTRICT).
// All write methods run inside the caller's transaction (BuilderCanvasRepository::save).

final class BuilderMediaRefs
{
    /**
     * @param int[] $ids
     * @return int[] the subset of $ids that exist in media_library
     */
    public static function existingIds(PDO $pdo, array $ids): array
    {
        $ids = self::uniqueInts($ids);
        if (!$ids) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare('SELECT id FROM media_library WHERE id IN (' . $in . ')');
        $stmt->execute($ids);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Replaces the reference set of one canvas. Throws InvalidArgumentException when an id is unknown.
     * @param int[] $mediaIds
     */
    public static function sync(PDO $pdo, int $canvasId, array $mediaIds): void
    {
        $mediaIds = self::uniqueInts($mediaIds);
        if ($mediaIds && count(self::existingIds($pdo, $mediaIds)) !== count($mediaIds)) {
            throw new InvalidArgumentException('Unknown media id');
        }
        $pdo->prepare('DELETE FROM builder_media_refs WHERE canvas_id = ?')->execute([$canvasId]);
        if ($mediaIds) {
            $insert = $pdo->prepare('INSERT INTO builder_media_refs (canvas_id, media_id) VALUES (?, ?)');
            foreach ($mediaIds as $mediaId) {
                $insert->execute([$canvasId, $mediaId]);
            }
        }
    }

    /** @return array<int, array{id:int,title:string}> canvases that use this media file (at most $limit) */
    public static function canvasesUsing(PDO $pdo, int $mediaId, int $limit = 6): array
    {
        $limit = max(1, min(50, $limit));
        $stmt = $pdo->prepare('SELECT c.id, c.title FROM builder_media_refs r JOIN builder_canvases c ON c.id = r.canvas_id WHERE r.media_id = ? ORDER BY c.id LIMIT ' . $limit);
        $stmt->execute([$mediaId]);
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = ['id' => (int)$row['id'], 'title' => (string)$row['title']];
        }
        return $rows;
    }

    public static function countUsing(PDO $pdo, int $mediaId): int
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM builder_media_refs WHERE media_id = ?');
        $stmt->execute([$mediaId]);
        return (int)$stmt->fetchColumn();
    }

    /** @return int[] */
    private static function uniqueInts(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            $i = (int)$id;
            if ($i > 0) {
                $out[$i] = $i;
            }
        }
        return array_values($out);
    }
}
```

**`includes/builder/canvas_repository.php`** — replace the whole file (reference implementation; behaviour verified against SQLite by the author, **not** against MySQL — the clone run is your evidence). New: `BuilderVersionConflictException`; `save(PDO $pdo, ?int $canvasId, array $payload, int $userId, ?int $expectedVersion = null)`; update uses `UPDATE … SET version = version + 1 … WHERE id = ? AND version = ?` and a row count ≠ 1 throws the conflict exception; page **and** template slug race retries on error 1062; each block's `data` goes through `sanitizeData()` and its media ids are collected with `extractMedia()`; refs are synced after the blocks are inserted, inside the same transaction; `json_encode(..., JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)` (MariaDB stores JSON as text, so `\/uploads\/…` would otherwise end up in the table); `formatCanvas()` returns `version`.
```php
<?php
// Canvas persistence for the visual page builder (Part 0).
// Owns the save transaction: canvas row + full block-tree replace.

/** Thrown by save() when expected_version no longer matches the stored version (someone else saved first). */
final class BuilderVersionConflictException extends RuntimeException
{
}

final class BuilderCanvasRepository
{
    /** A fresh [header, body, footer] tree with new uids. */
    public static function defaultTree(): array
    {
        $tree = [];
        foreach (['header', 'body', 'footer'] as $type) {
            $tree[] = [
                'uid' => bin2hex(random_bytes(16)),
                'type' => $type,
                'data' => BuilderBlockRegistry::defaultData($type),
                'children' => [],
            ];
        }
        return $tree;
    }

    /**
     * @return array{canvas: array, tree: array}|null
     */
    public static function load(PDO $pdo, int $canvasId): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM builder_canvases WHERE id = ?');
        $stmt->execute([$canvasId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $stmt = $pdo->prepare('SELECT id, parent_id, block_uid, block_type, data FROM builder_blocks WHERE canvas_id = ? ORDER BY parent_id IS NULL DESC, parent_id ASC, sort_order ASC');
        $stmt->execute([$canvasId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            return ['canvas' => self::formatCanvas($row), 'tree' => self::defaultTree()];
        }

        // Single pass: index by id, attach children, collect roots.
        $byId = [];
        foreach ($rows as $r) {
            $data = json_decode($r['data'], false);
            if (!($data instanceof stdClass)) {
                $data = BuilderBlockRegistry::defaultData($r['block_type']);
            }
            $byId[(int) $r['id']] = [
                'uid' => $r['block_uid'],
                'type' => $r['block_type'],
                'data' => $data,
                'children' => [],
                '_parent_id' => $r['parent_id'] === null ? null : (int) $r['parent_id'],
            ];
        }

        $tree = [];
        foreach ($byId as $id => &$node) {
            $parentId = $node['_parent_id'];
            unset($node['_parent_id']);
            if ($parentId === null || !isset($byId[$parentId])) {
                $tree[] = &$node;
            } else {
                $byId[$parentId]['children'][] = &$node;
            }
        }
        unset($node);

        // Re-index children lists so they encode as JSON arrays.
        $reindex = function (array &$nodes) use (&$reindex): void {
            $nodes = array_values($nodes);
            foreach ($nodes as &$n) {
                if (!empty($n['children'])) {
                    $reindex($n['children']);
                } else {
                    $n['children'] = [];
                }
            }
            unset($n);
        };
        $reindex($tree);

        return ['canvas' => self::formatCanvas($row), 'tree' => $tree];
    }

    /**
     * @return array{canvas: array, tree: array}|null
     */
    public static function findByTemplate(PDO $pdo, int $templateId): ?array
    {
        $stmt = $pdo->prepare('SELECT id FROM builder_canvases WHERE template_id = ? LIMIT 1');
        $stmt->execute([$templateId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return self::load($pdo, (int) $row['id']);
    }

    /**
     * Creates or replaces one canvas plus its whole block tree, transactionally.
     * Called only after validation has passed.
     *
     * $payload keys: buildable_type (string), template_id (?int),
     *                title (string), status (string), tree (array of stdClass nodes).
     * $expectedVersion is REQUIRED when updating ($canvasId !== null) and ignored when creating.
     *
     * @return array{canvas: array, block_count: int, buildable_type_ignored: bool}
     * @throws BuilderVersionConflictException when the canvas was saved by someone else since it was loaded
     * @throws InvalidArgumentException when a block's sanitize hook rejects its data or a referenced media id does not exist
     */
    public static function save(PDO $pdo, ?int $canvasId, array $payload, int $userId, ?int $expectedVersion = null): array
    {
        if ($canvasId !== null && ($expectedVersion === null || $expectedVersion < 1)) {
            throw new InvalidArgumentException('expected_version is required when updating a canvas');
        }
        $buildableTypeIgnored = false;
        $stored = null;

        if ($canvasId !== null) {
            $stmt = $pdo->prepare('SELECT id, slug, status, published_at, buildable_type, template_id FROM builder_canvases WHERE id = ?');
            $stmt->execute([$canvasId]);
            $stored = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$stored) {
                throw new RuntimeException('Canvas not found');
            }
        }

        $pdo->beginTransaction();
        try {
            if ($stored === null) {
                // New canvas: insert with slug = NULL; the slug is set in step 4.
                $templateId = $payload['template_id'] ?? null;
                $stmt = $pdo->prepare('INSERT INTO builder_canvases (buildable_type, template_id, title, slug, status, created_by, updated_by) VALUES (?,?,?,?,?,?,?)');
                $stmt->execute([
                    $payload['buildable_type'],
                    $templateId,
                    $payload['title'],
                    null,
                    $payload['status'],
                    $userId,
                    $userId,
                ]);
                $canvasId = (int) $pdo->lastInsertId();
                $storedBuildableType = $payload['buildable_type'];
                $storedTemplateId = $templateId === null ? null : (int) $templateId;
                $storedSlug = null;
                $storedStatus = $payload['status'];
                $storedPublishedAt = null;
            } else {
                // Existing canvas: buildable_type and template_id are immutable.
                $storedBuildableType = $stored['buildable_type'];
                $storedTemplateId = $stored['template_id'] === null ? null : (int) $stored['template_id'];
                $storedSlug = $stored['slug'];
                $storedStatus = $stored['status'];
                $storedPublishedAt = $stored['published_at'];
                if ($payload['buildable_type'] !== $storedBuildableType) {
                    $buildableTypeIgnored = true;
                }
                // Optimistic lock: the row is only updated while its version still equals the one the client loaded.
                $stmt = $pdo->prepare('UPDATE builder_canvases SET title = ?, status = ?, updated_by = ?, updated_at = NOW(), version = version + 1 WHERE id = ? AND version = ?');
                $stmt->execute([$payload['title'], $payload['status'], $userId, $canvasId, $expectedVersion]);
                if ($stmt->rowCount() !== 1) {
                    throw new BuilderVersionConflictException('Canvas was modified by someone else');
                }
                $storedStatus = $payload['status'];
            }

            // Step 4: slug. A stored non-empty slug is never regenerated.
            if ($storedSlug === null || $storedSlug === '') {
                $candidate = function_exists('slugify') ? slugify($payload['title']) : '';
                if ($candidate === '') {
                    $candidate = 'canvas-' . $canvasId;
                }
                // The UNIQUE index is the real guard: if another request wins the race for a slug (error 1062),
                // move on to the next candidate instead of failing the whole save (L11).
                $slugStmt = $pdo->prepare('UPDATE builder_canvases SET slug = ? WHERE id = ?');
                $slugSet = false;
                for ($n = 1; $n <= 50 && !$slugSet; $n++) {
                    $try = $n === 1 ? $candidate : $candidate . '-' . $n;
                    if (self::slugTaken($pdo, $try, $canvasId)) {
                        continue;
                    }
                    try {
                        $slugStmt->execute([$try, $canvasId]);
                        $slugSet = true;
                    } catch (PDOException $e) {
                        if ((int) ($e->errorInfo[1] ?? 0) !== 1062) {
                            throw $e;
                        }
                    }
                }
                if (!$slugSet) {
                    $slugStmt->execute(['canvas-' . $canvasId, $canvasId]);
                }
            }

            // Step 5: full replace of the block rows.
            $stmt = $pdo->prepare('DELETE FROM builder_blocks WHERE canvas_id = ?');
            $stmt->execute([$canvasId]);

            // Step 6: insert the tree in pre-order, depth first.
            // The tree already passed validation (depth <= 8), so a bounded
            // recursive helper is safe here.
            $blockCount = 0;
            $mediaIds = [];
            $insertStmt = $pdo->prepare('INSERT INTO builder_blocks (canvas_id, parent_id, block_uid, block_type, sort_order, data) VALUES (?,?,?,?,?,?)');
            $insertNode = null;
            $insertNode = function (stdClass $node, ?int $parentId, int $sortOrder) use (&$insertNode, &$blockCount, &$mediaIds, $insertStmt, $pdo, $canvasId): void {
                // H7: the type's sanitize hook is the only thing allowed to decide what reaches the database.
                $data = BuilderBlockRegistry::sanitizeData($node->type, $node->data, $pdo);
                foreach (BuilderBlockRegistry::extractMedia($node->type, $data) as $mediaId) {
                    $mediaIds[$mediaId] = $mediaId;
                }
                // No JSON_UNESCAPED_SLASHES-less storage: "/uploads/x" must be stored as-is (M5).
                $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($encoded === false) {
                    throw new InvalidArgumentException('Block data cannot be encoded');
                }
                $insertStmt->execute([$canvasId, $parentId, $node->uid, $node->type, $sortOrder, $encoded]);
                $blockCount++;
                $newId = (int) $pdo->lastInsertId();
                foreach (array_values($node->children) as $childIndex => $child) {
                    $insertNode($child, $newId, $childIndex);
                }
            };
            foreach (array_values($payload['tree']) as $index => $node) {
                $insertNode($node, null, $index);
            }

            // Step 6b: media references (FK ... ON DELETE RESTRICT keeps in-use files from being deleted).
            BuilderMediaRefs::sync($pdo, $canvasId, array_values($mediaIds));

            // Step 7: title write-through, only for the ACTIVE template's canvas.
            if ($storedBuildableType === 'template' && $storedTemplateId !== null) {
                $stmt = $pdo->prepare('SELECT active_template_id FROM site_settings WHERE id = 1');
                $stmt->execute();
                $settings = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($settings && (int) $settings['active_template_id'] === (int) $storedTemplateId) {
                    $stmt = $pdo->prepare('UPDATE site_settings SET site_name = ? WHERE id = 1');
                    $stmt->execute([$payload['title']]);
                }
            }

            // Step 8: stamp published_at once.
            if ($storedStatus === 'published' && $storedPublishedAt === null) {
                $stmt = $pdo->prepare('UPDATE builder_canvases SET published_at = NOW() WHERE id = ?');
                $stmt->execute([$canvasId]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $fresh = self::load($pdo, $canvasId);
        return [
            'canvas' => $fresh['canvas'],
            'block_count' => $blockCount,
            'buildable_type_ignored' => $buildableTypeIgnored,
        ];
    }

    private static function slugTaken(PDO $pdo, string $slug, int $exceptId): bool
    {
        $stmt = $pdo->prepare('SELECT id FROM builder_canvases WHERE slug = ? AND id <> ? LIMIT 1');
        $stmt->execute([$slug, $exceptId]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /** Formats a canvas row per §8.2. */
    public static function formatCanvas(array $row): array
    {
        $updatedAt = $row['updated_at'] ?? null;
        $jalali = '-';
        if ($updatedAt !== null && function_exists('toJalaliDateTime')) {
            $jalali = toJalaliDateTime($updatedAt);
        }
        return [
            'id' => $row['id'] === null ? null : (int) $row['id'],
            'buildable_type' => $row['buildable_type'],
            'template_id' => $row['template_id'] === null ? null : (int) $row['template_id'],
            'title' => $row['title'],
            'slug' => $row['slug'],
            'status' => $row['status'],
            'version' => isset($row['version']) ? (int) $row['version'] : null,
            'published_at' => $row['published_at'],
            'updated_at' => $updatedAt,
            'updated_at_jalali' => $jalali,
        ];
    }
}
```

**`includes/builder/bootstrap.php`** — after `require_once $builderBase . '/canvas_repository.php';` add:
```php
    require_once $builderBase . '/html_sanitizer.php';
    require_once $builderBase . '/media_refs.php';
```
(both must load **before** `canvas_repository.php` is *used*, which they do; keep `builder_assets.php` and the `types/*.php` glob as they are.)

**`public/api/builder/save.php`** — replace the whole file (reference implementation). New: on update `expected_version` must be an integer ≥ 1 (else the existing "invalid request" path); `BuilderVersionConflictException` ⇒ **409** with `STR_VERSION_CONFLICT`; `InvalidArgumentException` from a sanitize hook or an unknown media id ⇒ **400** with `STR_BLOCK_INVALID` (details only in the error log).
```php
<?php
// POST /api/builder/save.php — creates or replaces one canvas plus its whole
// block tree, transactionally. Accepts a JSON request body (not a form post).
// This file is served from two depths (api/ and public/api/); the two copies
// are byte-identical. The project root is located by walking up until
// config/database.php is found, so no hard-coded depth is needed.

$builderBase = __DIR__;
while (!file_exists($builderBase . '/config/database.php') && dirname($builderBase) !== $builderBase) {
    $builderBase = dirname($builderBase);
}
require_once $builderBase . '/config/database.php';
require_once $builderBase . '/includes/auth.php';
require_once $builderBase . '/includes/permissions.php';
require_once $builderBase . '/includes/functions.php';
require_once $builderBase . '/includes/csrf.php';
require_once $builderBase . '/includes/builder/bootstrap.php';

// 1. Method must be POST.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'متد مجاز نیست'], 405);
}

// 2. Login first; the body is not parsed for an unauthenticated client.
requireLogin();

// 3. CSRF: token from the X-CSRF-TOKEN header, falling back to a csrf_token
// key inside the JSON body. When absent from the header, the decision is
// deferred until after step 5 (JSON decode).
$headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_X_CSRFTOKEN'] ?? null;
$csrfFromHeader = is_string($headerToken) && $headerToken !== '';
if ($csrfFromHeader && !csrf_verify($headerToken)) {
    jsonResponse(['success' => false, 'message' => 'توکن نامعتبر'], 403);
}

// 4. Size guard: reject bodies over 2 MB.
$maxBytes = 2 * 1024 * 1024;
$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > $maxBytes) {
    jsonResponse(['success' => false, 'message' => 'حجم دادهی ارسالی بیش از حد مجاز است'], 413);
}
$raw = file_get_contents('php://input', false, null, 0, $maxBytes + 1);
if ($raw === false) {
    $raw = '';
}
if (strlen($raw) > $maxBytes) {
    jsonResponse(['success' => false, 'message' => 'حجم دادهی ارسالی بیش از حد مجاز است'], 413);
}

// 5. Decode. Malformed JSON, or anything that is not a JSON object, is rejected.
$body = json_decode($raw, false);
if (!($body instanceof stdClass)) {
    jsonResponse(['success' => false, 'message' => 'ساختار ارسالی نامعتبر است'], 400);
}

// Deferred CSRF decision from step 3.
if (!$csrfFromHeader) {
    $bodyToken = isset($body->csrf_token) && is_string($body->csrf_token) ? $body->csrf_token : null;
    if (!csrf_verify($bodyToken)) {
        jsonResponse(['success' => false, 'message' => 'توکن نامعتبر'], 403);
    }
}

// Normalise canvas_id: null / absent / 0 => create; positive int => update.
$rawCanvasId = $body->canvas_id ?? null;
$isCreate = ($rawCanvasId === null || $rawCanvasId === 0);
$canvasId = null;
if (!$isCreate) {
    if (is_int($rawCanvasId) && $rawCanvasId > 0) {
        $canvasId = $rawCanvasId;
    } else {
        jsonResponse(['success' => false, 'message' => 'ساختار ارسالی نامعتبر است'], 400);
    }
}

$pdo = getPDO();

// 6. Permissions. pages.edit always; templates.edit additionally when the
// effective buildable_type is 'template' (stored on update, posted on create).
requirePermission('pages', 'edit');

$stored = null;
if (!$isCreate) {
    $stmt = $pdo->prepare('SELECT id, buildable_type, template_id, status FROM builder_canvases WHERE id = ?');
    $stmt->execute([$canvasId]);
    $stored = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$stored) {
        jsonResponse(['success' => false, 'message' => 'بوم یافت نشد'], 404);
    }
}

$postedBuildableType = $body->buildable_type ?? null;
$effectiveBuildableType = $isCreate ? $postedBuildableType : $stored['buildable_type'];
if ($effectiveBuildableType === 'template') {
    requirePermission('templates', 'edit');
}

// 7. Scalar field validation (first failure wins) plus the tree-is-an-array
// check and the template_id exists / immutable / duplicate checks.
$invalid = function () {
    jsonResponse(['success' => false, 'message' => 'ساختار ارسالی نامعتبر است'], 400);
};

// buildable_type.
if ($isCreate) {
    if ($postedBuildableType !== 'page' && $postedBuildableType !== 'template') {
        $invalid();
    }
    $buildableType = $postedBuildableType;
} else {
    $buildableType = $postedBuildableType;
    if ($buildableType !== 'page' && $buildableType !== 'template') {
        // Missing or invalid on update: fall back to the stored (immutable) value.
        $buildableType = $stored['buildable_type'];
    }
}

// template_id. Immutable after creation.
$templateIdPosted = array_key_exists('template_id', (array) $body) ? $body->template_id : null;
if ($templateIdPosted !== null && !is_int($templateIdPosted)) {
    $invalid();
}
if ($isCreate) {
    if ($buildableType === 'page') {
        if ($templateIdPosted !== null) {
            $invalid();
        }
        $templateId = null;
    } else {
        if (!is_int($templateIdPosted) || $templateIdPosted <= 0) {
            $invalid();
        }
        $stmt = $pdo->prepare('SELECT id FROM templates WHERE id = ? LIMIT 1');
        $stmt->execute([$templateIdPosted]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $invalid();
        }
        $stmt = $pdo->prepare('SELECT id FROM builder_canvases WHERE template_id = ? LIMIT 1');
        $stmt->execute([$templateIdPosted]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            jsonResponse(['success' => false, 'message' => 'برای این قالب قبلاً یک بوم ساخته شده است'], 409);
        }
        $templateId = $templateIdPosted;
    }
} else {
    $storedTemplateId = $stored['template_id'] === null ? null : (int) $stored['template_id'];
    // Absent key means "unchanged"; any posted value differing from stored is rejected.
    if (array_key_exists('template_id', (array) $body) && $templateIdPosted !== $storedTemplateId) {
        $invalid();
    }
    $templateId = $storedTemplateId;
}

// title: required, trimmed, 1-255 chars.
if (!isset($body->title) || !is_string($body->title)) {
    $invalid();
}
$title = trim($body->title);
if ($title === '' || mb_strlen($title, 'UTF-8') > 255) {
    $invalid();
}

// status: draft | published; absent => draft on create, unchanged on update.
if (!array_key_exists('status', (array) $body) || $body->status === null) {
    $status = $isCreate ? 'draft' : $stored['status'];
} elseif ($body->status === 'draft' || $body->status === 'published') {
    $status = $body->status;
} else {
    $invalid();
}

// tree must be a JSON array (a PHP array after decoding with assoc=false).
if (!array_key_exists('tree', (array) $body) || !is_array($body->tree)) {
    $invalid();
}
$tree = $body->tree;

// 8. Tree validation.
$treeErrors = BuilderTreeValidator::validate($tree);
if (!empty($treeErrors)) {
    jsonResponse(['success' => false, 'message' => $treeErrors[0], 'errors' => array_values($treeErrors)], 400);
}

// 8b. Optimistic lock: updates must say which version they were loaded from (M13).
$expectedVersion = null;
if (!$isCreate) {
    $rawVersion = $body->expected_version ?? null;
    if (!is_int($rawVersion) || $rawVersion < 1) {
        $invalid();
    }
    $expectedVersion = $rawVersion;
}

// 9. Save.
try {
    $result = BuilderCanvasRepository::save($pdo, $canvasId, [
        'buildable_type' => $buildableType,
        'template_id' => $templateId,
        'title' => $title,
        'status' => $status,
        'tree' => $tree,
    ], (int) $_SESSION['user_id'], $expectedVersion);
} catch (BuilderVersionConflictException $e) {
    jsonResponse(['success' => false, 'message' => 'این صفحه توسط شخص دیگری تغییر کرده است. صفحه را دوباره بارگذاری کنید.'], 409);
} catch (InvalidArgumentException $e) {
    error_log('builder/save.php: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'داده‌ی یکی از بلاک‌ها نامعتبر است'], 400);
} catch (PDOException $e) {
    error_log('builder/save.php: ' . $e->getMessage());
    $sqlState = $e->getCode();
    $driverCode = $e->errorInfo[1] ?? null;
    if (($sqlState === '23000' || (string) $sqlState === '23000') && (int) $driverCode === 1062 && $buildableType === 'template') {
        jsonResponse(['success' => false, 'message' => 'برای این قالب قبلاً یک بوم ساخته شده است'], 409);
    }
    jsonResponse(['success' => false, 'message' => 'خطا در ذخیرهسازی'], 500);
} catch (RuntimeException $e) {
    if ($e->getMessage() === 'Canvas not found') {
        jsonResponse(['success' => false, 'message' => 'بوم یافت نشد'], 404);
    }
    error_log('builder/save.php: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'خطا در ذخیرهسازی'], 500);
} catch (Throwable $e) {
    error_log('builder/save.php: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'خطا در ذخیرهسازی'], 500);
}

// 10. Activity log. A logging failure must never fail the save.
try {
    logActivity('builder_save', 'pages', 'ذخیره بوم ' . $result['canvas']['id']);
} catch (Throwable $e) {
    // Intentionally ignored.
}

// 11. Respond.
jsonResponse([
    'success' => true,
    'message' => 'بوم با موفقیت ذخیره شد',
    'data' => [
        'canvas' => $result['canvas'],
        'block_count' => $result['block_count'],
        'buildable_type_ignored' => $result['buildable_type_ignored'],
    ],
]);
```

**`public/api/builder/load.php`** — replace the whole file (P1's `templates.view` checks are already inside; new: `version => null` in the blank canvas shape, real `version` from `formatCanvas()`).
```php
<?php
// GET /api/builder/load.php — returns one canvas plus its block tree as JSON.
// This file is served from two depths (api/ and public/api/); the two copies
// are byte-identical. The project root is located by walking up until
// config/database.php is found, so no hard-coded depth is needed.

$builderBase = __DIR__;
while (!file_exists($builderBase . '/config/database.php') && dirname($builderBase) !== $builderBase) {
    $builderBase = dirname($builderBase);
}
require_once $builderBase . '/config/database.php';
require_once $builderBase . '/includes/auth.php';
require_once $builderBase . '/includes/permissions.php';
require_once $builderBase . '/includes/functions.php';
require_once $builderBase . '/includes/builder/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'متد مجاز نیست'], 405);
}

requireLogin();
requirePermission('pages', 'view');

$pdo = getPDO();

/** Builds an unsaved (id: null) canvas shape per §8.2. */
$blankCanvas = function (string $buildableType, ?int $templateId): array {
    return [
        'id' => null,
        'buildable_type' => $buildableType,
        'template_id' => $templateId,
        'title' => '',
        'slug' => null,
        'status' => 'draft',
        'version' => null,
        'published_at' => null,
        'updated_at' => null,
        'updated_at_jalali' => '-',
    ];
};

try {
    // Form 1: load by canvas id.
    if (isset($_GET['canvas_id']) && $_GET['canvas_id'] !== '') {
        $canvasId = filter_var($_GET['canvas_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($canvasId === false) {
            jsonResponse(['success' => false, 'message' => 'شناسه نامعتبر'], 400);
        }
        $result = BuilderCanvasRepository::load($pdo, (int) $canvasId);
        if ($result === null) {
            jsonResponse(['success' => false, 'message' => 'بوم یافت نشد'], 404);
        }
        if (($result['canvas']['buildable_type'] ?? '') === 'template') {
            requirePermission('templates', 'view');
        }
        jsonResponse(['success' => true, 'data' => $result]);
    }

    $buildableType = $_GET['buildable_type'] ?? null;
    $isNew = isset($_GET['new']) && (string) $_GET['new'] === '1';
    $templateIdRaw = $_GET['template_id'] ?? null;

    // Form 2: blank unsaved canvas.
    if ($isNew) {
        if ($buildableType !== 'page' && $buildableType !== 'template') {
            jsonResponse(['success' => false, 'message' => 'شناسه نامعتبر'], 400);
        }
        $templateId = null;
        if ($templateIdRaw !== null && $templateIdRaw !== '') {
            $templateId = filter_var($templateIdRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($templateId === false) {
                jsonResponse(['success' => false, 'message' => 'شناسه نامعتبر'], 400);
            }
            $templateId = (int) $templateId;
        }
        if ($buildableType === 'page') {
            $templateId = null;
        }
        if ($buildableType === 'template') {
            requirePermission('templates', 'view');
        }
        jsonResponse([
            'success' => true,
            'data' => [
                'canvas' => $blankCanvas($buildableType, $templateId),
                'tree' => BuilderCanvasRepository::defaultTree(),
            ],
        ]);
    }

    // Form 3: load the canvas attached to a template (or a blank one).
    if ($buildableType === 'template' && $templateIdRaw !== null && $templateIdRaw !== '') {
        requirePermission('templates', 'view');
        $templateId = filter_var($templateIdRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($templateId === false) {
            jsonResponse(['success' => false, 'message' => 'شناسه نامعتبر'], 400);
        }
        $templateId = (int) $templateId;
        $stmt = $pdo->prepare('SELECT id FROM templates WHERE id = ? LIMIT 1');
        $stmt->execute([$templateId]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            jsonResponse(['success' => false, 'message' => 'بوم یافت نشد'], 404);
        }
        $result = BuilderCanvasRepository::findByTemplate($pdo, $templateId);
        if ($result === null) {
            $result = [
                'canvas' => $blankCanvas('template', $templateId),
                'tree' => BuilderCanvasRepository::defaultTree(),
            ];
        }
        jsonResponse(['success' => true, 'data' => $result]);
    }

    jsonResponse(['success' => false, 'message' => 'شناسه نامعتبر'], 400);
} catch (Throwable $e) {
    error_log('builder/load.php: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'خطا در بارگذاری'], 500);
}
```

**`public/api/media/delete.php`** — replace the whole file (reference implementation for this phase; P4 swaps the unlink for `FileStore::remove()` and adds the `purpose = 'library'` filter once that column exists). The legacy `need_force` response disappears; `media/index.php` already handles a plain error message, so the page needs no change in P3.
```php
<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/builder/media_refs.php';
requireLogin(); requirePermission('media','delete');
if($_SERVER['REQUEST_METHOD']!=='POST') jsonResponse(['success'=>false,'message'=>'متد مجاز نیست'],405);
if(!csrf_verify($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')){ jsonResponse(['success'=>false,'message'=>'توکن نامعتبر'],403); }
$id=(int)($_POST['id']??0);
if(!$id) jsonResponse(['success'=>false,'message'=>'شناسه نامعتبر'],400);
$pdo=getPDO();

/** 409 with the titles of the pages that still use the file. No "force" any more: the database itself refuses (FK RESTRICT). */
function mediaInUseResponse(PDO $pdo, int $id): void {
  $total=BuilderMediaRefs::countUsing($pdo,$id);
  $titles=array_map(function($c){ return $c['title']; }, BuilderMediaRefs::canvasesUsing($pdo,$id,6));
  $msg='این فایل در صفحه‌های زیر استفاده شده و قابل حذف نیست: '.implode('، ',$titles);
  if($total>count($titles)) $msg.=' و '.toPersianDigits($total-count($titles)).' مورد دیگر';
  jsonResponse(['success'=>false,'message'=>$msg],409);
}

$m=$pdo->prepare("SELECT id, file_path FROM media_library WHERE id=?"); $m->execute([$id]); $row=$m->fetch();
if(!$row) jsonResponse(['success'=>false,'message'=>'یافت نشد'],404);
if(BuilderMediaRefs::countUsing($pdo,$id)>0) mediaInUseResponse($pdo,$id);

// Order matters: DELETE the row first (the DB may still say no), unlink only after it succeeded.
try{
  $pdo->prepare("DELETE FROM media_library WHERE id=?")->execute([$id]);
}catch(PDOException $e){
  if((int)($e->errorInfo[1]??0)===1451){ mediaInUseResponse($pdo,$id); }   // lost a race with a save
  error_log('media/delete.php: '.$e->getMessage());
  jsonResponse(['success'=>false,'message'=>'خطا در حذف فایل'],500);
}
$path=(string)$row['file_path'];
if(strpos($path,'/uploads/')===0 && strpos($path,'..')===false){
  $abs=PUBLIC_PATH.$path;
  if(is_file($abs) && !@unlink($abs)) error_log('media/delete.php: could not unlink '.$abs);
}
logActivity('media_delete','media','حذف رسانه '.$id);
jsonResponse(['success'=>true,'message'=>'فایل حذف شد']);
```

**`public/assets/js/builder/builder-shell.js`** — two edits, nothing else:
1. In `readJsonResponse(response)`, make a 401 count as an expired session. The condition becomes
   `if (response.status === 401 || response.redirected || contentType.indexOf('application/json') === -1) { return { expired: true }; }`
   (since P1, `requireLogin()` answers an API call with JSON 401 instead of a redirect; without this the user would see a generic error instead of the existing "session expired" dialog).
2. In the `save()` function, the `body` object gets one more key, after `tree: tree`:
   `expected_version: (state.canvas && state.canvas.id !== null) ? state.canvas.version : null`
   (`state.canvas` is the whole canvas object the server returned, so it already carries `version`; after a successful save the same assignment refreshes it). A 409 falls into the existing error branch, which shows the server's message.

## P3.4 Tests

`tests/html_sanitizer_test.php` and `tests/builder_registry_test.php` (reference implementations, executed by the author):
```php
<?php
// Plain-PHP CLI test. Exit code 0 on success, 1 on failure.
require __DIR__ . '/../includes/builder/html_sanitizer.php';

$fail = 0;
function check(string $label, $actual, $expected): void {
    global $fail;
    if ($actual !== $expected) {
        $fail++;
        fwrite(STDERR, "FAIL: $label\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n");
    }
}
$c = static function (string $h): string { return BuilderHtmlSanitizer::clean($h); };

check('script dropped, onclick stripped', $c('<script>alert(1)</script><b onclick="x()">hi</b>'), '<b>hi</b>');
check('javascript: href unwrapped', $c('<a href="javascript:alert(1)">x</a>'), 'x');
check('obfuscated javascript:', $c("<a href=\" JaVa\tScRiPt:alert(1)\">x</a>"), 'x');
check('entity-encoded javascript:', $c('<a href="&#106;avascript:alert(1)">x</a>'), 'x');
check('data: href', $c('<a href="data:text/html;base64,AAAA">x</a>'), 'x');
check('https href kept + rel', $c('<a href="https://example.com/a?b=1&c=2" onclick="x">go</a>'), '<a href="https://example.com/a?b=1&amp;c=2" rel="noopener noreferrer">go</a>');
check('mailto kept', $c('<a href="mailto:a@b.co">m</a>'), '<a href="mailto:a@b.co" rel="noopener noreferrer">m</a>');
check('relative path kept', $c('<a href="/page/about">p</a>'), '<a href="/page/about" rel="noopener noreferrer">p</a>');
check('protocol-relative rejected', $c('<a href="//evil.com">p</a>'), 'p');
check('backslash trick rejected', $c('<a href="/\\evil.com">p</a>'), 'p');
check('img removed', $c('<img src=x onerror=alert(1)>'), '');
check('svg dropped with content', $c('a<svg><script>alert(1)</script></svg>b'), 'ab');
check('iframe dropped', $c('<iframe src="//evil"></iframe>ok'), 'ok');
check('div becomes p', $c('<div>line</div>'), '<p>line</p>');
check('br kept', $c('a<br>b'), 'a<br>b');
check('nested kept', $c('<b><i>x</i></b>'), '<b><i>x</i></b>');
check('unknown tag unwrapped', $c('<font color="red">t</font>'), 't');
check('unclosed closed', $c('<b>unclosed'), '<b>unclosed</b>');
check('escaped text stays text', $c('&lt;script&gt;alert(1)&lt;/script&gt;'), '&lt;script&gt;alert(1)&lt;/script&gt;');
check('persian preserved', $c('<p>سلام <b>دنیا</b></p>'), '<p>سلام <b>دنیا</b></p>');
check('noscript mXSS dropped', $c('<noscript><p title="</noscript><img src=x onerror=alert(1)>"></noscript>'), '');
check('comment dropped', $c('a<!-- x -->b'), 'ab');
check('empty', $c('   '), '');
check('NUL stripped', $c("a\0b"), 'ab');
check('style attr stripped', $c('<p style="position:fixed">x</p>'), '<p>x</p>');
check('safeUrl bare relative rejected', BuilderHtmlSanitizer::safeUrl('foo.html'), null);
check('safeUrl anchor', BuilderHtmlSanitizer::safeUrl('#top'), '#top');
check('safeUrl tel', BuilderHtmlSanitizer::safeUrl('tel:+98912'), 'tel:+98912');
check('safeUrl vbscript', BuilderHtmlSanitizer::safeUrl('vbscript:x'), null);
$deep = str_repeat('<b>', 200) . 'x' . str_repeat('</b>', 200);
check('deep nesting does not crash', is_string($c($deep)), true);
check('oversize truncated not crash', is_string(BuilderHtmlSanitizer::clean(str_repeat('<b>a</b>', 10000), BuilderHtmlSanitizer::DEFAULT_TAGS, 1000)), true);

if ($fail > 0) { fwrite(STDERR, "$fail failure(s)\n"); exit(1); }
echo "html_sanitizer_test: OK\n";
```
```php
<?php
require __DIR__ . '/../includes/builder/block_registry.php';
$fail = 0;
function check(string $label, $actual, $expected): void {
    global $fail;
    if ($actual !== $expected) { $fail++; fwrite(STDERR, "FAIL: $label\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n"); }
}
function def(string $type, array $extra = []): array {
    return array_merge([
        'type' => $type, 'label' => 'x', 'icon' => 'bi-x', 'category' => 'content',
        'allows_children' => false, 'min_children' => 0, 'max_children' => 0,
        'supports_text_formatting' => false, 'allowed_child_types' => [], 'allowed_parent_types' => [],
        'default_data' => [],
    ], $extra);
}
function throws(callable $fn): bool { try { $fn(); } catch (InvalidArgumentException $e) { return true; } return false; }
$pdo = (new ReflectionClass(PDO::class))->newInstanceWithoutConstructor();

check('leaf without sanitize rejected', throws(function () { BuilderBlockRegistry::register(def('t_leaf_a')); }), true);
check('leaf with null sanitize rejected', throws(function () { BuilderBlockRegistry::register(def('t_leaf_b', ['sanitize' => null])); }), true);
check('leaf with sanitize accepted', throws(function () { BuilderBlockRegistry::register(def('t_leaf_c', ['sanitize' => function (stdClass $d, $pdo) { return $d; }])); }), false);
check('container without sanitize accepted', throws(function () {
    BuilderBlockRegistry::register(def('t_box_a', ['allows_children' => true, 'category' => 'structure']));
}), false);
check('non-callable extract_media rejected', throws(function () {
    BuilderBlockRegistry::register(def('t_leaf_d', ['sanitize' => function (stdClass $d, $pdo) { return $d; }, 'extract_media' => 'nope_not_a_function']));
}), true);

BuilderBlockRegistry::register(def('t_media_a', [
    'sanitize' => function (stdClass $d, $pdo) { $d->clean = true; return $d; },
    'extract_media' => function (stdClass $d) { return [3, 3, 7]; },
]));
$out = BuilderBlockRegistry::sanitizeData('t_media_a', (object)['a' => 1], $pdo);
check('sanitize runs', $out->clean ?? null, true);
check('extract dedupes', BuilderBlockRegistry::extractMedia('t_media_a', new stdClass), [3, 7]);
check('extract unknown type', BuilderBlockRegistry::extractMedia('nope', new stdClass), []);
check('sanitize unknown type passthrough', BuilderBlockRegistry::sanitizeData('nope', (object)['k' => 1], $pdo)->k, 1);

BuilderBlockRegistry::register(def('t_bad_ret', [
    'sanitize' => function (stdClass $d, $pdo) { return ['array']; },
    'extract_media' => function (stdClass $d) { return ['7']; },
]));
check('sanitize must return stdClass', throws(function () use ($pdo) { BuilderBlockRegistry::sanitizeData('t_bad_ret', new stdClass, $pdo); }), true);
check('extract must return ints', throws(function () { BuilderBlockRegistry::extractMedia('t_bad_ret', new stdClass); }), true);

BuilderBlockRegistry::register(def('t_throw', ['sanitize' => function (stdClass $d, $pdo) { throw new InvalidArgumentException('bad'); }]));
check('sanitize may reject', throws(function () use ($pdo) { BuilderBlockRegistry::sanitizeData('t_throw', new stdClass, $pdo); }), true);

// the five real structural types must still register
require __DIR__ . '/../includes/builder/types/structural.php';
check('structural registered', BuilderBlockRegistry::exists('column_container') && BuilderBlockRegistry::exists('footer'), true);
if ($fail > 0) { fwrite(STDERR, "$fail failure(s)\n"); exit(1); }
echo "builder_registry_test: OK\n";
```

## P3.5 The block-data contract for Parts 4–6 (D19 — pinned here, binding for every later leaf type)

1. A leaf type (`allows_children=false`) **must** define `sanitize(stdClass $data, PDO $pdo): stdClass`. It **rebuilds** the object from a whitelist of keys (never returns the input), cuts string lengths, validates enums, and throws `InvalidArgumentException` to reject. Rich text goes through `BuilderHtmlSanitizer::clean()`; link targets through `BuilderHtmlSanitizer::safeUrl()`.
2. Media is referenced by **integer `media_id`**. `sanitize` checks the id with `BuilderMediaRefs::existingIds()` and **the server writes the URL** into the data (read from `media_library.file_path`); a client-supplied URL is ignored. `extract_media(stdClass $data): int[]` returns the ids so the repository can keep `builder_media_refs` in sync.
3. The renderer (Part 7) escapes again on output (defence in depth).

Illustration only — **do not create this file now**; Part 4 owns `includes/builder/types/*.php`:
```php
<?php
// EXAMPLE of the contract (not part of P3).
BuilderBlockRegistry::register([
    'type' => 'image', 'label' => 'تصویر', 'icon' => 'bi-image', 'category' => 'content',
    'allows_children' => false, 'min_children' => 0, 'max_children' => 0,
    'supports_text_formatting' => false, 'allowed_child_types' => [], 'allowed_parent_types' => [],
    'default_data' => ['media_id' => 0, 'src' => '', 'alt' => ''],
    'sanitize' => function (stdClass $data, PDO $pdo): stdClass {
        $mediaId = (int)($data->media_id ?? 0);
        $out = new stdClass();
        $out->media_id = 0; $out->src = ''; $out->alt = mb_substr(strip_tags((string)($data->alt ?? '')), 0, 200, 'UTF-8');
        if ($mediaId > 0) {
            $st = $pdo->prepare("SELECT file_path FROM media_library WHERE id = ? AND purpose = 'library' AND file_type = 'image'");
            $st->execute([$mediaId]);
            $path = $st->fetchColumn();
            if ($path === false) { throw new InvalidArgumentException('Unknown media'); }
            $out->media_id = $mediaId; $out->src = (string)$path;
        }
        return $out;
    },
    'extract_media' => function (stdClass $data): array {
        return ((int)($data->media_id ?? 0) > 0) ? [(int)$data->media_id] : [];
    },
]);
```

## P3.6 Must NOT do (Phase 3)

| Forbidden |
|---|
| Registering any real leaf block type, or touching `types/structural.php` (Part 4). |
| Creating `builder_canvas_revisions` or any draft/published split (Appendix C is a design input for Part 7). |
| Any change to JS/CSS other than the two `builder-shell.js` edits. |
| Searching `builder_blocks.data` with `LIKE` for media usage — always use `builder_media_refs`. |
| Dropping `media_usage` (P5, after this phase's `delete.php` no longer reads it). |
| Running `0004` without the clone test; ignoring a failed preflight. |

## P3.7 Gate / checklist

| # | Check |
|---|---|
| 1 | `php tests/run_all.php` exit 0; `php -l` on every changed file; Arabic-letter grep clean. |
| 2 | `0004` ran on the clone, then on `business_panel`; `diagnose.php --phase=3`: D07, D08, D09 = 0 rows, D10 INFO, D11 (phase 3) and D21 PASS. |
| 3 | **M2 (clone only):** inserting a block whose `parent_id` belongs to another canvas fails with a foreign-key error; a root block (`parent_id NULL`) inserts fine; deleting a canvas still cascades to its blocks. |
| 4 | **M13:** open one canvas in two tabs, save in tab 1, save in tab 2 ⇒ tab 2 shows `STR_VERSION_CONFLICT` and nothing is overwritten; `GET load.php` returns `version`; a POST update without `expected_version` ⇒ 400 invalid request. |
| 5 | **Session expiry:** logged-out tab, press save ⇒ the existing "session expired" dialog (not a generic error). |
| 6 | **H7/M5 end to end:** temporarily create `includes/builder/types/zz_probe.php` (a leaf type following §P3.5 with an `image`-like `extract_media`), save a canvas that contains a block of that type with a real library `media_id` ⇒ one `builder_media_refs` row; `POST media/delete.php` for that file ⇒ **409** with `STR_MEDIA_IN_USE` + the page title; remove the block, save, delete again ⇒ success and the file is gone from disk; bad `media_id` ⇒ 400 `STR_BLOCK_INVALID`. **Delete `zz_probe.php` afterwards** and list it in the changelog's cleanup list. |
| 7 | **L11:** two quick saves of two new pages with the same title ⇒ both succeed (`-2` suffix), no 500. **JSON slashes:** a stored block `data` containing a path shows `/uploads/…` (no `\/`). |
| 8 | **H7 negative:** `BuilderBlockRegistry::register()` of a leaf type without `sanitize` throws (covered by the unit test; paste the test line from the output). |
| 9 | **Owner regression:** load/save a page canvas and a template canvas; staff with `pages.edit` only still cannot save a template canvas. |

Append the P3 section to the changelog before starting P4.

---


# PHASE 4 — File storage: one gateway, private files, media registry (migration `0005`)

**Goal:** close H1 (server side), H2, H4, H9, M11 (zip), M12, L6, L7, L10. Every uploaded byte goes through `FileStore`; public files are registered in `media_library`; business documents (licence certificates, template packages) leave `public/`. Order inside the phase: **`0005` [pre-code] → config → code → tests → backfill tool → gate.** (There is no [post-code] migration in P4.)

## P4.1 File map

```
panel/
├── config/config.php                              M  + STORAGE_PRIVATE, size/zip limits; − dead constants
├── database/
│   ├── migrations/0005_files_pre_code.php         N  [pre-code]
│   └── tools/
│       ├── backfill_files.php                     N  one-off: hash/register legacy files, move legacy private files (dry-run by default)
│       └── gc_files.php                           N  report (and with --delete remove) orphan files on disk
├── includes/
│   ├── file_rules.php                             N  Pure rules (MIME→ext maps, path safety, zip entry rules, upload error texts)
│   ├── file_store.php                             N  THE upload gateway (FileStore, FileStoreException)
│   └── media_registry.php                         N  mediaRegisterFile(), mediaReleasePath()
├── storage/private/                               N  OUTSIDE public/. .htaccess ("Require all denied"), .gitkeep, licenses/ and templates/ created on demand
├── tests/
│   ├── file_rules_test.php                        N
│   └── file_store_test.php                        N
└── public/
    ├── uploads/.htaccess                          -  created in P1.9; P4 only verifies it is still there and unchanged
    ├── file.php                                   N  the ONLY way to download a private file (permission-checked)
    ├── api/media/upload.php                       M  FileStore + registry + duplicate response
    ├── api/media/delete.php                       M  final version (purpose filter, FileStore::remove)
    ├── api/templates/upload.php                   M  private storage + zip inspection WITHOUT extraction
    ├── api/licenses/update.php                    M  private certificate + input validation
    ├── api/staff/remove.php                       M  release the avatar file after the commit
    └── modules/
        ├── licenses/index.php                     M  «مشاهده گواهی» link
        ├── media/index.php                        M  purpose filter, LIKE escape, page clamp, duplicate notice, dead code removed
        └── settings/{general,profile}.php         M  FileStore + registry (logo, favicon, avatar)
```

Evidence for the dead constants (run before deleting them): `grep -rn "UPLOAD_PRODUCTS\|UPLOAD_TEMPLATES\|UPLOAD_LICENSES\|ALLOWED_IMAGE_\|ALLOWED_VIDEO_\|ALLOWED_TEMPLATE_EXTS" --include=*.php public includes config` — after this phase's rewrites it must print only `config/config.php`. Then delete them.

## P4.2 Design (PK/FK, why)

| Item | Design | Reason |
|---|---|---|
| `media_library.purpose` | `ENUM('library','site_asset','avatar') NOT NULL DEFAULT 'library'` | One registry for every **public** file; the library screen and GC must be able to tell "user content" from "panel chrome". Existing rows are all library files ⇒ the default is correct. |
| `media_library.sha256` | `CHAR(64) CHARACTER SET ascii NULL`, hash of the **original uploaded bytes** | The stored image is re-encoded (EXIF stripped, rotated), so its hash is not stable across GD versions. `NULL` for legacy rows (UNIQUE allows many NULLs). |
| `UNIQUE (purpose, sha256)` | `uq_media_purpose_sha256` | Same bytes twice in the same bucket = same file (H9). The same picture may legitimately be a logo **and** a library item ⇒ the key includes `purpose`. |
| `idx_media_purpose_created` | `(purpose, created_at)` | The library page filters by purpose and sorts by date. |
| `templates.package_path` / `package_sha256` | `VARCHAR(500) NULL` / `CHAR(64) ascii NULL` | Today the uploaded zip is stored but **no row points to it** (orphan). `package_path` is relative to `storage/private/` (e.g. `templates/<hex>.zip`). `NULL` for system templates. |
| `licenses.certificate_file` | existing column; **new meaning**: relative private path `licenses/<hex>.pdf` | A web path (`/uploads/licenses/…`) was world-readable. A relative path cannot be turned into a URL by accident. Legacy values starting with `/uploads/` are migrated by `backfill_files.php` (D14). |
| `site_settings.logo_path/favicon_path`, `panel_users.avatar_path` | keep as denormalised web paths | They are read on every page; a join per request buys nothing. The registry row is the ownership/GC record. No new FK (the path is not a key). |

## P4.3 Library decision

`FileStore` uses **GD + fileinfo + ZipArchive + hash** only — all bundled PHP extensions. **No** Intervention/Imagine/Flysystem (new Composer packages are forbidden, and 4 buckets do not need an abstraction layer). If an extension is missing, `database/tools/env_check.php` (P0) already reports it; `FileStore` throws `FileStoreException` with a Persian message instead of a fatal error.

## P4.4 Migration `0005` [pre-code] — run FIRST

```php
<?php
// Phase 4 — [pre-code]: additive; run FIRST in P4.
return [
    'description' => 'Files: media_library sha256 + purpose + UNIQUE(purpose, sha256), templates package columns',
    'up' => function (PDO $pdo, Migrator $m): void {
        if (!$m->columnExists('media_library', 'sha256')) {
            $pdo->exec("ALTER TABLE media_library ADD COLUMN sha256 CHAR(64) CHARACTER SET ascii DEFAULT NULL AFTER mime_type");
        }
        if (!$m->columnExists('media_library', 'purpose')) {
            $pdo->exec("ALTER TABLE media_library ADD COLUMN purpose ENUM('library','site_asset','avatar') NOT NULL DEFAULT 'library' AFTER file_type");
        }
        // NULL sha256 (legacy rows) never collides: UNIQUE allows many NULLs
        $m->ensureIndex('media_library', 'uq_media_purpose_sha256', '(purpose, sha256)', true);
        $m->ensureIndex('media_library', 'idx_media_purpose_created', '(purpose, created_at)');

        if (!$m->columnExists('templates', 'package_path')) {
            $pdo->exec("ALTER TABLE templates ADD COLUMN package_path VARCHAR(500) DEFAULT NULL AFTER preview_url");
        }
        if (!$m->columnExists('templates', 'package_sha256')) {
            $pdo->exec("ALTER TABLE templates ADD COLUMN package_sha256 CHAR(64) CHARACTER SET ascii DEFAULT NULL AFTER package_path");
        }
    },
];
```

Clone first (Rule 12), then `business_panel`. Afterwards `diagnose.php --phase=4`: D12 = 0 rows, D13 INFO (NULL-hash count), D14 and D22 may list rows until the backfill tool runs (P4.9).

## P4.5 Config and storage

**`config/config.php`** — in the "paths" block add, in the "limits" block replace the old upload constants, and delete the dead ones listed above:
```php
define('STORAGE_PRIVATE', BASE_PATH . '/storage/private');       // outside public/: never web-served
define('MAX_CERT_BYTES', 5 * 1024 * 1024);                       // licence certificate (PDF/JPG/PNG/WEBP)
define('MAX_TEMPLATE_ZIP_BYTES', 50 * 1024 * 1024);              // the old code allowed 20 MB; raised to 50 MB (author's decision, record it)
define('MAX_ZIP_FILES', 500);                                    // entries inside a template zip
define('MAX_ZIP_UNPACKED_BYTES', 100 * 1024 * 1024);             // sum of uncompressed sizes (zip-bomb guard)
```
Keep `MAX_IMAGE_SIZE`, `MAX_VIDEO_SIZE`, `UPLOAD_BASE`, `UPLOAD_MEDIA`, `UPLOAD_AVATARS`.

**`storage/private/.htaccess`** (belt and braces — the folder is already outside the web root `public/`):
```
Require all denied
```
Create `storage/private/.gitkeep`. `public/uploads/.htaccess` already exists since **P1.9** (no script execution, `nosniff`, restrictive CSP); do not rewrite it — only verify `curl -I /uploads/media/<any file>` still shows the `X-Content-Type-Options` header (Apache only; on nginx see `database/README.md`).

Append to `.gitignore` (skip what P0 already added): `storage/private/*` with `!storage/private/.gitkeep` and `!storage/private/.htaccess`; `public/uploads/*` with `!public/uploads/.htaccess`.

## P4.6 Code

**`includes/file_rules.php`** (pure, unit-tested):
```php
<?php
// Pure rules for uploaded files. No DB, no filesystem writes. Unit-tested in tests/file_rules_test.php.

/** Persian message for a PHP UPLOAD_ERR_* code (never UPLOAD_ERR_OK). */
function uploadErrorMessage(int $code): string {
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE: return 'حجم فایل بیش از حد مجاز سرور است';
        case UPLOAD_ERR_PARTIAL:   return 'فایل به‌صورت ناقص ارسال شد. دوباره تلاش کنید';
        case UPLOAD_ERR_NO_FILE:   return 'فایلی ارسال نشده';
        default:                   return 'خطای سرور در دریافت فایل';
    }
}

function imageExtForMime(string $mime): ?string {
    $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    return $map[$mime] ?? null;
}

function videoExtForMime(string $mime): ?string {
    $map = ['video/mp4' => 'mp4', 'video/webm' => 'webm'];
    return $map[$mime] ?? null;
}

function certificateExtForMime(string $mime): ?string {
    $map = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    return $map[$mime] ?? null;
}

/** MIME types finfo may report for a real .ico file. */
function isIcoMime(string $mime): bool {
    return in_array($mime, ['image/vnd.microsoft.icon', 'image/x-icon'], true);
}

/** An .ico file starts with 00 00 01 00 (ICONDIR, type 1). */
function hasIcoMagic(string $firstBytes): bool {
    return strlen($firstBytes) >= 4 && substr($firstBytes, 0, 4) === "\x00\x00\x01\x00";
}

/** Public paths we ever write: /uploads/{media|avatars}/<32 hex>.<ext>. */
function isSafePublicUploadPath(string $path): bool {
    return (bool)preg_match('#^/uploads/(media|avatars)/[0-9a-f]{32}\.(jpg|png|webp|mp4|webm|ico)$#', $path);
}

/** Private paths are stored relative to storage/private: licenses|templates/<32 hex>.<ext>. */
function isSafePrivatePath(string $rel): bool {
    return (bool)preg_match('#^(licenses|templates)/[0-9a-f]{32}\.(pdf|jpg|png|webp|zip)$#', $rel);
}

/** Image sanity limits before handing bytes to GD (decompression-bomb guard). */
function imageDimensionsAllowed(int $w, int $h): bool {
    return $w >= 1 && $h >= 1 && $w <= 10000 && $h <= 10000 && ($w * $h) <= 40000000;
}

/** Entry names inside an uploaded template zip. Directories (trailing /) are judged by path only. */
function zipEntryIsSafe(string $name): bool {
    if ($name === '' || strlen($name) > 255) { return false; }
    if (strpos($name, "\0") !== false || strpos($name, '\\') !== false) { return false; }
    if ($name[0] === '/' || preg_match('#^[A-Za-z]:#', $name)) { return false; }
    foreach (explode('/', $name) as $segment) {
        if ($segment === '..') { return false; }
    }
    return true;
}

/** Extensions allowed for FILES inside a template zip (no php, no svg, no dotfiles). */
function zipEntryExtensionAllowed(string $name): bool {
    if (substr($name, -1) === '/') { return true; }
    $base = basename($name);
    if ($base === '' || $base[0] === '.') { return false; }
    $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
    $allowed = ['html','htm','css','js','json','txt','md','png','jpg','jpeg','gif','webp','ico',
                'woff','woff2','ttf','otf','eot','mp4','webm','map'];
    return in_array($ext, $allowed, true);
}
```

**`includes/file_store.php`** — the single upload gateway. Rules it enforces (all are covered by `file_store_test.php`): the client's file name and `Content-Type` are never trusted — the MIME comes from `finfo` on the temp file; images are decoded by GD and **re-encoded** (JPEG→JPEG, PNG→PNG, WEBP→WEBP; EXIF orientation applied, all metadata dropped; dimension/decompression-bomb limits from `imageDimensionsAllowed`); the stored name is `<32 random hex>.<ext>`; `sha256` is of the original bytes; every `remove*()` re-validates the stored path with the safe-path regexes and refuses to unlink anything outside its base directory; `.ico` is accepted only with the ICONDIR magic bytes; `.svg` is **not accepted anywhere** (H2).
```php
<?php
// The ONLY code allowed to write or delete uploaded files. Requires includes/file_rules.php.
// Constants used: UPLOAD_MEDIA, UPLOAD_AVATARS, UPLOAD_BASE, STORAGE_PRIVATE, MAX_IMAGE_SIZE, MAX_VIDEO_SIZE.
require_once __DIR__ . '/file_rules.php';

/** Message is Persian and safe to show to the user. */
final class FileStoreException extends RuntimeException {}

final class FileStore
{
    /** @return array{path:string,abs:string,mime:string,ext:string,size_kb:int,width:?int,height:?int,sha256:string,file_type:string} */
    public static function storeImage(array $file, string $bucket, ?int $maxBytes = null): array
    {
        $tmp = self::checkUpload($file, $maxBytes ?? MAX_IMAGE_SIZE);
        $mime = self::mimeOf($tmp);
        $ext = imageExtForMime($mime);
        if ($ext === null) {
            throw new FileStoreException('فرمت تصویر مجاز نیست (jpg/png/webp)');
        }
        $info = @getimagesize($tmp);
        if ($info === false || !imageDimensionsAllowed((int)$info[0], (int)$info[1])) {
            throw new FileStoreException('ابعاد تصویر بیش از حد مجاز است یا تصویر معتبر نیست');
        }
        $bytes = file_get_contents($tmp);
        $image = $bytes === false ? false : @imagecreatefromstring($bytes);
        if ($image === false) {
            throw new FileStoreException('تصویر معتبر نیست');
        }
        $sha256 = hash_file('sha256', $tmp);

        if ($ext === 'jpg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($tmp);
            $angle = [3 => 180, 6 => -90, 8 => 90][(int)($exif['Orientation'] ?? 1)] ?? 0;
            if ($angle !== 0) {
                $rotated = imagerotate($image, $angle, 0);
                if ($rotated !== false) { $image = $rotated; }
            }
        }

        $dir = self::bucketDir($bucket);
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $abs = $dir . '/' . $name;
        $ok = false;
        if ($ext === 'jpg') {
            $ok = imagejpeg($image, $abs, 85);
        } elseif ($ext === 'png') {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $ok = imagepng($image, $abs, 6);
        } elseif (function_exists('imagewebp')) {
            imagepalettetotruecolor($image);
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $ok = imagewebp($image, $abs, 85);
        } else {
            // GD without WebP support: keep the validated original bytes (EXIF may remain; recorded in the changelog).
            $ok = file_put_contents($abs, $bytes) !== false;
        }
        if (!$ok || !is_file($abs) || filesize($abs) === 0) {
            if (is_file($abs)) { @unlink($abs); }
            throw new FileStoreException('خطا در ذخیره فایل');
        }
        $final = @getimagesize($abs);
        return [
            'path' => '/uploads/' . $bucket . '/' . $name, 'abs' => $abs, 'mime' => $mime, 'ext' => $ext,
            'size_kb' => (int)ceil(filesize($abs) / 1024),
            'width' => $final ? (int)$final[0] : (int)$info[0], 'height' => $final ? (int)$final[1] : (int)$info[1],
            'sha256' => $sha256, 'file_type' => 'image',
        ];
    }

    public static function storeVideo(array $file): array
    {
        $tmp = self::checkUpload($file, MAX_VIDEO_SIZE);
        $mime = self::mimeOf($tmp);
        $ext = videoExtForMime($mime);
        if ($ext === null) {
            throw new FileStoreException('فرمت ویدیو مجاز نیست (mp4/webm)');
        }
        $sha256 = hash_file('sha256', $tmp);
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $abs = self::bucketDir('media') . '/' . $name;
        self::moveUploaded($tmp, $abs);
        return [
            'path' => '/uploads/media/' . $name, 'abs' => $abs, 'mime' => $mime, 'ext' => $ext,
            'size_kb' => (int)ceil(filesize($abs) / 1024), 'width' => null, 'height' => null,
            'sha256' => $sha256, 'file_type' => 'video',
        ];
    }

    /** Favicon in .ico format only (GD cannot re-encode ICO, so the bytes are validated by MIME + magic bytes + size). */
    public static function storeIco(array $file, int $maxBytes = 1048576): array
    {
        $tmp = self::checkUpload($file, $maxBytes);
        $mime = self::mimeOf($tmp);
        $head = (string)file_get_contents($tmp, false, null, 0, 4);
        if (!isIcoMime($mime) || !hasIcoMagic($head)) {
            throw new FileStoreException('فایل ICO معتبر نیست');
        }
        $sha256 = hash_file('sha256', $tmp);
        $name = bin2hex(random_bytes(16)) . '.ico';
        $abs = self::bucketDir('media') . '/' . $name;
        self::moveUploaded($tmp, $abs);
        return [
            'path' => '/uploads/media/' . $name, 'abs' => $abs, 'mime' => 'image/x-icon', 'ext' => 'ico',
            'size_kb' => (int)ceil(filesize($abs) / 1024), 'width' => null, 'height' => null,
            'sha256' => $sha256, 'file_type' => 'image',
        ];
    }

    /**
     * Private file (outside public/): returns the path RELATIVE to STORAGE_PRIVATE, e.g. "licenses/<hex>.pdf".
     * @param array<string,string> $mimeToExt allowed MIME => extension
     * @return array{rel:string,abs:string,mime:string,ext:string,size_kb:int,sha256:string}
     */
    public static function storePrivate(array $file, string $kind, array $mimeToExt, int $maxBytes): array
    {
        if (!in_array($kind, ['licenses', 'templates'], true)) {
            throw new InvalidArgumentException('bad private kind');
        }
        $tmp = self::checkUpload($file, $maxBytes);
        $mime = self::mimeOf($tmp);
        if (!isset($mimeToExt[$mime])) {
            throw new FileStoreException('فرمت فایل مجاز نیست');
        }
        $ext = $mimeToExt[$mime];
        $sha256 = hash_file('sha256', $tmp);
        $dir = STORAGE_PRIVATE . '/' . $kind;
        self::ensureDir($dir);
        $rel = $kind . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
        $abs = STORAGE_PRIVATE . '/' . $rel;
        self::moveUploaded($tmp, $abs);
        return ['rel' => $rel, 'abs' => $abs, 'mime' => $mime, 'ext' => $ext, 'size_kb' => (int)ceil(filesize($abs) / 1024), 'sha256' => $sha256];
    }

    /** Deletes a public upload by its web path. Never throws; failures are logged. */
    public static function remove(?string $publicPath): bool
    {
        if ($publicPath === null || $publicPath === '' || !isSafePublicUploadPath($publicPath)) {
            return false;
        }
        $abs = UPLOAD_BASE . substr($publicPath, strlen('/uploads'));
        return self::unlinkInside($abs, UPLOAD_BASE);
    }

    /** Deletes a private file by its relative path. Never throws; failures are logged. */
    public static function removePrivate(?string $rel): bool
    {
        if ($rel === null || $rel === '' || !isSafePrivatePath($rel)) {
            return false;
        }
        return self::unlinkInside(STORAGE_PRIVATE . '/' . $rel, STORAGE_PRIVATE);
    }

    /** Absolute path of a private file, or null when the stored value is not a valid private path / file is missing. */
    public static function privateAbsolute(?string $rel): ?string
    {
        if ($rel === null || !isSafePrivatePath($rel)) { return null; }
        $abs = STORAGE_PRIVATE . '/' . $rel;
        return is_file($abs) ? $abs : null;
    }

    // ---- internals ----

    private static function checkUpload(array $file, int $maxBytes): string
    {
        $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            throw new FileStoreException(uploadErrorMessage($err));
        }
        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !self::isUploaded($tmp)) {
            throw new FileStoreException('فایلی ارسال نشده');
        }
        $size = @filesize($tmp);
        if ($size === false || $size <= 0) {
            throw new FileStoreException('فایل خالی است');
        }
        if ($size > $maxBytes) {
            throw new FileStoreException('حجم فایل بیش از حد مجاز است');
        }
        return $tmp;
    }

    private static function mimeOf(string $tmp): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string)finfo_file($finfo, $tmp) : '';
        if ($finfo) { finfo_close($finfo); }
        return $mime;
    }

    private static function bucketDir(string $bucket): string
    {
        if ($bucket !== 'media' && $bucket !== 'avatars') {
            throw new InvalidArgumentException('bad bucket');
        }
        $dir = $bucket === 'media' ? UPLOAD_MEDIA : UPLOAD_AVATARS;
        self::ensureDir($dir);
        return $dir;
    }

    private static function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new FileStoreException('خطا در ایجاد پوشه ذخیره‌سازی');
        }
    }

    private static function isUploaded(string $tmp): bool
    {
        return (defined('FILESTORE_TESTING') && FILESTORE_TESTING === true) || is_uploaded_file($tmp);
    }

    private static function moveUploaded(string $tmp, string $dest): void
    {
        $ok = (defined('FILESTORE_TESTING') && FILESTORE_TESTING === true) ? copy($tmp, $dest) : move_uploaded_file($tmp, $dest);
        if (!$ok || !is_file($dest)) {
            throw new FileStoreException('خطا در ذخیره فایل');
        }
    }

    private static function unlinkInside(string $abs, string $baseDir): bool
    {
        $base = realpath($baseDir);
        $real = realpath($abs);
        if ($base === false || $real === false) { return false; }
        if (strpos($real, $base . DIRECTORY_SEPARATOR) !== 0) { return false; }
        if (!@unlink($real)) {
            error_log('FileStore: could not delete ' . $real);
            return false;
        }
        return true;
    }
}
```

**`includes/media_registry.php`** — the only code that writes `media_library` rows for library uploads, avatars and site assets. A duplicate (`1062` on `uq_media_purpose_sha256`) reuses the existing row and deletes the file that was just stored:
```php
<?php
// The only code that writes rows into media_library for avatars and site assets (and the library upload).
// Requires includes/file_store.php. File bytes are written by FileStore; this file keeps the DB side consistent.

/**
 * Registers a file that FileStore just stored. UNIQUE(purpose, sha256) makes a second upload of the same bytes
 * hit error 1062: then the existing row is reused and the NEW file is deleted (nothing references it).
 * Works inside the caller's transaction (a 1062 only rolls back that single statement in InnoDB).
 * @param array $stored result of FileStore::storeImage/storeVideo/storeIco
 * @return array{id:int,path:string,reused:bool}
 */
function mediaRegisterFile(PDO $pdo, array $stored, string $displayName, string $purpose, int $userId): array {
    try {
        $pdo->prepare("INSERT INTO media_library (file_name, file_path, file_type, purpose, mime_type, sha256, file_size_kb, width, height, uploaded_by)
                       VALUES (?,?,?,?,?,?,?,?,?,?)")
            ->execute([sanitizeClientFileName($displayName), $stored['path'], $stored['file_type'], $purpose, $stored['mime'],
                       $stored['sha256'], $stored['size_kb'], $stored['width'], $stored['height'], $userId]);
        return ['id' => (int)$pdo->lastInsertId(), 'path' => $stored['path'], 'reused' => false];
    } catch (PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) !== 1062) { throw $e; }
        $st = $pdo->prepare("SELECT id, file_path FROM media_library WHERE purpose = ? AND sha256 = ? LIMIT 1");
        $st->execute([$purpose, $stored['sha256']]);
        $row = $st->fetch();
        if (!$row) { throw $e; }
        if ($row['file_path'] !== $stored['path']) { FileStore::remove($stored['path']); }
        return ['id' => (int)$row['id'], 'path' => (string)$row['file_path'], 'reused' => true];
    }
}

/**
 * Call AFTER the transaction that replaced a path (old avatar, old logo/favicon) has committed.
 * Deletes the old file and its registry row only when nothing else still points at it. Never throws.
 */
function mediaReleasePath(PDO $pdo, ?string $path, string $purpose): void {
    if ($path === null || $path === '' || !isSafePublicUploadPath($path)) { return; }
    try {
        $used = 0;
        $q = $pdo->prepare("SELECT COUNT(*) FROM site_settings WHERE logo_path = ? OR favicon_path = ?");
        $q->execute([$path, $path]);
        $used += (int)$q->fetchColumn();
        $q = $pdo->prepare("SELECT COUNT(*) FROM panel_users WHERE avatar_path = ?");
        $q->execute([$path]);
        $used += (int)$q->fetchColumn();
        if ($used > 0) { return; }

        $q = $pdo->prepare("SELECT id, purpose FROM media_library WHERE file_path = ? LIMIT 1");
        $q->execute([$path]);
        $row = $q->fetch();
        if ($row) {
            if ($row['purpose'] !== $purpose) { return; }                 // belongs to someone else (e.g. the library)
            $pdo->prepare("DELETE FROM media_library WHERE id = ?")->execute([(int)$row['id']]);   // FK RESTRICT may refuse: caught below
        }
        FileStore::remove($path);
    } catch (Throwable $e) {
        error_log('mediaReleasePath: ' . $e->getMessage());
    }
}
```

**`public/api/media/upload.php`** — replace the whole file. A duplicate upload is a **success** with `duplicate:true` and `STR_MEDIA_DUPLICATE` (the front end shows an info dialog instead of an error):
```php
<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/file_store.php';
require_once __DIR__ . '/../../../includes/media_registry.php';
requireLogin(); requirePermission('media','edit');
// GET ?id=… : info about one library file
if($_SERVER['REQUEST_METHOD']==='GET' && isset($_GET['id'])){
  $pdo=getPDO();
  $stmt=$pdo->prepare("SELECT id, file_name, file_path, file_type, mime_type, file_size_kb, width, height, created_at FROM media_library WHERE id=? AND purpose='library'"); $stmt->execute([(int)$_GET['id']]);
  $row=$stmt->fetch(); if(!$row) jsonResponse(['success'=>false,'message'=>'یافت نشد'],404);
  jsonResponse(['success'=>true,'data'=>$row]);
}
if($_SERVER['REQUEST_METHOD']!=='POST') jsonResponse(['success'=>false,'message'=>'متد مجاز نیست'],405);
if(!csrf_verify($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')){ jsonResponse(['success'=>false,'message'=>'توکن نامعتبر'],403); }

$file=$_FILES['file'] ?? null;
if(!is_array($file)) jsonResponse(['success'=>false,'message'=>'فایلی ارسال نشده'],400);
if((int)($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) jsonResponse(['success'=>false,'message'=>uploadErrorMessage((int)$file['error'])],400);

// Decide image vs video from the real content, never from the client's name or type.
$finfo=finfo_open(FILEINFO_MIME_TYPE); $mime=$finfo? (string)finfo_file($finfo,(string)$file['tmp_name']) : '';
if($finfo) finfo_close($finfo);
$isImage=imageExtForMime($mime)!==null; $isVideo=videoExtForMime($mime)!==null;
if(!$isImage && !$isVideo) jsonResponse(['success'=>false,'message'=>'فرمت فایل مجاز نیست (jpg/png/webp/mp4/webm)'],400);

try{
  $stored=$isImage ? FileStore::storeImage($file,'media') : FileStore::storeVideo($file);
}catch(FileStoreException $e){
  jsonResponse(['success'=>false,'message'=>$e->getMessage()],400);
}

$pdo=getPDO();
try{
  $reg=mediaRegisterFile($pdo,$stored,(string)($file['name']??'file'),'library',(int)$_SESSION['user_id']);
}catch(Throwable $e){
  FileStore::remove($stored['path']);               // no row ⇒ the file must not stay behind
  error_log('media/upload.php: '.$e->getMessage());
  jsonResponse(['success'=>false,'message'=>'خطا در ذخیره فایل'],500);
}
if($reg['reused']){
  jsonResponse(['success'=>true,'duplicate'=>true,'message'=>'این فایل قبلاً آپلود شده است','data'=>['id'=>$reg['id'],'file_path'=>$reg['path']]]);
}
logActivity('media_upload','media','آپلود رسانه '.$reg['id']);
jsonResponse(['success'=>true,'message'=>'آپلود موفق','data'=>['id'=>$reg['id'],'file_path'=>$reg['path'],'file_name'=>sanitizeClientFileName((string)($file['name']??'file'))]]);
```

**`public/api/media/delete.php`** — replace the whole file (this is the final version; the P3 version lacked the `purpose` filter). Only `purpose='library'` rows are deletable here; avatars and site assets are released by their own screens through `mediaReleasePath()`:
```php
<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/builder/media_refs.php';
require_once __DIR__ . '/../../../includes/file_store.php';
requireLogin(); requirePermission('media','delete');
if($_SERVER['REQUEST_METHOD']!=='POST') jsonResponse(['success'=>false,'message'=>'متد مجاز نیست'],405);
if(!csrf_verify($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')){ jsonResponse(['success'=>false,'message'=>'توکن نامعتبر'],403); }
$id=(int)($_POST['id']??0);
if(!$id) jsonResponse(['success'=>false,'message'=>'شناسه نامعتبر'],400);
$pdo=getPDO();

function mediaInUseResponse(PDO $pdo, int $id): void {
  $total=BuilderMediaRefs::countUsing($pdo,$id);
  $titles=array_map(function($c){ return $c['title']; }, BuilderMediaRefs::canvasesUsing($pdo,$id,6));
  $msg='این فایل در صفحه‌های زیر استفاده شده و قابل حذف نیست: '.implode('، ',$titles);
  if($total>count($titles)) $msg.=' و '.toPersianDigits($total-count($titles)).' مورد دیگر';
  jsonResponse(['success'=>false,'message'=>$msg],409);
}

// Only library files can be deleted here: avatars and site assets are released by their own screens.
$m=$pdo->prepare("SELECT id, file_path FROM media_library WHERE id=? AND purpose='library'"); $m->execute([$id]); $row=$m->fetch();
if(!$row) jsonResponse(['success'=>false,'message'=>'یافت نشد'],404);
if(BuilderMediaRefs::countUsing($pdo,$id)>0) mediaInUseResponse($pdo,$id);
try{
  $pdo->prepare("DELETE FROM media_library WHERE id=? AND purpose='library'")->execute([$id]);
}catch(PDOException $e){
  if((int)($e->errorInfo[1]??0)===1451){ mediaInUseResponse($pdo,$id); }
  error_log('media/delete.php: '.$e->getMessage());
  jsonResponse(['success'=>false,'message'=>'خطا در حذف فایل'],500);
}
FileStore::remove((string)$row['file_path']);       // after the row is gone; logs (never throws) when the unlink fails
logActivity('media_delete','media','حذف رسانه '.$id);
jsonResponse(['success'=>true,'message'=>'فایل حذف شد']);
```

**`public/modules/settings/general.php`** — replace the whole file. Flow: `page_boot.php` → permission checks (`settings.view` to open, `settings.edit` to save) → on POST validate every field **before** touching the disk (`STR_SITE_NAME_INVALID`, `STR_DOMAIN_TOO_LONG`, `STR_FAVICON_FORMAT`) → store new files → **one transaction** (`mediaRegisterFile(…, 'site_asset', …)` + `UPDATE site_settings`) → after the commit `mediaReleasePath()` for the replaced paths → `logActivity('site_settings_update', …)` → redirect (PRG). A failure removes the files stored in this request. Logo = jpg/png/webp (`storeImage`), favicon = PNG (through `storeImage`, after the caller checked the MIME is `image/png`) or ICO (through `storeIco`).
```php
<?php
$currentModule='settings'; $pageTitle='تنظیمات عمومی'; $breadcrumb='تنظیمات پنل ‹ عمومی';
require_once __DIR__ . '/../../../includes/page_boot.php';
require_once __DIR__ . '/../../../includes/file_store.php';
require_once __DIR__ . '/../../../includes/media_registry.php';
requirePermission('settings','view');
$canEdit=hasPermission('settings','edit');
$pdo=getPDO();
$site=$pdo->query("SELECT id, site_name, site_domain, logo_path, favicon_path FROM site_settings WHERE id=1")->fetch();
$error=null;

if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!$canEdit){ denyAccess(); }
  requireCsrf();
  $site_name=trim((string)($_POST['site_name']??''));
  $site_domain=trim((string)($_POST['site_domain']??''));
  if(mb_strlen($site_name,'UTF-8')<1 || mb_strlen($site_name,'UTF-8')>150) $error='نام سایت باید بین ۱ تا ۱۵۰ کاراکتر باشد.';
  elseif(strlen($site_domain)>190) $error='دامنه حداکثر ۱۹۰ کاراکتر است.';

  $stored=[];            // files written in this request (rolled back on any failure)
  $newPath=['logo_path'=>$site['logo_path'],'favicon_path'=>$site['favicon_path']];
  $oldPaths=[];
  if($error===null){
    try{
      foreach(['logo'=>'logo_path','favicon'=>'favicon_path'] as $field=>$col){
        $f=$_FILES[$field]??null;
        if(!is_array($f) || (int)($f['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) continue;
        if($field==='logo'){
          $s=FileStore::storeImage($f,'media');                     // jpg / png / webp, re-encoded; SVG is no longer accepted (H2)
        } else {
          $fi=finfo_open(FILEINFO_MIME_TYPE); $mime=$fi? (string)finfo_file($fi,(string)($f['tmp_name']??'')):''; if($fi) finfo_close($fi);
          if(isIcoMime($mime))            $s=FileStore::storeIco($f);
          elseif($mime==='image/png')     $s=FileStore::storeImage($f,'media');
          else throw new FileStoreException('فاوآیکون باید PNG یا ICO باشد');
        }
        $stored[$col]=[$s,(string)($f['name']??'file')];
      }
    }catch(FileStoreException $e){
      foreach($stored as $pair){ FileStore::remove($pair[0]['path']); }
      $stored=[]; $error=$e->getMessage();
    }
  }
  if($error===null){
    try{
      $pdo->beginTransaction();
      foreach($stored as $col=>$pair){
        $reg=mediaRegisterFile($pdo,$pair[0],$pair[1],'site_asset',(int)$_SESSION['user_id']);
        if($site[$col]!==$reg['path']) $oldPaths[]=$site[$col];
        $newPath[$col]=$reg['path'];
      }
      $pdo->prepare("UPDATE site_settings SET site_name=?, site_domain=?, logo_path=?, favicon_path=? WHERE id=1")
          ->execute([$site_name,$site_domain,$newPath['logo_path'],$newPath['favicon_path']]);
      $pdo->commit();
    }catch(Throwable $e){
      if($pdo->inTransaction()) $pdo->rollBack();
      foreach($stored as $pair){ FileStore::remove($pair[0]['path']); }   // reused rows keep their older file (remove() only touches the new name)
      error_log('general.php: '.$e->getMessage());
      $error='خطا در ذخیره‌سازی.';
    }
  }
  if($error===null){
    foreach($oldPaths as $old){ mediaReleasePath($pdo,$old,'site_asset'); }   // only after the commit
    logActivity('site_settings_update','settings','ویرایش تنظیمات عمومی');
    header('Location: general.php?saved=1'); exit;
  }
  $site['site_name']=$site_name; $site['site_domain']=$site_domain;           // keep what the user typed
}
require_once __DIR__ . '/../../../includes/header.php';
$dis=$canEdit?'':'disabled';
?>
<?php if(isset($_GET['saved'])): ?><div class="alert alert-success">تنظیمات ذخیره شد.</div><?php endif; ?>
<?php if($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div class="card p-4">
<form method="post" enctype="multipart/form-data">
<?= csrf_field() ?>
<div class="mb-3"><label class="form-label">نام سایت</label><input name="site_name" value="<?= e($site['site_name']) ?>" class="form-control" maxlength="150" <?= $dis ?>></div>
<div class="mb-3"><label class="form-label">دامنه</label><input name="site_domain" value="<?= e($site['site_domain']) ?>" class="form-control" placeholder="example.com" maxlength="190" <?= $dis ?>></div>
<div class="mb-3"><label class="form-label">لوگو</label><input type="file" name="logo" accept="image/jpeg,image/png,image/webp" class="form-control" <?= $dis ?>><?php if($site['logo_path']): ?><img src="<?= e($site['logo_path']) ?>" width="100" class="mt-2"><?php endif; ?></div>
<div class="mb-3"><label class="form-label">فاوآیکون</label><input type="file" name="favicon" accept="image/png,image/x-icon,image/vnd.microsoft.icon,.ico" class="form-control" <?= $dis ?>><?php if($site['favicon_path']): ?><img src="<?= e($site['favicon_path']) ?>" width="32" class="mt-2"><?php endif; ?></div>
<?php if($canEdit): ?><button class="btn btn-primary">ذخیره تنظیمات</button><?php endif; ?>
</form>
</div>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
```

**`public/modules/settings/profile.php`** — replace the avatar block (marked "2) avatar: unchanged in P2") with the version below, and add `require_once __DIR__ . '/../../../includes/file_store.php'; require_once __DIR__ . '/../../../includes/media_registry.php';` under the `page_boot.php` line. Keep everything else byte-identical. The avatar is stored **after** validation succeeded and registered **inside** the profile transaction; the old file is released after the commit; on a rollback the new file is removed:
```php
    // 2) avatar (P4): FileStore → registry inside the same transaction as the profile
    $avatarPath=$user['avatar_path']; $newAvatar=null; $oldAvatar=null;
    $f=$_FILES['avatar'] ?? null;
    if(is_array($f) && (int)($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
      try{ $newAvatar=FileStore::storeImage($f,'avatars'); }
      catch(FileStoreException $e){ $msg=$e->getMessage(); $msgClass='danger'; $newAvatar=false; }
    }
    if($newAvatar!==false){
      try{
        $pdo->beginTransaction();
        if($newAvatar){
          $reg=mediaRegisterFile($pdo,$newAvatar,'avatar-'.(int)$user['id'],'avatar',(int)$user['id']);
          $oldAvatar=$user['avatar_path']; $avatarPath=$reg['path'];
        }
        $pdo->prepare("UPDATE panel_users SET full_name=?, email=?, phone=?, avatar_path=?, theme_preference=? WHERE id=?")
            ->execute([$full_name,$email,$phone,$avatarPath,$theme,(int)$user['id']]);
        /* … the existing password block, unchanged … */
        $pdo->commit();
        if($oldAvatar!==null && $oldAvatar!==$avatarPath){ mediaReleasePath($pdo,$oldAvatar,'avatar'); }   // only after the commit
        /* … existing success messages / logActivity, unchanged … */
      }catch(PDOException $e){
        if($pdo->inTransaction()) $pdo->rollBack();
        if($newAvatar){ FileStore::remove($newAvatar['path']); }      // harmless if mediaRegisterFile already reused/removed it
        /* … existing 1062 / generic error handling, unchanged … */
      }
    }
```
(`storeImage` is told `'avatars'`, which `FileStore::bucketDir()` maps to `UPLOAD_AVATARS`; the web path is `/uploads/avatars/<hex>.<ext>`.) The `<img>` tag stays as is. Behaviour change to record in the changelog: an **invalid** avatar is now an error message and nothing is saved (before: silently ignored while the rest was saved).

**`public/api/staff/remove.php`** — after the commit and before `logActivity`, release the avatar. Select the path **before** the transaction:
```php
$av=$pdo->prepare("SELECT avatar_path FROM panel_users WHERE id=?"); $av->execute([$id]); $oldAvatar=$av->fetchColumn();
// … existing transaction (it already sets avatar_path=NULL) …
if($oldAvatar){ mediaReleasePath($pdo,(string)$oldAvatar,'avatar'); }   // after commit; never throws
```
(add the two `require_once` lines for `file_store.php` and `media_registry.php`.) `diagnose` D20 stays 0.

**`public/api/templates/upload.php`** — replace the whole file. Owner only. The zip is stored privately, **inspected without extracting anything** (entry names via `zipEntryIsSafe`, extension whitelist via `zipEntryExtensionAllowed`, symlink entries rejected via `getExternalAttributesIndex`, entry count ≤ `MAX_ZIP_FILES`, sum of uncompressed sizes ≤ `MAX_ZIP_UNPACKED_BYTES`); any failure deletes the stored file and answers with `STR_ZIP_UNSAFE` / `STR_ZIP_FORBIDDEN_FILE` / `STR_ZIP_TOO_MANY` / `STR_ZIP_TOO_BIG`; the template name comes from the file name, capped at 150 (`STR_TEMPLATE_NAME_LONG`); the row stores `package_path` and `package_sha256`:
```php
<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/file_store.php';
requireLogin(); requirePermission('templates','edit');
if(!isOwner()) jsonResponse(['success'=>false,'message'=>'فقط مالک می‌تواند قالب آپلود کند'],403);
if($_SERVER['REQUEST_METHOD']!=='POST') jsonResponse(['success'=>false,'message'=>'متد مجاز نیست'],405);
if(!csrf_verify($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')){ jsonResponse(['success'=>false,'message'=>'توکن نامعتبر'],403); }
$file=$_FILES['template_zip'] ?? null;
if(!is_array($file) || (int)($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) jsonResponse(['success'=>false,'message'=>'فایلی انتخاب نشده'],400);

$name=sanitizeClientFileName(pathinfo((string)($file['name']??''),PATHINFO_FILENAME));
if(mb_strlen($name,'UTF-8')>150) jsonResponse(['success'=>false,'message'=>'نام قالب بیش از ۱۵۰ کاراکتر است'],400);   // checked BEFORE the file is stored

try{
  $stored=FileStore::storePrivate($file,'templates',['application/zip'=>'zip','application/x-zip-compressed'=>'zip','multipart/x-zip'=>'zip'],MAX_TEMPLATE_ZIP_BYTES);
}catch(FileStoreException $e){
  jsonResponse(['success'=>false,'message'=>$e->getMessage()],400);
}

// Inspect the archive WITHOUT extracting it: zip-slip, symlinks, forbidden extensions, entry count, unpacked size (zip bomb).
function rejectZip(array $stored, string $message): void {
  FileStore::removePrivate($stored['rel']);
  jsonResponse(['success'=>false,'message'=>$message],400);
}
$zip=new ZipArchive();
if($zip->open($stored['abs'],ZipArchive::RDONLY)!==true) rejectZip($stored,'فایل ZIP خراب است');
if($zip->numFiles<1){ $zip->close(); rejectZip($stored,'ZIP خالی است'); }
if($zip->numFiles>MAX_ZIP_FILES){ $zip->close(); rejectZip($stored,'تعداد فایل‌های ZIP بیش از حد مجاز است'); }
$unpacked=0;
for($i=0;$i<$zip->numFiles;$i++){
  $st=$zip->statIndex($i);
  $entry=(string)($st['name']??'');
  if(!zipEntryIsSafe($entry)){ $zip->close(); rejectZip($stored,'ساختار فایل ZIP امن نیست'); }
  $opsys=0; $attr=0;
  if($zip->getExternalAttributesIndex($i,$opsys,$attr) && $opsys===ZipArchive::OPSYS_UNIX && ((($attr>>16)&0170000)===0120000)){
    $zip->close(); rejectZip($stored,'ساختار فایل ZIP امن نیست');                       // symlink entry
  }
  if(!zipEntryExtensionAllowed($entry)){ $zip->close(); rejectZip($stored,'فایل ZIP شامل فایل غیرمجاز است'); }
  $unpacked+=(int)($st['size']??0);
  if($unpacked>MAX_ZIP_UNPACKED_BYTES){ $zip->close(); rejectZip($stored,'حجم بازشده‌ی ZIP بیش از حد مجاز است'); }
}
$zip->close();

$pdo=getPDO();
try{
  $pdo->prepare("INSERT INTO templates (category_id, name, version, thumbnail_path, source, is_installed, uploaded_by, package_path, package_sha256) VALUES (NULL, ?, '1.0.0', NULL, 'uploaded', 0, ?, ?, ?)")
      ->execute([$name,(int)$_SESSION['user_id'],$stored['rel'],$stored['sha256']]);
  $newId=(int)$pdo->lastInsertId();
}catch(Throwable $e){
  FileStore::removePrivate($stored['rel']);                       // no row ⇒ no orphan file
  error_log('templates/upload.php: '.$e->getMessage());
  jsonResponse(['success'=>false,'message'=>'خطا در ذخیره‌سازی.'],500);
}
logActivity('template_upload','templates','آپلود قالب '.$newId);
jsonResponse(['success'=>true,'message'=>'قالب با موفقیت آپلود شد']);
```

**`public/file.php`** — new. `GET /file.php?type=license&id=<n>` (needs `licenses.view`) or `?type=template&id=<n>` (owner only). It looks the path up **in the database by id**, never from the URL; resolves it with `FileStore::privateAbsolute()`; sends `X-Content-Type-Options: nosniff`, `Content-Disposition: inline` for pdf/jpg/png/webp and `attachment` for zip, `Cache-Control: private, no-store`; 404 for anything missing/invalid:
```php
<?php
// Serves private files (licence certificates, template packages) from storage/private/ after a permission check.
// Not under /modules/, so it does its own checks instead of the page guard.   /file.php?type=license|template&id=N
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/file_store.php';
requireLogin();

$type=(string)($_GET['type']??''); $id=(int)($_GET['id']??0);
$pdo=getPDO();
if($type==='license'){
  requirePermission('licenses','view');
  $st=$pdo->prepare("SELECT certificate_file FROM licenses WHERE id=?");
} elseif($type==='template'){
  requireOwner();                                  // the package is source code: owner only
  $st=$pdo->prepare("SELECT package_path FROM templates WHERE id=?");
} else { http_response_code(404); exit; }
$st->execute([$id]);
$rel=$st->fetchColumn();
$abs=($rel===false || $rel===null) ? null : FileStore::privateAbsolute((string)$rel);
if($abs===null){ http_response_code(404); exit; }

// The Content-Type comes from OUR extension whitelist, never from the client or the file content.
$ext=strtolower(pathinfo($abs,PATHINFO_EXTENSION));
$types=['pdf'=>'application/pdf','jpg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','zip'=>'application/zip'];
if(!isset($types[$ext])){ http_response_code(404); exit; }
header('Content-Type: '.$types[$ext]);
header('Content-Length: '.filesize($abs));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
header('Content-Disposition: '.($ext==='zip'?'attachment':'inline').'; filename="'.($type==='license'?'certificate-':'template-').$id.'.'.$ext.'"');
readfile($abs);
exit;
```

**`public/api/licenses/update.php`** — replace the whole file (reference implementation). Changes versus the original: input validation (length caps, **merchant id must be a UUID**, `STR_MERCHANT_INVALID`); the certificate is stored by `FileStore::storePrivate()` (PDF/JPG/PNG/WEBP only, ≤ `MAX_CERT_BYTES`, `STR_CERT_FORMAT` on a wrong type); the old certificate (private **or** legacy `/uploads/licenses/…`) is deleted after the row is updated; **zarinpal status is now always `pending`** — the original wrote `verified` without verifying anything (*author's correction, record it in the changelog; the «تست تراکنش آزمایشی» button stays as it is*); the previous path is read first and the update is a single statement. P6 changes only the two lines marked `P6` (encryption).
```php
<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/file_store.php';
requireLogin(); requirePermission('licenses','edit');
if($_SERVER['REQUEST_METHOD']!=='POST') jsonResponse(['success'=>false,'message'=>'متد مجاز نیست'],405);
if(!csrf_verify($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')){ jsonResponse(['success'=>false,'message'=>'توکن نامعتبر'],403); }
$type=(string)($_POST['license_type']??'');
if(!in_array($type,['enamad','zarinpal','business_license'],true)) jsonResponse(['success'=>false,'message'=>'نوع مجوز نامعتبر'],400);

function postedText(string $key, int $max): string {
  $v=trim((string)($_POST[$key]??''));
  if(mb_strlen($v,'UTF-8')>$max) jsonResponse(['success'=>false,'message'=>'درخواست نامعتبر'],400);
  return $v;
}
$creds=[]; $status='pending';
if($type==='enamad'){
  $creds['enamad_code']=postedText('enamad_code',190);
} elseif($type==='zarinpal'){
  $mid=postedText('merchant_id',64);
  if($mid!=='' && !preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/D',$mid))
    jsonResponse(['success'=>false,'message'=>'مرچنت آیدی نامعتبر است'],400);
  $creds['merchant_id']=$mid;
  $creds['api_key']=postedText('api_key',512);          // P6 REPLACES this line (keep-existing + encrypt)
} else {
  $creds['license_number']=postedText('license_number',190);
  $status=(string)($_POST['status']??'pending');
  if(!in_array($status,['pending','verified','rejected'],true)) $status='pending';   // 'not_connected' is system-set only
}

$pdo=getPDO();
$old=$pdo->prepare("SELECT certificate_file FROM licenses WHERE license_type=?"); $old->execute([$type]);
$oldCert=$old->fetchColumn();
if($oldCert===false) jsonResponse(['success'=>false,'message'=>'نوع مجوز نامعتبر'],400);

$newCert=null;
$f=$_FILES['certificate'] ?? null;
if($type!=='zarinpal' && is_array($f) && (int)($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
  try{
    $newCert=FileStore::storePrivate($f,'licenses',
      ['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'],MAX_CERT_BYTES);
  }catch(FileStoreException $e){
    $m=$e->getMessage();
    jsonResponse(['success'=>false,'message'=>($m==='فرمت فایل مجاز نیست' ? 'فرمت مجاز: PDF/JPG/PNG/WEBP' : $m)],400);
  }
}
$credsJson=json_encode($creds, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);          // P6 wraps the secret before this line
try{
  if($newCert){
    $pdo->prepare("UPDATE licenses SET credentials=?, certificate_file=?, status=?, updated_by=? WHERE license_type=?")
        ->execute([$credsJson,$newCert['rel'],$status,(int)$_SESSION['user_id'],$type]);
  } else {
    $pdo->prepare("UPDATE licenses SET credentials=?, status=?, updated_by=? WHERE license_type=?")
        ->execute([$credsJson,$status,(int)$_SESSION['user_id'],$type]);
  }
}catch(Throwable $e){
  if($newCert) FileStore::removePrivate($newCert['rel']);
  error_log('licenses/update.php: '.$e->getMessage());
  jsonResponse(['success'=>false,'message'=>'خطا در ذخیره‌سازی.'],500);
}
if($newCert && $oldCert){                                 // after the row changed: drop the previous certificate
  if(isSafePrivatePath((string)$oldCert)) FileStore::removePrivate((string)$oldCert);
  else FileStore::remove((string)$oldCert);               // legacy "/uploads/licenses/…" value (no-op when not a safe public path)
}
logActivity('license_update','licenses','بروزرسانی مجوز '.$type);
jsonResponse(['success'=>true,'message'=>'اطلاعات مجوز بروزرسانی شد']);
```
*Note on the messages:* `مرچنت آیدی نامعتبر است` is `STR_MERCHANT_INVALID` (exact), `خطا در ذخیره‌سازی.` is `STR_SAVE_ERROR`, `فرمت مجاز: PDF/JPG/PNG/WEBP` is `STR_CERT_FORMAT`. The original `'حجم فایل گواهی حداکثر ۵ مگابایت'` is replaced by FileStore's generic size message; if you prefer to keep the old wording, map `'حجم فایل بیش از حد مجاز است'` to it — that is the only allowed variation.

> `FileStore::remove()` of a legacy `/uploads/licenses/<hex>.pdf` value is a **no-op** by design (`isSafePublicUploadPath` only allows `media|avatars`). Legacy certificate files are moved by `backfill_files.php`, never deleted by request code.

**`public/modules/licenses/index.php`** — two edits: (1) in the `enamad` and `business_license` forms, directly under the `<input type="file" name="certificate" …>` add
```php
<?php if(!empty($lic['certificate_file'])): ?><a class="small d-block mb-2" target="_blank" href="/file.php?type=license&amp;id=<?= (int)$lic['id'] ?>">مشاهده گواهی</a><?php endif; ?>
```
(`STR_VIEW_CERT`); (2) the file inputs get `accept=".pdf,.jpg,.jpeg,.png,.webp"`. Do **not** touch the zarinpal form here (P6 owns it).

**`public/modules/media/index.php`** — edits (the page is otherwise unchanged):
1. Library only: the first condition becomes `$where=["purpose='library'"];` (then the existing type/search conditions are appended).
2. Search: escape LIKE wildcards with `|` as the escape character (backslash is risky inside PDO + `NO_BACKSLASH_ESCAPES`): `$like='%'.str_replace(['|','%','_'],['||','|%','|_'],$search).'%'; $where[]="file_name LIKE ? ESCAPE '|'"; $params[]=$like;`
3. Pagination: `$pages=max(1,(int)ceil($totalCount/$per)); $page=min(max(1,$page),$pages);` **before** computing `$offset`; render at most a window of 5 page links centred on `$page` (plus first/last when outside the window).
4. Delete the dead `await fetch('/api/media/upload.php?id='+id);` line in `openPreview()` and the whole `if(j.need_force){…}` branch in the delete handler (the endpoint answers 409 with a message instead; show it with `Swal.fire('خطا', j.message, 'error')`).
5. In `uploadFiles()`: on `r.success && r.duplicate` show `Swal.fire('توجه', r.message, 'info')` and do **not** treat it as an error; any non-200 / unparsable response shows `Swal.fire('خطا', 'خطا در آپلود فایل', 'error')` (today it fails silently).
6. In `openPreview()` build the HTML with `textContent`/`createElement` for the file name (it is user-controlled), not template-string concatenation.

## P4.7 Tests

```php
<?php
require __DIR__ . '/../includes/file_rules.php';
$fail = 0;
function check(string $label, $actual, $expected): void {
    global $fail;
    if ($actual !== $expected) { $fail++; fwrite(STDERR, "FAIL: $label\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n"); }
}
check('err ini', uploadErrorMessage(UPLOAD_ERR_INI_SIZE), 'حجم فایل بیش از حد مجاز سرور است');
check('err form', uploadErrorMessage(UPLOAD_ERR_FORM_SIZE), 'حجم فایل بیش از حد مجاز سرور است');
check('err nofile', uploadErrorMessage(UPLOAD_ERR_NO_FILE), 'فایلی ارسال نشده');
check('err cant write', uploadErrorMessage(UPLOAD_ERR_CANT_WRITE), 'خطای سرور در دریافت فایل');
check('img jpeg', imageExtForMime('image/jpeg'), 'jpg');
check('img svg refused', imageExtForMime('image/svg+xml'), null);
check('img gif refused', imageExtForMime('image/gif'), null);
check('video mp4', videoExtForMime('video/mp4'), 'mp4');
check('video avi refused', videoExtForMime('video/x-msvideo'), null);
check('cert pdf', certificateExtForMime('application/pdf'), 'pdf');
check('cert html refused', certificateExtForMime('text/html'), null);
check('ico mime', isIcoMime('image/x-icon'), true);
check('ico mime 2', isIcoMime('image/vnd.microsoft.icon'), true);
check('ico mime png', isIcoMime('image/png'), false);
check('ico magic', hasIcoMagic("\x00\x00\x01\x00\x01\x00"), true);
check('ico magic bad', hasIcoMagic("GIF89a"), false);
check('public ok', isSafePublicUploadPath('/uploads/media/' . str_repeat('a1', 16) . '.jpg'), true);
check('public avatar ok', isSafePublicUploadPath('/uploads/avatars/' . str_repeat('0f', 16) . '.png'), true);
check('public traversal', isSafePublicUploadPath('/uploads/media/../../config/config.php'), false);
check('public php ext', isSafePublicUploadPath('/uploads/media/' . str_repeat('a1', 16) . '.php'), false);
check('public uppercase hex', isSafePublicUploadPath('/uploads/media/' . str_repeat('A1', 16) . '.jpg'), false);
check('public other dir', isSafePublicUploadPath('/uploads/licenses/' . str_repeat('a1', 16) . '.pdf'), false);
check('private ok', isSafePrivatePath('licenses/' . str_repeat('a1', 16) . '.pdf'), true);
check('private tpl ok', isSafePrivatePath('templates/' . str_repeat('a1', 16) . '.zip'), true);
check('private traversal', isSafePrivatePath('licenses/../../x.pdf'), false);
check('private abs', isSafePrivatePath('/etc/passwd'), false);
check('dims ok', imageDimensionsAllowed(1920, 1080), true);
check('dims zero', imageDimensionsAllowed(0, 10), false);
check('dims huge side', imageDimensionsAllowed(10001, 10), false);
check('dims bomb', imageDimensionsAllowed(9000, 9000), false);
check('zip ok', zipEntryIsSafe('theme/css/app.css'), true);
check('zip dir ok', zipEntryIsSafe('theme/'), true);
check('zip slip', zipEntryIsSafe('../evil.html'), false);
check('zip slip nested', zipEntryIsSafe('a/../../evil.html'), false);
check('zip abs', zipEntryIsSafe('/etc/passwd'), false);
check('zip drive', zipEntryIsSafe('C:evil.txt'), false);
check('zip backslash', zipEntryIsSafe('a\\..\\evil'), false);
check('zip nul', zipEntryIsSafe("a\0.html"), false);
check('zip empty', zipEntryIsSafe(''), false);
check('zip ext html', zipEntryExtensionAllowed('index.html'), true);
check('zip ext php', zipEntryExtensionAllowed('x/shell.php'), false);
check('zip ext phtml', zipEntryExtensionAllowed('x/shell.phtml'), false);
check('zip ext svg refused', zipEntryExtensionAllowed('logo.svg'), false);
check('zip htaccess refused', zipEntryExtensionAllowed('.htaccess'), false);
check('zip double ext', zipEntryExtensionAllowed('a.php.png'), true);
check('zip no ext refused', zipEntryExtensionAllowed('Makefile'), false);
check('zip dir allowed', zipEntryExtensionAllowed('assets/'), true);
if ($fail > 0) { fwrite(STDERR, "$fail failure(s)\n"); exit(1); }
echo "file_rules_test: OK\n";
```

```php
<?php
// Needs GD. Uses a temp directory; never touches the real uploads folder.
define('FILESTORE_TESTING', true);
$tmpRoot = sys_get_temp_dir() . '/fs_test_' . bin2hex(random_bytes(4));
define('UPLOAD_BASE', $tmpRoot . '/public/uploads');
define('UPLOAD_MEDIA', UPLOAD_BASE . '/media');
define('UPLOAD_AVATARS', UPLOAD_BASE . '/avatars');
define('STORAGE_PRIVATE', $tmpRoot . '/storage/private');
define('MAX_IMAGE_SIZE', 5 * 1024 * 1024);
define('MAX_VIDEO_SIZE', 50 * 1024 * 1024);
require __DIR__ . '/../includes/file_store.php';

$fail = 0;
function check(string $label, $actual, $expected): void {
    global $fail;
    if ($actual !== $expected) { $fail++; fwrite(STDERR, "FAIL: $label\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n"); }
}
function fileOf(string $bytes, string $name = 'u.bin'): array {
    $t = tempnam(sys_get_temp_dir(), 'up');
    file_put_contents($t, $bytes);
    return ['name' => $name, 'tmp_name' => $t, 'error' => UPLOAD_ERR_OK, 'size' => strlen($bytes)];
}
function rejects(callable $fn, ?string $msg = null): bool {
    try { $fn(); } catch (FileStoreException $e) { return $msg === null || $e->getMessage() === $msg; }
    return false;
}

$im = imagecreatetruecolor(40, 20); imagefill($im, 0, 0, imagecolorallocate($im, 200, 10, 10));
ob_start(); imagejpeg($im); $jpeg = ob_get_clean();
ob_start(); imagepng($im); $png = ob_get_clean();
$webp = null; if (function_exists('imagewebp')) { ob_start(); imagewebp($im); $webp = ob_get_clean(); }

// 1. JPEG with trailing PHP payload is re-encoded: payload must vanish.
$evil = $jpeg . "<?php echo 'pwned'; ?>";
$r = FileStore::storeImage(fileOf($evil, 'x.jpg'), 'media');
check('jpeg stored', is_file($r['abs']), true);
check('jpeg ext', $r['ext'], 'jpg');
check('jpeg path shape', isSafePublicUploadPath($r['path']), true);
check('payload stripped', strpos((string)file_get_contents($r['abs']), '<?php') === false, true);
check('dims', [$r['width'], $r['height']], [40, 20]);
check('sha256 is of the original upload', $r['sha256'], hash('sha256', $evil));
check('remove works', FileStore::remove($r['path']), true);
check('file gone', is_file($r['abs']), false);
check('remove twice', FileStore::remove($r['path']), false);

// 2. PNG alpha path
$r2 = FileStore::storeImage(fileOf($png, 'a.png'), 'avatars');
check('png avatar bucket', strpos($r2['path'], '/uploads/avatars/') === 0, true);
FileStore::remove($r2['path']);

// 3. WebP
if ($webp !== null) { $r3 = FileStore::storeImage(fileOf($webp, 'a.webp'), 'media'); check('webp ext', $r3['ext'], 'webp'); FileStore::remove($r3['path']); }

// 4. rejections
check('html disguised as jpg', rejects(function () { FileStore::storeImage(fileOf('<html><script>alert(1)</script></html>', 'a.jpg'), 'media'); }, 'فرمت تصویر مجاز نیست (jpg/png/webp)'), true);
check('svg refused', rejects(function () { FileStore::storeImage(fileOf('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'a.svg'), 'media'); }), true);
check('gif refused', rejects(function () { FileStore::storeImage(fileOf("GIF89a\x01\x00\x01\x00\x00\x00\x00;", 'a.gif'), 'media'); }), true);
check('truncated image', rejects(function () use ($jpeg) { FileStore::storeImage(fileOf(substr($jpeg, 0, 50), 'a.jpg'), 'media'); }), true);
check('empty file', rejects(function () { FileStore::storeImage(fileOf('', 'a.jpg'), 'media'); }, 'فایل خالی است'), true);
check('too big', rejects(function () use ($jpeg) { FileStore::storeImage(fileOf($jpeg, 'a.jpg'), 'media', 10); }, 'حجم فایل بیش از حد مجاز است'), true);
check('upload error mapped', rejects(function () { FileStore::storeImage(['error' => UPLOAD_ERR_INI_SIZE, 'tmp_name' => '', 'name' => 'a'], 'media'); }, 'حجم فایل بیش از حد مجاز سرور است'), true);
check('bad bucket', (function () use ($jpeg) { try { FileStore::storeImage(fileOf($jpeg), '../etc'); } catch (InvalidArgumentException $e) { return true; } return false; })(), true);

// 5. ICO
$ico = "\x00\x00\x01\x00\x01\x00\x10\x10\x00\x00\x01\x00\x20\x00" . str_repeat("\x00", 40);
$rIco = (function () use ($ico) { try { return FileStore::storeIco(fileOf($ico, 'f.ico')); } catch (FileStoreException $e) { return $e->getMessage(); } })();
check('ico handled (stored or rejected by finfo build)', is_array($rIco) || $rIco === 'فایل ICO معتبر نیست', true);
if (is_array($rIco)) { FileStore::remove($rIco['path']); }
check('png as ico refused', rejects(function () use ($png) { FileStore::storeIco(fileOf($png, 'f.ico')); }, 'فایل ICO معتبر نیست'), true);

// 6. private
$pdf = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF";
$p = FileStore::storePrivate(fileOf($pdf, 'c.pdf'), 'licenses', ['application/pdf' => 'pdf'], 1024 * 1024);
check('private rel shape', isSafePrivatePath($p['rel']), true);
check('private under storage', strpos($p['abs'], STORAGE_PRIVATE . '/licenses/') === 0, true);
check('private absolute lookup', FileStore::privateAbsolute($p['rel']), $p['abs']);
check('private html refused', rejects(function () { FileStore::storePrivate(fileOf('<html></html>', 'c.pdf'), 'licenses', ['application/pdf' => 'pdf'], 1024); }, 'فرمت فایل مجاز نیست'), true);
check('private bad kind', (function () use ($pdf) { try { FileStore::storePrivate(fileOf($pdf), '../x', ['application/pdf' => 'pdf'], 1024); } catch (InvalidArgumentException $e) { return true; } return false; })(), true);
check('removePrivate traversal refused', FileStore::removePrivate('licenses/../../x.pdf'), false);
check('removePrivate works', FileStore::removePrivate($p['rel']), true);
check('privateAbsolute after delete', FileStore::privateAbsolute($p['rel']), null);

// 7. remove refuses anything outside the safe shape / base dir
file_put_contents($tmpRoot . '/victim.jpg', 'x');
check('remove traversal refused', FileStore::remove('/uploads/media/../../../victim.jpg'), false);
check('victim intact', is_file($tmpRoot . '/victim.jpg'), true);
check('remove null', FileStore::remove(null), false);

// cleanup
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmpRoot, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($it as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
@rmdir($tmpRoot);
if ($fail > 0) { fwrite(STDERR, "$fail failure(s)\n"); exit(1); }
echo "file_store_test: OK\n";
```

`php tests/run_all.php` must be green. `file_store_test.php` needs the GD extension; if it is missing it prints `SKIP (gd)` and exits 0 — then the gate row for it is ⏭️ with that reason.

## P4.8 Behaviour that must not change

Allowed library types stay jpg/png/webp/mp4/webm; image limit 5 MB, video 50 MB; the front end still sends `file` to `/api/media/upload.php`; Persian error texts that already exist are unchanged. Persian digits and Jalali rendering are untouched.

## P4.9 Backfill and GC tools (one-off, written by you from this specification)

**`database/tools/backfill_files.php`** — CLI, **dry-run by default** (prints what it would do); `--apply` performs the changes; `--dedupe` additionally lets it repoint duplicates. Uses `getPDO()` and the app constants; never touches a file outside `UPLOAD_BASE` / `STORAGE_PRIVATE`. Each step prints `OK | SKIP | PROBLEM` lines and a final count.

1. **Hash legacy library rows.** For every `media_library` row with `sha256 IS NULL` whose file exists under `UPLOAD_BASE`: `hash_file('sha256')` of the stored file (documented limitation: legacy hashes are of the stored bytes). On `1062` print both ids as `PROBLEM duplicate content` and leave both rows alone (a human decides).
2. **Register unregistered public assets.** For each distinct path in `panel_users.avatar_path`, `site_settings.logo_path`, `site_settings.favicon_path` that starts with `/uploads/`, exists on disk and has **no** `media_library.file_path` row: insert one (`purpose='avatar'` for avatars, `'site_asset'` otherwise; `file_type='image'`; MIME by `finfo`; size, width/height by `getimagesize` (NULL for `.ico`); `uploaded_by` = the user that owns the avatar, or the first `role='owner'` user for site assets). On `1062` the same bytes are already registered: with `--dedupe` repoint every referencing column to the existing row's path and delete the duplicate file; without it print `PROBLEM duplicate asset (use --dedupe)`.
3. **Move legacy licence certificates.** For each `licenses.certificate_file LIKE '/uploads/%'`: if the file is missing ⇒ `PROBLEM missing file` (leave the column); if `finfo` says it is not one of pdf/jpg/png/webp ⇒ move it to `storage/private/quarantine/` (create), set the column `NULL`, print `QUARANTINED`; otherwise copy to `storage/private/licenses/<new 32-hex>.<ext>`, `UPDATE` the row to the relative path, then delete the old file.
4. **Orphan template zips.** Every file in `public/uploads/templates/` (the old upload code never recorded them) is moved to `storage/private/quarantine/` and listed; nothing is linked to a `templates` row.
5. **SVG report.** Print every `site_settings.logo_path` / `favicon_path` ending in `.svg` (they are not served-safe any more) as `PROBLEM svg asset — owner must upload a PNG/JPG/ICO in Settings → General`. Do not convert or delete.

**`database/tools/gc_files.php`** — CLI report of files in `public/uploads/{media,avatars}` that have no `media_library.file_path` row **and** are not referenced by `site_settings`/`panel_users`, and of files in `storage/private/{licenses,templates}` not referenced by `licenses.certificate_file` / `templates.package_path`. Only files whose names match the safe-name regexes and that are **at least 24 hours old** are candidates (a file may be mid-upload). `--delete` removes the candidates; the default only reports. It never touches `quarantine/`.

## P4.10 Must NOT do (Phase 4)

| Forbidden |
|---|
| Accepting SVG anywhere; trusting `$_FILES[...]['name']`, `['type']` or the extension; `move_uploaded_file()` outside `FileStore`. |
| Extracting a template zip (no `extractTo`, no shell `unzip`) — inspection only. |
| Serving anything from `storage/private/` other than through `public/file.php`; building a private path from request input. |
| Deleting a file from request code without going through `FileStore::remove*()` (they re-validate the path). |
| Adding a `files` table, a `visibility` column, a CDN/storage abstraction or a Composer image library. |
| Encrypting API keys, touching `sales/*`, the zarinpal form, or `.htaccess` of `public/` (P6/P7). |
| Dropping `media_usage` or any legacy table (P5). |
| Running `backfill_files.php --apply` or `gc_files.php --delete` without a fresh backup of `public/uploads/` and the database taken in this phase. |
| Changing the block/builder JS or PHP. |

## P4.11 Gate / checklist

| # | Check |
|---|---|
| 1 | `php tests/run_all.php` exit 0; `php -l` on every changed file; Arabic-letter grep clean; the dead-constants grep prints only `config/config.php` before deletion and nothing after. |
| 2 | `0005` ran on the clone, then on `business_panel`; `diagnose.php --phase=4`: D12 PASS; D13/D14/D22 INFO or PASS. |
| 3 | **H2/H4 (negative uploads, via the media page and via curl with a valid session + CSRF):** a `.php` file renamed `.jpg` ⇒ rejected; a real PNG with `<?php` appended ⇒ stored **re-encoded** (the stored file contains no `<?php`); an SVG ⇒ rejected everywhere (library, logo, favicon, avatar); a 6 MB image ⇒ rejected; GIF ⇒ rejected. |
| 4 | **H9:** uploading the same image twice to the library ⇒ second answer is `success:true, duplicate:true` with `STR_MEDIA_DUPLICATE`, still **one** row and one file on disk. Same image as logo **and** library item is allowed (different `purpose`). |
| 5 | **Private files:** upload a PDF certificate ⇒ stored under `storage/private/licenses/<hex>.pdf`; `GET /uploads/licenses/<anything>` ⇒ 404; `GET /file.php?type=license&id=N` ⇒ the PDF with a logged-in user who has `licenses.view`, **403/302 for staff without it**, 302/401 when logged out; replacing the certificate deletes the previous file from disk. |
| 6 | **Templates:** a clean zip ⇒ success, `package_path`/`package_sha256` set, file in `storage/private/templates/`, **nothing extracted anywhere**. Crafted zips (entry `../evil.php`; entry `a.php`; a symlink entry; > 500 entries; > 100 MB unpacked) ⇒ the matching `STR_ZIP_*` message and **no file left behind**. Staff (non-owner) ⇒ 403. |
| 7 | **Avatar:** change the avatar twice ⇒ the first file is gone from disk and from `media_library`; remove a staff member who has an avatar ⇒ file and row gone, `diagnose` D20 = 0 rows. Invalid avatar ⇒ error message, nothing else saved. |
| 8 | **Settings/General:** logo (jpg/png/webp) and favicon (PNG, ICO) save; an SVG favicon ⇒ `STR_FAVICON_FORMAT`; replacing the logo deletes the old file; a validation error (empty site name) leaves files and DB untouched. |
| 9 | **Media page:** search with `%` and `_` finds only literal matches; `?page=999` shows the last page; deleting a used file still answers 409 (P3 behaviour); `duplicate` shows the info dialog. |
| 10 | **Backfill:** dry-run output pasted into the changelog; after `--apply` (backup first): `diagnose` D14 = 0, D22 has no unregistered path other than reported SVGs, D13 shows only reported duplicates. The three real avatar files in the project are accounted for (registered, deduped with `--dedupe` or reported). |
| 11 | **Owner regression:** upload an image + a video in the library, delete both, edit profile, change site settings, upload a template zip — all work. |
| 12 | **Bytes on disk:** `find public/uploads -type f ! -name .htaccess` lists only files matching `^[0-9a-f]{32}\.(jpg|png|webp|mp4|webm|ico)$`. |

Append the P4 section to the changelog (include the backfill dry-run and the *Behaviour changes* list: invalid avatar now errors, zarinpal status `pending`, SVG no longer accepted, template zip limit 50 MB) before starting P5.

---

# PHASE 5 — Legacy cleanup, single source of truth for the active template (migration `0006`)

**Goal:** close H5, M4, M10 (snapshots, notifications), M11 (install/activate/categories). Order inside the phase: **code first → tests → `0006` [post-code] last.** Nothing in P5 may be dropped before the code that stopped using it is deployed.

## P5.1 File map

```
panel/
├── database/migrations/0006_legacy_cleanup_post_code.php   N  [post-code]
├── includes/header.php                                      M  notifications switch (NOTIFICATIONS_ENABLED)
├── config/config.php                                        M  + NOTIFICATIONS_ENABLED
└── public/
    ├── api/templates/{activate,install}.php                 M
    └── modules/
        ├── dashboard/index.php                              M
        └── templates/index.php                              M
```

## P5.2 Decisions and design

- **Single source of truth (H5):** `site_settings.active_template_id` is the only record of the active template. `templates.is_active` (+ its index) is dropped. A template is "active" ⇔ its id equals `site_settings.active_template_id`.
- **FK `fk_settings_template` → `RESTRICT`:** deleting the active template must fail loudly rather than leave the site without one (today `SET NULL` silently does that). *Nothing in the app deletes templates yet; this protects Part 7 and manual SQL.*
- **Legacy tables** `page_blocks, article_blocks, pages, articles, article_categories, media_usage` are dead since Part 0 (builder replaced them). Migration `0006` drops them **only if they are empty** (guard, `MIGRATE_DROP_LEGACY=1` is the explicit human override). `dashboard_snapshots.articles_count` stays as a column (always `0`) — dropping it buys nothing and keeps old rows valid.
- **Install** is a state flip with no file work: *Author's decision* — a template package is a **file the future renderer reads**; extracting it is Part 7's job, so `install.php` only validates (row exists, uploaded templates must have a readable package) and sets `is_installed`.
- **Category «همه»** is a UI filter, not data: the row with slug `all` is deleted; the page keeps its own «همه» pill.

## P5.3 Code

**`config/config.php`** — add `define('NOTIFICATIONS_ENABLED', false); // no code produces notifications yet; flip to true when something does`.

**`includes/header.php`** — wrap the two notification queries (lines that run `SELECT * FROM notifications …` and `SELECT COUNT(*) …`) in `if (NOTIFICATIONS_ENABLED) { … } else { $notifications = []; $unreadCount = 0; }`. Do not change the markup (an empty list already renders «اعلانی وجود ندارد»). Nothing else in `header.php` changes in this phase.

**`public/modules/dashboard/index.php`** — replace the statistics block and the card list (D24). Keep everything else (health block, greeting, `trend()`):
```php
$pagesCount=(int)$pdo->query("SELECT COUNT(*) FROM builder_canvases WHERE buildable_type='page'")->fetchColumn();
$site=$pdo->query("SELECT * FROM site_settings WHERE id=1")->fetch();
$tmpl=null;
if($site && $site['active_template_id']){
  $s=$pdo->prepare("SELECT name, version FROM templates WHERE id=?"); $s->execute([$site['active_template_id']]); $tmpl=$s->fetch();
}
// daily snapshot (lazy). INSERT IGNORE + UNIQUE(snapshot_date) = no race between two simultaneous first visits.
$weekAgo=null;
try{
  $pdo->prepare("INSERT IGNORE INTO dashboard_snapshots (snapshot_date, pages_count, articles_count) VALUES (CURDATE(), ?, 0)")->execute([$pagesCount]);
  // the newest snapshot that is at least 7 days old (exact "today-7" row may be missing on a quiet week)
  $stmt=$pdo->prepare("SELECT pages_count FROM dashboard_snapshots WHERE snapshot_date <= CURDATE() - INTERVAL 7 DAY ORDER BY snapshot_date DESC LIMIT 1");
  $stmt->execute(); $weekAgo=$stmt->fetch();
}catch(Throwable $e){ error_log('dashboard snapshot: '.$e->getMessage()); }
```
Remove the card «تعداد مقالات» (the whole `col-md-4` block) and every use of `$articlesCount`. The card «تعداد ساخت سایت» now shows `$pagesCount` with `trend($pagesCount, $weekAgo['pages_count'] ?? null)`. The card «نوع قالب» shows `$tmpl['name']`. Use `CURDATE()` (database clock) for both writes and reads so PHP and MySQL time zones can never disagree. The "last snapshot" row is the only change in trend semantics: record it under *Behaviour changes*.

**`public/api/templates/activate.php`** — replace the transaction body; `is_active` no longer exists after `0006`, so the code must not mention it. During the window between the code deploy and `0006` the column still exists but is simply unused:
```php
$st=$pdo->prepare("SELECT id, is_installed FROM templates WHERE id=?"); $st->execute([$id]); $tpl=$st->fetch();
if(!$tpl) jsonResponse(['success'=>false,'message'=>'قالب یافت نشد'],404);
if(!(int)$tpl['is_installed']) jsonResponse(['success'=>false,'message'=>'قالب ابتدا باید نصب شود'],400);
try{
  $pdo->prepare("UPDATE site_settings SET active_template_id=? WHERE id=1")->execute([$id]);
  logActivity('activate','templates','فعال‌سازی قالب '.$id);
  jsonResponse(['success'=>true,'message'=>'قالب با موفقیت فعال شد']);
}catch(Throwable $e){
  error_log('templates/activate.php: '.$e->getMessage());
  jsonResponse(['success'=>false,'message'=>'خطا در فعال‌سازی قالب'],500);   // STR_ACTIVATE_ERROR — never echo $e->getMessage()
}
```
Keep the first lines of the file (requires, `requireLogin(); requirePermission('templates','edit');`, CSRF, `$id` check) as they are. The original returned the raw exception text to the browser — that is gone.

**`public/api/templates/install.php`** — replace the body after the CSRF check:
```php
if($_SERVER['REQUEST_METHOD']!=='POST') jsonResponse(['success'=>false,'message'=>'متد مجاز نیست'],405);
$id=(int)($_POST['id']??0);
if(!$id) jsonResponse(['success'=>false,'message'=>'شناسه نامعتبر'],400);
$pdo=getPDO();
$st=$pdo->prepare("SELECT id, source, package_path, is_installed FROM templates WHERE id=?"); $st->execute([$id]); $tpl=$st->fetch();
if(!$tpl) jsonResponse(['success'=>false,'message'=>'قالب یافت نشد'],404);
if((int)$tpl['is_installed']) jsonResponse(['success'=>true,'message'=>'قالب نصب شد']);          // idempotent
if($tpl['source']==='uploaded' && FileStore::privateAbsolute($tpl['package_path'])===null)
  jsonResponse(['success'=>false,'message'=>'فایل بسته‌ی قالب پیدا نشد'],400);                   // STR_PACKAGE_MISSING
$pdo->prepare("UPDATE templates SET is_installed=1, installed_at=NOW() WHERE id=? AND is_installed=0")->execute([$id]);
logActivity('install','templates','نصب قالب '.$id);
jsonResponse(['success'=>true,'message'=>'قالب نصب شد']);
```
(add `require_once __DIR__ . '/../../../includes/file_store.php';`). The original updated any id without checking it existed.

**`public/modules/templates/index.php`** — edits:
1. `$activeId=$site['active_template_id']` stays; add `$activeId=$site ? (int)$site['active_template_id'] : 0;` and decide "active" per card with `(int)$t['id']===$activeId` instead of `$t['is_active']`. The query for the cards selects `t.*` — no `is_active`.
2. Category counts in **one** query instead of N: `SELECT c.id, c.slug, c.name, COUNT(t.id) AS n FROM template_categories c LEFT JOIN templates t ON t.category_id=c.id GROUP BY c.id, c.slug, c.name ORDER BY c.sort_order` (replaces the loop of `COUNT(*)` prepares). The «همه» pill shows the total count of templates.
3. Page clamp exactly as in P4 `media/index.php` item 3 (`$per=9`).
4. Offsets/limits stay integers cast with `(int)` (already the case).
5. The upload form is shown only to the owner — unchanged. The file input gets `accept=".zip"` (already) and the JS shows `j.message` as today.

## P5.4 Migration `0006` [post-code] — run LAST

Deploy and test the code above first (all of `dashboard`, `activate`, `install`, `templates/index.php`, `media/delete.php`, `header.php` must run **without** `pages`, `articles`, `media_usage` and `templates.is_active`). Then:
```php
<?php
// Phase 5 — [post-code]: run LAST in P5, after dashboard, delete.php, activate.php and templates/index.php stopped using the legacy objects.
return [
    'description' => 'Drop legacy tables and templates.is_active, settings FK RESTRICT, remove category "all"',
    'up' => function (PDO $pdo, Migrator $m): void {
        // Guard: the legacy tables are expected to be empty. Non-empty needs a human decision.
        $legacy = ['page_blocks', 'article_blocks', 'pages', 'articles', 'article_categories', 'media_usage'];
        $nonEmpty = [];
        foreach ($legacy as $t) {
            if ($m->tableExists($t) && $m->count("SELECT COUNT(*) FROM `$t`") > 0) { $nonEmpty[] = $t; }
        }
        if ($nonEmpty && getenv('MIGRATE_DROP_LEGACY') !== '1') {
            throw new RuntimeException('Legacy table(s) with rows: ' . implode(', ', $nonEmpty)
                . '. Back up, then re-run with MIGRATE_DROP_LEGACY=1 if the data is really disposable.');
        }
        // Children first, then parents (FK order).
        $pdo->exec("DROP TABLE IF EXISTS page_blocks, article_blocks, pages, articles, article_categories, media_usage");

        // H5: site_settings.active_template_id becomes the only source of truth.
        if ($m->columnExists('templates', 'is_active')) {
            $pdo->exec("UPDATE site_settings SET active_template_id = (SELECT id FROM templates WHERE is_active = 1 ORDER BY id LIMIT 1)
                        WHERE id = 1 AND active_template_id IS NULL
                          AND EXISTS (SELECT 1 FROM templates WHERE is_active = 1)");
            if ($m->indexExists('templates', 'idx_templates_active')) {
                $pdo->exec("ALTER TABLE templates DROP INDEX idx_templates_active");
            }
            $pdo->exec("ALTER TABLE templates DROP COLUMN is_active");
        }
        // Deleting the active template must fail loudly instead of silently leaving the site without one.
        $m->replaceForeignKey('site_settings', 'fk_settings_template', 'active_template_id', 'templates', 'id', 'RESTRICT');

        $pdo->exec("DELETE FROM template_categories WHERE slug = 'all'");
    },
];
```

Clone first. If the guard reports non-empty legacy tables, **stop** and report them to Pouya (do not set `MIGRATE_DROP_LEGACY` yourself). After the run: `diagnose.php --phase=5` ⇒ D11 (incl. `fk_settings_template=RESTRICT`), D15, D16, D17 PASS.

## P5.5 Must NOT do (Phase 5)

| Forbidden |
|---|
| Dropping `dashboard_snapshots.articles_count`, the `articles` **permission key**, or the `modules/articles` page (its menu label «صفحات ساخته شده» is historical and stays). |
| Extracting or rendering a template package in `install.php`; adding "uninstall"/"delete template". |
| Writing notifications or building a notifications feature; touching `public/api/notifications/mark_read.php`. |
| Running `0006` before the code is deployed and tested, or with `MIGRATE_DROP_LEGACY=1` on your own initiative. |
| Re-introducing `is_active` anywhere (grep must be empty). |
| Changing the dashboard's greeting, health card or layout beyond the two card edits. |

## P5.6 Gate / checklist

| # | Check |
|---|---|
| 1 | `php tests/run_all.php` exit 0; `php -l` on changed files; Arabic-letter grep clean; `grep -rn "is_active" public includes` prints nothing (except the permission-matrix files if they use an unrelated `is_active` — then list them); `grep -rniE "FROM (pages|articles|media_usage)\b" public includes` prints nothing. |
| 2 | Code deployed **before** `0006`: dashboard, templates page, activate, install, media delete and every page render without PHP errors while the legacy tables still exist. |
| 3 | `0006` ran on the clone, then on `business_panel`; `diagnose.php --phase=5` PASS (D11, D15, D16, D17). |
| 4 | **H5:** activate template A ⇒ `site_settings.active_template_id = A`; the card shows «قالب فعال» for A only and the dashboard card «نوع قالب» shows A; activate B ⇒ only B. A non-installed template ⇒ `قالب ابتدا باید نصب شود`. |
| 5 | **Install:** an uploaded template with a package installs; one whose package file was removed from disk ⇒ `STR_PACKAGE_MISSING`; unknown id ⇒ 404 `قالب یافت نشد`. |
| 6 | **FK RESTRICT (clone only):** `DELETE FROM templates WHERE id = <active id>` fails with a foreign-key error; deleting a non-active template works (use a row you inserted yourself). |
| 7 | **Dashboard:** exactly two snapshot rows are never created for the same date (open the page in two tabs at once); the card «تعداد ساخت سایت» equals `SELECT COUNT(*) FROM builder_canvases WHERE buildable_type='page'`; there is no «تعداد مقالات» card. |
| 8 | **Notifications:** with `NOTIFICATIONS_ENABLED=false` the page runs no `notifications` query (check with the MySQL general log on the clone, or a temporary `error_log` in a probe — list the probe in cleanup) and the bell shows «اعلانی وجود ندارد». |
| 9 | Templates page: category counts correct, «همه» pill present, `?page=999` clamps, the «همه» category row no longer exists in `template_categories`. |
| 10 | **Owner regression:** staff with only `templates.view` sees the page but gets 403 on activate/install. |

Append the P5 section to the changelog before starting P6.

---


# PHASE 6 — Secrets and database access (migration `0007`)

**Goal:** close H3 and H10. API keys are encrypted at rest and never sent back to the browser; the application connects with a least-privilege database user; production refuses to start on unsafe configuration. Order inside the phase: **`0007` [pre-code] → code → tests → one-off encryption of existing rows → gate.** There is no [post-code] migration.

## P6.1 File map

```
panel/
├── config/
│   ├── database.php                                   M  production fail-closed (final version; replaces the P1 one)
│   └── env.example.php                                M  comments only: point to gen_secret_key.php / create_db_users.sql
├── database/
│   ├── migrations/0007_secret_columns_pre_code.php    N  [pre-code]
│   └── tools/
│       ├── create_db_users.sql                        N  panel_app (DML) and panel_migrator (DDL); run once by a DB admin
│       ├── gen_secret_key.php                         N  prints a fresh APP_SECRET_KEY; writes nothing
│       ├── encrypt_existing_secrets.php               N  one-off: encrypts plaintext rows already in the database
│       ├── env_check.php                              M  + secret key / sodium / DB user / php.ini limits
│       └── diagnose.php                               M  + D18 (already specified in §P0.5)
├── includes/secrets.php                               N  secret_key/is_encrypted/encrypt/decrypt/mask
├── tests/secrets_test.php                             N
└── public/
    ├── api/sales/{connect,disconnect}.php             M
    ├── api/licenses/update.php                        M  the two lines marked "P6"
    └── modules/licenses/index.php                     M  zarinpal form never prints the key
```

## P6.2 Design (why)

| Item | Design | Reason |
|---|---|---|
| Algorithm | libsodium `crypto_secretbox` (XSalsa20-Poly1305), random 24-byte nonce per value | Authenticated encryption, in PHP core since 7.2 (no package). A tampered value fails to decrypt instead of returning garbage. |
| Stored format | `v1:` + `base64(nonce ‖ ciphertext)` | The prefix makes "is this value encrypted?" a string test and leaves room for a `v2:` (key rotation) without a migration. |
| Key | env `APP_SECRET_KEY` = base64 of exactly 32 random bytes; **never** in the database, the repo, a log or the changelog | A database dump alone must not reveal the secrets. |
| Columns | `marketplace_connections.api_key`, `.api_secret` → `VARCHAR(512)`; zarinpal `api_key` inside `licenses.credentials` (JSON string) | A 300-byte secret encrypts to ≈ 454 characters; 512 leaves headroom. `credentials` is already `TEXT`/JSON. |
| UI | Stored secrets are **never** rendered. Forms show an empty field with `STR_KEY_PLACEHOLDER`; a blank submit keeps the stored value | A secret that cannot be displayed cannot leak through page source, screenshots or a shared screen. |
| DB users | `panel_app`: `SELECT, INSERT, UPDATE, DELETE`; `panel_migrator`: DML + `CREATE, ALTER, DROP, INDEX, REFERENCES` | A SQL injection or stolen app credential can no longer `DROP` tables or read other schemas. `migrate.php` is the only consumer of the migrator user (`DB_MIGRATE_USER`/`DB_MIGRATE_PASS`, already implemented in P0). |
| Fail-closed | any `APP_ENV` other than exactly `local` is production: DB settings must all come from the environment, `root` is refused, `display_errors` is off | A forgotten env var must never silently fall back to `root` with an empty password. |

## P6.3 Migration `0007` [pre-code] — run FIRST

```php
<?php
// Phase 6 — [pre-code]: widen the columns that will hold "v1:" + base64(nonce || ciphertext). Run FIRST in P6.
return [
    'description' => 'Secrets: widen marketplace_connections.api_key / api_secret to VARCHAR(512)',
    'up' => function (PDO $pdo, Migrator $m): void {
        foreach (['api_key', 'api_secret'] as $col) {
            if ($m->columnType('marketplace_connections', $col) !== 'varchar(512)') {
                $pdo->exec("ALTER TABLE marketplace_connections MODIFY $col VARCHAR(512) DEFAULT NULL");
            }
        }
    },
];
```

Clone first (Rule 12), then `business_panel`.

## P6.4 Code

**`includes/secrets.php`** (reference implementation, unit-tested; requires the `sodium` extension — `env_check.php` reports it):
```php
<?php
// Application-level secret encryption (libsodium secretbox). Format: "v1:" . base64(nonce(24) . ciphertext).
// The key lives in the environment (APP_SECRET_KEY = base64 of 32 random bytes), never in the database or the repo.

const SECRET_PREFIX = 'v1:';

/** @throws RuntimeException when the key is missing or malformed. */
function secret_key(): string {
    $b64 = getenv('APP_SECRET_KEY');
    if (!is_string($b64) || $b64 === '') {
        throw new RuntimeException('APP_SECRET_KEY is not set');
    }
    $key = base64_decode($b64, true);
    if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
        throw new RuntimeException('APP_SECRET_KEY must be base64 of exactly 32 bytes');
    }
    return $key;
}

function secret_is_encrypted(?string $value): bool {
    return $value !== null && strncmp($value, SECRET_PREFIX, strlen(SECRET_PREFIX)) === 0;
}

/** @throws RuntimeException */
function secret_encrypt(string $plain, ?string $key = null): string {
    $key = $key ?? secret_key();
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $cipher = sodium_crypto_secretbox($plain, $nonce, $key);
    return SECRET_PREFIX . base64_encode($nonce . $cipher);
}

/** Returns null when the payload is not ours, is tampered with, or the key is wrong. Never throws on bad payloads. */
function secret_decrypt(?string $payload, ?string $key = null): ?string {
    if (!secret_is_encrypted($payload)) { return null; }
    $raw = base64_decode(substr($payload, strlen(SECRET_PREFIX)), true);
    if ($raw === false || strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) { return null; }
    $key = $key ?? secret_key();
    $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $plain = sodium_crypto_secretbox_open($cipher, $nonce, $key);
    return $plain === false ? null : $plain;
}

/** What the UI may show for a stored secret: bullets plus at most the last 4 characters (only when the secret is long). */
function secret_mask(?string $plain): string {
    if ($plain === null || $plain === '') { return ''; }
    $len = mb_strlen($plain, 'UTF-8');
    return $len >= 12 ? '••••••••' . mb_substr($plain, -4, null, 'UTF-8') : '••••••••';
}
```

**`config/database.php`** — replace the whole file with the final fail-closed version. `getPDO()` must keep its name and signature; the generic Persian message `خطا در اتصال به پایگاه داده` is the only thing a browser ever sees, details go to `error_log`:
```php
<?php
require_once __DIR__ . '/config.php';

function getPDO(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    // Production fails closed: every setting must come from the environment and root is refused.
    $host = getenv('DB_HOST');
    $db   = getenv('DB_NAME');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS');
    if (APP_ENV === 'production') {
        if (!$host || !$db || !$user || $pass === false || $pass === '' || strtolower((string)$user) === 'root') {
            error_log('Refusing to start: production needs DB_HOST, DB_NAME, DB_USER (not root) and a non-empty DB_PASS in the environment.');
            failDatabase();
        }
    } else {
        $host = $host ?: '127.0.0.1';
        $db   = $db   ?: 'business_panel';
        $user = $user ?: 'root';
        $pass = ($pass === false) ? '' : $pass;
    }

    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("SET time_zone = '" . DB_TIMEZONE_OFFSET . "'");
    } catch (PDOException $e) {
        error_log('DB connection failed: ' . $e->getMessage());
        failDatabase();
    }
    return $pdo;
}

/** Never returns. Generic message only. */
function failDatabase(): void {
    if (!headers_sent()) { http_response_code(500); }
    if (function_exists('isApiRequest') && isApiRequest()) {
        if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); }
        echo json_encode(['success' => false, 'message' => 'خطا در اتصال به پایگاه داده'], JSON_UNESCAPED_UNICODE);
    } else {
        if (!headers_sent()) { header('Content-Type: text/plain; charset=utf-8'); }
        echo 'خطا در اتصال به پایگاه داده';
    }
    exit;
}
```

**`config/env.example.php`** — leave the keys as they are; extend the two comments so they read `// P6: php database/tools/gen_secret_key.php` for `APP_SECRET_KEY` and `// run database/tools/create_db_users.sql once, then put the generated passwords here (local) or in real env vars (production)` for the DB users.

**`database/tools/gen_secret_key.php`** — `php_sapi_name() === 'cli'` guard, then `echo base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)), PHP_EOL;` and a one-line reminder on STDERR ("store it in env.local.php or a real environment variable; if you lose it the stored secrets cannot be recovered"). It never writes a file.

**`database/tools/create_db_users.sql`** — exactly this (the executor replaces the two `<…>` placeholders with passwords generated at run time, and never writes those passwords into any committed file or the changelog):
```sql
-- Run once as a DB administrator. Adjust the host part ('localhost') to your server.
CREATE USER IF NOT EXISTS 'panel_app'@'localhost'      IDENTIFIED BY '<generate: 24+ chars>';
CREATE USER IF NOT EXISTS 'panel_migrator'@'localhost' IDENTIFIED BY '<generate: 24+ chars>';
GRANT SELECT, INSERT, UPDATE, DELETE ON business_panel.* TO 'panel_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES ON business_panel.* TO 'panel_migrator'@'localhost';
FLUSH PRIVILEGES;
```

**`database/tools/env_check.php`** — add these lines to its output (never print secret values): `APP_ENV` (and "treated as production: yes/no"); `APP_SECRET_KEY` present and valid (✓/✗ only); `sodium` extension; DB user name and a ✗ when it is `root` while production; and php.ini limits with a WARN when too low: `upload_max_filesize` ≥ 50M, `post_max_size` ≥ 55M, `memory_limit` ≥ 256M (large images are decoded by GD), `max_file_uploads` ≥ 20 (M12). The task of raising them is Pouya's (php.ini); the tool only reports.

**`public/api/sales/connect.php`** — replace the body after the CSRF check (keep the file's `require`s, add `require_once __DIR__ . '/../../../includes/secrets.php';`):
```php
if($_SERVER['REQUEST_METHOD']!=='POST') jsonResponse(['success'=>false,'message'=>'متد مجاز نیست'],405);
$mid=(int)($_POST['marketplace_id']??0);
$apiKey=trim((string)($_POST['api_key']??''));
$apiSecret=trim((string)($_POST['api_secret']??''));
$flags=[(int)!empty($_POST['sync_products']),(int)!empty($_POST['sync_price_stock']),(int)!empty($_POST['receive_orders']),(int)!empty($_POST['sales_report'])];
$pdo=getPDO();
$chk=$pdo->prepare("SELECT id FROM marketplaces WHERE id=?"); $chk->execute([$mid]);
if(!$mid || !$chk->fetchColumn()) jsonResponse(['success'=>false,'message'=>'مارکت‌پلیس نامعتبر'],400);          // STR_MARKET_INVALID
if($apiKey==='') jsonResponse(['success'=>false,'message'=>'کلید API الزامی است'],400);
if(strlen($apiKey)>300 || strlen($apiSecret)>300) jsonResponse(['success'=>false,'message'=>'کلید API یا رمز API بیش از حد طولانی است'],400);   // STR_KEY_TOO_LONG
try{
  $encKey=secret_encrypt($apiKey);
  $encSecret=$apiSecret!=='' ? secret_encrypt($apiSecret) : null;
}catch(Throwable $e){
  error_log('sales/connect.php: '.$e->getMessage());
  jsonResponse(['success'=>false,'message'=>'خطا در رمزنگاری اطلاعات'],500);                                     // STR_SECRET_ERROR — nothing is stored
}
$pdo->prepare("INSERT INTO marketplace_connections (marketplace_id, is_connected, api_key, api_secret, sync_products, sync_price_stock, receive_orders, sales_report, connected_at, connected_by)
               VALUES (?,1,?,?,?,?,?,?,NOW(),?)
               ON DUPLICATE KEY UPDATE is_connected=1, api_key=VALUES(api_key), api_secret=VALUES(api_secret), sync_products=VALUES(sync_products),
                 sync_price_stock=VALUES(sync_price_stock), receive_orders=VALUES(receive_orders), sales_report=VALUES(sales_report), connected_at=NOW(), connected_by=VALUES(connected_by)")
    ->execute([$mid,$encKey,$encSecret,$flags[0],$flags[1],$flags[2],$flags[3],(int)$_SESSION['user_id']]);
logActivity('sales_connect','sales','اتصال مارکت‌پلیس '.$mid);
jsonResponse(['success'=>true,'message'=>'اتصال با موفقیت برقرار شد']);
```
(The success text `اتصال با موفقیت برقرار شد` is the original. A previously blank `api_secret` was stored as `''`; it is now `NULL`.)

**`public/api/sales/disconnect.php`** — replace the `UPDATE` with `UPDATE marketplace_connections SET is_connected=0, api_key=NULL, api_secret=NULL WHERE marketplace_id=?` (disconnecting destroys the stored credentials), add the `POST`-only check and `$mid` validation (`if(!$mid) … 'شناسه نامعتبر' 400`), and `logActivity('sales_disconnect','sales','قطع اتصال مارکت‌پلیس '.$mid)` after the update. Message `اتصال قطع شد` unchanged.

**`public/api/licenses/update.php`** — the two `P6` lines of the P4 file become:
1. The line `$creds['api_key']=postedText('api_key',512);` becomes `$plainKey=trim((string)($_POST['api_key']??'')); if(strlen($plainKey)>300) jsonResponse(['success'=>false,'message'=>'کلید API یا رمز API بیش از حد طولانی است'],400);` (`STR_KEY_TOO_LONG`).
2. Immediately **before** `$credsJson=json_encode(…)` insert (it needs `$pdo`, which exists by then; add `require_once …/includes/secrets.php`):
```php
if($type==='zarinpal'){
  $cur=$pdo->prepare("SELECT credentials FROM licenses WHERE license_type='zarinpal'"); $cur->execute();
  $curCreds=json_decode((string)$cur->fetchColumn(), true);
  $curKey=is_array($curCreds) ? (string)($curCreds['api_key']??'') : '';
  try{
    if($plainKey!=='')       { $creds['api_key']=secret_encrypt($plainKey); }   // new key typed
    elseif($curKey!=='')     { $creds['api_key']=secret_is_encrypted($curKey) ? $curKey : secret_encrypt($curKey); }   // blank = keep (and upgrade legacy plaintext)
    else                     { $creds['api_key']=''; }
  }catch(Throwable $e){
    if($newCert) FileStore::removePrivate($newCert['rel']);
    error_log('licenses/update.php: '.$e->getMessage());
    jsonResponse(['success'=>false,'message'=>'خطا در رمزنگاری اطلاعات'],500);   // STR_SECRET_ERROR
  }
}
```

**`public/modules/licenses/index.php`** — zarinpal form only: the `api_key` input loses its `value="…"` attribute entirely, gets `placeholder="برای تغییر، کلید جدید را وارد کنید (در غیر این صورت خالی بگذارید)"` (`STR_KEY_PLACEHOLDER`) and `autocomplete="new-password"`; when `!empty($creds['api_key'])` print `<small class="text-muted d-block mb-2">کلید ثبت شده است ••••••••</small>` under it. The page **never decrypts** anything. `merchant_id` stays prefilled (it is an identifier, not a secret). `sales/index.php` already prints no keys — do not touch it.

## P6.5 One-off: encrypt what is already stored

**`database/tools/encrypt_existing_secrets.php`** — CLI, **dry-run by default**, `--apply` writes. Requires `APP_SECRET_KEY` (exit 1 with a clear message otherwise). Take a database backup first (the changelog must name the backup file). For every row of `marketplace_connections` with `api_key`/`api_secret` not `NULL`, not `''` and not starting with `v1:`; and for `licenses` where `license_type='zarinpal'` and `credentials` JSON has an `api_key` that is non-empty and not `v1:`-prefixed: encrypt, **decrypt again and compare** before writing, update inside one transaction per table. Prints only counts and row ids (never values). Idempotent: a second run reports 0. Then `diagnose.php --phase=6` ⇒ D18 PASS.

> **Key custody (state this in `database/README.md` and in the final message to Pouya):** `APP_SECRET_KEY` must be backed up **separately** from database dumps. Losing it makes every stored secret unrecoverable (the owner would have to enter the API keys again — nothing else breaks). Never send it through chat, e-mail or the changelog.

## P6.6 Tests

```php
<?php
require __DIR__ . '/../includes/secrets.php';
$fail = 0;
function check(string $label, $actual, $expected): void {
    global $fail;
    if ($actual !== $expected) { $fail++; fwrite(STDERR, "FAIL: $label\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n"); }
}
$key = sodium_crypto_secretbox_keygen();
$enc = secret_encrypt('my-api-key-123456', $key);
check('prefix', substr($enc, 0, 3), 'v1:');
check('is_encrypted', secret_is_encrypted($enc), true);
check('plain not encrypted', secret_is_encrypted('my-api-key-123456'), false);
check('null not encrypted', secret_is_encrypted(null), false);
check('roundtrip', secret_decrypt($enc, $key), 'my-api-key-123456');
check('nonce random', secret_encrypt('x', $key) !== secret_encrypt('x', $key), true);
check('wrong key', secret_decrypt($enc, sodium_crypto_secretbox_keygen()), null);
$tampered = substr($enc, 0, -2) . (substr($enc, -2) === 'AA' ? 'BB' : 'AA');
check('tampered', secret_decrypt($tampered, $key), null);
check('garbage', secret_decrypt('v1:%%%', $key), null);
check('short', secret_decrypt('v1:' . base64_encode('short'), $key), null);
check('plain payload', secret_decrypt('plain', $key), null);
check('unicode roundtrip', secret_decrypt(secret_encrypt('کلید-رمز', $key), $key), 'کلید-رمز');
check('max length payload fits 512', strlen(secret_encrypt(str_repeat('k', 255), $key)) <= 512, true);
check('mask short', secret_mask('abc'), '••••••••');
check('mask long', secret_mask('abcdefghijkl1234'), '••••••••1234');
check('mask empty', secret_mask(''), '');
check('mask null', secret_mask(null), '');
putenv('APP_SECRET_KEY');
$threw = false; try { secret_key(); } catch (RuntimeException $e) { $threw = true; }
check('missing key throws', $threw, true);
putenv('APP_SECRET_KEY=' . base64_encode('too-short'));
$threw = false; try { secret_key(); } catch (RuntimeException $e) { $threw = true; }
check('short key throws', $threw, true);
putenv('APP_SECRET_KEY=' . base64_encode($key));
check('env key works', secret_decrypt(secret_encrypt('v'), null), 'v');
if ($fail > 0) { fwrite(STDERR, "$fail failure(s)\n"); exit(1); }
echo "secrets_test: OK\n";
```

## P6.7 Production switch (how Pouya turns it on; you only document and verify it)

1. Run `create_db_users.sql` (DB admin). 2. Set real environment variables: `APP_ENV=production`, `DB_HOST`, `DB_NAME`, `DB_USER=panel_app`, `DB_PASS`, `DB_MIGRATE_USER=panel_migrator`, `DB_MIGRATE_PASS`, `APP_SECRET_KEY`, and `TRUSTED_PROXIES` when behind a proxy/CDN. 3. Remove `config/env.local.php` from the server. 4. `php database/tools/env_check.php` must show no ✗.
Local development keeps `APP_ENV=local` and `root` — that stays allowed.

## P6.8 Must NOT do (Phase 6)

| Forbidden |
|---|
| Printing, logging, committing or putting into the changelog any key, secret, password or `APP_SECRET_KEY` (including test values — use throwaway strings and delete the rows). |
| Decrypting a secret inside a page/template, or returning one in any JSON response. |
| Inventing an encryption scheme (no `openssl_encrypt`, no custom XOR/base64 "obfuscation"); changing the `v1:` format. |
| Storing the key in the database, in `config.php`, or in any file under `public/`. |
| Giving `panel_app` DDL rights, or using `panel_migrator` from request code. |
| Making `APP_ENV` default to production for local development, or silently falling back to `root` in production. |
| Running `encrypt_existing_secrets.php --apply` without a backup, or without the dry-run output in the changelog. |
| Touching `sales/index.php`, the builder, files/uploads (P4) or CDN tags (P7). |

## P6.9 Gate / checklist

| # | Check |
|---|---|
| 1 | `php tests/run_all.php` exit 0 (round trip, tamper ⇒ `null`, wrong key ⇒ `null`, missing/short key ⇒ exception); `php -l`; Arabic-letter grep clean. |
| 2 | `0007` ran on the clone, then on `business_panel`; both columns are `varchar(512)`. |
| 3 | **H3 end to end:** with a throwaway key string `TEST-KEY-123456`, connect a marketplace ⇒ the DB value starts with `v1:` and does not contain the string; `curl` the sales and licenses pages with a valid session and `grep -c TEST-KEY` ⇒ **0**; disconnect ⇒ both columns `NULL`. Then delete the test rows' data (disconnect) and list it in cleanup. |
| 4 | **Licenses:** save a zarinpal key ⇒ stored `v1:`; re-save with a blank key ⇒ **unchanged**; the page source contains no key and no `value=` on that input; an invalid merchant id ⇒ `STR_MERCHANT_INVALID`; a 400-char key ⇒ `STR_KEY_TOO_LONG`. |
| 5 | **Missing key:** unset `APP_SECRET_KEY` on the clone ⇒ connect answers `STR_SECRET_ERROR` (500) and **nothing** is stored. |
| 6 | **Encrypt tool:** dry-run output in the changelog; `--apply` after a backup; second run reports 0; `diagnose.php --phase=6` ⇒ D18 PASS. |
| 7 | **H10:** `APP_ENV=production` + `DB_USER=root` ⇒ HTTP 500 with only `خطا در اتصال به پایگاه داده`, a line in the PHP error log, **no** stack trace/host/user in the response; with `panel_app` the panel works; as `panel_app`, `CREATE TABLE zz_probe (id INT)` is **denied** (`ERROR 1142`), `SELECT` works; `migrate.php --status` works with `panel_migrator`. (Run the privilege checks on the clone database.) |
| 8 | `env_check.php` prints the php.ini limits and flags any below the thresholds (no ✗ for APP_SECRET_KEY / sodium). |
| 9 | **Owner regression:** connect/disconnect a marketplace, save every license type, upload a certificate — all work. |

Append the P6 section to the changelog (never include secrets) before starting P7.

---

# PHASE 7 — Frontend pinning, final regression, cleanup

**Goal:** close L1 (`public/.htaccess`), L7 (avatar placeholder), L8 (CDN pinning); prove the whole job end to end; leave the repository clean. No migration.

## P7.1 File map

```
panel/
├── includes/{header,footer}.php         M  pinned CDN tags with SRI
├── public/
│   ├── login.php                        M  pinned CDN tag with SRI
│   ├── .htaccess                        M  rewritten (the old rules never matched anything)
│   └── assets/img/default-avatar.svg    N  placeholder avatar (header.php pointed to a PNG that does not exist)
├── database/README.md                   M  runbook (P7.6)
└── changelogs/
    ├── changelog-fix-hardening.md       M  final section + cleanup list
    └── diagnostics-after.txt            N  output of the full diagnostic suite
```

## P7.2 Pin the CDN assets (L8, D25)

Find every external tag: `grep -rn "cdn.jsdelivr.net\|cdnjs\|unpkg" public includes --include=*.php`. Today there are: `bootstrap@5.3.3` RTL CSS (`header.php`, `login.php`), `bootstrap-icons@1.11.3` CSS (`header.php`), `bootstrap@5.3.3` JS bundle (`footer.php`) and **`sweetalert2@11`** (`footer.php`, a floating tag — any future 11.x would be executed in the admin panel).

For each URL, with the project's real internet access (the DB is not needed):
1. Resolve the exact version of floating tags: `curl -sI https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js | grep -i x-jsd-version` — use that exact version in the URL (`…/npm/sweetalert2@<x.y.z>/dist/sweetalert2.all.min.js`).
2. Compute the SRI value from the **pinned** URL: `curl -s <pinned-url> | openssl dgst -sha384 -binary | openssl base64 -A` and write `integrity="sha384-<value>"`.
3. Every pinned tag gets `crossorigin="anonymous"`.
4. Keep the URL path style jsDelivr already serves (`.../dist/css/bootstrap.rtl.min.css`, `.../dist/js/bootstrap.bundle.min.js`, `.../font/bootstrap-icons.css`).

Never invent a hash: if a download fails, leave that tag unpinned, mark the row ⏭️ and put it in "Known problems". After editing, load every page type (login, dashboard, a module, the builder page) with the browser console open: **no SRI/`integrity` errors, Swal dialogs and the dropdowns still work**. Record the final version table (library → version → file) in the changelog.

## P7.3 `public/.htaccess` (L1) — replace the whole file

The old file's `RewriteCond` lines apply only to the **next** `RewriteRule`, and that rule only fires for paths that do not exist — so it blocked nothing (and `config/`, `includes/`, `database/` are outside `public/` anyway). Replace with rules that actually do something:
```apache
Options -Indexes
# Never serve dotfiles or tooling/documentation files that may end up under public/ by mistake
<FilesMatch "^\.">
    Require all denied
</FilesMatch>
<FilesMatch "\.(md|sql|log|lock|bak|ini|dist|sh|yml|yaml|env)$">
    Require all denied
</FilesMatch>
```
Do **not** add rewrite rules (the panel has no front controller), and do not re-block `/api/` or `/modules/` — they are the served pages. (`public/uploads/.htaccess` from P1.9 is separate and stays.) Test: `curl -I /.htaccess` and `/anything.md` ⇒ 403; `/login.php`, `/assets/css/style.css`, `/api/...` unchanged.

## P7.4 Default avatar (L7)

`grep -rn "default-avatar" includes public --include=*.php` shows (only `includes/header.php`) the missing `/assets/img/default-avatar.png`. Create `public/assets/img/default-avatar.svg` (original artwork, static, not user-supplied) and point the markup at it:
```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 80 80" width="80" height="80" role="img" aria-label="avatar">
  <rect width="80" height="80" rx="40" fill="#dee2e6"/>
  <circle cx="40" cy="32" r="14" fill="#adb5bd"/>
  <path d="M12 74c3-16 15-24 28-24s25 8 28 24z" fill="#adb5bd"/>
</svg>
```
Only the `src` value changes in the markup. (SVG is fine here: it is a first-party static file; the ban on SVG applies to **uploads**.)

## P7.5 Decision: no Content-Security-Policy yet (record, do not implement)

Pages use inline `<script>` blocks, inline event handlers and `window.CSRF_TOKEN` injection; a CSP without `'unsafe-inline'` would break them and one with it protects little. The headers already sent (P1: `X-Content-Type-Options`, `X-Frame-Options: DENY`, `Referrer-Policy`) stay. **When Part 4+ moves page scripts into files, add a nonce-based CSP.** Put this under "Known limits" in the changelog.

## P7.6 `database/README.md` — update (runbook, short)

Sections: (1) vhost Document Root must be `…/panel/public` (+ the nginx snippet `location ~* ^/uploads/.*\.php$ { deny all; }`); (2) first install: `schema.sql` → `builder-schema.sql` → `php database/migrate.php` → `php database/tools/reset_owner_password.php`; (3) every tool with one line (`migrate.php [--status]`, `diagnose.php [--phase=N]`, `env_check.php`, `purge_logs.php [--dry-run]`, `backfill_files.php`, `gc_files.php`, `encrypt_existing_secrets.php`, `gen_secret_key.php`, `create_db_users.sql`); (4) the clone-first procedure of Rule 12; (5) backup procedure: database dump **plus** `public/uploads/` **plus** `storage/private/`, key stored separately; (6) a cron/Task-Scheduler line for `purge_logs.php` (daily) and `gc_files.php` (weekly, report only); (7) the production switch of P6.7.

## P7.7 Final regression and cleanup

1. **Full regression.** `php tests/run_all.php`; `php -l` on every `*.php` under `config includes public database tests`; the Arabic-letter grep over the **whole** `public includes config database` tree; `php database/tools/diagnose.php` (no `--phase`) ⇒ save as `changelogs/diagnostics-after.txt`; expected: no FAIL, D03 not WARN unless an account still has the seed password, D05 WARN only for pre-existing odd usernames (listed, not fixed). Run `php database/migrate.php --status` ⇒ `0001`–`0007` all applied.
2. **Role sweep** (Appendix E has the matrix): as **owner** and as **staff with `dashboard.view` only** visit every page and call one API per module; the second user gets 403 where the matrix says so and **nothing is printed before the 403**.
3. **Cleanup.** Drop `business_panel_migtest`; delete every probe/test artefact you listed during the job (e.g. `includes/builder/types/zz_probe.php`, test staff users and test rows, test uploads, temporary `error_log` probes); remove `backups/` from the repository tree if it is inside it (move it outside; it is git-ignored anyway) and tell Pouya where the backups are; confirm `git status` shows only intended files.
4. **Changelog final.** Append "P7", then "Deviations", "Known problems", "Known limits" (Appendix B items you confirmed) and the cleanup list with ✅ for each item.

## P7.8 Must NOT do (Phase 7)

| Forbidden |
|---|
| Adding a CSP, a build step, a bundler, a local copy of the CDN libraries, or any new JS/CSS library. |
| Changing UI markup/styles beyond the CDN attributes and the avatar `src`. |
| Leaving the migration test database, probe files, test accounts or test uploads behind. |
| Declaring the job finished while a Gate row of any phase is ❌ without Pouya's written OK. |

## P7.9 Gate / checklist

| # | Check |
|---|---|
| 1 | All CDN tags pinned with `integrity` + `crossorigin`; no console errors; the version table is in the changelog. |
| 2 | `/.htaccess`, `/x.md`, `/x.sql` ⇒ 403; the panel works as before. |
| 3 | The header avatar placeholder loads (HTTP 200) for a user without an avatar. |
| 4 | Regression commands of P7.7-1 all green; `diagnostics-after.txt` saved. |
| 5 | Role sweep done for owner and a minimal staff user, evidence in the changelog. |
| 6 | Cleanup list complete, migtest database dropped, `git status` clean of probes. |
| 7 | The combined global gate (Appendix E) is filled in. |

---

# Appendix A — Permission matrix (reference; do not change)

Module keys (table `permissions`): `dashboard, pages, articles, templates, sales, media, licenses, settings`. Hierarchy `delete ⇒ edit ⇒ view`. **Owner** bypasses every check. **Staff** default = deny; new staff get `dashboard.view`.

| Screen (`access_map.php`) | Rule |
|---|---|
| `/modules/dashboard/index.php` | `dashboard.view` |
| `/modules/pages/index.php` | `pages.view` |
| `/modules/articles/index.php` | `articles.view` (label «صفحات ساخته شده», historical key) |
| `/modules/templates/index.php` | `templates.view` |
| `/modules/sales/index.php` | `sales.view` |
| `/modules/media/index.php` | `media.view` |
| `/modules/licenses/index.php` | `licenses.view` |
| `/modules/settings/general.php` | `settings.view` to open, `settings.edit` to save |
| `/modules/settings/profile.php` | login only (and reachable while `must_change_password`) |
| `/modules/settings/staff.php` | owner only |
| any other module file | **denied** (must be registered) |

| API | Rule |
|---|---|
| `builder/load.php` | `pages.view`; template canvases also `templates.view` |
| `builder/save.php` | `pages.edit`; template canvases also `templates.edit` |
| `media/upload.php` | `media.edit` · `media/delete.php` `media.delete` |
| `templates/upload.php` | owner · `templates/install.php`, `activate.php` `templates.edit` |
| `licenses/update.php` | `licenses.edit` · `file.php?type=license` `licenses.view` · `?type=template` owner |
| `sales/connect.php`, `disconnect.php` | `sales.edit` |
| `staff/*` (invite, remove, save_permissions, set_status) | owner only |
| `notifications/mark_read.php`, `staff/toggle_theme.php` | login only |

# Appendix B — Honest limits and known leftovers (list in the changelog; none is a defect of this job)

1. **DDL was never executed by the author.** Every migration is statically reviewed and `php -l` clean only; this is why each is clone-tested first. A failing clone run is a finding to fix in the migration, recorded under Deviations.
2. `APP_ENV` defaults to `local` when unset: a server that forgets the variable behaves like a dev machine (no fail-closed). `env_check.php` flags it; the production switch (P6.7) is a manual step.
3. `dashboard_snapshots.articles_count` stays (always `0`); the permission key `articles` and its screen stay.
4. Legacy password hashes keep their old cost until the user changes the password (no forced rehash).
5. No Content-Security-Policy (P7.5). No rate limit on the APIs other than login. No two-factor authentication.
6. Legacy media hashes are of the stored bytes (not original bytes); dedupe protects new uploads only.
7. Template packages are stored and inspected, **not extracted or rendered** (Part 7).
8. Revision history / draft-publish for the builder is **not** implemented (Appendix C).
9. `activity_logs` keeps 180 days and `login_attempts` 7 days only if `purge_logs.php` is scheduled.

# Appendix C — Design inputs for Part 7 (builder revisions) — NOT implemented here

Taken from the DB audit §12–§13 so Part 7 does not start from zero. **Do not create these tables in this job.**
- `builder_blocks.id` is **not stable** across saves (the save strategy rewrites the rows), so anything that must point at "a block" has to use `block_uid` (ascii_bin, unique per canvas) instead of `id`.
- Revisions: one row per saved snapshot, separate from the live tables:
  ```sql
  CREATE TABLE builder_canvas_revisions (
      id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      canvas_id     INT UNSIGNED NOT NULL,
      version       INT UNSIGNED NOT NULL,             -- = builder_canvases.version at save time
      snapshot      JSON NOT NULL,                     -- the canvas + block tree exactly as saved
      kind          ENUM('draft','published') NOT NULL DEFAULT 'draft',
      created_by    INT UNSIGNED NOT NULL,
      created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY uq_rev_canvas_version (canvas_id, version),
      KEY idx_rev_canvas_kind (canvas_id, kind, id),
      CONSTRAINT fk_rev_canvas  FOREIGN KEY (canvas_id)  REFERENCES builder_canvases(id) ON DELETE CASCADE,
      CONSTRAINT fk_rev_creator FOREIGN KEY (created_by) REFERENCES panel_users(id)     ON DELETE RESTRICT
  ) ENGINE=InnoDB;
  ```
  (`RESTRICT` on the creator follows D12; `CASCADE` on the canvas because a revision has no meaning without it.) The optimistic lock (`version`, D20) is already the revision counter.
- Media usage inside revisions must be reflected in `builder_media_refs` only for the **published** snapshot, otherwise a file used by an old draft would block deletion forever.

# Appendix D — Activity-log coverage and changelog template

| action_type | module_key | Where | Phase |
|---|---|---|---|
| `staff_invite`, `staff_permissions`, `staff_status` | settings | `staff/invite.php`, `save_permissions.php`, `set_status.php` | P1 |
| `staff_remove` | settings | `staff/remove.php` | P2 |
| `login`, `logout` | auth | `loginUser()`, `logout.php` | P2 |
| `profile_update`, `password_change` | settings | `profile.php` | P2 |
| `media_upload`, `media_delete` | media | `media/upload.php`, `delete.php` | P4 |
| `site_settings_update` | settings | `settings/general.php` | P4 |
| `license_update` | licenses | `licenses/update.php` | P4 |
| `template_upload` | templates | `templates/upload.php` | P4 |
| `install`, `activate` | templates | `templates/install.php`, `activate.php` | P5 |
| `sales_connect`, `sales_disconnect` | sales | `sales/connect.php`, `disconnect.php` | P6 |

Rule: log **after** success, never log secrets/passwords/keys, descriptions ≤ the column size (`logActivity()` truncates).

**Changelog template** (`changelogs/changelog-fix-hardening.md`, one section per phase, appended at the end of the phase):
```
## P<N> — <title>   (<date>)
Files changed: <list, N/M per file>
Migrations: <file> — clone: ✅/❌  real: ✅/❌   (or "none")
Gate: | # | Result ✅/❌/⏭️ | Evidence (one line) |
Behaviour changes: <list>
Deviations: <what differed from the prompt, and why>
Known problems: <list or "none">
Cleanup list (probes/test data created): <list>
```

# Appendix E — Combined global gate (fill in at the very end of P7)

| # | Check | Result |
|---|---|---|
| 1 | One served tree; `/login.php` is the only login; old trees gone (P0). | |
| 2 | `php tests/run_all.php` green; `php -l` clean; no Arabic ي/ك. | |
| 3 | `migrate.php --status`: `0001`–`0007` applied; `diagnose.php` (all phases) no FAIL. | |
| 4 | Permissions: Appendix A verified for owner and a minimal staff; 403 before any output; suspended/removed users cut off immediately. | |
| 5 | Accounts: no seed password in use; soft delete keeps content and logs; lock keyed by identifier+IP; password change kills other sessions. | |
| 6 | Builder: media refs enforced, stale save ⇒ 409, sanitizer contract in place, composite FK active. | |
| 7 | Files: no SVG; all uploads through `FileStore`; private files only via `file.php`; duplicates rejected; legacy files backfilled. | |
| 8 | Legacy tables and `templates.is_active` gone; active template single-sourced; category «همه» gone. | |
| 9 | Secrets encrypted (`D18` PASS); no key in any page/response; production fail-closed verified. | |
| 10 | CDN pinned with SRI; `public/.htaccess` effective; default avatar loads. | |
| 11 | Cleanup list done; migtest DB dropped; changelog complete with Deviations, Known problems, Known limits. | |

**Final message to Pouya (3–6 lines, plain):** what was completed (phases), what is ⏭️ and why, the three things he must do himself (vhost root = `panel/public`, production env variables + DB users, back up `APP_SECRET_KEY` separately), and where the changelog is.
