# Master Prompt — Part 1: Visual Page Builder — Builder Page Shell

**Target executor:** an AI coding agent (OpenCode or equivalent) with write access to the existing panel project.
**Project:** Website & Business Management Panel (PHP 8 + PDO + MySQL, Persian/RTL) — see `PANEL-BLUEPRINT.md`.
**Feature:** Visual Page Builder, Part 1 (Builder Page Shell). Roadmap: `roadmap-page-builder.md` (v4), §4 row 1 and §5.1.
**Deliverable of this part:** the builder *screen* — its entry points, two-column layout, title input, Save / Preview / Publish actions, the `load.php` / `save.php` client wiring, the builder asset tags, and the documented JS seam that Part 2 plugs its canvas into. **No block rendering of any kind.**

---

## 0. State inherited from Part 0 (read this first)

Part 0 shipped and its changelog (`changelogs/changelog-part-0-foundation.md`, reproduced in roadmap §9) reports no defect that blocks this part. **No fix-only prompt is required before Part 1**, and you must not "repair" anything from Part 0.

Five facts from that changelog change how you write this part. They are not optional trivia:

1. **`requireLogin()` does not answer JSON 403 — it sends `header('Location: /login.php'); exit;`.** An expired session therefore makes `fetch()` follow a 302 and receive **HTML**, not JSON. Your client code must detect this (see §9.3) instead of blowing up in `JSON.parse`.
2. **The two endpoint copies are byte-identical via a walk-up idiom**, not via a relative-path trick: both `api/builder/*.php` and `public/api/builder/*.php` walk up from `__DIR__` until `config/database.php` is found. The `api/media/upload.php` "byte-identical idiom" the Part 0 prompt referred to **does not exist**. Use the same walk-up idiom for the module screen copies (§5).
3. **A blank canvas from `load.php?...&new=1` has `title: ''`, `slug: null`, `status: 'draft'`, `published_at: null`, `updated_at: null`, `updated_at_jalali: '-'`, `id: null`.** Your UI must render all of those without printing `null` on the screen.
4. **`BuilderTreeValidator` caps its returned `errors` list at 100 entries**; `save.php` still puts the first error in `message`. Your error display must read `message`, and may show `errors.length` as a count — never assume the list is complete.
5. **`save.php` on an update ignores a posted `buildable_type` that contradicts the stored one** (and flags it in `buildable_type_ignored`), but **rejects a posted `template_id` that differs from the stored one with HTTP 400**. Always post back the values the canvas already has.

Part 0's shipped interface that this part consumes (authoritative source: roadmap §6 and §9):

- `BuilderAssets::clientScripts(): string[]` → `/assets/js/builder/block-registry.js`, `/assets/js/builder/builder-core.js`, then every `/assets/js/builder/types/*.js` sorted alphabetically.
- `GET /api/builder/load.php` — `?canvas_id=N` | `?buildable_type=page|template&new=1` (+ optional `template_id`) | `?buildable_type=template&template_id=N`. Gate: `pages.view`. No CSRF.
- `POST /api/builder/save.php` — JSON body `{canvas_id?, buildable_type, template_id?, title, status?, tree}`, max 2 MB, CSRF via `X-CSRF-TOKEN` header or body `csrf_token`. Gate: `pages.edit`, plus `templates.edit` when the canvas is a template.
- `window.BuilderCore` — `createUid()`, `createNode(type)`, `createColumnContainer(2..4)`, `defaultTree()`, `walkTree(tree, cb)`, `findByUid(tree, uid)`.
- `window.BuilderRegistry` — `registerBlockType`, `getBlockType`, `getAllBlockTypes`, `getInsertableBlockTypes`, `hasBlockType`.
- Node shape: `{"uid": "<32 hex>", "type": "<registered>", "data": <object>, "children": [<nodes>]}`.
- Canvas shape: `{id, buildable_type, template_id, title, slug, status, published_at, updated_at, updated_at_jalali}`.

---

## 1. Read before you write anything

1. `PANEL-BLUEPRINT.md` — §3 (tech stack), §4 (permissions), §5 (folder layout), §8 (shared components: `header.php` / `sidebar.php` / `footer.php`), §9 (endpoint conventions), §11 (security), §12 (UI/UX), §13 (localization), §17 (naming). All of it is binding here.
2. `roadmap-page-builder.md` — §4 row 1, §5.1, §6, and §9 (the Appendix, which holds Part 0's master prompt and changelog).
3. The actual existing code, specifically:
   - `modules/pages/index.php` **and** `public/modules/pages/index.php` — the two files you are replacing. Read both, and note exactly how each resolves its includes today.
   - `modules/media/index.php` — the closest existing example of a screen that does real AJAX work; copy its structural idioms (`$currentModule` / `$pageTitle` / `$breadcrumb`, header/footer includes, Swal usage, CSRF plumbing). Do not copy its media logic.
   - `includes/header.php`, `includes/sidebar.php`, `includes/footer.php` — what the shell already emits, which globals it expects to be set before the include, and where `</body>` is closed.
   - `includes/csrf.php` — the **real** function names for producing a token. Do not assume `csrf_token()` exists with that exact name until you have read it.
   - `includes/permissions.php` — the real signatures of `hasPermission()` / `requirePermission()`.
   - `includes/functions.php` — `e()`, `toPersianDigits()`, `toJalaliDateTime()`.
   - `public/assets/js/admin.js` — whether it already exposes a CSRF-header helper and a Persian-digit helper, and under what names.
   - `includes/builder/builder_assets.php` and both builder endpoints — you are calling them, so know their exact output.
4. `config/config.php` — for `BASE_PATH` / `PUBLIC_PATH`.

**Blueprint conflict you must not trip over.** Blueprint §7.2 describes `pages` as an empty placeholder with no block-based editor ("Do not invent any"), and blueprint §15 Phase 10 and the last box of §16 repeat that. Those sentences describe the base panel *before* the builder and are **deliberately superseded by this part, for the `pages` module only** — this part is the "future visual page builder" that blueprint §7.2 itself points to as "specified in its own document". Do not stop, do not ask, and do not edit the blueprint. `articles` is unchanged (§3 rule 6). Record in your changelog that blueprint §7.2, §15 Phase 10 and the last §16 item are now stale for `pages`; updating the blueprint is a separate documentation change request, not this part.

If anything above is missing or behaves differently from what this document claims, **stop, do not guess, record the discrepancy in your changelog (§18), and implement against the code that actually exists.**

---

## 2. What this part is

This part turns the empty `"ساخت سایت"` placeholder into a working builder screen — but a screen with a **hole in the middle**. The hole is the canvas, and Part 2 fills it.

Concretely, after this part:

- Opening `"ساخت سایت"` loads the canvas of the **active site template** from `load.php` and shows its title, status, and metadata.
- The owner can rename the canvas, save it, publish it, and unpublish it. The tree round-trips through the shell untouched.
- A block tree that already exists in the database is loaded into memory and posted back byte-for-byte on save — it is *not* drawn.
- Every builder JS file that exists (now, or after Parts 4–6 drop new ones in) is on the page, in the right order, without this file ever being edited again.
- Part 2 activates the canvas by calling **one** documented function (§8). It will not need to modify a single line you write.

That last point is the hard requirement of this part. If a design choice you are about to make would force Part 2, 3, 4, 5, 6, or 7 to edit a file created here, the choice is wrong.

---

## 3. Must NOT do

| # | Do not | Why |
|---|---|---|
| 1 | Do not render the block tree, or create any DOM for any block | Part 2 owns canvas rendering; the roadmap's Part 1 row says so explicitly |
| 2 | Do not build a per-block toolbar, a settings panel, or a "+" insert menu | Parts 2 and 3 |
| 3 | Do not register any block type, and do not add a file to `public/assets/js/builder/types/` | Parts 4–6 |
| 4 | Do not modify **any** file Part 0 created: `includes/builder/*`, `api/builder/*`, `public/api/builder/*`, `public/assets/js/builder/block-registry.js`, `public/assets/js/builder/builder-core.js`, `database/builder-schema.sql`, or the builder banner in `database/schema.sql` | A later part never edits an earlier part's files. If you believe one is wrong, report it in your changelog instead of fixing it |
| 5 | Do not add a third builder endpoint — no `list.php`, `delete.php`, `publish.php`, `duplicate.php` | `save.php` already creates, updates, publishes and unpublishes. A canvas *list* is real missing work, but it is not this part (§17) |
| 6 | Do not touch `modules/articles/index.php` or `public/modules/articles/index.php` | `"صفحات ساخته شده"` stays an empty placeholder until the list screen is scoped (§16 item 3, §17) |
| 7 | Do not modify `api/templates/activate.php` | The `site_name` sync gap is a base-panel Change Request (roadmap §8 item 2) |
| 8 | Do not modify `includes/header.php`, `sidebar.php`, `footer.php`, `csrf.php`, `auth.php`, `permissions.php`, `functions.php`, or `public/assets/css/style.css` | Shared files; the builder ships its own CSS and reads what it needs (§12) |
| 9 | Do not extend `media_usage.usable_type` and do not write a `media_usage` row | Part 4 owns that |
| 10 | Do not read or write the legacy `pages`, `page_blocks`, `articles`, `article_blocks`, `article_categories` tables | The builder uses `builder_canvases` / `builder_blocks` only; the legacy tables stay at zero rows |
| 11 | Do not "fix" the dashboard `"تعداد ساخت سایت"` card | Known base-panel CR (roadmap §8 item 1); flag it, do not touch it |
| 12 | Do not add SortableJS, Swiper.js, a rich-text editor, a frontend framework, jQuery, or any npm/build step | Blueprint §3; SortableJS arrives in Part 2 and Swiper.js in Part 5 |
| 13 | Do not implement autosave, undo/redo, revisions, or drafts-in-localStorage | Not in this part's scope, and a full-tree replace makes autosave actively dangerous before Part 2 exists |
| 14 | Do not implement the `site_name` write-through in this part | It lives in Part 0's `save.php` / repository and fires only for the active template. You only post `title` (roadmap §5.1) |
| 15 | Do not hard-code the builder `<script>` list | Loop `BuilderAssets::clientScripts()`; a hard-coded list forces every later part to edit this file |
| 16 | Do not invent Persian strings | Every user-facing string you need is given verbatim in §13 |
| 17 | Do not delete or modify `public/assets/js/block-editor.js` | Legacy dead code; a later part decides its fate |

---

## 4. Language and library decisions (fixed — do not reconsider)

| Concern | Decision |
|---|---|
| Screen code | PHP 8, procedural, exactly as the other module screens are written. No class, no new `includes/` file |
| Client code | One vanilla ES5-compatible JS file, IIFE, **exactly one new global** (`window.BuilderShell`). `fetch` for both endpoints. No build step, no bundler, no npm |
| Styling | One new CSS file of your own (§12). Bootstrap 5.3.3 RTL utilities and grid (already loaded by the shell) for layout; custom CSS only for what Bootstrap cannot express |
| Dialogs | SweetAlert2 v11 (`Swal.fire`), already loaded by `includes/footer.php` — blueprint §12. Never `window.alert` / `window.confirm` |
| Icons | Bootstrap Icons 1.11.3 classes, already loaded |
| Third-party libraries added by this part | **None** |
| Transport | `fetch` with `credentials: 'same-origin'`; `GET` for load, `POST` + `Content-Type: application/json` for save |
| CSRF | `X-CSRF-TOKEN` request header, token rendered into the page by PHP (§9.2) |
| Numbers / dates | Persian digits everywhere (blueprint §13). PHP-rendered values use `toPersianDigits()`; values updated by JS after a save use a private digit helper inside the IIFE (or `admin.js`'s helper if one already exists — read it first) |

---

## 5. File and folder map

Create/modify exactly these files. Nothing else.

```text
panel/
  modules/
    pages/
      index.php                                 # REWRITTEN: the builder shell screen (was an empty placeholder)
  public/
    modules/
      pages/
        index.php                               # REWRITTEN: served copy, byte-identical to modules/pages/index.php
    assets/
      css/
        builder/
          10-shell.css                          # NEW: builder shell layout, side column, states, dark-mode overrides
      js/
        builder/
          builder-shell.js                      # NEW: window.BuilderShell — load/save wiring and the Part 2 seam
```

Rules that go with this map:

- **The two `index.php` copies must be byte-identical.** Use the same walk-up idiom Part 0 used in its endpoints: from `__DIR__`, walk up until `config/database.php` is found, and derive `BASE_PATH` from there. Verify with `sha256sum` (or equivalent) and state both hashes in your changelog.
- **`builder-shell.js` does NOT go in `public/assets/js/builder/types/`.** It is not a block type. `BuilderAssets::clientScripts()` will not return it, and you must not try to make it do so (that file is Part 0's). The screen emits it with its own explicit `<script>` tag, **after** the `clientScripts()` loop (§7.4).
- **CSS files are numbered.** `10-shell.css` is this part's. Later parts drop `20-canvas.css`, `30-toolbar.css`, etc. into the same folder; the numeric prefix is what gives them deterministic cascade order under an alphabetical glob (§12).
- No new file in `includes/`. The screen is self-contained so the served copy stays a true byte-for-byte mirror.

---

## 6. Entry points and URL contract

`modules/pages/index.php` is the single builder screen. Which canvas it opens is decided **server-side** from the query string, and the result is handed to JS as data attributes — the client never parses the query string itself.

| URL | Opens | Notes |
|---|---|---|
| `/modules/pages/index.php` (no parameters) | The canvas of the **active site template** | The default entry from the `"ساخت سایت"` sidebar link. Resolve `active_template_id` from `site_settings` (`SELECT active_template_id FROM site_settings WHERE id = 1`), then load with `?buildable_type=template&template_id={id}` |
| `?canvas_id=N` (N > 0) | That canvas | Used after a save (`history.replaceState`) and by any future list screen |
| `?buildable_type=page&new=1` | A blank, unsaved page canvas | The only way to create a page canvas in this part |
| `?buildable_type=template&template_id=N` (N > 0) | That template's canvas (blank when none exists yet) | Explicit form of the default entry |

Server-side rules for this screen:

1. `requirePermission('pages','view')` before any work — same gate as `load.php`.
2. Validate the parameters yourself before building the load URL. Any combination outside the table above (`canvas_id` not a positive int, `buildable_type` not `page`/`template`, `template_id` missing for the template form, `new` present with `canvas_id`) renders the **invalid-request state** (§7.5) — a Persian card, HTTP 200, no crash, no JSON.
3. For the no-parameter entry, when `site_settings.active_template_id` is `NULL`, render the **no-active-template state** (§7.5) with a link to `"قالب سایت"` and **do not** initialise the builder at all.
4. The screen itself performs **no** canvas query. It resolves the active template id only (one prepared `SELECT`), then builds the `load.php` URL. Everything else comes from the endpoints, so load has exactly one code path (§16 item 4).
5. Set `$currentModule = 'pages';`, `$pageTitle` and `$breadcrumb` to `"ساخت سایت"` before including the header, exactly as the other module screens do.

---

## 7. Screen layout

### 7.1 Structure

Below the shared header, inside the module's normal content container:

```text
<div id="builder-root" data-*>                 ← the descriptor element; all data-* attributes live here
  <div class="row g-3">
    <div class="col-12 col-lg-9">              ← ~75% main column
      <div class="card builder-title-card">    ← the title input + optional template hint
      <div class="card builder-canvas-card">
        <div id="builder-canvas" class="builder-canvas"></div>       ← Part 2's mount point. Empty in this part.
        <div id="builder-canvas-state" class="builder-canvas-state"> ← loading / error / no-renderer state
      </div>
    </div>
    <div class="col-12 col-lg-3">              ← ~25% side column, sticky on lg and up
      <div class="card builder-box-publish">   ← "انتشار"
      <div class="card builder-box-meta">      ← "مشخصات بوم"
    </div>
  </div>
</div>
```

The two-column split is the roadmap's ~70–75 / ~25–30 layout (§3 of the roadmap). Use the Bootstrap grid (`col-lg-9` / `col-lg-3`) so it collapses to a single stacked column below the `lg` breakpoint; the side column is `position: sticky` from `lg` upward only.

### 7.2 Title card

- `<label>` `"عنوان"`, one `<input type="text" id="builder-title" maxlength="255">` with placeholder `"عنوان بوم را بنویسید"`.
- Rendered value: the canvas title, `e()`-escaped. A blank canvas gives `''` — render an empty input, never the string `null`.
- **Template hint (client-side, one code path for every entry).** The hint element is always present in the markup, inside `.form-text` below the input, with the `hidden` attribute set and the text `"این عنوان روی نام سایت هم اعمال می‌شود"`. `builder-shell.js` un-hides it after every successful load and every successful save **only when** the normalised canvas (§9.2) has `buildable_type === 'template'` **and** `Number(canvas.template_id) === Number(data-active-template-id)` **and** that number is greater than 0; in every other case it stays hidden. This mirrors exactly when Part 0's write-through fires (roadmap §5.1); showing it anywhere else would be a lie to the user. It is decided on the client for **every** entry, not only `?canvas_id=N`, because this screen performs no canvas query (§6 rule 4) and therefore cannot know a canvas's type at render time for that entry, and one code path is easier to verify than two. Compare with `Number(...)`: the JSON canvas carries an integer while a `data-*` attribute is always a string, so `===` on the raw values is always false.
- When the user has no edit right (§11), the input is `disabled`.

### 7.3 Canvas card

- `#builder-canvas` is **empty** in this part. Do not put anything inside it and do not style its children — Part 2 owns everything under it.
- `#builder-canvas-state` renders exactly one of:
  - **loading** (initial): a Bootstrap spinner plus `"در حال بارگذاری بوم..."`;
  - **load error**: `"خطا در بارگذاری بوم"`, plus the server's `message` when the response carried one, plus a `"تلاش دوباره"` button that re-runs the load;
  - **loaded, no renderer installed**: `"بوم بارگذاری شد — نمایش و ویرایش بلاک‌ها در بخش بعدی اضافه می‌شود."`
- The moment a canvas controller is installed (§8), `#builder-canvas-state` is emptied and hidden, and never shown again except on a load error.

### 7.4 Asset tags

At the end of the screen's own markup, **before** including `includes/footer.php`:

1. `<link rel="stylesheet" href="/assets/css/builder/{file}">` for every file returned by globbing `PUBLIC_PATH . '/assets/css/builder/*.css'`, sorted alphabetically (§12).
2. `<script src="{path}"></script>` for every path returned by `BuilderAssets::clientScripts()`, in the order returned. No `defer`, no `async`, no `type="module"` — execution order must be exactly the array order, because `block-registry.js` has to run before any `types/*.js`.
3. `<script src="/assets/js/builder/builder-shell.js"></script>` last.

Emitting these before the footer means SweetAlert2 and `admin.js` are still loading when `builder-shell.js` is parsed. That is fine and intentional: `builder-shell.js` must only *reference* `Swal` inside event handlers and callbacks, never at parse time. Do not restructure the page to load the footer first.

Append a cache-busting query (`?v=` + the file's `filemtime()`) to each builder asset URL. Without it, an agent-built change to a cached JS file is invisible on the next reload and costs an hour of false debugging.

### 7.5 Blocking states

Two states replace the whole builder (no title card, no side column, no builder scripts):

- **No active template** (no-parameter entry, `active_template_id IS NULL`): a centred card with `"هنوز قالبی فعال نیست"`, the line `"برای ساخت سایت ابتدا یک قالب را فعال کنید."`, and a link button `"رفتن به قالب سایت"` pointing at the templates module.
- **Invalid request** (§6 rule 2): a centred card with `"درخواست نامعتبر است"` and the line `"نشانی صفحه‌ساز درست نیست. از منوی «ساخت سایت» دوباره وارد شوید."`

Both render the normal panel shell (header, sidebar, footer) around them.

---

## 8. The `window.BuilderShell` contract (the seam Part 2 plugs into)

This is the single most important output of this part. Part 2's master prompt will be written against these exact signatures, so implement them exactly as written and report any deviation loudly in your changelog.

```js
window.BuilderShell = {
  getCanvas: function () {},          // → a copy of the canvas object (§0), never the internal reference
  getTree: function () {},            // → the tree that would be posted right now (controller's when installed)
  setCanvasController: function (controller) {},
  isDirty: function () {},            // → bool
  markDirty: function () {},
  markClean: function () {},
  canEdit: function () {},            // → bool; false in read-only mode (§11)
  save: function (options) {}         // → Promise, see §10
};
```

### 8.1 `setCanvasController(controller)`

`controller` is a plain object with exactly two functions:

```js
{
  render: function (mountEl, tree, canvas) {},  // mountEl is the #builder-canvas element
  getTree: function () {}                       // → the current tree, in the §0 node shape
}
```

Rules, all mandatory:

- Throws `Error` when `controller` is not an object, when either function is missing or not a function, or when a controller has **already** been installed. One controller per page, no silent overwrite.
- **Order-independent.** Part 2's file may load before or after the canvas finishes loading. The shell tracks two facts — "controller installed" and "tree loaded" — and calls `controller.render(mountEl, tree, canvas)` exactly once, as soon as both are true. Never call `render` twice, and never call it with a `null` tree.
- Once installed, `BuilderShell.getTree()` returns `controller.getTree()`. Before installation it returns the shell's own copy of the loaded tree.
- Installing a controller empties and hides `#builder-canvas-state` (§7.3).
- If `controller.getTree()` throws or returns anything that is not an array at save time, the save is aborted with the error dialog `"خطا در خواندن محتوای بوم"` and nothing is posted. A broken renderer must never overwrite a good canvas with garbage.

### 8.2 Dirty state

- `markDirty()` sets the flag, enables the `beforeunload` guard, and shows `"ذخیره نشده"` in the publish box.
- `markClean()` clears it; a successful save calls it for you.
- Typing in the title input calls `markDirty()` automatically. Part 2 calls it for canvas edits.

### 8.3 Events

Dispatch these on `document` with `CustomEvent`, so any later part can observe the shell without touching it:

| Event | `detail` | When |
|---|---|---|
| `builder:loaded` | `{ canvas, tree }` | After a successful load, **before** `controller.render()` is called |
| `builder:saved` | `{ canvas, block_count, buildable_type_ignored }` | After a successful save and after the side column is updated |
| `builder:error` | `{ stage: 'load'\|'save', status: <int\|0>, message: <string> }` | After any failed load or save, after the dialog is shown |

### 8.4 Globals

`builder-shell.js` defines `window.BuilderShell` and nothing else. No helper globals, no `window.__builder*`. Everything else lives inside the IIFE.

---

## 9. Load flow

### 9.1 Descriptor

PHP renders these attributes on `#builder-root` (all values `e()`-escaped):

| Attribute | Value |
|---|---|
| `data-load-url` | The fully-built `load.php` URL for this entry (§6) |
| `data-save-url` | `/api/builder/save.php` |
| `data-csrf-token` | The session CSRF token, read from whatever `includes/csrf.php` actually exposes |
| `data-can-edit` | `"1"` or `"0"` (§11) |
| `data-login-url` | The login page URL, for the expired-session path (§9.3) |
| `data-can-edit-templates` | `"1"` or `"0"` — `hasPermission('templates','edit')`; always rendered (§11) |
| `data-active-template-id` | `site_settings.active_template_id` as a decimal string, or `""` when it is null; always rendered (§7.2) |
| `data-entry-type` | `"template"` for the two template entries, `"page"` for `?buildable_type=page&new=1`, `""` for `?canvas_id=N` |
| `data-entry-template-id` | The template id for the two template entries (the resolved active id for the no-parameter entry), `""` otherwise |

Reading the CSRF token from a data attribute rather than from `admin.js` internals is deliberate (§16 item 5): it makes the screen correct regardless of how the global helper happens to be written, and it keeps this part from depending on a file it is forbidden to modify.

### 9.2 Steps

1. On `DOMContentLoaded`, show the loading state and `fetch(data-load-url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })`.
2. On HTTP 200 with `success: true`: normalise `data.canvas` (paragraph below the steps), store it and `data.tree` internally, paint the title input (only if the user has not already typed — the input keeps the server value; it is painted once, before any interaction), paint the side column (§10.4), evaluate the template hint (§7.2), dispatch `builder:loaded`, then run the render-when-both-ready rule from §8.1.
3. On any failure: render the load-error state with the server's `message` when one was parsed, and dispatch `builder:error` with `stage: 'load'`.

**Normalising an unsaved canvas (mandatory).** The Part 0 changelog documents what `load.php` puts in `title`, `slug`, `status`, `published_at`, `updated_at` and `updated_at_jalali` for a blank canvas, but it does **not** state what it puts in `buildable_type` and `template_id` for a blank *template* canvas. Do not assume. Right after a successful load, when `canvas.id === null`: if `canvas.buildable_type` is empty, use `data-entry-type`; if `canvas.template_id` is null or empty, use `data-entry-template-id` (as an integer, or `null` when that attribute is `""`). From then on every part of this file — the hint, the read-only re-evaluation (§11), the save body (§10.1 step 5), `BuilderShell.getCanvas()` — reads the normalised canvas, never `location`. Record in your changelog what `load.php` actually returned for those two fields on a blank template canvas. When the canvas already has an id, none of this applies and the stored values are used untouched.

### 9.3 Session-expiry handling (mandatory)

Because `requireLogin()` redirects instead of returning JSON (§0 item 1), every `fetch` in this file must:

1. Check `response.redirected === true`, **or** a `Content-Type` that is not `application/json`, **or** a body that fails to parse as JSON.
2. In any of those cases, treat it as an expired session: show `Swal.fire` with title `"نشست منقضی شده"` and text `"لطفاً دوباره وارد شوید."`, and on confirm send the browser to `data-login-url`.
3. Never let a `JSON.parse` exception surface as an unhandled rejection.

---

## 10. Save / Preview / Publish

### 10.1 `save(options)`

`options` is optional: `{ status: 'draft'|'published' }`. Absent `status` means "keep the canvas's current status" — and for a blank canvas that is `'draft'`.

Steps, in order:

1. If `canEdit()` is false → reject, no request. (The buttons are not rendered in that mode anyway; this is the programmatic guard.)
2. If a save is already in flight → reject immediately. One concurrent save, never two.
3. Read and `trim()` the title. Empty → `Swal.fire` error `"عنوان بوم را بنویسید"`, focus the input, abort. Longer than 255 characters → `"عنوان نباید بیشتر از ۲۵۵ نویسه باشد"`, abort. (The server enforces both too; this is only to save a round-trip.)
4. `tree = BuilderShell.getTree()`; apply the §8.1 guard.
5. Build the body: `{ canvas_id, buildable_type, template_id, title, status, tree }` — `canvas_id` is the stored id or `null`, and `buildable_type` / `template_id` are **the stored values** of the normalised canvas (§9.2), never re-derived from the URL (§0 item 5).
6. Disable all three action buttons and show a spinner in the pressed one.
7. `POST` to `data-save-url` with `Content-Type: application/json` and `X-CSRF-TOKEN: <data-csrf-token>`, `credentials: 'same-origin'`, body `JSON.stringify(...)`. Do not send `csrf_token` in the body as well — one mechanism, the header.
8. Apply the §9.3 expired-session check.
9. **On success:** replace the internal canvas with `data.canvas`, store `data.block_count`, repaint the side column and the title input, re-evaluate the template hint (§7.2), `markClean()`, and — when the canvas id was `null` before this save — `history.replaceState(null, '', '?canvas_id=' + id)` so a reload edits the canvas instead of creating a second one. Then dispatch `builder:saved` and show the success toast using the **server's** `message`.
10. **On failure:** show `Swal.fire` error with title `"خطا"` and the server's `message`. When `errors` is a non-empty array, append `"(" + count + " خطا)"` with the count in Persian digits. Dispatch `builder:error` with `stage: 'save'`.
11. `finally`: re-enable the buttons and clear the in-flight flag — including on every early abort above.

Step 9's `replaceState` is not cosmetic: without it, a second save of a freshly-created **template** canvas posts `canvas_id: null` again and earns HTTP 409 `"برای این قالب قبلاً یک بوم ساخته شده است"`, and a page canvas silently duplicates. Treat it as a correctness requirement, not polish.

### 10.2 The three buttons

| Button | Label | Class | Action |
|---|---|---|---|
| Save | `"ذخیره"` | `btn btn-primary` | `save()` — status unchanged |
| Preview | `"پیش‌نمایش"` | `btn btn-outline-secondary` | `save()`, then §10.3 |
| Publish / Unpublish | `"انتشار"` when status is `draft`; `"لغو انتشار"` when it is `published` | `btn btn-success` / `btn btn-outline-danger` | `save({status:'published'})` / `save({status:'draft'})` |

The publish button's label and class are recomputed from the canvas status after every load and every save. `Ctrl+S` / `Cmd+S` triggers Save and calls `preventDefault()` — a small, deliberate addition (§16 item 6).

### 10.3 Preview, with Part 7 deferred

Part 7 (the renderer) does not exist, so there is nothing to preview yet. Do **not** ship a dead button and do **not** ship a disabled one that Part 7 would have to come back and enable — that would mean editing this file later.

Implement it as a hook:

```js
// after a successful save():
if (typeof window.BuilderPreview === 'function') {
    window.BuilderPreview(BuilderShell.getCanvas());
} else {
    // Swal info
}
```

The info dialog: title `"پیش‌نمایش"`, text `"تغییرات ذخیره شد. نمایش سایت هنوز ساخته نشده است."`. When Part 7 later defines `window.BuilderPreview`, the button lights up with **zero** edits to this part. Record this contract in your changelog so Part 7's prompt can be written against it.

### 10.4 Side column contents

**Box `"انتشار"`:**

| Row | Value |
|---|---|
| `"وضعیت"` | badge `"منتشر شده"` (`bg-success`) or `"پیش‌نویس"` (`bg-secondary`); plus `"ذخیره نشده"` (`bg-warning`) beside it while dirty |
| `"آخرین به‌روزرسانی"` | `canvas.updated_at_jalali`, or `"—"` when it is `null` or `'-'` |
| — | the three buttons from §10.2 |

**Box `"مشخصات بوم"`:**

| Row | Value |
|---|---|
| `"نوع"` | `"قالب سایت"` when `buildable_type === 'template'`, `"صفحه"` when `'page'` |
| `"نشانی"` | `canvas.slug`, or `"—"` when null |
| `"تعداد بلاک‌ها"` | Persian digits. Before the first save, compute it client-side by counting nodes with `BuilderCore.walkTree()`; after a save, use `data.block_count`. Counting nodes is not rendering them — this stays within §3 rule 1 |
| `"شناسه"` | Persian digits, or `"—"` when the canvas is unsaved |

Every one of these must be updatable by JS after a save, so give each value element an id.

---

## 11. Permissions and read-only mode

The screen's own gate is `requirePermission('pages','view')`. Editing is a separate, finer question, and the screen must match `save.php`'s gate exactly so no user ever meets a surprise 403:

```
canEdit = hasPermission('pages','edit')
          && ( buildable_type !== 'template' || hasPermission('templates','edit') )
```

Because `buildable_type` is known server-side only for the *entry* (template entries always are; `?canvas_id=N` is not), resolve it like this: for the template entries, evaluate the full expression; for `?canvas_id=N`, evaluate `hasPermission('pages','edit')` server-side, and then, once `load.php` returns a canvas with `buildable_type === 'template'`, re-evaluate client-side against a second data attribute `data-can-edit-templates` (`"1"`/`"0"`) that PHP always renders. If the re-evaluation turns `canEdit` false, switch to read-only mode at that moment.

Read-only mode: the title input is `disabled`, all three action buttons are **absent from the DOM** (not hidden with CSS — blueprint §4 is explicit that forbidden controls are absent), and an `alert alert-info` at the top of the main column reads `"شما اجازه‌ی ویرایش این بوم را ندارید."`. `BuilderShell.canEdit()` returns `false` and `save()` rejects.

---

## 12. CSS

Create `public/assets/css/builder/10-shell.css`. Requirements:

- Define the builder's own CSS custom properties on `#builder-root` (surface, border, muted text, canvas background), give light-mode values there, and override them under `body.dark-mode #builder-root`. Do not restate hard-coded colours throughout the file, and do not touch `style.css` or `dark-theme.css`.
- Everything is RTL-first: use `margin-inline-start` / `padding-inline-end` / `inset-inline-*` rather than left/right, so nothing has to be mirrored later.
- `#builder-canvas` gets a minimum height (about `60vh`) and the canvas surface background, so the empty canvas card does not collapse into a strip before Part 2 exists.
- The side column is `position: sticky` at `lg` and above only, and static below it. Its offset comes from one custom property, `--builder-sticky-top`, defined on `#builder-root`. Set it to the **actual rendered height of the panel's top header plus 16px**: read that height from the header's real markup and CSS (`includes/header.php`, `public/assets/css/style.css` — read-only) instead of guessing, and state the value and where you found it in your changelog. If the header is not sticky or fixed and scrolls away with the page, the value is simply `16px`.
- Style **no** selector that starts with a block class (`.builder-block*`, `.builder-toolbar*`, `.builder-settings*`, etc.). Those namespaces belong to Parts 2–6, and colliding with them now would silently break their styling later.

**The glob mechanism (this part's own, deliberately parallel to `BuilderAssets`):** the screen emits one `<link>` per file found by globbing `PUBLIC_PATH . '/assets/css/builder/*.css'`, sorted alphabetically. Parts 2–6 add stylesheets by dropping a numbered file into that folder — no edit to this screen, ever. This duplicates the pattern of `BuilderAssets::clientScripts()` on purpose: adding a `clientStyles()` method there would mean modifying a Part 0 file, which §3 rule 4 forbids. Document the `NN-name.css` naming convention in a comment at the top of `10-shell.css` so the next part follows it.

---

## 13. Persian strings (verbatim — invent nothing)

Copy these exactly, including every ZWNJ (`‌`). Do not normalise, do not re-spell, do not translate.

| Context | String |
|---|---|
| Page title / breadcrumb | `ساخت سایت` |
| Title label | `عنوان` |
| Title placeholder | `عنوان بوم را بنویسید` |
| Active-template hint | `این عنوان روی نام سایت هم اعمال می‌شود` |
| Canvas loading | `در حال بارگذاری بوم...` |
| Canvas load error | `خطا در بارگذاری بوم` |
| Retry button | `تلاش دوباره` |
| Loaded, no renderer | `بوم بارگذاری شد — نمایش و ویرایش بلاک‌ها در بخش بعدی اضافه می‌شود.` |
| Publish box title | `انتشار` |
| Status row label | `وضعیت` |
| Status: published | `منتشر شده` |
| Status: draft | `پیش‌نویس` |
| Unsaved marker | `ذخیره نشده` |
| Last update label | `آخرین به‌روزرسانی` |
| Save button | `ذخیره` |
| Preview button | `پیش‌نمایش` |
| Publish button | `انتشار` |
| Unpublish button | `لغو انتشار` |
| Meta box title | `مشخصات بوم` |
| Type label | `نوع` |
| Type: template | `قالب سایت` |
| Type: page | `صفحه` |
| Slug label | `نشانی` |
| Block count label | `تعداد بلاک‌ها` |
| Canvas id label | `شناسه` |
| Empty value | `—` |
| Empty title error | `عنوان بوم را بنویسید` |
| Title too long | `عنوان نباید بیشتر از ۲۵۵ نویسه باشد` |
| Broken renderer | `خطا در خواندن محتوای بوم` |
| Error dialog title | `خطا` |
| Success dialog title | `موفق` |
| Preview dialog title | `پیش‌نمایش` |
| Preview not available | `تغییرات ذخیره شد. نمایش سایت هنوز ساخته نشده است.` |
| Expired session title | `نشست منقضی شده` |
| Expired session text | `لطفاً دوباره وارد شوید.` |
| No active template title | `هنوز قالبی فعال نیست` |
| No active template text | `برای ساخت سایت ابتدا یک قالب را فعال کنید.` |
| Go to templates button | `رفتن به قالب سایت` |
| Invalid request title | `درخواست نامعتبر است` |
| Invalid request text | `نشانی صفحه‌ساز درست نیست. از منوی «ساخت سایت» دوباره وارد شوید.` |
| Read-only banner | `شما اجازه‌ی ویرایش این بوم را ندارید.` |

Success messages for saves come from the server response (`"بوم با موفقیت ذخیره شد"`), not from a string you hard-code. Same for every save error message.

---

## 14. Conventions you must honour (from the blueprint)

- Every dynamic value printed into HTML passes through `e()`. Never `e()` a value that goes into JSON.
- Every user-facing number passes through `toPersianDigits()` (PHP) or the JS equivalent; every date through `toJalaliDateTime()` — though here the server already returns `updated_at_jalali`, so use that rather than converting again.
- Every query is a PDO prepared statement. This screen runs exactly one query (the active-template lookup); it is still prepared.
- Every PHP file starts with `<?php` and has **no** closing `?>`.
- `snake_case` for PHP, `camelCase` for JS, `kebab-case` for asset filenames and CSS classes.
- RTL everywhere; Bootstrap 5.3.3 RTL utilities; Vazirmatn is already applied by the shell.
- Destructive or result dialogs use SweetAlert2, never `window.confirm` / `window.alert`.

---

## 15. Acceptance checklist

Verify each item yourself by execution and report the result of each in your changelog.

- [ ] `modules/pages/index.php` and `public/modules/pages/index.php` are byte-identical (`sha256` of both stated in the changelog) and both render correctly from their own depth.
- [ ] Opening `"ساخت سایت"` from the sidebar with an active template renders the builder, and the network tab shows exactly one `GET /api/builder/load.php?buildable_type=template&template_id={active}`.
- [ ] With `site_settings.active_template_id` set to `NULL`, the same entry renders the `"هنوز قالبی فعال نیست"` card, loads **no** builder script, and makes no request to `load.php`.
- [ ] `?canvas_id=abc`, `?canvas_id=0`, `?buildable_type=x&new=1`, and `?canvas_id=5&new=1` each render the `"درخواست نامعتبر است"` card with HTTP 200 and no PHP notice.
- [ ] The page's `<script>` tags for the builder match `BuilderAssets::clientScripts()` exactly, in the same order, followed by `builder-shell.js`. Prove the Lego property: drop a temporary `public/assets/js/builder/types/zz_dummy.js`, reload, confirm it appears in the HTML with **no edit** to any file; delete it and confirm it is gone.
- [ ] Same proof for CSS: drop a temporary `public/assets/css/builder/99-dummy.css`, reload, confirm the `<link>` appears after `10-shell.css`; delete it.
- [ ] A fresh template canvas (no row yet) loads with `id: null`, an empty title input, `"پیش‌نویس"`, `"—"` for slug/id, and block count `۳`.
- [ ] For that blank template canvas, the changelog states exactly what `load.php` returned in `buildable_type` and `template_id`, and the first save succeeds regardless (§9.2 normalisation). No 400, no 409.
- [ ] The template hint (§7.2) is visible on the active template's canvas for **both** the no-parameter entry and the `?canvas_id={that canvas id}` entry; it stays hidden on a non-active template's canvas and on a page canvas. Verify each of the four cases in the browser.
- [ ] Typing a title and pressing `"ذخیره"` creates the canvas, shows the server's success message, and the URL becomes `?canvas_id={id}` without a page reload.
- [ ] Pressing `"ذخیره"` a **second** time on that same canvas updates it — no HTTP 409, no duplicate row. Verify the row count in `builder_canvases` is still 1 for that template.
- [ ] After a save, the side column shows the returned id, slug, block count, and Jalali update time, all in Persian digits, with no page reload.
- [ ] Saving the active template canvas with the title `"سایت تستی"` sets `site_settings.site_name` to `"سایت تستی"` — and the screen contains **no code** that does this (it is Part 0's write-through). Restore the original site name afterwards.
- [ ] Saving a **non-active** template's canvas leaves `site_settings.site_name` unchanged.
- [ ] `"انتشار"` sets `status` to `published`, `published_at` is stamped once, the badge turns `"منتشر شده"`, and the button becomes `"لغو انتشار"`. Pressing it again returns the canvas to `"پیش‌نویس"` while `published_at` stays set.
- [ ] `"پیش‌نمایش"` saves and then shows the `"پیش‌نمایش"` info dialog. Defining `window.BuilderPreview = function (c) { console.log(c); }` in the console makes the same button call it instead — with no file edited.
- [ ] Submitting an empty title shows `"عنوان بوم را بنویسید"` and sends **no** request.
- [ ] A tree loaded from an existing canvas is posted back unchanged: save without touching anything, then confirm `builder_blocks` for that canvas has the same uid/type/parent/sort_order set as before (row-by-row check, not just a count).
- [ ] Round-trip integrity: a block whose `data` is `{}` and one whose `data` is `{"settings": {}, "slides": []}` survive a shell save unchanged in the raw `load.php` response (`{}` stays `{}`, `[]` stays `[]`). Use a throwaway type file, then delete it.
- [ ] Destroying the session (clear the cookie) and then pressing `"ذخیره"` shows the `"نشست منقضی شده"` dialog and redirects to login — no console exception, no `JSON.parse` error.
- [ ] Posting with a bogus CSRF token (edit `data-csrf-token` in devtools) shows the server's `"توکن نامعتبر"` message in the error dialog.
- [ ] A staff account with `pages.view` but without `pages.edit` sees the read-only banner, a disabled title input, and **no** action buttons in the DOM.
- [ ] A staff account with `pages.edit` but without `templates.edit` is read-only on the template canvas and editable on a `?buildable_type=page&new=1` canvas.
- [ ] `window.BuilderShell` is the only new global the page defines (compare `Object.keys(window)` before and after loading `builder-shell.js`).
- [ ] `BuilderShell.setCanvasController({render, getTree})` called from the console **before** the load finishes results in exactly one `render(mountEl, tree, canvas)` call after the load; calling it **after** the load also results in exactly one call, immediately. A second call throws.
- [ ] With a controller installed whose `getTree()` returns a rearranged tree, `"ذخیره"` posts that tree; with one whose `getTree()` throws, the save aborts with `"خطا در خواندن محتوای بوم"` and the database is unchanged.
- [ ] `markDirty()` shows `"ذخیره نشده"` and arms the leave-page warning; a successful save clears both.
- [ ] Dark mode (`body.dark-mode`) renders the builder legibly — no white-on-white card, no black-on-black text — and the layout stacks correctly at a 390 px viewport width.
- [ ] No file outside §5's map was created or modified. In particular `modules/articles/index.php`, every `includes/builder/*` file, both builder endpoints, both Part 0 JS files, `style.css`, and `api/templates/activate.php` are untouched (verify with `git status` or a hash comparison).

---

## 16. Deviations and decisions made by this prompt

Recorded so nobody re-derives or contradicts them later. These are **decisions, not suggestions**.

1. **The builder screen is `modules/pages/index.php` itself, not a new `builder.php`.** The roadmap calls Part 1 the "Builder Page Shell" and the blueprint reserves `"ساخت سایت"` for exactly this. A separate file would need a list screen in front of it to be reachable at all, and that list is not in this part's scope (item 3).
2. **The no-parameter entry opens the active template's canvas.** `"ساخت سایت"` means "build your site", and the site is the active template. This is the decision that makes the sidebar link functional on day one instead of landing on a chooser that does not exist yet.
3. **`"صفحات ساخته شده"` (`modules/articles/index.php`) is left untouched, and page canvases are reachable only by URL in this part.** A canvas list needs a list endpoint, which neither Part 0 nor this part is allowed to add, and a list screen is not "the builder shell". This is a real, known gap — see §17 item 1. It is being handed forward deliberately, not overlooked.
4. **The canvas is fetched client-side from `load.php`, not inlined server-side by calling the repository.** The roadmap's Part 1 row says "`load.php` wiring", and one asynchronous path serves both the first load and the retry-after-error path. The screen's only query is the active-template lookup.
5. **The CSRF token is rendered into `data-csrf-token` rather than taken from `admin.js`.** This part may not modify `admin.js`, and depending on an unread helper's internals is how a save silently 403s in production. One mechanism, the header, one source, the data attribute.
6. **Three small additions beyond the roadmap's literal text**, each called out rather than smuggled in: the `Ctrl+S` shortcut, the `beforeunload` guard for unsaved changes, and the `?v=filemtime` cache-buster on builder assets. Each is one small block of code and each prevents a class of bug (lost work, stale cached JS) that would otherwise be blamed on a later part.
7. **Publish is a two-state toggle (`"انتشار"` / `"لغو انتشار"`), not a one-way action.** Part 0's `save.php` already accepts `status: 'draft'` on a published canvas and never clears `published_at`, so unpublish costs nothing and its absence would be a hole with no workaround in the UI.
8. **Preview is a runtime hook (`window.BuilderPreview`), not a disabled button.** Part 7 is deferred; a disabled button would force Part 7 to edit this part's file to enable it, breaking the Lego rule. Until Part 7 ships, the button still does something real — it saves.
9. **Builder CSS is emitted by a glob over `/assets/css/builder/*.css` with `NN-` numeric prefixes, implemented in this screen.** `BuilderAssets` has no stylesheet counterpart and is a Part 0 file this part may not modify, so the same Lego mechanism is reimplemented here rather than having Parts 2–6 edit a shared stylesheet.
10. **Read-only mode is computed to match `save.php`'s exact gate**, including the client-side re-evaluation for `?canvas_id=N` entries where `buildable_type` is unknown until the load returns. A screen whose buttons promise more than the endpoint allows is worse than no buttons.
11. **The template hint is decided client-side for every entry** (§7.2), with `data-active-template-id` always rendered. The screen performs no canvas query (§6 rule 4), so for `?canvas_id=N` the server cannot know the canvas type at render time; a server-side hint for some entries and a client-side one for others would be two code paths for one rule.
12. **Blueprint §7.2 / §15 Phase 10 / §16 last item are declared superseded for `pages`** (§1), so the agent does not read "Do not invent any" as a prohibition on this very part.
13. **A blank canvas's `buildable_type` / `template_id` are normalised from the descriptor** (§9.2), because the Part 0 changelog does not document what `load.php` returns in those two fields for a blank template canvas.

---

## 17. Open items handed to a later part (do not build them now)

Record these in your changelog so they reach the roadmap's Appendix:

1. **No canvas list screen exists.** Page canvases created via `?buildable_type=page&new=1` can only be reopened by their `?canvas_id=N` URL. A list screen in `"صفحات ساخته شده"` plus the read-only list endpoint it needs should be scoped as its own part (or folded into Part 7's prompt) once Parts 2–4 make a page canvas worth listing.
2. **No delete path for a canvas.** `save.php` cannot delete, and this part adds no endpoint.
3. **`window.BuilderPreview` is the agreed hook name for Part 7.** Part 7 defines it; nothing else may.
4. **The two base-panel Change Requests from roadmap §8 remain open** — the dashboard `"تعداد ساخت سایت"` card still counts the legacy `pages` table, and `api/templates/activate.php` still does not sync a newly activated template's canvas title into `site_settings.site_name`. Both are untouched by this part, as required.

---

## 18. Mandatory final step

Once you finish everything above, write a complete changelog of this work: every file you created or modified, every decision you made that this master prompt left open (there should be very few — if you had to make one, say exactly what and why), anything in this master prompt that turned out to be wrong, missing, or impossible to follow exactly as written and what you did instead, the result of every item in the §15 acceptance checklist, and the exact current state of everything future parts will depend on — the final shape of `window.BuilderShell` (every method signature, the `setCanvasController` contract including its throwing conditions and the order-independent render rule, the three `document` events and their `detail` shapes), the final `data-*` descriptor attributes on `#builder-root`, the exact ids of the DOM elements Part 2 will attach to (`#builder-canvas` above all), the CSS folder/naming convention for later parts, and the `window.BuilderPreview` hook contract for Part 7. Save it as `changelogs/changelog-part-1-builder-shell.md`.
