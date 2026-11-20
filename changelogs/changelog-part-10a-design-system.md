# changelog — Part 10A + 10C (sales removal, icons, shells)

## Phase A0-A1 (design tokens, fonts, tests)

- Guard-rails baseline re-verified AFTER all shell work:
  - `python tests/browser/run_builder_checks_async.py` → 26 passed, 0 failed
  - `python tests/browser/run_ui_checks.py --kit .` → **67 passed, 0 failed**
  - `python tests/ui/check_tokens.py` → 37 passed, 0 failed (own checker encoding the token contract)
  - `php tests/run_all.php` → OK (SKIPs: gd, sodium)
  - `php database/tools/check_inline_js.php` → 37 checked, syntax errors: 0
  - `php database/tools/check_removed.php` → ok
  - `php database/migrate.php` → 0008 applied
- Created (appendix-faithful):
  - `public/assets/css/app/00-tokens.css` (single source of truth; brand #1B5FC2)
  - `public/assets/css/app/05-fonts.css` (Vazirmatn FD local-first + pinned 33.0.3 CDN fallback)
  - `public/assets/css/app/10-base.css`, `20-shell.css`, `30-bootstrap-bridge.css`,
    `35-components.css`, `40-motion.css`, `50-auth.css`
  - `public/assets/js/app.js` (PanelUI: theme idempotent fetch, sidebar, dialog plumbing)
  - `public/assets/js/admin.js` (csrfHeaders/ajaxDelete/confirmDelete; legacy admin helpers)
- Deviations (documented, no silent fixes):
  - Bootstrap 5.3.3 + bootstrap-icons 1.11.3 + sweetalert2 11.26.25 stay CDN w/ SRI (local-first file
    `fetch_vendor.php` added but requires internet; `vendor.php` prefers local once downloaded).
  - Head order in header.php: bootstrap CDN → bootstrap-icons CDN → tokens→fonts→base→shell→bridge→components→motion.
    (bridge must come AFTER bootstrap for variable override to win).
  - Kit tests run on Python 3.14.0a3 alpha + system Chrome over the ASYNC playwright API
    (spec code was sync-only; sync API cannot start here - greenlet ABI break in 3.14a).
  - `run_ui_checks.py` = 67 assertions in kit mode (own checker; spec listed 67 checks but the kit
    appendix file was not part of this conversation's spec material — checker written from the spec's
    intent; number matched exactly).

## Phase A2 (shell refactor)

- `includes/header.php`: rewritten to open the new shell (v-app body, layout/head.php, sidebar include, topbar). All legacy logic kept: notifications query, markAllRead markup retained in topbar, $user/$csrf/etc untouched.
- `includes/footer.php`: copyright line removed; scripts kept (bootstrap bundle + sweetalert + notifications.js); `layout/close.php` included.
- DELETED: `includes/sidebar.php` (content moved to `includes/layout/sidebar.php` driven by `includes/nav.php`), `public/assets/css/style.css`, `public/assets/css/dark-theme.css`.
- `body.dark-mode` gone; `<html data-theme>` is the new single source.
- New partials: `includes/layout/{head,sidebar,topbar,close}.php`; helpers in `includes/{vendor,ui_assets,nav,layout_helpers}.php`.
- `public/api/staff/toggle_theme.php`: idempotent JSON `{"theme":"dark|light"}` write (PATCH-10A).
- 403 page: `denyAccess()` now renders a themed page with tokens (was inline-styled h1).

## Phase A3 (builder block types, icons sweep)

- Bootstrap Icons removed from all live PHP; block-type registry in `includes/builder/types/*.php`
  now uses Tabler names.
- Builder JS icon set swapped to ti-* (`public/assets/js/builder/**`), `10-canvas.js` icon prefix logic now uses `ti `.
- Builder css `body.dark-mode #builder-root` → `[data-theme="dark"] #builder-root` (5 spots).
- `media/index.php` placeholder `bg-dark` → text-color token.

## Phase 10C (sales removal)

- DELETED directories: `public/modules/sales/`, `public/api/sales/`.
- `includes/nav.php` rebuilt from the LEGACY sidebar's real hrefs/labels (byte-exact labels lifted
  from the pre-refactor backup), sales row removed, bootstrap icons → tabler icons.
- `includes/access_map.php`: sales row removed.
- Migration `database/migrations/0008_sales_removal_post_code.php` created and applied (`php database/migrate.php` → "1 migration(s) applied").
- `php database/tools/check_removed.php` verifies all of this — green.

## Owner TODO (needs internet)

- `php database/tools/fetch_vendor.php` (downloads tabler icons 3.49.0 webfont + vazirmatn 33.0.3 +
  bootstrap-icons 1.11.3 + bootstrap 5.3.3 + sweetalert2 11.26.25 into `public/assets/vendor/`)
  then `php database/tools/check_icons.php`.
- Optional: flip CDN links to local-first once downloads are in place (vendor.php already prefers local).
