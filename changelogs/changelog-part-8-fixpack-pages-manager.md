# Changelog — Part 8: Builder Fix Pack, Pages Manager, Media Picker

## 1. Summary

- Fixed the five reproduced editor defects (A1–A5) plus A6–A9, so the block toolbar/delete works with a real mouse, text selection/typing no longer loses focus or keystrokes, and block settings can be reopened from a new toolbar gear.
- Replaced the empty `modules/articles/index.php` shell with the real **"صفحات ساخته شده"** list screen (status tabs, search, pagination, per-row edit/view/preview/delete), backed by a new DB class and a new `POST /api/builder/delete.php` endpoint (page canvases only; templates refused with 409).
- Replaced the fragile entry-resolution ladder in `modules/pages/index.php` with the unit-tested `builderResolveEntry()`, corrected the module/title for page canvases and new pages, and added the permanent "ساخت صفحه جدید" button plus the post-publish dialog.
- Built a vanilla media picker overlay (`window.BuilderMediaPicker`, `GET /api/builder/media.php` gated by `pages.view`) and removed every hand-typed numeric `media_id` field from the image/video, button, slider and story panels.
- `activate.php` now syncs `site_name` from the active template's canvas title in one transaction (roadmap §8 CR / D1). No schema change, no new library, no build step.

## 2. Defect table (A1–A9)

| ID | Status | Files | How it was fixed |
|---|---|---|---|
| A1 | fixed | `ui/10-canvas.js`, `types/10-text.js` | `updateToolbar()` only re-assigns `innerHTML` when the generated HTML string changes (`toolbarHtmlCache`); `setFormatState()` now paints the `builder-toolbar-active` class in place (`paintFormatState()`) instead of rebuilding; the toolbar gets a `mousedown` `preventDefault()`; `syncFormatState(ev)` bails when the event target is inside the toolbar/settings panel/insert menu/media picker or when the selection is not in a `.builder-text-editable`. |
| A2 | fixed | `types/10-text.js` | Removed the click `stopPropagation`; added `pointerdown`/`focusin` on the editable calling `BuilderCanvas.select(uid)` (idempotent, inside try/catch). |
| A3 | fixed | `ui/10-canvas.js`, `types/10-text.js` | `updateBlockData(uid, data, opts)` honours `{skipRefresh:true}`; the text commit passes it so the block element being typed in is not destroyed. Removed the redundant second `refreshBlock` in `renderSettingsPanel.persist`. |
| A4 | fixed | `builder-shell.js`, `types/10-text.js` | `save()` emits `builder:before-save` before reading the title/tree; the text block keeps a `pending` map and `flushAll()` commits every pending uid on that event (and on `blur`). `queueSave` marks the shell dirty immediately. |
| A5 | fixed | `ui/10-canvas.js`, `ui/20-settings-panel.js`, `types/10-text.js` | Toolbar gear `data-act="settings"` emits `builder:settings-requested`; the panel toggles open/close for that uid; block types may set `settingsOnSelect:false` (text does); the outside-click handler treats `.builder-toolbar` and `.builder-media-picker` as inside. |
| A6 | fixed | `types/11-image-video.js`, `12-button.js`, `20-slider.js`, `21-story.js`, `ui/40-media-picker.js`, `api/builder/media.php` | All editor media lookups now go through `BuilderMediaPicker.getInfo` → `GET /api/builder/media.php`, gated by `pages.view`, so staff without `media.edit` can see images. |
| A7 | fixed | `includes/builder/entry.php`, `modules/pages/index.php` | `builderResolveEntry()` (isset/filter_var only, no `&&`/`??` mixing); the page sets `$currentModule='articles'` + the right `$pageTitle`/`$breadcrumb` for page canvases and new pages. No undefined-key warning (verified). |
| A8 | fixed | `types/20-slider.js`, `types/21-story.js` | Slide / ring / item delete buttons are now `<i class="bi bi-trash"></i>` + label + `aria-label` and ask through `Swal.fire` first (titles/text per §11). |
| A9 | fixed | `builder-shell.js`, `modules/pages/index.php` | Dirty badge uses the `hidden` attribute in `paintSide`/`markDirty`/`markClean` and in the shell markup (no inline `style`). |

## 3. Files created / edited

**Created**
- `includes/builder/pages_list.php`, `includes/builder/entry.php`
- `public/api/builder/delete.php`, `public/api/builder/media.php`
- `public/assets/js/pages-manager.js`
- `public/assets/js/builder/ui/40-media-picker.js`, `public/assets/js/builder/ui/50-page-actions.js`
- `public/assets/css/builder/50-media-picker.css`
- `tests/builder_pages_list_test.php`, `tests/builder_entry_test.php`
- `tests/browser/run_builder_checks.py` (Appendix A, verbatim)
- `tests/browser/run_builder_checks_async.py` (environment workaround — see §5)
- `changelogs/changelog-part-8-fixpack-pages-manager.md` (this file)

**Edited**
- `public/modules/articles/index.php` (empty shell → list screen)
- `public/modules/pages/index.php` (entry resolution, titles, descriptor attrs, publish-card buttons, dirty badge `hidden`)
- `public/assets/js/builder/ui/10-canvas.js`, `ui/20-settings-panel.js`
- `public/assets/js/builder/types/10-text.js`, `11-image-video.js`, `12-button.js`, `20-slider.js`, `21-story.js`
- `public/assets/js/builder/builder-shell.js`
- `public/api/templates/activate.php` (D1)
- `public/assets/css/style.css` (added `.pages-slug`) — see §5.3
- `mps/update.md` (one dated line at the end pointing at this Part)

**Never edited (verified):** `database/schema.sql`, `database/migrations/*`, `includes/builder/canvas_repository.php`, `tree_validator.php`, `block_registry.php`, the block-type PHP files, `renderer.php`, `ui/00-sortable.js`, and the asset-glob code in `modules/pages/index.php`.

## 4. Decisions I made (where this document was silent)

1. **The shared media control lives in the picker.** Added `BuilderMediaPicker.field({kind, mediaId, onChange})` (a 5th method) returning the 56×56 thumbnail + `انتخاب از کتابخانه` + `حذف رسانه` control. The four block types call it (with a tiny `رسانه یافت نشد.` fallback if the picker is absent) instead of four near-identical copies.
2. **In-panel labels** (the doc only fixed the buttons): image/video `رسانه`; button `تصویر دکمه`; slider slides have no label (thumbnail + buttons); story ring cover `کاور حلقه`; story items have no label.
3. **`.pages-slug{max-width:220px}` appended to `public/assets/css/style.css`** to satisfy "max-width 220px via a CSS class (no inline style)". The builder CSS is not loaded on this module page, so the rule had to live in the module stylesheet; the doc did not name a file.
4. **`pagesListUrl()` guarded with `function_exists()`** (page hygiene; harmless in production, avoids a fatal on double-include in tests/probes).
5. **Delete-result toast** title is `موفق` with the server message as text (`صفحه حذف شد`); the confirm title/text/buttons are exactly §11.
6. **`media.php? id=` invalid** (non-integer/0) returns 400 `شناسه نامعتبر`; only a valid-but-missing id returns 404 `یافت نشد` (the doc specified only the 404 case).
7. **`data-can-upload-media`** is driven by `media.edit`; the picker's type filter is hidden for `image`/`video` kinds; list page size fixed at 24 (per §6.1).

## 5. Deviations from this document

1. **The browser harness could not run as delivered.** This machine's only Python is **3.14.0a3 (alpha)**; Playwright's *sync* API is greenlet-based and greenlet is broken on this alpha (`Greenlet.switch() returned NULL without an exception set`), and Playwright's managed Chromium **failed to download** (`storage.googleapis.com` timed out after 30 s, repeatedly). I therefore added `tests/browser/run_builder_checks_async.py`: the **same 12 checks** (identical names/assertions) implemented with the **async** API, launched against the **system-installed Chrome** (`executable_path`). It adds 4 checks (image/slider/story trash from §13, and two P4 picker/field checks). The Appendix-A file `run_builder_checks.py` is present, verbatim and unmodified.
2. **The async port differs from Appendix A only in environment-driven ways:** async API; system-Chrome `executable_path`; `ThreadingHTTPServer` instead of the single-threaded `TCPServer` (that server's backlog of 5 refused Chrome's parallel asset requests on Windows); `sys.stdout.reconfigure(encoding='utf-8')` (the cp1252 console crashed printing Persian failure detail). No check was removed or weakened.
3. **`public/assets/css/style.css`** was edited (added `.pages-slug`); it is not in §9's "Edited" list. See §4.3.
4. **`BuilderMediaPicker.field()`** is an extra public method beyond the four in §6.2. See §4.1.

No other deviations. All Persian strings are exactly the ones in §11.

## 6. Errors found in this document or earlier Parts

- **`builder_media_refs` is not in `database/schema.sql`.** §D2 names it, but the baseline schema (lines 352–395) defines only `builder_canvases` and `builder_blocks`. `builder_media_refs` and `media_library.purpose`/`sha256` come from migrations (`0004_builder_integrity_pre_code.php`, `0005_files_pre_code.php`). Not a problem for this Part (it never touches those tables), but the document's "everything needed already exists (schema)" is imprecise.
- The pre-fix harness result matches the document exactly (**3 passed, 9 failed**), confirming the defect analysis.
- `mps/update.md` is a stale refactor document unrelated to the builder; it was still the file §9 pointed at, so one dated line was appended there.

## 7. Verification evidence (V1–V9)

Environment: Windows, PHP 8.0.30 (CLI), Node v24.20.0, Python 3.14.0a3, Playwright 1.64.0 (sync **and** managed Chromium unusable here — see §5.1), system Chrome 156 via `executable_path`.

**V1 — before P1** `python tests/browser/run_builder_checks_async.py --public public`:
```
FAIL A2 click inside text block selects the block
FAIL A1 real-mouse click on trash deletes the TEXT block -- block was never selected
FAIL A1 real-mouse click on trash deletes the BUTTON block -- ['text', 'button']
FAIL A1 real-mouse click on split(2) wraps the block in a 2-column container -- ['text', 'button']
FAIL A3 typing before AND after the debounce is kept (focus not lost) -- <p>سلامabc</p>
FAIL A4 save right after typing includes the last keystrokes
PASS A5 image block opens its settings panel when selected
FAIL A5 toolbar gear reopens the settings panel after it was dismissed
PASS A5 selecting a text block does NOT auto-open the drawer
FAIL A5 gear opens text settings on demand
FAIL A1 real-mouse click on Bold formats the selected text -- <p>سلام</p>
PASS SMOKE inserting + selecting every content type raises no JS error

3 passed, 9 failed
```

**V2 — after P1 and at the end** `python tests/browser/run_builder_checks_async.py --public public`:
```
PASS A2 click inside text block selects the block
PASS A1 real-mouse click on trash deletes the TEXT block
PASS A1 real-mouse click on trash deletes the BUTTON block
PASS A1 real-mouse click on split(2) wraps the block in a 2-column container
PASS A1 real-mouse trash deletes the image_video block
PASS A1 real-mouse trash deletes the slider block
PASS A1 real-mouse trash deletes the story block
PASS A3 typing before AND after the debounce is kept (focus not lost)
PASS A4 save right after typing includes the last keystrokes
PASS A5 image block opens its settings panel when selected
PASS A5 toolbar gear reopens the settings panel after it was dismissed
PASS A5 selecting a text block does NOT auto-open the drawer
PASS A5 gear opens text settings on demand
PASS A1 real-mouse click on Bold formats the selected text
PASS P4 media picker opens and closes with Escape
PASS P4 media field shows a placeholder when no media is chosen
PASS SMOKE inserting + selecting every content type raises no JS error

17 passed, 0 failed
```
(The canonical Appendix-A file has 12 checks; `run_builder_checks.py` is unchanged. It could not be executed here — §5.1.)

**V3 — `php tests/run_all.php`** → exit 0:
```
auth_rules_test.php: OK · builder_entry_test.php: OK · builder_pages_list_test.php: OK
builder_registry_test.php: OK · file_rules_test.php: OK · file_store_test.php: SKIP (gd)
functions_test.php: OK · html_sanitizer_test.php: OK · permission_rules_test.php: OK
secrets_test.php: SKIP (sodium)
```

**V4 — `php -l`** on all 9 new/edited PHP files → `No syntax errors detected` for each.

**V5 — `node --check`** on all 11 new/edited JS files → all OK.

**V6 — real database (partial: server side verified; browser click-through ❌ not run)**
- Data layer, against the live `business_panel` DB in a rolled-back transaction (temp script):
```
connected: business_panel
PASS counts sees the new page -- {"all":1,"published":1,"draft":0}
PASS fetchPage finds the page by search
PASS fetchPage block_count = 1
PASS fetchPage creator_name set
PASS fetchPage status published
PASS deletePage(page) = deleted
PASS deletePage(template) = not_a_page
PASS deletePage(missing) = not_found
```
- List screen render (owner session, 2 temporary page canvases, rolled back): `(all) rows=2 · status=published rows=1 · status=draft rows=1 · q=منتشر rows=1 · q=zzznope rows=0`, all `no warnings`, table header `عملیات` and both status badges present.
- D1 sync (rolled back): `PASS site_name synced from template canvas title`.
- **Not run:** the browser click-through of edit/preview/delete and the SweetAlert flows (no logged-in browser session available). ❌

**V7 — permission matrix ❌ not verified.** No staff accounts/permissions were provisioned, so the `articles.view`-only (no pencil/delete) and `articles.view`+`pages.view` (eye) cases, and the 403 JSON from `delete.php` without `articles.delete`, were checked by code review only (`requirePermission('articles','delete')`, `hasPermission` gates in the template).

**V8 — picker for staff without `media.edit` ❌ not verified** in a browser with a real staff user. The endpoint is gated by `pages.view` by construction, and the picker only calls it, but the actor-level test was not run.

**V9 — warnings/console** ✅
- `modules/pages/index.php` included via CLI as an owner session for `(no params)`, `canvas_id=5`, `canvas_id=x`, `buildable_type=page&new=1`, `new=1` → `no warnings` for all five.
- `modules/articles/index.php` for `(no params)`, `status=published`, `status=draft`, `q=test`, `status=evil&page=0`, `page=2` → `no warnings` for all six.
- Browser console clean: the harness `SMOKE` check (no uncaught JS errors across all content types) passes and there were 0 `pageerror`/`console.error` entries throughout the 17 checks.

## 8. Acceptance checklist (§13)

**Editor**
- ✅ A real mouse click on a block's trash icon asks for confirmation and deletes it — **verified for text, button, image_video, slider and story** (harness).
- ✅ Clicking anywhere inside a text block selects it and shows its toolbar (harness A2).
- ✅ Typing, pausing, typing again never loses focus or text; Ctrl+S immediately after typing saves everything typed (harness A3, A4).
- ✅ Bold/italic/heading/list/link work with a real click — **Bold clicked in automation**; italic/heading/list/link share the same `builder:format` handler/`applyCommand` path (not individually clicked).
- ✅ The toolbar gear opens and closes block settings; text blocks do not auto-open the drawer (harness A5 ×3).
- ✅ `run_builder_checks` ends with `0 failed` — via the async port (17/17); the sync file could not be executed here (§5.1).

**Pages manager**
- ✅ `صفحات ساخته شده` lists every page canvas (and only pages) with title, slug, status badge, block count, Jalali date, creator (server render probe).
- ✅ Status tabs, search and pagination work and keep each other's params; digits are Persian (server render probe; pagination window code follows the media module; not exercised with >20 rows).
- ⚠️ Edit icon opens the page with all its blocks (link target verified in markup; builder opens `?canvas_id=`); eye icon for read-only staff — **❌ not verified (no staff session)**; preview opens in a new tab (markup verified).
- ⚠️ Delete asks/deletes/refreshes and template canvases are refused 409 — endpoint + class verified; **browser flow ❌ not run**; permission/CSRF 403 — code only ❌.
- ✅ Editing a built page highlights "صفحات ساخته شده" in the sidebar and uses the §5.3 titles (page probe: canvas page → article titles/module).
- ⚠️ After publishing the dialog offers `ساخت صفحه جدید`; the button also exists permanently; leaving with unsaved changes asks first — code + markup verified; **browser flow ❌ not run**.
- ✅ No PHP warning for any entry URL form (V9).

**Media**
- ⚠️ Image/button/slider/story panels pick from the library with thumbnails and **no numeric id field remains** (grep-verified: no `media_id` number inputs, no `infoCache`/`csrfToken` copies left); picking/uploading in a browser and the staff-without-`media.edit` path **❌ not run**.
- ⚠️ The picker opens, closes with Esc, and renders the placeholder field (harness); filter/search/page/upload/focus-restore **❌ not run** (no DB in the harness).

**Quality**
- ✅ `php tests/run_all.php` passes; `php -l` and `node --check` clean; browser console clean.
- ✅ `activate.php` syncs `site_name` from the template canvas title in a transaction (DB probe passed).
- ✅ No new inline `style=""`/`onclick=""` in new markup; the only new public global is `window.BuilderMediaPicker`; no schema change.

## 9. Open items for the owner

1. **D2 (recorded, not built):** a public route that serves a published page to visitors (next Part); touch swipe on the rendered slider; cross-container drag; block-level undo.
2. **Not verified here (need a logged-in browser + staff accounts + real media):** V6 browser click-through, V7 permission matrix, V8 staff-without-`media.edit`, and the browser-side picker flows (filter/search/page/upload/focus) and the publish dialog / unsaved-changes confirm.
3. **Python/tooling:** this environment has only Python 3.14.0a3 (alpha) and no Playwright-managed Chromium; to run the canonical `tests/browser/run_builder_checks.py` unchanged, install a stable Python (≤3.13) and `python -m playwright install chromium`, or keep using `run_builder_checks_async.py`.
4. **`builder_media_refs` / `media_library.purpose` are migration-only** (not in `schema.sql`) — the owner may want the baseline schema to reflect them.