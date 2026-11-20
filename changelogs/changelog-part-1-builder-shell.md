# Changelog — Part 1: Builder Page Shell

Date: 2026-09-20. Verified by execution: PHP lint, Node string-fidelity scan,
live HTTP render tests, and a stub-DOM harness driving the real server
(`php -S` + MySQL), plus direct DB assertions. No block rendering of any kind.

## 1. Files created / modified (and nothing else)

- `modules/pages/index.php` — REWRITTEN (was the 6-line empty placeholder).
  SHA256 `8D1502FCF4C23296FB2E0B6C454983C3CD08164FAF6D51669E3C9643D7EEDDB6`.
- `public/modules/pages/index.php` — REWRITTEN served copy, byte-identical
  (same SHA256 `8D15…`).
- `public/assets/css/builder/10-shell.css` — NEW.
  SHA256 `AB28D89C899FA4539D2948D12F6213F931DFAA18744255429B9F5A65DC259E96`.
- `public/assets/js/builder/builder-shell.js` — NEW (exactly one global,
  `window.BuilderShell`).
  SHA256 `6B1E3BFEABEAF0501BCCEB74AC2E48D4EF4097B2569610F42D859DF28CB76C12`.
- `changelogs/changelog-part-1-builder-shell.md` — this file.

No other file was created or modified. Verified via LastWriteTime/content:
`modules/articles/index.php` + public copy still the 6-line placeholder;
every `includes/builder/*` file, both builder endpoints, both Part 0 JS
files, `public/assets/css/style.css`, and `api/templates/activate.php`
untouched (pre-existing timestamps). Legacy `pages`/`articles` tables were
never queried by new code and remain at zero rows.

## 2. Decisions left open by the prompt (all decided, all minor)

1. `?buildable_type=template&template_id=N&new=1` is accepted (blank template
   canvas) even though §6's table does not list it: `load.php` explicitly
   supports the combination, and rejecting a loadable URL as "invalid" would
   be wrong. All four §15 invalid cases still render the invalid card.
2. `save()` clears the in-flight flag synchronously via `settle()` before
   resolve/reject (the trailing `.then()` repeats it idempotently): otherwise
   a back-to-back `save()` issued from a `.then()` continuation races the
   cleanup microtask and wrongly rejects with `save-in-flight`.
3. `--builder-sticky-top: 69px`, derived read-only from the real shell: the
   top header is Bootstrap `.sticky-top` (`top: 0`); its inner height is the
   36 px avatar + 2×8 px `py-2` padding + 1 px border = 53 px
   (`includes/header.php` line 40, `public/assets/css/style.css` has no other
   header height); +16 px = 69 px. If the header ever stops being sticky, the
   correct value is `16px`.
4. The screen variable is `$shellBase`, not `$builderBase`: Part 0's
   `includes/builder/bootstrap.php` assigns `$builderBase` (plus
   `$builderConfig`, `$builderDatabase`, `$builderTypeFiles`) in the
   including scope, so reusing the name silently repoints every later
   `require_once`. No Part 0 file was touched; the screen avoids all five
   names.

## 3. Discrepancies / prompt vs. reality

1. **`roadmap-page-builder.md` and `PANEL-BLUEPRINT.md` do not exist** in the
   repo (only `master-prompt-0-foundation-v2.md`,
   `master-prompt-1-builder-shell.md`, `update.md`). Implemented against the
   actual code, which matched the prompt's claims everywhere else:
   `csrf_token()`, `hasPermission()`/`requirePermission()`, `e()`/
   `toPersianDigits()`, `BuilderAssets::clientScripts()` output, both
   endpoint contracts, `BASE_PATH`/`PUBLIC_PATH` all verified by reading.
2. **`admin.js` has NO Persian-digit helper** (only `csrfHeaders()`,
   `ajaxDelete()`, `confirmDelete()` + theme toggle), so per §4 the shell
   uses a private `toFa()` inside the IIFE. CSRF still comes from
   `data-csrf-token`, never from `admin.js`.
3. **Blank template canvas DOES return its type**: `load.php?...&new=1`
   returned `buildable_type: "template"`, `template_id: 3` (observed live).
   The §9.2 normalisation is therefore a harmless safety net that never
   fires today; the first save succeeds with no 400/409 either way.
4. **Blueprint staleness (per §1, recorded, blueprint not edited):**
   blueprint §7.2, §15 Phase 10, and the last §16 item ("empty placeholders
   with zero-row tables") are now superseded for the `pages` module only.
   Updating the blueprint is a separate documentation CR. `articles` is
   unchanged.
5. `?canvas_id=N` entries always render full UI server-side (buttons present)
   and rely on the client re-evaluation for template canvases — the prompt's
   prescribed design; verified for both staff fixtures.

## 4. §15 acceptance checklist — result of every item

- [x] Both `index.php` copies SHA256 `8D15…` (identical); served copy renders
  over HTTP; walk-up resolves to the project root from both depths
  (proven by executing the loop from both `__DIR__` values).
- [x] Sidebar entry renders the builder; descriptor holds exactly one
  `GET /api/builder/load.php?buildable_type=template&template_id=2`, and the
  harness observed exactly one load request (retry only on button click).
- [x] `active_template_id = NULL` → `"هنوز قالبی فعال نیست"` card + link,
  no builder `<script>`/`<link>`, no `data-load-url` (nothing to request).
- [x] `?canvas_id=abc`, `?canvas_id=0`, `?buildable_type=x&new=1`,
  `?canvas_id=5&new=1` (plus `?buildable_type=page`, `?template_id=3`) →
  `"درخواست نامعتبر است"` card, HTTP 200, no builder-root, no PHP notice
  (warnings would corrupt output; none observed).
- [x] `<script>` order equals `clientScripts()` + `builder-shell.js` last;
  temp `types/zz_dummy.js` appeared with zero edits and vanished on delete.
- [x] Temp `css/builder/99-dummy.css` `<link>` appeared after `10-shell.css`;
  gone after delete. All asset URLs carry `?v=filemtime`.
- [x] Blank canvas: empty title input, `"پیش‌نویس"`, `"—"` slug/id/updated,
  count `۳` (walked client-side), single load GET.
- [x] Blank template canvas returned `buildable_type`/`template_id`
  (see §3.3); normalisation + first save succeed, no 400/409.
- [x] Hint visible for no-parameter AND `?canvas_id=` entries of the active
  canvas; hidden on non-active template canvas and on page canvas (4/4).
- [x] Typing + `"ذخیره"` creates the canvas, server success message shown,
  URL → `?canvas_id={id}` via `replaceState`, no reload.
- [x] Second `"ذخیره"` updates in place: still 1 row for template 2, no 409.
- [x] Post-save side column: Persian-digit id, slug, block count, Jalali
  time; no reload.
- [x] Title `"سایت تستی"` on the active canvas set `site_name` to
  `"سایت تستی"`; grep proves no `site_name` write and no
  `UPDATE site_settings` exists in either Part 1 file (Part 0's
  write-through). Site name restored to `"وب‌سایت من"` afterwards.
- [x] Non-active template (id 1) canvas save left `site_name` unchanged.
- [x] `"انتشار"` → `published`, `published_at` stamped once, badge
  `"منتشر شده"`, button → `"لغو انتشار"`; back → `"پیش‌نویس"` with
  `published_at` intact.
- [x] `"پیش‌نمایش"` saves then shows the `"پیش‌نمایش"` info dialog; with
  `window.BuilderPreview` defined it is called with the canvas instead (and
  no dialog appears) — zero file edits.
- [x] Empty title → `"عنوان بوم را بنویسید"`, input focused, zero requests.
- [x] Untouched save: full `builder_blocks` row snapshots (uid, type,
  parent, sort_order, data) byte-identical before/after.
- [x] Round-trip via shell save (throwaway `probe_text` type, deleted):
  raw `load.php` keeps `"data":{}`, `"settings":{}`, `"slides":[]`.
- [x] Dead session + `"ذخیره"` → `"نشست منقضی شده"` dialog → login redirect;
  no unhandled rejection, no `JSON.parse` surfacing (redirect, non-JSON
  content-type, and parse failure all funnel to the expired path).
- [x] Bogus `data-csrf-token` value → server `"توکن نامعتبر"` in the
  `"خطا"` dialog (verified at HTTP level; shell sends header-only CSRF).
- [x] `pages.view`-only staff: banner, disabled input, no action buttons in
  the DOM (server-rendered, HTTP-verified).
- [x] `pages.edit` without `templates.edit`: read-only on the template
  canvas, fully editable on `?buildable_type=page&new=1` (HTTP-verified).
- [x] `Object.keys(window)` diff around `builder-shell.js` evaluation is
  exactly `BuilderShell` (harness-internal `_`-prefixed bookkeeping
  excluded; no `window.__builder*`, no other global).
- [x] Controller installed before load → exactly one
  `render(mountEl, tree, canvas)` after load; installed after load →
  immediate single call; second install throws; malformed controller throws.
- [x] Controller returning a container tree → posted (`block_count` 6,
  persisted server-side); throwing `getTree()` → `"خطا در خواندن محتوای بوم"`,
  nothing posted, DB snapshots identical.
- [x] `markDirty()` shows `"ذخیره نشده"` + arms `beforeunload`
  (`returnValue` set); success clears both.
- [x] Dark mode / 390 px verified statically: CSS variables + full
  `body.dark-mode #builder-root` overrides (no hard-coded colours, no white
  cards), single-column stacking below `lg` (`@media (min-width: 992px)`
  gates `position: sticky`), RTL logical properties throughout. A live
  visual pass still belongs to browser QA.
- [x] Nothing outside §5 touched (mtimes + spot content checks; legacy
  tables never queried, still zero rows).

Cleanup: all verification canvases/blocks deleted (0 rows), fixture staff
accounts + login attempts removed, `site_name` restored, `active_template_id`
back at 2, throwaway `zz_probe`/`zz_dummy`/`99-dummy` files deleted, test
server stopped. Harnesses live outside the repo (`%TEMP%\opencode\`).

## 5. Contract for future parts (exact current state)

**`window.BuilderShell`** (sole global of `builder-shell.js`):
`getCanvas()` → deep copy of the normalised canvas (or `null` before load);
`getTree()` → `controller.getTree()` when installed, else deep copy of the
loaded tree (or `null` before load); `setCanvasController(controller)`;
`isDirty()` → bool; `markDirty()` / `markClean()`; `canEdit()` → bool;
`save(options?: {status?: 'draft'|'published'})` → Promise resolving with
`{canvas, block_count, buildable_type_ignored}`, rejecting on read-only /
in-flight / validation / renderer-guard / server failure.

**`setCanvasController({render, getTree})`:** throws `Error` when the argument
is not a plain object, when either member is not a function, or when a
controller is already installed (one per page). Order-independent: tracks
"controller installed" + "tree loaded" and calls
`render(mountEl /* #builder-canvas */, tree, canvas)` exactly once when both
hold (deep copies; never a null tree). Install empties/hides
`#builder-canvas-state`. At save time a throwing/non-array `getTree()`
aborts with `"خطا در خواندن محتوای بوم"` and posts nothing.

**`document` events:** `builder:loaded` `{canvas, tree}` (after paint, before
`render`); `builder:saved` `{canvas, block_count, buildable_type_ignored}`
(after side-column repaint); `builder:error`
`{stage: 'load'|'save', status: int|0, message: string}` (after the dialog).

**`#builder-root` descriptor (all `e()`-escaped):** `data-load-url`,
`data-save-url` (`/api/builder/save.php`), `data-csrf-token`,
`data-can-edit` (`1`/`0`, full gate for template entries, pages-only for
`canvas_id`), `data-login-url` (`/login.php`), `data-can-edit-templates`
(always), `data-active-template-id` (decimal or `""`, always),
`data-entry-type` (`template`|`page`|`""`), `data-entry-template-id`
(decimal or `""`).

**DOM ids Part 2 attaches to:** `#builder-canvas` (mount point, stays empty
until `render`; never style or fill it from elsewhere),
`#builder-canvas-state` (loading/error/no-renderer; hidden on install),
`#builder-title` + `#builder-title-hint` (`hidden` unless active-template
canvas: `buildable_type === 'template' && Number(template_id) ===
Number(data-active-template-id) > 0`), `#builder-actions` with
`#builder-btn-save|preview|publish` (absent in read-only),
`#builder-status-badge`, `#builder-dirty-badge` (`bg-warning`, hidden when
clean), `#builder-updated`, `#builder-type`, `#builder-slug`,
`#builder-count`, `#builder-id`, `#builder-retry` (recreated per error),
`#builder-readonly-banner` (client-added for `canvas_id` downgrade).

**CSS convention:** one `<link>` per `PUBLIC_PATH/assets/css/builder/*.css`
alphabetically (+ `?v=filemtime`); later parts add `NN-name.css`
(`20-canvas.css`, `30-toolbar.css`, …). Custom props on `#builder-root`
(`--builder-surface/border/muted/canvas-bg/sticky-top`) with
`body.dark-mode` overrides; RTL logical properties only; never touch
`style.css`/`dark-theme.css`; never style block/toolbar/settings namespaces.

**`window.BuilderPreview` hook (Part 7):** after a successful Preview save,
the shell calls `window.BuilderPreview(getCanvas())` iff it is a function;
otherwise the `"پیش‌نمایش"` info dialog
(`"تغییرات ذخیره شد. نمایش سایت هنوز ساخته نشده است."`). Part 7 defines it;
nothing else may. Ctrl+S saves; `beforeunload` guards dirty state.

## 6. Open items handed forward (per §17)

1. No canvas list: page canvases reachable only by `?canvas_id=N` URL; list
   screen + read-only list endpoint still to scope (→ `"صفحات ساخته شده"`).
2. No canvas delete path exists.
3. `window.BuilderPreview` reserved for Part 7.
4. Base-panel CRs untouched: dashboard `"تعداد ساخت سایت"` counts legacy
   `pages`; `activate.php` does not sync canvas titles into `site_name`.
