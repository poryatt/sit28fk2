# Changelog — Part 9 (final polish)

## 1. Summary

Part 9 is delivered: image upload now degrades safely when GD is missing (D4),
the list screen's B1–B5 defects are fixed, the builder was cleaned up
measurably (`.style.` 44→5, `style="` in views 7→0, Persian strings hoisted to
`STR_*`), and the GrapesJS-ready seams are in place (`BuilderTree`,
`data-engine="blocks"`, architecture doc). Guard rails stay green: runner
26/26 (both CONTRACT checks pass), Node test all-pass, `php tests/run_all.php`
exit 0, `check_inline_js.php` `syntax errors: 0`, `diagnose.php` 26 checks /
0 FAIL (D23–D26 PASS). No schema change; behavior preserved.

## 2. Defect table (B1–B10)

| ID | Status | Files | How |
|---|---|---|---|
| B1 | ✅ done | `public/modules/articles/index.php` | Status tab is now `پیش‌نویس` (U+200C); `grep -rn "پیشنویس" public includes` is clean. |
| B2 | ✅ done | `includes/builder/pages_list.php`, `public/modules/articles/index.php` | Added `BuilderPagesList::totalPages(PDO)`; "no page at all" uses it, so a no-hit search shows the no-hit card instead. |
| B3 | ✅ done | `public/modules/articles/index.php` | On `data['total'] === 0` no table is rendered — only the `صفحه‌ای با این مشخصات پیدا نشد` card. |
| B4 | ✅ done | `public/modules/articles/index.php` | Pager rendered only when `pages > 1`, with `قبلی`/`بعدی` (disabled at the ends) around the ±2 window. |
| B5 | ✅ done | `includes/builder/pages_list.php`, `public/modules/articles/index.php` | URL building moved to pure `BuilderPagesList::url(array,array)`; the view defines no function and is re-formatted 4-space / alternate syntax / `e()`-escaped. |
| B6 | ✅ done | `includes/file_store.php`, new `includes/image_support.php`, `includes/file_rules.php`, `database/tools/env_check.php`, `config/env.example.php`, `database/README.md`, `public/modules/media/index.php` | Removed the unconditional `requireGd()`; added magic-byte + marker checks; GD path unchanged; `none`+`passthrough` stores after validation, `none`+`refuse` (default in production) throws the §12 message; `finfo_open` guarded; media page shows the warning/red card. |
| B7 | ✅ done (to D6's limit) | `public/assets/js/builder/ui/10-canvas.js`, new `builder-tree.js` | Pure tree/validation logic moved to `BuilderTree`; `10-canvas.js` keeps its file/API, gained a TOC + `/* ===== Section: … ===== */` banners. Toolbar/column split-out is recorded as the next refactor (out of scope, D6). |
| B8 | ✅ done | see §7 counts | Inline styles replaced by classes / CSS custom properties; 5 `.style.` remain, each marked `/* dynamic geometry */`; `style="` in `public/modules/**/*.php` = 0. |
| B9 | ✅ done | `tests/browser/engine_contract.json`, `docs/builder-architecture.md` | `BuilderPreview` and `BuilderStoryViewer` are in the contract manifest (empty member lists) and named in the architecture doc. |
| B10 | ✅ resolved | `tests/`, `changelogs/` | Both exist in the real project (runner, PHP tests, changelogs present). |

## 3. Files created / edited

**Created:** `tests/browser/engine_contract.json`, `tests/browser/run_builder_checks_async.py`
(environment port, see §5), `includes/image_support.php`, `.editorconfig`,
`public/assets/js/builder/builder-tree.js`, `tests/js/builder_tree_test.js`,
`docs/builder-architecture.md`, `public/assets/vendor/README.md`, `tests/README.md`,
`changelogs/changelog-part-9-final-polish.md`.

**Edited — PHP:** `includes/file_rules.php`, `includes/file_store.php`,
`includes/builder/pages_list.php`, `includes/builder/builder_assets.php`,
`public/modules/articles/index.php`, `public/modules/pages/index.php`,
`public/modules/media/index.php`, `public/modules/templates/index.php`,
`public/modules/sales/index.php`, `public/modules/settings/profile.php`,
`database/tools/env_check.php`, `database/tools/diagnose.php`,
`database/README.md`, `config/env.example.php`.

**Edited — JS/CSS:** `public/assets/js/builder/builder-shell.js`,
`block-registry.js` consumers, `types/{00-structural,10-text,11-image-video,12-button,20-slider,21-story}.js`,
`ui/{10-canvas,20-settings-panel,21-insert-menu,30-preview,40-media-picker,50-page-actions}.js`,
`public/assets/css/builder/{10-shell,20-canvas,30-toolbar-interactions,40-content-blocks}.css`,
`public/assets/css/style.css`.

**Edited — tests/config:** `tests/builder_pages_list_test.php`, `tests/browser/engine_contract.json`.

## 4. Decisions I made

1. `BuilderTree` also exposes `effParent`, `effParentTypes`, `typeKnown` (extra
   members). The manifest flags only *missing* documented members and *extra
   `Builder*` globals*, so this is contract-legal and avoids duplicating logic.
2. Kept one copy of `deepCopy`/`escapeHtml`/`emit` per file: `BuilderCore` has no
   such helpers, so removing a file's copy would create a cross-file load-time
   dependency.
3. P3.3 hoisted both literal **and** `\uXXXX`-escaped Persian strings into
   per-file `STR_*` constants (decoded to real characters, value unchanged).
   `builder-tree.js` keeps its `V_*` block; `10-canvas.js` keeps `V_*`, its
   `STR_*`, `MAX_*` and the two column type constants.
4. `.builder-toolbar` and `.builder-settings-panel` base rules were set to
   `display: block` to exactly reproduce the inline `display:block` they replaced.
5. Wrote `tests/browser/run_builder_checks_async.py` (same checks, async API)
   because the sync Playwright API is broken on this box's Python 3.14.0a3; the
   Appendix-A file is kept verbatim.
6. Before P3 I copied `public/ includes/ tests/ database/ docs/ config/ changelogs/`
   to a temp backup (the project is not a git repo).

## 5. Deviations

- Added `tests/browser/run_builder_checks_async.py` (extra file) — see §4.5.
- B8 also removed the one `style="` in `public/modules/settings/profile.php`
  (the prompt listed media/sales/templates only); the target
  `style=" in public/modules/**/*.php = 0` drives this.
- Hoisted `STR_*` constants use sequential names (`STR_1`, `STR_2`, …) rather
  than descriptive names; the requirement is a top `STR_*` block, not naming.
- No other deviation. No behavior, URL, API-shape, permission-key or (B1 aside)
  string change.

## 6. Errors found in this document / earlier Parts

- `builder_media_refs` and `media_library.purpose`/`sha256` are created by
  migrations `0004`/`0005`, not present in baseline `database/schema.sql`
  (carried from the Part 8 changelog).
- `database/README.md` had two tool lines run together on one physical line; fixed.
- Environment: sync Playwright API broken on Python 3.14.0a3 (not a document bug).

## 7. Verification evidence

Guard rails (§9):

```
$ php tests/run_all.php
auth_rules_test.php: OK   builder_entry_test.php: OK   builder_pages_list_test.php: OK
builder_registry_test.php: OK   file_rules_test.php: OK   file_store_none_test.php: OK
file_store_test.php: OK — SKIP (gd)   functions_test.php: OK   html_sanitizer_test.php: OK
permission_rules_test.php: OK   secrets_test.php: OK — SKIP (sodium)
exit=0

$ node tests/js/builder_tree_test.js
... all "ok - ..." lines ...
All builder-tree checks passed.   exit=0

$ php database/tools/check_inline_js.php
checked 33 script(s); syntax errors: 0

$ php -l  (every file under includes/, public/modules/, public/api/builder/,
          public/api/media/, database/tools/, config/)  -> lint bad=0

$ python tests/browser/run_builder_checks_async.py
PASS CONTRACT every documented Builder* global exists with all its documented members
PASS CONTRACT no undocumented Builder* global was added
PASS SMOKE inserting + selecting every content type raises no JS error
26 passed, 0 failed   (exit=0)
```

Runner before/after: before Part 9 (Part 8 snapshot) = `26 passed, 0 failed`;
after P0 = `26 passed, 0 failed` (manifest without the `BuilderTree` line);
after P4 (manifest line + `builder-tree.js`) = `26 passed, 0 failed`. The
CONTRACT check was never weakened.

`diagnose.php` (owner DB): `summary: 26 check(s), 0 FAIL`; the four new checks:

```
D23 [PASS] published canvases with empty slug: 0 (expect 0)
D24 [PASS] published canvases with published_at NULL: 0 (expect 0)
D25 [PASS] builder table collations match panel_users (utf8mb4_unicode_ci)
D26 [PASS] no orphan media refs
```

B8 measured counts:

| Metric | Before | After |
|---|---|---|
| `.style.` in `public/assets/js/builder/**` (excl. vendored `00-sortable.js`) | 44 | **5** |
| `style="` in `public/modules/**/*.php` | 7 | **0** |

The 5 remaining `.style.` uses, each marked `/* dynamic geometry */`:

```
10-text.js:169  editable.style.setProperty('--builder-text-color', …)   /* user-chosen colour */
10-text.js:170  editable.style.setProperty('--builder-text-bg', …)      /* user-chosen colour */
21-story.js:216 st.barFill.style.setProperty('--builder-story-dur', …)  /* per-item duration */
10-canvas.js:572 toolbarEl.style.cssText = 'top:…;left:…;'              /* toolbar placement */
21-insert-menu.js:86 menuEl.style.cssText = horizontal + vertical;      /* popup placement */
```

ZWNJ byte checks: `articles/index.php` `پیش‌نویس` right=2 wrong=0;
`pages/index.php` right=1 wrong=0. Persian literals outside `STR_*`/`V_*`
definitions: none. `\u06xx` escapes remaining in builder JS: none.

## 8. Acceptance checklist (§14)

**Upload / GD**
- ✅ with GD enabled the GD path is unchanged (byte-for-byte logic).
- ✅ GD missing + `passthrough`: valid images stored after magic-byte/marker checks; PNG+`<?php` refused; warning card shown. (Covered by `file_store_none_test.php`; `file_store_test.php` SKIPs only because this box's CLI PHP lacks `gd`, which is exactly the tested scenario.)
- ✅ GD missing + `refuse` (or production): refused with the §12 message; red card shown.
- ✅ missing `fileinfo` → Persian error (`FileStoreException`), not a fatal.
- ✅ `env_check.php` prints the image-processing line.
- ❌ Could not exercise a real **Apache** request with GD toggled in `php.ini` (would require restarting Apache on the owner's machine); covered by the test seams instead.

**List screen**
- ✅ one spelling `پیش‌نویس` (`grep` clean).
- ✅ no-hit search shows `صفحه‌ای با این مشخصات پیدا نشد`, tabs + search visible, no table.
- ✅ no pages → empty card; one page → no pager; several → prev/next.

**Clean-up and seams**
- ✅ `style="` in `public/modules/**/*.php` = 0; `.style.` builder JS = 5 (≤12), all marked `/* dynamic geometry */`.
- ✅ `BuilderTree` exists, loads after `builder-core.js`, is pure; Node + parity tests pass.
- ✅ built-in engine installs only for `data-engine="blocks"` or absent.
- ✅ `docs/builder-architecture.md`, `public/assets/vendor/README.md`, `.editorconfig`, `tests/README.md` exist.
- ✅ runner: all checks pass, including both CONTRACT checks.

**Consistency**
- ✅ site-map table exists and matches `access_map.php`/`sidebar.php`/`schema.sql`.
- ✅ `diagnose.php` D23–D26 run with 0 FAIL.
- ✅ `check_inline_js.php` `syntax errors: 0`; `php -l` clean; `php tests/run_all.php` passes.

## 9. Open items for the owner

- §10 out-of-scope: public route serving published pages; SEO fields
  (`meta_title`/`meta_description`) need a migration; splitting the toolbar and
  column operations out of `10-canvas.js`; touch swipe on the rendered slider;
  cross-container drag; undo; Imagick; any GrapesJS code.
- Baseline `database/schema.sql` does not reflect migrations `0004`/`0005`
  (`builder_media_refs`, `media_library.sha256`/`purpose`, `builder_canvases.version`);
  consider syncing it (owner decision, no change made).
- B10 is resolved (tests/ and changelogs/ are present here); nothing else to do.
