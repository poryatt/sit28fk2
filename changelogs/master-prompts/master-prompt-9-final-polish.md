# MASTER PROMPT — Part 9: Final Polish — Upload (GD) Fix, Part 8 Defects, Code Clean-up, GrapesJS-Ready Seams, Site/DB Consistency

> **Audience:** an autonomous AI coding agent (OpenCode). **Type of task:** EDIT of the existing, working PHP + MySQL + vanilla-JS admin panel (`panel/`, Persian RTL, Bootstrap 5 RTL, bootstrap-icons, SweetAlert2). Not a rebuild, and **not** a feature Part: behavior stays the same except where this document says otherwise.
> **Language rule:** instructions are English. Every string an admin sees is Persian and is written below exactly as it must be typed. Never translate, shorten or "improve" an existing Persian string unless this document changes it.
> **Authority:** Parts 0–8 and `update.md` still apply except where this document explicitly overrides them (§3).
> **How this document was produced:** the project zip (`panel-beside-2.zip`, the state after Part 8) was unpacked and read file by file; the builder's real JavaScript was executed in headless Chromium (only `fetch()` and `Swal` stubbed) with real mouse/keyboard input, using the Part 8 browser runner extended in Appendix A (26 checks, **26 pass** on the delivered snapshot). **I could not execute any PHP** (no PHP in my environment), so every PHP statement below comes from reading the code. Findings are tagged **[VERIFIED]** (reproduced in a browser or byte-checked) or **[CODE REVIEW]** (read only: re-check it yourself).

---

## 0. Working rules (read first)

1. **Read before you write.** Read, in this order: `update.md` (if present), `changelogs/` (if present), `includes/file_store.php`, `includes/file_rules.php`, `public/api/media/upload.php`, `public/modules/media/index.php`, `includes/builder/{pages_list,entry,builder_assets,tree_validator,block_registry}.php`, `public/modules/{articles,pages}/index.php`, `public/assets/js/builder/ui/10-canvas.js`, `builder-shell.js`, `database/tools/{diagnose,env_check}.php`, `database/README.md`. **Do NOT open `public/assets/js/builder/ui/00-sortable.js`** (vendored, minified, one giant line).
2. **Characterise first, then change.** Before touching anything, install the guard rails of §9 (runner, manifest, Node test skeleton) and run them. Every later phase must keep them green. A clean-up that breaks a guard rail is wrong, not the guard rail.
3. **Small commits, one phase each** (`git commit` after every phase if the project is a git repo). If you cannot use git, copy the project folder before P3 and say so in the changelog.
4. **Verify for real.** Run what is runnable: `php -l`, `php tests/run_all.php`, `node --check`, the browser runner (`pip install playwright` then `python -m playwright install chromium`). Anything you cannot run is marked ❌ **not verified** with the reason. **Never mark an item ✅ that you did not run.**
5. **Nothing silent.** Every change not asked for here, and every place where reality differs from this document, goes into the changelog (§13).
6. **Lego rule stays:** one global per JS file; no file calls another at load time (call-time use of a documented global is fine); delete-a-file-and-nothing-else-breaks. New files under `ui/`, `types/` and `assets/css/builder/` are picked up by the existing globs. The one explicit exception in this Part is the new top-level `builder-tree.js` (§7.2), which must be listed in `BuilderAssets::clientScripts()`.

---

## 1. The owner's requests and how they are read

| # | Request (paraphrased) | Reading used |
|---|---|---|
| R1 | "Is the project OK now? Any gaps?" | §2 verdict + §4 fixes. |
| R2 | "Give the code a final clean-up" | §6 (P3): measurable, behavior-preserving clean-up. |
| R3 | "The database I built is very good; keep everything consistent with the rest of the site" | The schema and the migration framework are **kept as they are** (no new table/column/migration). Consistency means: the code stays aligned with the DB and the rest of the panel, a written map module ↔ table ↔ permission ↔ URL exists, and the existing `diagnose.php` gets builder checks (§8). If "the rest of the site" meant the public website, §8's map and §7's contract are what it needs; the missing public route stays out of scope (§10). |
| R4 | "Tomorrow I may merge this with GrapesJS or bring its features; the clean-up must not get in the way" | §7: seams, an engine contract frozen by a test, a pure shared tree module, an architecture document. **No GrapesJS code or library is added.** |
| R5 | "Uploading an image fails with: پردازش تصویر در سرور فعال نیست (افزونه GD)" | §5 (P1): root cause, an immediate manual fix, and a code fix so the panel never blocks uploads because of a missing extension. |

---

## 2. Verdict on the current state (Part 8 as delivered)

**Works — [VERIFIED] in a real browser:** every editor fix of Part 8 (toolbar clicks, text-block selection, typing across the debounce, save-after-typing, gear, format buttons); the publish dialog appears on the first publish only; the new-page button asks before discarding unsaved work; the media picker lists, selects and closes (Esc); image/button/slider/story panels use the picker instead of typed ids. 26/26 runner checks pass. All JS files pass `node --check`.
**Reads correct — [CODE REVIEW]:** `delete.php` (POST-only, CSRF, permission, template canvases refused with 409), `media.php` (GET-only, `pages.view`, library files only, escaped `LIKE`), `entry.php` (pure resolver), `pages_list.php` (prepared statements), the `activate.php` transaction, `pages/index.php` (entry resolved before `header.php`, sidebar module and titles per entry kind).
**Defects found in the delivered code:**

| ID | Defect | Evidence |
|---|---|---|
| B1 | The status tab label in `modules/articles/index.php` is `پیشنویس`, **without** the zero-width non-joiner. The spec string is `پیش‌نویس` (U+200C after `ش`). The badges in the table rows have it right, so the same word is spelled two ways on one screen. | **[VERIFIED]** byte check: the tab literal has code points `…634, 646…`, the badge has `…634, 200c, 646…`. |
| B2 | The "no page exists at all" state is decided with `counts['all'] === 0`, but `counts()` honours the search term. A search with no hits therefore shows `هنوز صفحه‌ای ساخته نشده است` (and hides the tabs) instead of `صفحه‌ای با این مشخصات پیدا نشد`. | **[CODE REVIEW]** |
| B3 | When a filter/search returns nothing, the table header is still rendered with an empty body and the message below it. | **[CODE REVIEW]** |
| B4 | Pagination is printed even when there is a single page (a lone "۱"), and has no previous/next. | **[CODE REVIEW]** |
| B5 | `pagesListUrl()` is declared inside a view file behind `function_exists`, and the file is written in a different, compressed style from the rest of `includes/builder/`. | **[CODE REVIEW]** |
| B6 | Image upload fails when the PHP **GD** extension is not loaded; `FileStore::requireGd()` throws before any validation. A missing **fileinfo** extension would instead be a PHP fatal (`finfo_open` is not guarded). | **[CODE REVIEW]** + the owner's report. |
| B7 | `ui/10-canvas.js` is 1301 lines mixing tree rules, rendering, drag-and-drop, selection, toolbar and column operations; the pure tree/validation logic is private to it. | **[VERIFIED]** (line count, function list). |
| B8 | Inline styling in JS: 44 `.style.` uses in builder files (insert-menu 11, story 10, canvas 5, slider 4, media-picker 4, text 3, settings-panel 3, shell 2, image-video 2) and 7 `style="` in PHP views (media 5, sales 1, templates 1). | **[VERIFIED]** grep counts. |
| B9 | A global `BuilderStoryViewer` (`types/21-story.js`) exists but is not documented anywhere; `BuilderPreview` is the same. | **[VERIFIED]** by the contract check. |
| B10 | `tests/`, `changelogs/`, `update.md` were not in the zip: I could not confirm that the Part 8 PHP tests and `tests/browser/run_builder_checks.py` exist in the real project. | Not verifiable by me. |

**Observed and fine (do not "fix"):** `config/env.php` contains only a loader (real secrets live in `config/env.local.php`, which is git-ignored and was not in the zip); `public/uploads/.htaccess` already disables script execution; legacy `pages`/`articles` tables were dropped by migration 0006; `dashboard_snapshots.articles_count` is vestigial (always 0) and stays (no migration, §3 D2).

---

## 3. Locked decisions and overrides (nothing here is TBD)

| # | Decision | Why |
|---|---|---|
| D1 | **No new library, framework or build step.** No GrapesJS code. Vanilla JS in the existing style. | R4 asks for readiness, not integration. |
| D2 | **No schema change, no migration, no edit to `schema.sql`.** | The owner is happy with the DB. |
| D3 | **Behavior-preserving clean-up.** Any visible or API-visible behavior change not listed in this document is a defect. The guard rails (§9) are the definition of "unchanged". | Safety. |
| D4 | **GD fallback = validated pass-through, policy-controlled.** If GD is missing, uploads of jpg/png/webp are accepted **only** after strict validation and stored without re-encoding, when the policy is `passthrough`; they are refused with a clear Persian message when the policy is `refuse`. Default policy: `refuse` when `APP_ENV` is `production`, otherwise `passthrough`. Env override: `IMAGE_PROCESSING_FALLBACK=passthrough|refuse`. No Imagick path is added. | GD re-encoding is a defence layer (strips metadata, rejects polyglots), so production must not silently lose it; local development must not be blocked. |
| D5 | **Engine seam.** `BuilderShell.setCanvasController({render, getTree})` already exists and stays the one and only engine entry point. The built-in engine installs itself only when `#builder-root[data-engine]` is `blocks` (the default). | A future engine (GrapesJS) can install without a collision. |
| D6 | **Pure tree logic moves to a new global `BuilderTree`** (`builder-tree.js`): no DOM, no module state, tree passed as an argument. `ui/10-canvas.js` keeps its file and its public API, and calls `BuilderTree`. The toolbar/column-operation code is **not** split out of `10-canvas.js` in this Part (risk outweighs benefit; recorded in the architecture document as the next refactor). | Makes the constraint logic reusable and unit-testable by any engine. |
| D7 | **`BuilderCanvas` public API v1 is frozen** by `tests/browser/engine_contract.json`. | The future adapter's target. |
| D8 | `docs/builder-architecture.md` is a required deliverable (§7.4). | Knowledge transfer for the GrapesJS step. |
| O1 | **Overrides Part 0's** "`FileStore::storeImage` requires GD": it now degrades per D4. | R5. |
| O2 | **Overrides Part 8's** "no new top-level builder JS file": `builder-tree.js` is added. | D6. |

---

## 4. Phase P2 — Defects in the delivered list screen (do B1–B5)

(P1 is the upload fix, §5; it comes first in the order of §11 because it blocks the owner today. The numbering follows the document, not the order.)

1. **B1:** change the tab label to `پیش‌نویس` (with U+200C). Then run `grep -rn "پیشنویس" public includes` and fix every other spelling; the only correct spelling is with U+200C.
2. **B2/B3:** add `BuilderPagesList::totalPages(PDO $pdo): int` (unfiltered `COUNT(*)` of page canvases). The screen logic becomes: `totalPages === 0` → "no page exists" card (`هنوز صفحه‌ای ساخته نشده است` + the `ساخت اولین صفحه` button when `pages.edit`); otherwise tabs + search always shown; `data['total'] === 0` → **no table**, only a card with `صفحه‌ای با این مشخصات پیدا نشد`; else the table.
3. **B4:** render the pager only when `pages > 1`; add `قبلی` and `بعدی` items (disabled at the ends) around the existing ±2 window.
4. **B5:** move URL building into `BuilderPagesList::url(array $filters, array $overrides = []): string` (pure; keeps `status`, drops empty `q`, adds `page` only when overridden and > 1). The view file defines **no function**. Re-format `modules/articles/index.php` in the style of `includes/builder/*.php` (4 spaces, one statement per line, `<?php if (...): ?>` alternate syntax in markup). All output stays escaped with `e()`.
5. **Unit tests** (pure, no DB): extend `tests/builder_pages_list_test.php` for `url()` (status kept, empty `q` dropped, `page=1` omitted, special characters encoded, `q` with spaces) .

---

## 5. Phase P1 — Image upload without GD (R5, B6)

### 5.1 Root cause
`FileStore::storeImage()` calls `requireGd()` first; with no `imagecreatefromstring`/`imagejpeg`/`imagepng` it throws `پردازش تصویر در سرور فعال نیست (افزونه GD)`. PHP's GD is an **extension**, not a code problem: it is simply not loaded in the PHP that Apache uses (note: the CLI `php.ini` and Apache's `php.ini` can differ, so `php -m` in a terminal does not prove what the web server has).

### 5.2 Immediate manual fix for the owner (put this in `database/README.md`, "Troubleshooting")
- **Laragon:** tray icon → right-click → **PHP → Extensions** → tick `gd`, `exif`, `fileinfo`, `mbstring` → Laragon restarts Apache.
- **XAMPP:** open `C:\xampp\php\php.ini`, remove the leading `;` from `extension=gd`, `extension=mbstring`, `extension=exif` (exif **after** mbstring), `extension=fileinfo`; restart Apache from the XAMPP control panel.
- **Linux:** `sudo apt install php-gd php-mbstring` then restart Apache/php-fpm.
- **Check:** open the panel's media page: after this Part it shows a warning card while GD is missing (§5.4), and the card disappears once GD is active.

### 5.3 Code changes
**`includes/file_rules.php` (pure, unit-tested) — add:**
- `imageMagicMatches(string $first12Bytes, string $ext): bool` — `jpg`: starts `FF D8 FF`; `png`: starts `89 50 4E 47 0D 0A 1A 0A`; `webp`: bytes 0–3 are `RIFF` and bytes 8–11 are `WEBP`; any other extension → `false`.
- `containsServerScriptMarkers(string $bytes): bool` — true when `/<\?(?:php|=)|<script\b/i` matches. (Used only on the pass-through path.)
- `imageFallbackPolicy(?string $envValue, ?string $appEnv): string` — returns `'passthrough'` or `'refuse'`: an `$envValue` equal to one of the two wins; otherwise `$appEnv === 'production'` → `'refuse'`, anything else → `'passthrough'`.

**New `includes/image_support.php`** (required by `file_store.php`): `imageProcessingMode(): string` → `'gd'` when `imagecreatefromstring`, `imagejpeg` and `imagepng` all exist, else `'none'`; `imageFallbackPolicyCurrent(): string` → calls `imageFallbackPolicy(getenv('IMAGE_PROCESSING_FALLBACK') ?: null, getenv('APP_ENV') ?: null)` (read `config/config.php` and `database/tools/env_check.php` first and read the environment the same way they do).

**`includes/file_store.php` — `storeImage()`:**
1. Remove the unconditional `self::requireGd()` at the top.
2. `checkUpload`, `mimeOf`, `imageExtForMime`, `getimagesize` + `imageDimensionsAllowed` stay exactly as they are (`getimagesize` is core PHP and works without GD). Add the magic-byte check right after the extension is known: read the first 12 bytes and throw `FileStoreException('تصویر معتبر نیست')` when `imageMagicMatches()` is false.
3. Mode `gd` → the existing code path, **unchanged**.
4. Mode `none` and policy `refuse` → throw `FileStoreException('پردازش تصویر در سرور فعال نیست (افزونه GD). از مدیر سرور بخواهید آن را فعال کند.')`.
5. Mode `none` and policy `passthrough` → read the file (size is already ≤ `MAX_IMAGE_SIZE`), throw `FileStoreException('تصویر معتبر نیست')` if `containsServerScriptMarkers()`, then store it under a random 32-hex name with the validated extension through the existing `moveUploaded()`, and return the same result array as the GD path (`width`/`height` from `getimagesize`, `sha256` of the file, `file_type` `image`). Log once per request with `error_log('FileStore: GD missing, image stored without re-encoding')`.
6. `mimeOf()`: if `function_exists('finfo_open')` is false, throw `FileStoreException('افزونه fileinfo در سرور فعال نیست')` instead of letting PHP fatal.
7. Test seam: `FileStore` honours a constant `FILESTORE_TESTING_MODE` (`'gd'|'none'`) **only when** `FILESTORE_TESTING === true`, so the pass-through path is testable on a machine that has GD.

Everything that calls `storeImage()` (media upload, avatars, logo/favicon) benefits automatically; **do not** edit the callers except as §5.4 says. The Persian message is still delivered through the existing `FileStoreException` handling.

### 5.4 Visibility
- `database/tools/env_check.php`: print `image processing: gd | none (fallback: passthrough | refuse)` and the load state of `gd`, `exif`, `fileinfo`, `mbstring`.
- `config/env.example.php` and `database/README.md`: document `IMAGE_PROCESSING_FALLBACK`.
- `public/modules/media/index.php` (shown to users who can edit media): when `imageProcessingMode() === 'none'` show, above the grid, `<div class="alert alert-warning">` with `افزونه GD در این سرور فعال نیست؛ تصاویر بدون پردازش مجدد ذخیره می‌شوند. برای کیفیت و امنیت بهتر، GD را فعال کنید.` when the policy is `passthrough`, and `<div class="alert alert-danger">` with `افزونه GD در این سرور فعال نیست و آپلود تصویر امکان‌پذیر نیست.` when it is `refuse`. Nothing is shown when GD works.
- Nginx note stays in `database/README.md` (Apache already blocks script execution in `uploads/`).

### 5.5 Tests
`tests/file_rules_test.php`: magic bytes (valid jpg/png/webp headers, wrong extension for a valid header, truncated header, empty string), script markers (`<?php`, `<?=`, `<SCRIPT `, clean random bytes), `imageFallbackPolicy` (explicit `passthrough`, explicit `refuse`, `null` + `production`, `null` + `local`, `null` + `null`, garbage env value). `tests/file_store_test.php` (extend if it exists): with `FILESTORE_TESTING_MODE='none'` — passthrough stores a tiny valid PNG (build one from a base64 literal in the test) and returns its size and dimensions; a file with a valid PNG header followed by `<?php` is refused; policy `refuse` throws the Persian message.

---

## 6. Phase P3 — Code clean-up (behavior-preserving, measurable)

Do these in this order; run the guard rails (§9) after each numbered step.

1. **`.editorconfig`** (new, project root): `root = true`; `[*]` `end_of_line = lf`, `charset = utf-8`, `insert_final_newline = true`, `trim_trailing_whitespace = true`; `[*.php]` `indent_style = space`, `indent_size = 4`; `[*.{js,css,html,json,md,yml}]` `indent_size = 2`. Do not mass-reformat files you are not otherwise editing.
2. **Inline styles (B8).** Replace every `.style.` use and every `style="` in the files listed in B8 by a CSS class, the `hidden` attribute, or a CSS custom property set from JS, **except** these allowed cases, each marked with the comment `/* dynamic geometry */`: positioning that is computed at runtime (toolbar `top/left`, popup placement, drag handles) and `document.body.style.overflow` for scroll lock. Final targets: `style="` in `public/modules/**/*.php` = 0; `.style.` in `public/assets/js/builder/**` ≤ 12 (baseline 44; report the exact number, and if you cannot reach 12 without changing behavior, justify every remaining use in the changelog). New classes go in the owning CSS file under the `.builder-` prefix (module CSS for PHP views).
3. **String constants.** Each JS file keeps its Persian UI strings in a block of `STR_*` constants at the top (as `10-canvas.js` already does); no Persian literal in the middle of logic. Do not change any string except B1.
4. **Dead and duplicate code.** For each of the builder JS/PHP files, remove unused variables, unused functions, commented-out code, `console.*`, and duplicated helpers (`deepCopy`, `escapeHtml`, `emit` exist in several files: keep one copy per file only when removing it would create a cross-file dependency; otherwise reuse the `BuilderCore` helper). Write a one-off script (kept out of the project) that, for every function declared in a file, counts references in that file and reports the unused ones; delete only what the report proves unused.
5. **`ui/10-canvas.js` stays one file** (D6) but gets (a) a table of contents comment at the top listing its sections, (b) section banners (`/* ===== Section: … ===== */`) in the existing order, (c) the pure tree logic removed from it in favour of `BuilderTree` (§7.2).
6. **PHP views.** In `modules/articles/index.php` and `modules/pages/index.php` no function declarations, no business logic below the first HTML output, every dynamic value escaped. `modules/media/index.php`: replace its 5 inline styles by classes.
7. **Run** `php database/tools/check_inline_js.php` (must print `syntax errors: 0`) and `php -l` on every PHP file you touched.

---

## 7. Phase P4 — GrapesJS-ready seams (no GrapesJS added)

### 7.1 Engine switch (D5)
`pages/index.php` adds `data-engine="blocks"` to `#builder-root`. At the very end of `ui/10-canvas.js`, install the controller only when `(document.getElementById('builder-root') || {dataset:{}}).dataset.engine` is `'blocks'` or absent. Nothing else changes. (A future engine file installs itself the same way when the attribute holds its own name.)

### 7.2 `BuilderTree` — pure shared tree logic (D6)
New file `public/assets/js/builder/builder-tree.js`, listed in `BuilderAssets::clientScripts()` **right after** `builder-core.js`. One IIFE, one global `window.BuilderTree`, **no DOM, no module state, no calls at load time**. Move into it, with the tree passed as an argument instead of read from a module variable, the logic currently in `10-canvas.js`:
`isStructuralRootName`, `isStructuralName`, the effective-constraints helpers (`effParent`, `effParentTypes`, `typeKnown`; they may read `window.BuilderRegistry` **at call time**), `locate(tree, uid)`, `countNodes(tree)`, `subtreeSize(node)`, `subtreeHeight(node)`, `deepestDepth(node, depth)`, `checkInsert(tree, type, parentNode, parentDepth, addSize, addHeight)`, and the limits/messages mirrored from `includes/builder/tree_validator.php`.
Public surface (frozen by the manifest): `checkInsert, countNodes, deepestDepth, isStructuralName, isStructuralRootName, limits, locate, subtreeHeight, subtreeSize`, where `limits = { maxBlocks: 500, maxDepth: 8 }`.
`10-canvas.js` then calls `BuilderTree.*` and keeps behaving identically (same messages, same limits).

**Node unit test** `tests/js/builder_tree_test.js` (run with `node tests/js/builder_tree_test.js`, exit 1 on failure; load the file with `vm.runInNewContext` into a sandbox that has `window = {}` and a small `BuilderRegistry` stub): cases for `locate` (found/not found/depth), counts and heights, `checkInsert` for each of the six messages (unknown type, structural off-root, wrong child count, too many blocks, too deep, bad place) and for a legal insert, and a **parity test** that reads `includes/builder/tree_validator.php` as text, extracts `MAX_BLOCKS` and `MAX_DEPTH` with a regex and asserts they equal `BuilderTree.limits`; and that every `$errors[] = '…'` message string used for these rules appears character for character in `builder-tree.js`.

### 7.3 Engine contract (D7)
Create `tests/browser/engine_contract.json` with exactly the content of Appendix B and keep `tests/browser/run_builder_checks.py` at the Appendix A version (it reads this manifest). The two contract checks fail if a documented global/member disappears or an undocumented `Builder*` global appears, so any future change to the engine surface becomes a conscious, documented act. Note: `BuilderTree` is in the manifest, so the contract check is **red until §7.2 is done** (intended).

### 7.4 `docs/builder-architecture.md` (required, ≤ 250 lines, English)
Sections, in this order: (1) layer diagram in text: PHP registry/validator/sanitizer/repository/renderer ↔ JSON API ↔ `BuilderShell` ↔ engine (canvas controller) ↔ chrome modules (toolbar inside canvas, settings panel, insert menu, media picker, page actions); (2) the tree format `{uid, type, data, children}` and its invariants (three structural roots, limits, uid format, who validates what); (3) **the engine contract**: `setCanvasController({render(mountEl, tree, canvas), getTree()})`, the `BuilderCanvas` API v1 (every method, one line each), and the event table (`builder:loaded`, `:saved`, `:published` (cancelable), `:before-save`, `:selection-changed`, `:tree-changed`, `:insert-requested`, `:settings-requested`, `:format`, `:error`: who emits, payload, who listens); (4) the block-type contract on both sides and a "how to add a block type" checklist; (5) the media contract (`BuilderMediaPicker.open/getInfo/upload`, `api/builder/media.php`); (6) **the site map table**: module key ↔ menu label ↔ URL ↔ permission keys and actions ↔ DB tables ↔ API endpoints (rows: dashboard, pages = "ساخت سایت", articles = "صفحات ساخته شده", templates, sales, media, licenses, settings; call out that the module key `articles` is the page-list module and that the legacy `pages`/`articles` tables are gone); (7) **GrapesJS adapter plan** (below); (8) known limits and the next refactor (splitting toolbar/column operations out of `10-canvas.js`; the public site route; SEO fields).

**Content required in section (7), as decisions, not options:**
- The tree stays the single source of truth. A GrapesJS adapter is a *canvas controller* (`render`, `getTree`) plus chrome replacements; it never stores HTML as truth and always saves through `api/builder/save.php`.
- Mapping: GrapesJS component `type` ↔ `node.type`; component attributes/traits ↔ `node.data`; `components` ↔ `children`. Each existing block type becomes one custom GrapesJS component type; unknown components are refused by the adapter, because the PHP validators and `html_sanitizer.php` are the whitelist.
- Free-form HTML from GrapesJS needs a new block type (for example `custom_html`) validated by `includes/builder/html_sanitizer.php` on the server first; not before.
- Asset Manager → `BuilderMediaPicker.open({kind, onSelect})` and `api/builder/media.php`.
- Storage Manager → a thin adapter around `BuilderShell.save()`/`load.php`, keeping the optimistic `version` check.
- Vendor files live in `public/assets/vendor/<name>/<version>/` (local copies, no CDN); add `public/assets/vendor/README.md` saying so.
- GrapesJS renders in an iframe; the builder's CSS is scoped by `#builder-root`/`#builder-canvas` and the `.builder-` prefix, which keeps the two worlds apart. Keep it that way: no new bare element selectors.

---

## 8. Phase P5 — Consistency with the DB and the rest of the site (no schema change)

1. **The site map** (§7.4 item 6) is the deliverable that answers "consistent with the rest of the site". Build it from the code, not from memory: `includes/access_map.php`, `includes/sidebar.php`, `database/schema.sql`, `permissions` seed.
2. **`database/tools/diagnose.php`: add four read-only checks, `since = 8`**, in the same `report()` style, skipping when a table is missing:
   - **D23** published canvases with an empty slug (`status='published' AND (slug IS NULL OR slug = '')`) → expect 0, else FAIL.
   - **D24** published canvases with `published_at IS NULL` → expect 0, else WARN.
   - **D25** builder tables whose `TABLE_COLLATION` differs from `panel_users`' → expect none, else WARN.
   - **D26** `builder_media_refs` rows whose `media_id` is missing from `media_library` → expect 0, else FAIL; and an INFO line when `site_settings.site_name` differs from the active template canvas title.
   Update the header comment's range (`D01–D26`) and `database/README.md`'s line for the tool.
3. **Code ↔ DB alignment audit** (report in the changelog, fix only what is clearly wrong): every `builder_*`/`media_library` column used in PHP exists in `schema.sql` plus migrations (`version` comes from migration 0004); every `ON DELETE` rule the code relies on (canvas → blocks, canvas → media refs) is present; `delete.php` relies on those cascades. Note in the changelog that `dashboard_snapshots.articles_count` is vestigial.
4. **Recommended but NOT done (needs a migration; the owner decides):** the old `pages` table had `meta_title`/`meta_description`; `builder_canvases` has no SEO fields, which the public site will need. List it under "Open items".

---

## 9. Guard rails and tests (create first; keep green)

| Item | Where | Note |
|---|---|---|
| Browser runner | `tests/browser/run_builder_checks.py` = **Appendix A, verbatim** | Replaces the Part 8 file (it is a superset). 26 checks; on the delivered snapshot all pass. |
| Contract manifest | `tests/browser/engine_contract.json` = **Appendix B, verbatim** | Red for `BuilderTree` until §7.2. |
| Node test | `tests/js/builder_tree_test.js` | §7.2. |
| PHP tests | `tests/builder_pages_list_test.php` (+`url()`), `tests/file_rules_test.php` (+§5.5), `tests/file_store_test.php` (+§5.5, if present) | Same style as the existing tests: local `check()`, failure counter, non-zero exit. |
| One command list | `tests/README.md` (≤ 15 lines) | How to run: `php tests/run_all.php`, `node tests/js/builder_tree_test.js`, `python tests/browser/run_builder_checks.py`. |

---

## 10. Out of scope (record, do not build)

A public route that serves published pages to visitors; SEO fields (needs a migration); splitting the toolbar/column code out of `10-canvas.js`; touch swipe on the rendered slider; cross-container drag; undo; Imagick support; any GrapesJS code.

---

## 11. Phase order and commit points

1. **P0** guard rails (§9) — create the runner and the manifest (**without** the `BuilderTree` line, Appendix B), run them and keep the output: `26 passed, 0 failed`.
2. **P1** upload / GD (§5).
3. **P2** list-screen defects (§4).
4. **P3** clean-up (§6).
5. **P4** seams (§7): `builder-tree.js`, engine switch, manifest line for `BuilderTree`, architecture doc.
6. **P5** consistency (§8).
7. **P6** full verification and changelog.

---

## 12. Strings introduced or corrected

| Where | String |
|---|---|
| Status tab (B1) | `پیش‌نویس` (U+200C after `ش`) |
| Upload refused (policy `refuse`) | `پردازش تصویر در سرور فعال نیست (افزونه GD). از مدیر سرور بخواهید آن را فعال کند.` |
| fileinfo missing | `افزونه fileinfo در سرور فعال نیست` |
| Invalid image (magic bytes / script markers) | `تصویر معتبر نیست` |
| Media page, pass-through warning | `افزونه GD در این سرور فعال نیست؛ تصاویر بدون پردازش مجدد ذخیره می‌شوند. برای کیفیت و امنیت بهتر، GD را فعال کنید.` |
| Media page, refuse alert | `افزونه GD در این سرور فعال نیست و آپلود تصویر امکان‌پذیر نیست.` |
| List screen | `قبلی` · `بعدی` · `هنوز صفحه‌ای ساخته نشده است` · `صفحه‌ای با این مشخصات پیدا نشد` · `ساخت اولین صفحه` |

---

## 13. Must NOT do

| # | Do NOT | Because |
|---|---|---|
| 1 | Add GrapesJS, any new library, CDN or build step | D1 |
| 2 | Add a table, column, index or migration, or edit `schema.sql` | D2 |
| 3 | Change visible behavior, URLs, API shapes, permission keys or Persian strings other than §12 | D3 |
| 4 | Re-encode or "improve" images when GD exists (the GD path stays byte-for-byte the same logic) | O1 |
| 5 | Accept SVG/GIF or any other format in the pass-through path | Only jpg/png/webp are allowed today |
| 6 | Weaken the validation of the GD path or skip the magic-byte and marker checks in pass-through | Security baseline |
| 7 | Split `10-canvas.js` into several files, or rename `BuilderCanvas` members | D6, D7 |
| 8 | Put DOM access, module state or load-time calls in `builder-tree.js` | It must stay pure |
| 9 | Let one JS file call another at load time | Lego rule |
| 10 | Mass-reformat untouched files | Noise hides real changes |
| 11 | Edit the vendored `00-sortable.js` | Vendor file |
| 12 | Mark any check ✅ you did not run, or hide a failing guard rail by editing it | Honesty |
| 13 | Silently deviate from this document | Everything goes in the changelog |

---

## 14. Acceptance checklist

**Upload / GD**
- [ ] With GD enabled, jpg/png/webp upload and behave exactly as before.
- [ ] With GD disabled and policy `passthrough`: valid images upload, a PNG with `<?php` appended is refused, the media page shows the warning.
- [ ] With GD disabled and policy `refuse` (or `APP_ENV=production`): upload is refused with the §12 message and the media page shows the red alert.
- [ ] A missing `fileinfo` gives a Persian error, not a PHP fatal.
- [ ] `env_check.php` prints the image-processing line.

**List screen**
- [ ] One spelling, `پیش‌نویس`, everywhere (`grep` clean).
- [ ] A search with no hits shows `صفحه‌ای با این مشخصات پیدا نشد`, with tabs and search visible, and no table.
- [ ] No pages at all shows the empty card; one page of results shows no pager; several pages show prev/next.

**Clean-up and seams**
- [ ] `style="` in `public/modules/**/*.php` = 0; `.style.` in builder JS ≤ 12, each remaining one marked `/* dynamic geometry */` (exact count reported).
- [ ] `BuilderTree` exists, is loaded after `builder-core.js`, is pure, and `10-canvas.js` uses it; Node test and parity test pass.
- [ ] The built-in engine installs only for `data-engine="blocks"`.
- [ ] `docs/builder-architecture.md`, `public/assets/vendor/README.md`, `.editorconfig`, `tests/README.md` exist.
- [ ] Runner: **all checks pass**, including both CONTRACT checks.

**Consistency**
- [ ] The site map table exists and matches the code.
- [ ] `diagnose.php` reports D23–D26 with no FAIL on the owner's database (or the FAIL is explained).
- [ ] `check_inline_js.php` prints `syntax errors: 0`; `php -l` clean; `php tests/run_all.php` passes.

---

## 15. Final deliverable — `changelogs/changelog-part-9-final-polish.md` (mandatory)

In this order: **(1)** Summary (3–5 lines). **(2)** Defect table B1–B10 with status, files, one line on how. **(3)** Files created/edited. **(4)** Decisions you made yourself. **(5)** Deviations from this document, or `None`. **(6)** Errors found in this document or earlier Parts. **(7)** Verification evidence: the real output of every command in §9/§14 (runner before and after, Node test, PHP tests, `diagnose.php`, `check_inline_js.php`) and the measured B8 counts before/after; anything not run is ❌ with the reason. **(8)** Checklist result ✅/❌ per item. **(9)** Open items for the owner (§10, SEO fields, B10). The owner will hand this changelog back for verification, so it must describe what actually happened.

---


## Appendix A — `tests/browser/run_builder_checks.py` (create verbatim; replaces the Part 8 file)

No PHP or database needed. It copies `public/assets` to a temp folder, serves a stub page that loads the builder scripts in the same order as the real page (including `builder-tree.js` when it exists, then every `types/*.js` and `ui/*.js` alphabetically), stubs only `fetch()` and `Swal`, and drives the editor with real mouse and keyboard events. **On the delivered snapshot (with the §P0 manifest) it prints `26 passed, 0 failed`.** After you add `BuilderTree` to the manifest in P4, the CONTRACT check stays red until `builder-tree.js` is in place. You may add checks, never weaken or delete one.

```python
#!/usr/bin/env python3
"""Browser checks (v3) for the visual page builder (real Chromium, real mouse, real project JS).

Usage:  python tests/browser/run_builder_checks.py [--public PATH_TO_public_DIR]
Needs:  pip install playwright && python -m playwright install chromium
It serves a stub page (no PHP, no MySQL) that loads the project's builder scripts in the SAME order as
modules/pages/index.php, stubs only fetch() and Swal, and drives the editor with real mouse/keyboard input.
Exit code 0 = every check passed, 1 = at least one failed, 2 = Playwright is not available.
"""
import argparse, functools, glob, http.server, json, os, shutil, socketserver, sys, tempfile, threading

try:
    from playwright.sync_api import sync_playwright
except Exception:
    print('Playwright is not installed: browser checks NOT RUN (not verified).')
    sys.exit(2)

ap = argparse.ArgumentParser()
ap.add_argument('--public', default=os.path.join(os.path.dirname(__file__), '..', '..', 'public'))
args = ap.parse_args()
PUBLIC = os.path.abspath(args.public)
JS = os.path.join(PUBLIC, 'assets', 'js', 'builder')
CSS = sorted(glob.glob(os.path.join(PUBLIC, 'assets', 'css', 'builder', '*.css')))

def scripts():
    out = ['block-registry.js', 'builder-core.js']
    if os.path.exists(os.path.join(JS, 'builder-tree.js')):
        out.append('builder-tree.js')
    out += ['types/' + os.path.basename(f) for f in sorted(glob.glob(os.path.join(JS, 'types', '*.js')))]
    out += ['builder-shell.js']
    out += ['ui/' + os.path.basename(f) for f in sorted(glob.glob(os.path.join(JS, 'ui', '*.js')))]
    return out

U = lambda n: n * 32  # 32-char uid helper: U('a')
HTML = """<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8">
%(css)s
</head><body>
<script>
window.__swal = [];
window.__swalAnswer = 'confirm';
window.Swal = { fire: function (o) { window.__swal.push(o && o.title ? o.title : String(o)); return Promise.resolve(window.__swalAnswer === 'confirm' ? { isConfirmed: true, value: 'x' } : { isConfirmed: false, isDenied: false, isDismissed: true }); } };
window.__posts = [];
var TREE = %(tree)s;
var MEDIA = %(media)s;
function J(o, s) { return new Response(JSON.stringify(o), { status: s || 200, headers: { 'Content-Type': 'application/json' } }); }
window.fetch = function (u, o) {
  if (o && o.method === 'POST') { window.__posts.push({ url: String(u), body: o.body }); }
  if (String(u).indexOf('/save') === 0) {
    var b = JSON.parse(o.body);
    return Promise.resolve(J({ success: true, message: 'ok', data: { canvas: { id: 7, buildable_type: 'page', template_id: null, title: b.title, slug: 't', status: b.status, version: 2, updated_at_jalali: '-' }, block_count: 5 } }));
  }
  if (String(u).indexOf('/load') === 0) {
    return Promise.resolve(J({ success: true, data: { canvas: { id: 7, buildable_type: 'page', template_id: null, title: 't', slug: 't', status: 'draft', version: 1, updated_at_jalali: '-' }, tree: TREE } }));
  }
  if (String(u).indexOf('/api/builder/media.php') === 0) {
    var mm = String(u).match(/[?&]id=(\\d+)/);
    if (mm) { var it = MEDIA.filter(function (x) { return x.id == mm[1]; })[0]; return Promise.resolve(it ? J({ success: true, data: it }) : J({ success: false, message: 'x' }, 404)); }
    return Promise.resolve(J({ success: true, data: { items: MEDIA, page: 1, pages: 1, total: MEDIA.length } }));
  }
  return Promise.resolve(J({ success: false, message: 'stub: ' + u }, 404));
};
</script>
<div id="builder-root" data-load-url="/load" data-save-url="/save" data-csrf-token="x" data-can-edit="1"
     data-can-edit-templates="1" data-entry-type="page" data-can-upload-media="1" data-can-create-page="1" data-can-view-list="1">
  <div class="card builder-title-card"><input id="builder-title" class="form-control"></div>
  <div class="card builder-canvas-card p-3" style="position:relative"><div id="builder-canvas" class="builder-canvas"></div><div id="builder-canvas-state"></div></div>
  <div id="builder-actions"><button id="builder-btn-save">s</button><button id="builder-btn-preview">p</button><button id="builder-btn-publish">pub</button>
    <a id="builder-btn-new-page" href="#new-page">new</a><a id="builder-btn-back-list" href="#list">list</a></div>
  <span id="builder-status-badge"></span><span id="builder-dirty-badge" hidden></span>
</div>
%(js)s
</body></html>"""
TREE = [
  {'uid': U('a'), 'type': 'header', 'data': {}, 'children': []},
  {'uid': U('b'), 'type': 'body', 'data': {}, 'children': [
      {'uid': U('c'), 'type': 'text', 'data': {'html': '<p>سلام</p>', 'align': 'right'}, 'children': []},
      {'uid': U('d'), 'type': 'button', 'data': {'label': 'دکمه', 'url': '#', 'open_new_tab': False, 'media_id': None}, 'children': []}]},
  {'uid': U('e'), 'type': 'footer', 'data': {}, 'children': []}]

MEDIA = [{'id': i, 'file_name': 'img%d.jpg' % i, 'file_path': '/uploads/media/%032d.jpg' % i, 'file_type': 'image',
          'mime_type': 'image/jpeg', 'width': 10, 'height': 10} for i in range(1, 6)]
tmp = tempfile.mkdtemp(prefix='builder_harness_')
shutil.copytree(os.path.join(PUBLIC, 'assets'), os.path.join(tmp, 'assets'))
page = HTML % {
    'css': '\n'.join('<link rel="stylesheet" href="assets/css/builder/%s">' % os.path.basename(c) for c in CSS),
    'js': '\n'.join('<script src="assets/js/builder/%s"></script>' % s for s in scripts()),
    'tree': json.dumps(TREE),
    'media': json.dumps(MEDIA)}
open(os.path.join(tmp, 'index.html'), 'w', encoding='utf-8').write(page)

class Quiet(http.server.SimpleHTTPRequestHandler):
    def log_message(self, *a): pass
socketserver.TCPServer.allow_reuse_address = True
httpd = socketserver.TCPServer(('127.0.0.1', 0), functools.partial(Quiet, directory=tmp))
URL = 'http://127.0.0.1:%d/index.html' % httpd.server_address[1]
threading.Thread(target=httpd.serve_forever, daemon=True).start()

results = []
def check(name, ok, detail=''):
    results.append((name, bool(ok)))
    print(('PASS ' if ok else 'FAIL ') + name + ((' -- ' + str(detail)) if detail and not ok else ''))

def fresh(browser):
    pg = browser.new_page(viewport={'width': 1300, 'height': 900})
    errs = []
    pg.on('pageerror', lambda e: errs.append(str(e)))
    pg.on('console', lambda m: errs.append(m.text) if m.type == 'error' and 'ERR_NAME_NOT_RESOLVED' not in m.text and 'Failed to load resource' not in m.text else None)
    pg.goto(URL); pg.wait_for_function("window.BuilderCanvas && document.querySelectorAll('.builder-block').length > 0")
    return pg, errs

def center(pg, sel):
    b = pg.query_selector(sel).bounding_box()
    return b['x'] + b['width'] / 2, b['y'] + b['height'] / 2

def real_click(pg, sel):
    x, y = center(pg, sel); pg.mouse.move(x, y); pg.mouse.down(); pg.mouse.up()

def body_types(pg):
    return pg.evaluate("window.BuilderCanvas.getTree()[1].children.map(function(c){return c.type})")

with sync_playwright() as p:
    browser = p.chromium.launch()

    # A2 + A1: clicking INSIDE the text selects it; the real-mouse trash click then deletes it.
    pg, errs = fresh(browser)
    pg.click('.builder-text-editable'); pg.wait_for_timeout(250)
    sel = pg.evaluate("(window.BuilderCanvas.getSelected()||{node:{}}).node.type")
    check('A2 click inside text block selects the block', sel == 'text', sel)
    if sel == 'text':
        real_click(pg, '.builder-toolbar [data-act="delete"]'); pg.wait_for_timeout(400)
        check('A1 real-mouse click on trash deletes the TEXT block', body_types(pg) == ['button'], body_types(pg))
    else:
        check('A1 real-mouse click on trash deletes the TEXT block', False, 'block was never selected')
    pg.close()

    pg, errs = fresh(browser)
    bx, by = center(pg, '.builder-block[data-type="button"]'); pg.mouse.click(bx, by); pg.wait_for_timeout(250)
    real_click(pg, '.builder-toolbar [data-act="delete"]'); pg.wait_for_timeout(400)
    check('A1 real-mouse click on trash deletes the BUTTON block', body_types(pg) == ['text'], body_types(pg))
    pg.close()

    # A1: split buttons (1-4) are alive with a real click too.
    pg, errs = fresh(browser)
    bx, by = center(pg, '.builder-block[data-type="button"]'); pg.mouse.click(bx, by); pg.wait_for_timeout(250)
    real_click(pg, '.builder-toolbar [data-act="split"][data-n="2"]'); pg.wait_for_timeout(400)
    check('A1 real-mouse click on split(2) wraps the block in a 2-column container',
          'column_container' in body_types(pg), body_types(pg))
    pg.close()

    # A3: typing keeps focus across the 500 ms debounced commit.
    pg, errs = fresh(browser)
    pg.click('.builder-text-editable'); pg.keyboard.press('End'); pg.keyboard.type('abc'); pg.wait_for_timeout(900)
    pg.keyboard.type('XYZ'); pg.wait_for_timeout(900)
    html = pg.evaluate("window.BuilderCanvas.getNode('%s').data.html" % U('c'))
    check('A3 typing before AND after the debounce is kept (focus not lost)', 'abcXYZ' in html, html)
    pg.close()

    # A4: Ctrl+S right after typing must POST the new text.
    pg, errs = fresh(browser)
    pg.click('.builder-text-editable'); pg.keyboard.press('End'); pg.keyboard.type('LASTWORDS'); pg.keyboard.press('Control+s'); pg.wait_for_timeout(900)
    posted = pg.evaluate("window.__posts.length ? window.__posts[0].body : ''")
    check('A4 save right after typing includes the last keystrokes', 'LASTWORDS' in posted)
    pg.close()

    # A5: settings can be reopened from the toolbar gear after being dismissed.
    pg, errs = fresh(browser)
    uid = pg.evaluate("(function(){var n=window.BuilderCore.createNode('image_video'); return window.BuilderCanvas.insertBlock(n,'%s');})()" % U('b'))
    pg.evaluate("window.BuilderCanvas.select('%s')" % uid); pg.wait_for_timeout(300)
    check('A5 image block opens its settings panel when selected', pg.evaluate("window.BuilderSettingsPanel.isOpen()"))
    pg.keyboard.press('Escape'); pg.wait_for_timeout(200)
    closed = not pg.evaluate("window.BuilderSettingsPanel.isOpen()")
    gear = pg.query_selector('.builder-toolbar [data-act="settings"]')
    if gear:
        real_click(pg, '.builder-toolbar [data-act="settings"]'); pg.wait_for_timeout(300)
    check('A5 toolbar gear reopens the settings panel after it was dismissed', closed and gear is not None and pg.evaluate("window.BuilderSettingsPanel.isOpen()"))
    pg.close()

    # A5: a text block does not pop the drawer over the text while you type; the gear opens it on demand.
    pg, errs = fresh(browser)
    pg.click('.builder-text-editable'); pg.wait_for_timeout(300)
    check('A5 selecting a text block does NOT auto-open the drawer', not pg.evaluate("window.BuilderSettingsPanel.isOpen()"))
    gear = pg.query_selector('.builder-toolbar [data-act="settings"]')
    if gear:
        real_click(pg, '.builder-toolbar [data-act="settings"]'); pg.wait_for_timeout(300)
    check('A5 gear opens text settings on demand', gear is not None and pg.evaluate("window.BuilderSettingsPanel.isOpen()"))
    pg.close()

    # A1: a format button works with a real click while text is selected.
    pg, errs = fresh(browser)
    pg.click('.builder-text-editable'); pg.keyboard.press('Control+a'); pg.wait_for_timeout(100)
    bold = pg.query_selector('.builder-toolbar [data-act="format"][data-id="bold"]')
    if bold:
        real_click(pg, '.builder-toolbar [data-act="format"][data-id="bold"]'); pg.wait_for_timeout(900)
    html = pg.evaluate("window.BuilderCanvas.getNode('%s').data.html" % U('c'))
    check('A1 real-mouse click on Bold formats the selected text', bold is not None and ('<b>' in html or '<strong>' in html), html)
    pg.close()

    # P8: publishing a page shows the "new page" dialog (not the generic toast); a second save does not.
    pg, errs = fresh(browser)
    pg.evaluate("window.__swalAnswer = 'dismiss'")
    real_click(pg, '#builder-btn-publish'); pg.wait_for_timeout(700)
    swal = pg.evaluate("window.__swal")
    check('P8 publishing a page shows the publish dialog', 'صفحه منتشر شد' in swal, swal)
    check('P8 the generic success toast is replaced by the dialog on publish', 'موفق' not in swal, swal)
    pg.evaluate("window.__swal.length = 0")
    real_click(pg, '#builder-btn-save'); pg.wait_for_timeout(700)
    swal = pg.evaluate("window.__swal")
    check('P8 a later save of an already published page shows only the normal toast', 'صفحه منتشر شد' not in swal and 'موفق' in swal, swal)
    pg.close()

    # P8: the permanent "new page" button asks before throwing unsaved work away; clean state navigates directly.
    pg, errs = fresh(browser)
    real_click(pg, '#builder-btn-new-page'); pg.wait_for_timeout(300)
    check('P8 new-page button navigates directly when nothing is unsaved',
          pg.evaluate("location.hash") == '#new-page' and 'تغییرات ذخیره‌نشده' not in pg.evaluate("window.__swal"))
    pg.close()
    pg, errs = fresh(browser)
    pg.click('.builder-text-editable'); pg.keyboard.press('End'); pg.keyboard.type('x'); pg.wait_for_timeout(700)
    real_click(pg, '#builder-btn-new-page'); pg.wait_for_timeout(400)
    check('P8 new-page button asks for confirmation when there are unsaved changes',
          'تغییرات ذخیره‌نشده' in pg.evaluate("window.__swal"), pg.evaluate("window.__swal"))
    pg.close()

    # P8: media picker, and every media block panel uses it instead of a typed id.
    pg, errs = fresh(browser)
    for t in ['image_video', 'button']:
        u = pg.evaluate("(function(){var n=window.BuilderCore.createNode('%s'); return window.BuilderCanvas.insertBlock(n,'%s');})()" % (t, U('b')))
        pg.evaluate("window.BuilderCanvas.select('%s')" % u); pg.wait_for_timeout(250)
        btn = pg.query_selector('.builder-settings-panel-body button:text-is("انتخاب از کتابخانه")')
        check('P8 %s panel has the library picker button' % t, btn is not None)
        if t == 'image_video' and btn:
            btn.click(); pg.wait_for_selector('.builder-media-picker', timeout=3000); pg.wait_for_timeout(300)
            tiles = pg.query_selector_all('.builder-media-picker .builder-media-tile, .builder-media-picker [data-media-id]')
            check('P8 picker lists the library files', len(tiles) == 5, len(tiles))
            if tiles:
                tiles[1].click(); pg.wait_for_timeout(400)
                mid = pg.evaluate("window.BuilderCanvas.getNode('%s').data.media_id" % u)
                check('P8 choosing a tile stores its id and closes the picker', mid == 2 and pg.query_selector('.builder-media-picker') is None, mid)
            btn = pg.query_selector('.builder-settings-panel-body button:text-is("انتخاب از کتابخانه")')
            if btn:
                btn.click(); pg.wait_for_selector('.builder-media-picker'); pg.keyboard.press('Escape'); pg.wait_for_timeout(300)
                check('P8 Esc closes the picker', pg.query_selector('.builder-media-picker') is None)
    for t, add in [('slider', 'افزودن اسلاید'), ('story', 'افزودن حلقه')]:
        u = pg.evaluate("(function(){var n=window.BuilderCore.createNode('%s'); return window.BuilderCanvas.insertBlock(n,'%s');})()" % (t, U('b')))
        pg.evaluate("window.BuilderCanvas.select('%s')" % u); pg.wait_for_timeout(250)
        a = pg.query_selector('.builder-settings-panel-body button:text-is("%s")' % add)
        if a: a.click(); pg.wait_for_timeout(300)
        check('P8 %s panel offers the library picker per item' % t,
              pg.query_selector('.builder-settings-panel-body button:text-is("انتخاب از کتابخانه")') is not None)
    pg.close()

    # Part 9: the engine contract. Every documented Builder* global exists with its documented members and no
    # undocumented Builder* global appeared. The manifest lives next to this script (engine_contract.json).
    manifest_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'engine_contract.json')
    if os.path.exists(manifest_path):
        manifest = json.load(open(manifest_path, encoding='utf-8'))['globals']
        pg, errs = fresh(browser)
        actual = pg.evaluate("(function(){var o={};Object.keys(window).filter(function(k){return /^Builder[A-Z]/.test(k)}).forEach(function(k){var v=window[k];o[k]=(v&&(typeof v==='object'||typeof v==='function'))?Object.keys(v).sort():[]});return o})()")
        missing = {g: [k for k in keys if k not in actual.get(g, [])] for g, keys in manifest.items() if g not in actual or any(k not in actual[g] for k in keys)}
        extra = [g for g in actual if g not in manifest]
        check('CONTRACT every documented Builder* global exists with all its documented members', not missing, missing)
        check('CONTRACT no undocumented Builder* global was added', not extra, extra)
        pg.close()
    else:
        check('CONTRACT engine_contract.json exists next to this script', False, manifest_path)

    # Smoke: no uncaught errors during all of the above.
    pg, errs = fresh(browser)
    for t in ['image_video', 'slider', 'story', 'button']:
        u = pg.evaluate("(function(){var n=window.BuilderCore.createNode('%s'); return window.BuilderCanvas.insertBlock(n,'%s');})()" % (t, U('b')))
        pg.evaluate("window.BuilderCanvas.select('%s')" % u); pg.wait_for_timeout(150)
    check('SMOKE inserting + selecting every content type raises no JS error', not errs, errs[:3])
    pg.close()
    browser.close()

httpd.shutdown(); shutil.rmtree(tmp, ignore_errors=True)
failed = [n for n, ok in results if not ok]
print('\n%d passed, %d failed' % (len(results) - len(failed), len(failed)))
sys.exit(1 if failed else 0)
```

---

## Appendix B — `tests/browser/engine_contract.json` (create verbatim)

In **P0**, create it **without** the `BuilderTree` line (that global does not exist yet); in **P4**, add the `BuilderTree` line exactly as shown. The member lists were read from the live globals of the delivered snapshot; `BuilderTree` is the new frozen surface of §7.2.

```json
{
  "globals": {
    "BuilderRegistry": ["getAllBlockTypes", "getBlockType", "getInsertableBlockTypes", "hasBlockType", "registerBlockType"],
    "BuilderCore": ["createColumnContainer", "createNode", "createUid", "defaultTree", "findByUid", "walkTree"],
    "BuilderTree": ["checkInsert", "countNodes", "deepestDepth", "isStructuralName", "isStructuralRootName", "limits", "locate", "subtreeHeight", "subtreeSize"],
    "BuilderShell": ["canEdit", "getCanvas", "getTree", "isDirty", "markClean", "markDirty", "save", "setCanvasController"],
    "BuilderCanvas": ["clearSelection", "getBlockElement", "getCanvasElement", "getNode", "getSelected", "getToolbarElement", "getTree", "insertBlock", "moveBlock", "refreshBlock", "registerFormatCommand", "removeBlock", "select", "setFormatState", "updateBlockData"],
    "BuilderSettingsPanel": ["getOpenUid", "isOpen"],
    "BuilderInsertMenu": ["getAnchorParentUid", "isOpen"],
    "BuilderMediaPicker": ["close", "field", "getInfo", "open", "upload"],
    "BuilderPreview": [],
    "BuilderStoryViewer": []
  }
}
```
