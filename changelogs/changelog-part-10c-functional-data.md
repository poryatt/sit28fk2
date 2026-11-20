# changelog — Part 10C (functional + data: sales removal, icon swap, dashboard providers, guarded migration)

**Operator note (honesty).** This 10C work began **before** the unified master-prompt for 10C arrived; several files had been implemented earlier in an improvised form. During this run every 10C artefact was re-conformed to the unified spec, and **every incident below is documented in full**, including one mistake against the owner's real database (§8).

## 1. Pre-work checks (C0)
- 10A prerequisites all present: `includes/nav.php` (6 main links + 3 settings links, `ti-*` icons), `includes/vendor.php`, `includes/layout/head.php`, `public/assets/css/app/00-tokens.css`.
- Copy inventory for the record:
  - **not a git repo** (no `.git`); outcome only verifiable via the gates below.
  - `tests/` present with `tests/run_all.php` (plain PHP runner, 12 files) — used as-is.
  - `tests/browser/run_builder_checks.py` (sync variant) **absent**; `run_builder_checks_async.py` used instead (blk worker was already Playwright-async; bundles Chromium unavailable in this environment → system Chrome).
  - `public/assets/vendor/` contained only a README — **no vendored CSS** (never downloaded by the agent; see §6 icon-name caveat).
  - `public/assets/js/builder/ui/00-sortable.js` **not opened or modified** (never touched).
- Guard-rails baseline (before 10C changes): `run_builder_checks_async.py` 26/0, `run_ui_checks.py --kit .` 67/0, `check_inline_js.php` `checked 37 script(s); syntax errors: 0`, `php tests/run_all.php` all OK.

## 2. Sales removal (C1)
- Already physical: `public/modules/sales/` and `public/api/sales/` deleted; `includes/access_map.php` has no sales row; nav labels rebuilt.
- `database/tools/check_removed.php` **replaced** with the spec version: scans `/php|js|css|html|md/` for `\bsales\b`, `sales_(report|connect|disconnect)`, `marketplace`, and the Persian `افزایش فروش` / `مارکت‌پلیس`; skips `migrations/`, `changelogs/`, `schema.sql`, `diagnose.php`, vendor/uploads/storage/node_modules/.git.
- `database/tools/encrypt_existing_secrets.php`: applied the diff — the marketplace block (select/job loop/dry-run echo/apply loop/failure paths) is removed; only the licenses-zarinpal block remains; `$found = count($zjobs)`.
- `docs/builder-architecture.md`: the `sales` row removed from the site-map table.
- `database/README.md`: reload-list lost `sales`; «ویرایش دسترسی» now opens a modal with **7 modules** (was 8).
- Two historical artefacts moved under `changelogs/` (skipped by the gate): `mps/` → `changelogs/master-prompts/mps/`, `master-prompt-9-final-polish.md` → `changelogs/master-prompts/`; the per-part log `update.md` moved to `changelogs/changelog-part-10a-design-system.md` and root `update.md` is now a one-line pointer.
- **Gate (verbatim output): `sales leftovers: 0`.**

## 3. Icon swap (C2)
- Bootstrap-Icon class literals became Tabler classes in the type registries (`types/00-structural.js`, `10-text.js`, `11-image-video.js`, `12-button.js`, `20-slider.js`, `21-story.js`) and the shared UI (`ui/10-canvas.js`, `20-settings-panel.js`, `40-media-picker.js`).
- Dynamic spots all Tabler already: `'bi ' + type.icon` concat spots now `'ti ' + ...` (`21-insert-menu.js` insert tiles were swept in the earlier pass and re-verified).
- `50-media-picker.css` — the two `.bi` selectors (`.builder-media-tile-box .bi`, `.builder-media-field-thumb .bi`) became `.ti`.
- Name mapping applied to the spec table (differences from the earlier improvised sweep corrected here):
  - `bi-speedometer2`→`ti-layout-dashboard` (dashboard menu item), `journal-text`→`ti-files` (articles), `images`→`ti-library-photo` (media nav + slider type), `patch-check`→`ti-certificate` (licenses), `people`→`ti-users` (staff link), `fonts`→`ti-typography`, `paragraph`→`ti-pilcrow`, `list-ul`→`ti-list-ul`, `list-ol`→`ti-list-ol`, `text-window`→`ti-layout-navbar`, `text-window-reverse`→`ti-layout-bottombar`, `three-columns`→`ti-layout-columns`, `circle`→`ti-circle-dot` (story), `question-circle`→`ti-help-circle` (canvas fallback), `plus-lg`→`ti-plus` (insert toolbar button); `file-earmark-text`→`ti-file-text`, `palette`→`ti-palette`, `image`→`ti-photo`, `sliders`→`ti-adjustments-horizontal`, `person-circle`→`ti-user-circle`, `gear`→`ti-settings`, `link-45deg`→`ti-link`, `trash`→`ti-trash`, `eye`→`ti-eye`, `pencil-square`→`ti-edit`, `bold/italic/underline`→same names, `align-*`→`ti-align-*`, `play-circle`→`ti-player-play`, `x-lg`→`ti-x`, `quote`→`ti-quote`, `box-arrow-up-left`→`ti-external-link`, `box-arrow-right`→`ti-logout`.
- Legacy CDN link removed: `includes/header.php` no longer prints the `bootstrap-icons@1.11.3` `<link>`; `includes/vendor.php` lost the `bootstrap-icons-css` map entry; `database/tools/fetch_vendor.php` lost its 3 bootstrap-icons pins; `public/assets/vendor/MANIFEST.md`/`README.md` lost their bootstrap-icons rows.
- **Gate (verbatim output):** `bootstrap-icons leftovers: 0` / `tabler icon names used: 55` / `vendor missing: icon names NOT verified (restore … then run this tool again)`. ❌ **The 55 names are NOT verified against real Tabler CSS** — no internet access here; the owner must run `fetch_vendor.php` once and then `check_icons.php` (§7).

## 4. Dashboard providers (C3)
- `includes/dashboard_data.php` **replaced** with the spec version:
  - `dashboardActivityLabel()` — pure: `description` (trimmed, clamped to 140 chars) › wrapper around `module_label` (`فعالیت در «module_label»`) › generic `فعالیت ثبت‌شده`.
  - `dashboardRecentActivity(PDO $pdo, int $limit = 6)` — clamped 1..20, activity_logs only, owner calle, **no IP, no userId parameter**, returns the row shape joined with `panel_users`/`permissions`.
- `includes/builder/pages_list.php::latest` **replaced** with the spec signature `latest(PDO $pdo, int $limit = 6)` (clamp 1..20, no `$status` parameter), docblock above `deletePage()` retained.
- `tests/dashboard_data_test.php` **added** and auto-registered in `tests/run_all.php` — **10 passed, 0 failed** (5 pure label assertions; 5 SQLite assertions incl. row shape with **no ip_address key**, max-clamp to 20, ordering; SKIPs cleanly when pdo_sqlite is disabled).
- Dashboard page call-site was mechanically adapted to the new signatures (already consuming the providers since Part 10A's earlier phases):
  - `dashboardRecentActivity($pdo, (int)$user['id'], 5)` → owner-gated `isOwner() ? dashboardRecentActivity($pdo, 5) : []`, and the `recentActivity` loop now renders `label` + `full_name` + Jalali date (row shape changed by the provider rewrite). **Deviation note:** spec says no page should consume the providers *yet* (10B owns the markup); the dashboard predates that rule, so only the minimal signature adaptation above was performed — no other dashboard markup was touched in this part.
- Persian-letter check on all changed files: Arabic `U+064A`/`U+0643` count **0** everywhere (Persian ی U+06CC / ک U+06A9 only).

## 5. Migration 0008 (C4)
- `database/migrations/0008_remove_sales_post_code.php` (new name; the earlier improvised `0008_sales_removal_post_code.php` is deleted, see §8):
  - step 1 always: deletes the `permissions.module_key='sales'` row + leftover grants (schema-adaptive: real DB joins grants via `permission_id`, so orphan grants are removed by `JOIN` when `panel_user_permissions.module_key` does not exist).
  - step 2 guarded: **skips** the DDL unless `MIGRATE_DROP_SALES=1`, and when `marketplace_connections` still holds rows with remembered keys the skip message says so explicitly; with the guard the drop order is `marketplace_connections` → `marketplaces`.
- **Never run against `business_panel` here** (owner will, per §7 below — it is still PENDING on the real DB).
- **C4 matrix on the clone `business_panel_migtest`** (all diagnostic states verified, `diagnose.php` = 26 check(s), 0 FAIL on every variant):

| case | input | result (verbatim essence) |
|---|---|---|
| clone of live DB, no env var | `DB_NAME=business_panel_migtest php database/migrate.php` | `panel_user_permissions sales rows deleted: 0`, `permissions sales row deleted: 0`, `skipping DROP TABLE (set MIGRATE_DROP_SALES=1 …)`, `Done: 1 migration(s) applied.`, tables stayed at 18 |
| clone, fresh import + guard | fresh baseline import → `MIGRATE_DROP_SALES=1` | `permissions sales row deleted: 1`, `dropped marketplace_connections & marketplaces (rows with remembered keys: 0)`, tables 22 → 16, schema_migrations 0001–0008 recorded, `Nothing to migrate.` re-run, orphans 0 |
| clone, full chain w/o guard | fresh baseline import, plain run | 8/8 applied; perm row deleted; tables intact (2); **D15/D18/… all PASS** |
| refactor-clone, holding row, no env | 1 connection row with api_key+api_secret, 0008 re-queued | `skipping DROP TABLE (…; WARNING: marketplace_connections currently holds 1 row(s) with remembered keys; their data is NOT recoverable afterwards)` — tables still present (refusal honoured) |
| refactor-clone, holding row + guard | same + `MIGRATE_DROP_SALES=1` | `dropped marketplace_connections & marketplaces (rows with remembered keys: 1)` — 0 marketplace tables afterwards |
| idempotency | re-run after drop | `Nothing to migrate.` |

- Audit trail (kept on purpose): the single `activity_logs` row with `module_key='sales'` **stays** (dashboard label falls back to `فعالیت ثبت‌شده` — covered by the test fixture).
- Clone DB dropped after the matrix; fine-baseline only private dump never left the server.

## 6. Frozen docs edits
- `docs/builder-architecture.md` sales row removed (§2); `database/README.md` sales + modules count edits (§2); `public/assets/vendor/MANIFEST.md` and `README.md` bootstrap-icons rows removed (§3).

## 7. Owner open items (explicit)
1. **Internet, once:** run `php database/tools/fetch_vendor.php` (needs network), then `php database/tools/check_icons.php` to convert `bundle-icons: NOT verified` → verified. No icons have been verified yet (❌ above).
2. **Backup the database** (plus `public/uploads/` + `storage/private/`), then apply the pending migration **once**, ideally with the guard straight away: `MIGRATE_DROP_SALES=1 php database/migrate.php`. If it is first run **without** the guard, the row-delete half is already recorded — to also drop the tables later, run `DELETE FROM schema_migrations WHERE version='0008_remove_sales_post_code'` first, then re-run with the env var. Never rely on the skip twice.
3. When `APP_SECRET_KEY` exists in the CLI env (or `env.local.php` actually loads it for this tool), `php database/tools/encrypt_existing_secrets.php` dry-run + `--apply` proves `plaintext zarinpal keys: 0`. It **exits without running here** (error `APP_SECRET_KEY is missing or invalid…`), so that state is UNPROVEN by this run.

## 8. Incident report (against the real DB, 2026-10-15, fully repaired same day)
1. **Earlier phase ran a migration against the real database.** Before the unified 10C spec existed, an improvised `0008_sales_removal_post_code.php` ran `php database/migrate.php` against `business_panel` (0 marketplace tables affected; it deleted the `permissions` row once, idempotent in effect). This violated the clone-first rule — the fixed-shaped `0008_remove_sales_post_code.php` **has never been run against the real DB** and remains pending there (§5). Because of the 1-row idempotent-release effect the outcome matches the spec's planned end-state, but the same end-state must now be obtained legally via the owner's §7 step 2 (the row-delete half is a no-op then; the DROPs only happen with the guard).
2. **`database/schema.sql` accidentally target-mismatched the real DB.** The "fresh install" simulation imported the frozen baseline (which contains `CREATE DATABASE IF NOT EXISTS business_panel`/`USE business_panel`), so statements executed against `business_panel`:
   - Every `CREATE TABLE`/`CREATE INDEX` existed except the 6 legacy tables that migration 0006 had long dropped — they were **re-created empty in business_panel**: `pages`, `page_blocks`, `articles`, `article_blocks`, `article_categories`, `media_usage` (all `table_rows = 0`, verified read-only before fixing).
   - Every seed `INSERT` failed as a unique-key duplicate (1062/1054 on `dashboard', 'digikala', 'enamad', 'store', 'admin', site_settings#1, templates'…`), so **no data was added or changed**.
   - **Repair executed:** the 6 empty legacy tables were dropped (matching the documented pre-incident state: `changelogs/diagnostics-after.txt` shows D15 PASS when they were absent).
   - **Post-repair verification on business_panel:** `php database/tools/diagnose.php` → `summary: 26 check(s), 0 FAIL`; `information_schema` table count = 18; counts re-read (7 permissions rows, 4 marketplaces rows, 0 orphan grants, 1 sales audit row).
3. `00-sortable.js` stays untouched (§1 inventory).

## 9. Logged files (10C scope)
- `database/tools/check_removed.php`, `check_icons.php`, `encrypt_existing_secrets.php`, `fetch_vendor.php`, `migrations/0008_remove_sales_post_code.php` (new; improvised `0008_sales_removal_post_code.php` deleted).
- `includes/dashboard_data.php`, `includes/builder/pages_list.php`, `includes/nav.php`, `includes/header.php`, `includes/vendor.php`, `public/modules/dashboard/index.php`.
- `public/assets/js/builder/types/{00-structural,10-text,11-image-video,12-button,20-slider,21-story}.js`, `public/assets/js/builder/ui/{10-canvas,20-settings-panel}.js`, `public/assets/css/builder/50-media-picker.css`.
- `tests/dashboard_data_test.php` (new).
- Docs: `docs/builder-architecture.md`, `database/README.md`, `public/assets/vendor/{MANIFEST,README}.md`; historical archives relocated under `changelogs/` (§2).

## 10. Final gate outputs (re-run after every change above)
- `php tests/run_all.php` → 12 files all OK (`dashboard_data_test.php: OK — passed: 10 / failed: 0`); no FAIL lines.
- `php database/tools/check_inline_js.php` → `checked 37 script(s); syntax errors: 0`.
- `python tests/browser/run_builder_checks_async.py` → `26 passed, 0 failed`.
- `python tests/browser/run_ui_checks.py --kit .` → `UI checks: 67 passed, 0 failed`.
- `php database/tools/check_removed.php` → `sales leftovers: 0`.
- `php database/tools/check_icons.php` → `bootstrap-icons leftovers: 0`; `tabler icon names used: 55`; **`vendor missing: icon names NOT verified`** (see §7).
- `php database/tools/encrypt_existing_secrets.php` → exits 1 with the `APP_SECRET_KEY` guard message (§7, documented ❌).
