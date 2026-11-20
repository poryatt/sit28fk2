# Master Prompt — Part 3: Visual Page Builder — Block Toolbar Interactions

**Target executor:** an AI coding agent (OpenCode or equivalent) with write access to the existing panel project.
**Project:** Website & Business Management Panel (PHP 8 + PDO + MySQL, Persian/RTL) — see `PANEL-BLUEPRINT.md`.
**Feature:** Visual Page Builder, Part 3 (Block Toolbar Interactions). Roadmap: `roadmap-page-builder.md` (v4), §4 row 3 and §5.3.
**Deliverable of this part:** two independent, generic UI mechanisms that hang off Part 2's per-block toolbar — (1) a settings-panel **container and lifecycle only** (no settings form of any kind), and (2) the "+" **insert dropdown** (reads the registry, lists types, inserts on click). **Zero knowledge of what any specific block type is or does.**

---

## 0. Before you start

### 0.1 Hard dependency — verify it, do not assume it

Confirm, by actually opening the files, that Part 2 shipped:

- `public/assets/js/builder/ui/00-sortable.js` exists (vendored SortableJS).
- `public/assets/js/builder/ui/10-canvas.js` exists and defines exactly one new global, `window.BuilderCanvas`.
- `public/assets/css/builder/20-canvas.css` exists.
- `changelogs/changelog-part-2-canvas-engine.md` exists.

If any of these is missing, **stop immediately and report it** — do not build Part 2's work, and do not work around a missing piece by guessing its shape from this prompt. This prompt is written against the *shipped* Part 2, not the Part 2 master prompt's original plan; where the two differ, the changelog is authoritative and this prompt already reflects it (§0.2).

### 0.2 State inherited from Parts 0–2 (read this first)

Read the real files: `changelogs/changelog-part-0-foundation.md`, `changelogs/changelog-part-1-builder-shell.md`, `changelogs/changelog-part-1-fix-ui-script-glob.md`, `changelogs/changelog-part-2-canvas-engine.md`. If any behaves differently from what this section claims, trust the file, not this prompt, and record the divergence in your own changelog rather than silently building against a stale assumption.

Facts that change how you write this part:

1. **Every content type is still a ghost.** Parts 4–6 have not shipped. `public/assets/js/builder/types/` holds only `.gitkeep`. `BuilderRegistry.getInsertableBlockTypes()` therefore returns `[]` today, and **no registered type has a `renderSettingsPanel`.** This is the normal environment for this part, not an edge case — §10 tells you how to test against it using temporary fixture types, exactly as Part 2 did with `zz_probe`/`zz_box`.
2. **The settings panel's open trigger is already decided, by Part 2, not by you.** Part 2's own master prompt decided this in advance (§15 decision 4: the panel opens from selection, never a fifth button), and Part 2's real, executed changelog reaffirms the same fact verbatim in its own "open items handed forward" (§16 item 3: `builder:selection-changed` is Part 3's panel-open trigger, no toolbar button for it). You are not free to add a button; you must use the event.
3. **`registerFormatCommand` / `setFormatState` are reserved for Part 4.** Do not touch them, do not build any UI for them — they belong to the format-icon group Part 2 already renders on text-capable blocks.
4. **No client-side structural registry exists yet.** `header`/`body`/`footer`/`column_container`/`column` are registered server-side only (Part 0). This does not block you — `getInsertableBlockTypes()` filters to `category === 'content'` regardless, so the five structural types could never appear in your menu even once a client mirror lands.
5. **Validator/rejection strings you may see bubble up from `BuilderCanvas.insertBlock()` use the tree validator's literal file strings**, which are **not** the ZWNJ-normalised versions printed in the roadmap's own tables (e.g. the file says `تعداد بلاکها بیش از حد مجاز است`, without ZWNJ). Never author your own version of these strings — display exactly whatever `Error.message` you catch, verbatim.
6. **CSS namespaces are already carved up.** Part 2's is `.builder-block*`, `.builder-toolbar*`, `.builder-canvas-*`, `.builder-col`, `.builder-children-horizontal`. Free for this part: **`.builder-settings*`, `.builder-insert*`.** Parts 4–6 get their own type-prefixed names. Never touch `style.css`, `dark-theme.css`, or anything outside your own namespaces.
7. **The `ui/NN-name.js` convention**: `00-` is vendored libraries, `10-canvas.js` is Part 2's engine. This part is next in line and uses the `20-` decade. Exactly one `window.*` global per file.
8. **Dark mode selector is `body.dark-mode #builder-root`**, overriding custom properties already defined there by `10-shell.css`: `--builder-surface`, `--builder-border`, `--builder-muted`, `--builder-canvas-bg`, `--builder-sticky-top`. Reuse them; do not redefine them; add your own only for needs those five don't cover, in the same pattern.
9. **`anchorEl` in `builder:insert-requested` is not always the same kind of element.** Part 2 dispatches it from both the toolbar's insert button (index = selected sibling + 1) and from inline `.builder-block-add` affordances (index = child count). Your dropdown must position against whichever element it is actually given, never assume it is the toolbar.
10. **Both new elements must be attached inside `#builder-root`, not to `document.body`.** Item 8 above only helps if the panel and the dropdown actually inherit `--builder-surface` / `--builder-border` / `--builder-muted` / `--builder-canvas-bg` / `--builder-sticky-top` — and CSS custom properties only cascade to *descendants* of the element that defines them. `#builder-root` is where `10-shell.css` defines all five; `document.body` is their ancestor, not their descendant, so anything appended directly to `body` sees none of them. Mount both new elements as the last child of `#builder-root` instead (§6.4, §7.4). Before relying on `position: fixed` from inside that subtree, grep `10-shell.css`, `style.css`, and the module's own markup for `transform`, `filter`, `perspective`, `contain`, or `will-change: transform` on `#builder-root` or any of its ancestors — any one of those turns the ancestor into the fixed element's containing block instead of the viewport, which would break the positioning in §6.4/§7.4 outright. Record what you found (or didn't) in your changelog; if one is present, fixed positioning must be re-derived against that ancestor instead of the viewport, not silently left broken.

### 0.3 Part 0 and Part 2 surface this part consumes

```js
// Part 0 — pure data/logic, no DOM
BuilderRegistry.getBlockType(type)            // definition object, or null
BuilderRegistry.getInsertableBlockTypes()     // array, category === 'content' only; [] today
BuilderRegistry.hasBlockType(type)            // bool
BuilderCore.createUid()                       // 32 lowercase hex
BuilderCore.createNode(type)                  // { uid, type, data, children }; throws on unknown type; data = defaultData()

// Part 2 — the canvas
BuilderCanvas.getSelected()                   // { uid, node, el } | null   (node/el are the live selection's snapshot)
BuilderCanvas.getNode(uid)                    // deep copy | null
BuilderCanvas.getBlockElement(uid)            // element | null
BuilderCanvas.getToolbarElement()             // the single floating toolbar element
BuilderCanvas.getCanvasElement()              // #builder-canvas
BuilderCanvas.insertBlock(node, parentUid, index?)   // → uid, or throws Error(message)
BuilderCanvas.updateBlockData(uid, dataObject)       // → bool; throws on non-object; marks dirty; re-runs that block's render()
```

Client registry definition shape (camelCase): `type`, `label`, `icon`, `category` (`structure`|`content`), `allowsChildren`, `minChildren`, `maxChildren`, `supportsTextFormatting`, `allowedChildTypes`, `allowedParentTypes`, `defaultData()`, optional `render(node, el)`, optional **`renderSettingsPanel(node, panelEl)`** — the hook this part's panel calls.

**Events on `document` this part listens to:**

| Event | `detail` | Notes |
|---|---|---|
| `builder:selection-changed` | `{ uid, node, el }` (nulls on clear) | Fires exactly once per actual change. `node` is a **deep copy**, not the live tree node. |
| `builder:insert-requested` | `{ parentUid, index, anchorEl }` | `parentUid` is always a real block, never a root slot. `anchorEl` is guaranteed to be in the document at dispatch time. |

The settings panel does **not** listen to `builder:tree-changed` or `builder:format` at all. The insert menu also never listens to `builder:format`, but **does** read `builder:tree-changed` narrowly — only to notice its own `anchorEl` left the DOM (§7.4, §12 decision 8). That one exception is scoped to the insert menu alone; it is not a "this part" blanket rule.

---

## 1. Read before you write anything

1. `roadmap-page-builder.md` — §3, §4 row 3, **§5.2 in full** (so you know exactly what the four toolbar controls are and where your two mechanisms attach), **§5.3 in full** (this part's own scope), §6, and §9's Appendix entries for Parts 0, 1, the Part 1 fix, and Part 2 — especially Part 2's own "Contract for future parts" section, which this prompt's §0 already summarises but does not replace.
2. `PANEL-BLUEPRINT.md` — §3 (tech stack), §8 (existing dropdown/modal/toast conventions), §12 (destructive-action confirms via SweetAlert2, toast copy, loading states), §13 (localisation).
3. The real code, all of it, before writing a line:
   - `public/assets/js/builder/ui/10-canvas.js` — the exact behaviour, return values, and thrown-error strings of every `BuilderCanvas` method in §0.3.
   - `public/assets/js/builder/block-registry.js` and `builder-core.js` — `getInsertableBlockTypes()`, `getBlockType()`, `createNode()`.
   - `public/assets/css/builder/10-shell.css` and `20-canvas.css` — the custom properties and the dark-mode block pattern. Reuse, do not redefine.
   - `modules/pages/index.php` — confirm the `ui/*.js` glob (§0.1) and the DOM around `#builder-canvas` / `#builder-root`.

If anything above behaves differently from what this document claims, stop, do not guess, record it in your changelog (§14), and implement against the code that actually exists.

---

## 2. What this part is

After this part:

- Selecting a block whose registered type defines `renderSettingsPanel` opens a single settings panel, calls that function once against a fresh, empty container, and closes again on deselection, on selecting a block with no settings, on its own close control, on an outside click, or on <kbd>Escape</kbd>.
- Clicking any insert affordance (the toolbar's "+" or an inline `.builder-block-add`) opens a small dropdown listing every currently **insertable** (content-category) block type by its registered Persian label and icon. Clicking an entry creates that type with its own `defaultData()` at the requested position and closes the dropdown.
- Neither mechanism knows, or ever needs to know, what any specific block type's content or settings actually are. Until Parts 4–6 ship, the insert dropdown is always empty (its explicit empty state) and the settings panel never opens for anything real — **that is correct, expected behaviour, not a bug**, exactly as Part 2 experienced with content rendering. §10 tells you how to prove the mechanisms work anyway, with temporary fixtures.

---

## 3. Must NOT do

| # | Do not | Why |
|---|---|---|
| 1 | Register any block type, or create a file in `assets/js/builder/types/` | Parts 4–6's job. Your temporary test fixtures (§10) are removed before you finish, never shipped |
| 2 | Build any actual settings **form** — any field, label, input, or validation for a real block type | §5.3: this part builds the container and lifecycle only |
| 3 | Hard-code the name, icon, or behaviour of any specific block type anywhere in either file | This part's entire value is that it works identically for a type that doesn't exist yet |
| 4 | Add a fifth toolbar button, or any trigger for the settings panel other than `builder:selection-changed` | Already decided by Part 2's own changelog (§0.2 item 2) — not open for reconsideration |
| 5 | Implement `registerFormatCommand`, `setFormatState`, or any text-formatting behaviour | Part 4's territory |
| 6 | Call `BuilderCanvas.markDirty()` yourself, anywhere | `insertBlock()` and `updateBlockData()` already call it internally; calling it again double-counts and breaks any future "exactly once" proof |
| 7 | Call `BuilderCanvas.clearSelection()` from either mechanism | Selection state is Part 2's exclusive domain; this part only *reacts* to it (§12 decision 5 explains the consequence) |
| 8 | Edit any Part 0 file (`includes/builder/*`, `api/builder/*`, both builder JS files, schema files), any Part 1 file (`modules/pages/index.php` + served copy, `builder-shell.js`, `10-shell.css`), or `10-canvas.js` / `20-canvas.css` | The Lego rule |
| 9 | Call `fetch`, touch `load.php` / `save.php`, or read/write the CSRF token | Neither mechanism talks to the server. Inserting a block only mutates the in-memory tree via `BuilderCanvas` |
| 10 | Style `.builder-block*`, `.builder-toolbar*`, `.builder-canvas-*`, `.builder-col`, `.builder-children-horizontal`, `style.css`, or `dark-theme.css` | Reserved namespaces (§0.2 item 6) |
| 11 | Implement cross-container drag, undo/redo, copy/paste, duplicate-block, or multi-select | Not in this part's scope, unchanged from Part 2's own deferrals |
| 12 | Extend `media_usage`, touch `api/templates/activate.php`, or fix the dashboard card | Base-panel CRs, roadmap §8, out of scope |
| 13 | Invent or alter a block type's `label` or `icon` string | Always read from the registry; never hard-coded, never re-worded |
| 14 | Add jQuery, a frontend framework, a build step, or any library beyond SweetAlert2 (already loaded globally) | Blueprint §3; §4 below fixes the one exception |
| 15 | Leave a stray fixture — a registered test type, a test canvas row, a leftover DOM node — behind after finishing | §10 and §11 require full cleanup, proven, not asserted |

---

## 4. Language and library decisions (fixed — do not reconsider)

| Concern | Decision |
|---|---|
| Code | **Two** vanilla JS files, each one IIFE, each **exactly one** new global: `window.BuilderSettingsPanel` and `window.BuilderInsertMenu`. Rationale: §5.3 itself describes these as two sub-mechanisms that "neither depends on the other" — splitting them keeps that true at the file level, matches Part 2's "one global per file" convention, and lets either be rebuilt later without touching the other (the Lego test) |
| Styling | **One** CSS file, `30-toolbar-interactions.css`, covering both mechanisms. Rationale: unlike the JS, the CSS for both is simple chrome sharing the same visual language (surface colour, radius, shadow) drawn from the same five custom properties — one file avoids duplicating that shared base twice for no isolation benefit, and follows the exact naming Part 1's own changelog anticipated for "later parts" (`…30-toolbar.css…`) |
| Dialogs | **SweetAlert2** (`Swal.fire`), but **only** for reporting an insert failure (§7.3) — matching Part 2's own `خطا` dialog pattern for its rejections, so one family of errors never looks different from another. Nothing else in this part uses a dialog |
| Icons | Bootstrap Icons 1.11.3 classes, already loaded globally. A block type's icon always comes from the registry's `icon` field. The one icon this part owns outright is the close glyph (`bi-x-lg`) on the settings panel, which is UI chrome, not a block icon |
| Other libraries | None |

---

## 5. File and folder map

```text
public/assets/js/builder/ui/
  20-settings-panel.js      NEW — window.BuilderSettingsPanel
  21-insert-menu.js         NEW — window.BuilderInsertMenu

public/assets/css/builder/
  30-toolbar-interactions.css   NEW — styles for both mechanisms above
```

No PHP file. No change to any existing file. Both `ui/*.js` files are picked up automatically by the glob Part 1's fix prompt added; both are emitted after `10-canvas.js` (alphabetical: `10-` < `20-` < `21-`). The CSS file is picked up automatically by Part 1's CSS glob, after `20-canvas.css`.

---

## 6. The settings panel (`20-settings-panel.js`)

### 6.1 Trigger and open/close lifecycle

- On script load, create exactly one panel root element (detached, not yet in the DOM) and exactly one listener for `builder:selection-changed` on `document`. Nothing is appended to the page until the first time it needs to show.
- The first time it opens, append it as the **last child of `#builder-root`** — never `document.body` (§0.2 item 10). This is what makes `var(--builder-surface)` etc. resolve at all; getting this wrong doesn't throw an error, it just silently renders with the browser's default `initial` values for every custom property, which is easy to miss in a quick look and wrong in every theme.
- On `{ uid, node }` where `node` is not `null`:
  1. Look up `var type = BuilderRegistry.getBlockType(node.type);`
  2. If `type` exists and `typeof type.renderSettingsPanel === 'function'` → open the panel for this `uid` (§6.2). If the panel is already open for a **different** `uid`, this **replaces** its content in place — never stack two panels, never flash-close-then-reopen.
  3. Otherwise (`type` is `null`, or has no `renderSettingsPanel`) → if the panel is currently open, close it (§6.4). If it is already closed, do nothing.
- On `{ uid: null }` (deselect) → close the panel if open.
- There is only ever one panel DOM node for the whole page's lifetime; it is reused, not recreated, on every open.

### 6.2 Rendering contract — the hook Parts 4–6 are written against

This is the most important part of this document for anyone who builds Parts 4–6 later: it is the first time `renderSettingsPanel`'s exact contract is pinned down, not just named.

- Before calling the hook, clear the panel's body element (`innerHTML = ''`) so a previous type's leftover DOM can never bleed into the next one.
- Set the panel's header text to `"تنظیمات " + type.label` (§9), and its header icon to `type.icon`, both read fresh from the registry, never cached from a previous open.
- Call `type.renderSettingsPanel(node, panelBodyEl)` **exactly once** per open, and exactly once again on every subsequent switch to a different `uid` — never on a re-selection of the same still-open `uid`, and never automatically afterward.
- **`node` is a deep copy**, the same object the triggering `builder:selection-changed` event carried — it is **not** a live reference into the canvas tree. A block type's settings form must call `BuilderCanvas.updateBlockData(uid, newData)` to persist any change; mutating the `node` object it was handed does nothing and is silently lost. State this exact sentence nowhere else needs restating — it is the one fact a Part 4–6 author must not get wrong.
- **The call is synchronous; any return value, including a Promise, is ignored.** The panel is considered open the moment `renderSettingsPanel` returns — there is no `await`, no `.then()`, no waiting on this container's part. A type that needs data asynchronously must render its own loading state into `panelBodyEl` immediately and update that same element in place once the data arrives; it has no way to tell this container "not ready yet."
- **The panel never re-renders on its own after the initial call.** It does not listen to `builder:tree-changed`. A form's own inputs are that form's own live state from the moment `renderSettingsPanel` returns; re-invoking the hook on every keystroke-driven `updateBlockData` would wipe focus and cursor position out from under whoever is typing. If a later part needs to reflect an *external* change to the currently-open block's data, that is that part's own problem to solve inside its hook — not this container's job.

### 6.3 Error handling

- Wrap the call to `renderSettingsPanel` in `try/catch`. On a thrown error: log it with `console.error`, leave the header as set, and put exactly one line in the (already-cleared) panel body — `"خطا در بارگذاری تنظیمات این بلاک"` (§9). Do not let the error propagate and do not break the rest of the page.
- A block type with a `renderSettingsPanel` that is present but not a function (e.g. `true`, a string) is treated identically to "no `renderSettingsPanel`" (§6.1 step 3) — it never gets called at all, so it cannot throw.

### 6.4 Positioning and responsive behaviour

This part reads "positioned beside the selected block" (§5.3) as a **fixed side drawer**, not a tooltip that tracks the block's pixel position. Reasoning, so nobody re-derives or reverses this later: a pixel-tracked popover would have to duplicate Part 2's own scroll/resize repositioning logic for the toolbar, would fight it for the same screen space, and cannot be made to behave deterministically at a 390 px viewport, which every part in this roadmap is tested against. A fixed drawer is exactly the pattern real block-based editors (Notion, WordPress's own block inspector) already converge on for the same reason, and it is trivially testable.

- **≥ 768 px viewport width:** `position: fixed`, `inset-inline-end: 0` (the visual **left** edge under this project's RTL), `top: var(--builder-sticky-top)`, `bottom: 0`, `width: min(340px, 92vw)`, internal `overflow-y: auto`. It sits beside the canvas column, not over it.
- **< 768 px viewport width:** becomes a bottom sheet — `position: fixed`, `inset-inline: 0`, `bottom: 0`, `max-height: 70vh`, `overflow-y: auto`, rounded top corners only.
- The breakpoint is a plain `matchMedia('(min-width: 768px)')` check, re-evaluated on `resize` while the panel is open (swapping the class, not recreating the DOM).
- The panel never resizes or repositions itself in response to canvas scrolling — only in response to the viewport width crossing the breakpoint.

### 6.5 Close behaviour

Three equivalent close triggers, all doing exactly the same thing — hide the panel (`display: none`, header/body left as-is until next open) — and **none of them touches canvas selection**:

1. An explicit close button (`bi-x-lg`, `title="بستن"`) in the panel header, always present.
2. A `mousedown`/`pointerdown` listener on `document` that closes the panel when the event target is outside both the panel element and (defensively) the currently selected block's element.
3. A `keydown` listener for `Escape` on `document`, active only while the panel is open. This is an addition beyond §5.3's literal "outside click" wording — cheap, standard, and consistent with the dropdown's own Escape handling (§7.5); flagged explicitly here, not silently added.

Deliberately **not** wired to `BuilderCanvas.clearSelection()` (§3 item 7, §12 decision 5): the block stays selected, with Part 2's own outline/toolbar still visible, even after its settings drawer is manually dismissed. Re-opening it requires a genuine selection change elsewhere and back — a known, accepted, minor limitation, not something this prompt asks you to solve.

### 6.6 Public surface

```js
window.BuilderSettingsPanel = {
  isOpen: function () {},     // → bool
  getOpenUid: function () {}  // → the uid currently shown, or null when closed
};
```

Nothing else. This exists purely so the acceptance checklist (§11) and any future part can query state without reaching into private DOM.

---

## 7. The insert ("+") menu (`21-insert-menu.js`)

### 7.1 Trigger

- One listener for `builder:insert-requested` on `document`. On receipt, close any currently-open instance of this same dropdown first (never stack, never leave a stale one attached to a now-wrong `anchorEl`), then open fresh against the new `{ parentUid, index, anchorEl }`.
- Defensively confirm `document.contains(anchorEl)` before opening; if the element is no longer in the document (the block it belonged to was removed in the same tick), do nothing.

### 7.2 Listing and empty state

- Build the list from `BuilderRegistry.getInsertableBlockTypes()`, called fresh on every open — never cached, so a type registered after this file first loaded still appears.
- Each entry is one row: `<i class="bi {icon}"></i>` + the registry's `label` text, in registration order, exactly as returned — no re-sorting, no filtering beyond what the registry already applied.
- If the array is empty (today's actual state, per §0.2 item 1): render a single non-interactive row, muted styling, text `"هنوز نوع بلاکی برای افزودن ثبت نشده است"` (§9). The dropdown still opens, still positions itself, still closes the normal ways — only its content differs.

### 7.3 Selecting an entry

```js
try {
  var node = BuilderCore.createNode(type);
  var uid = BuilderCanvas.insertBlock(node, parentUid, index);
  BuilderCanvas.select(uid);   // see rationale below
} catch (err) {
  Swal.fire({ icon: 'error', title: 'خطا', text: err.message });
} finally {
  close();
}
```

- **Auto-selecting the freshly inserted block is an addition beyond §5.3's literal text**, flagged here explicitly: it means a block type with settings opens its panel immediately after being inserted, which is the natural next thing anyone doing this would want, and it costs nothing — `select()` is part of `BuilderCanvas`'s own public surface. If a later part finds this actively unwanted for some specific type, that is a call for that type's own `renderSettingsPanel`, not a reason to remove this.
- A caught error is reported with `BuilderCanvas.insertBlock()`'s **own thrown message, verbatim** (§0.2 item 5) — never reworded, never re-translated. A legitimately insertable type at a valid position should rarely throw, but the depth (8) and block-count (500) ceilings are real and reachable, and the user needs to see exactly why the click did nothing.
- The dropdown closes (`finally`) whether the insert succeeded or was rejected — a failed insert is not a reason to leave a stale menu open over a dialog.

### 7.4 Positioning

- Appended as the **last child of `#builder-root`** — not `document.body` (§0.2 item 10: it needs the same five custom properties the panel does, which only cascade inside `#builder-root`'s own subtree), and not inside `#builder-canvas` either — so it is never clipped by the canvas card's own overflow, and never mistaken by Part 2's tree-walking code for part of the block tree.
- Positioned from `anchorEl.getBoundingClientRect()`, `position: fixed`, opening downward and toward the inline-start side by default, flipped to whichever side/direction actually fits the viewport (checked at open time and on `resize`/`scroll` while open — recalculate, don't just hide).
- If `anchorEl` leaves the document while the menu is open (e.g. its block was deleted by some other interaction), close the menu on the next `builder:tree-changed`... — no: this part does not listen to `builder:tree-changed` for the panel (§6.2), but the **insert menu** may, narrowly, for exactly this one self-defence check: `if (!document.contains(anchorEl)) close();`. This is the one place in this file that event is read, and it changes nothing else.

### 7.5 Closing

Closes on: selecting an entry (§7.3), an outside click, <kbd>Escape</kbd>, a new `builder:insert-requested` arriving (replaces, per §7.1), or `builder:selection-changed` firing for any reason while the menu is open (a block just got selected or deselected elsewhere — the menu's anchor context is stale either way).

### 7.6 Public surface

```js
window.BuilderInsertMenu = {
  isOpen: function () {},        // → bool
  getAnchorParentUid: function () {}  // → the parentUid the open menu targets, or null when closed
};
```

---

## 8. CSS (`30-toolbar-interactions.css`)

- Reuse `--builder-surface`, `--builder-border`, `--builder-muted`, `--builder-canvas-bg` from `10-shell.css` for both the panel and the dropdown's background, border, and secondary text — do not hard-code a colour anywhere in this file.
- `body.dark-mode #builder-root` override block, mirroring the pattern already established by `10-shell.css` / `20-canvas.css` — no separate dark-mode selector, no `prefers-color-scheme`.
- RTL logical properties only (`inset-inline-*`, `margin-inline-*`, `padding-inline-*`, `border-inline-*`) — never `left`/`right`.
- Namespace every class under `.builder-settings-*` (panel) or `.builder-insert-*` (dropdown). Suggested shapes, not mandatory exact names: `.builder-settings-panel`, `.builder-settings-panel-header`, `.builder-settings-panel-body`, `.builder-settings-panel-close`; `.builder-insert-menu`, `.builder-insert-menu-item`, `.builder-insert-menu-empty`.
- `z-index`: the settings panel above the canvas and Part 2's toolbar (e.g. `1040`); the insert dropdown above the panel (e.g. `1045`) — both below SweetAlert2's own stacking, which this part never needs to coordinate with directly since the one place it calls `Swal.fire` (§7.3) always runs after the dropdown has already closed.
- Style only your own two namespaces, `#builder-root`'s custom properties, and nothing else — no touching `style.css`, `dark-theme.css`, or any selector under `.builder-block*` / `.builder-toolbar*` / `.builder-canvas-*` / `.builder-col` / `.builder-children-horizontal`.

---

## 9. Persian strings (verbatim — author these yourself, invent nothing beyond them)

The roadmap hands this part no pre-written strings of its own (§5.3 describes behaviour, not copy). These are the complete set; do not add, remove, or reword any of them, and do not introduce a string not listed here for anything a real user can see:

| Context | String |
|---|---|
| Settings panel header prefix (before the block's own label) | `"تنظیمات "` |
| Settings panel close button `title` / `aria-label` | `"بستن"` |
| Settings panel render-error fallback | `"خطا در بارگذاری تنظیمات این بلاک"` |
| Insert dropdown empty state | `"هنوز نوع بلاکی برای افزودن ثبت نشده است"` |
| Insert-failure dialog title | `"خطا"` |
| Insert-failure dialog body | *(not authored — always the caught `Error.message`, verbatim, §7.3)* |

---

## 10. How to test this part before Parts 4–6 exist

Mirror Part 2's own §13 approach exactly. Neither mechanism has anything real to render against today, so prove both work with **temporary** fixtures, then delete every trace of them:

1. Register (via `BuilderRegistry.registerBlockType`, in a throwaway harness script — never committed under `types/`) at least:
   - `zz_settings_probe` — `category: 'content'`, `supportsTextFormatting: false`, a `renderSettingsPanel(node, panelEl)` that writes one visible marker element into `panelEl` and calls `updateBlockData` once from a button click, so you can prove both the hook fires and that a data change survives.
   - `zz_no_settings_probe` — `category: 'content'`, no `renderSettingsPanel` at all.
2. With both registered: select `zz_settings_probe` → panel opens, marker present, header shows its label. Select `zz_no_settings_probe` → panel closes (§6.1 step 3), not just goes blank. Select `zz_settings_probe` again → panel re-opens, hook called again (count it — exactly once for this open, not a leftover from before). Deselect → panel closes.
3. Before either probe is registered: trigger `builder:insert-requested` on a real parent → dropdown opens with the empty state (§7.2) and only the empty state. Register both probes → same trigger now lists exactly two rows, correct icons/labels, in registration order. Click one → a new block of that type appears at the requested position with `defaultData()`, and (per §7.3) becomes selected — if it is `zz_settings_probe`, its panel opens automatically.
4. Force a rejection (build a fixture tree already at the 500-block ceiling, or nested to depth 8, then attempt an insert at that spot) → `Swal.fire` shows `"خطا"` with the exact thrown string, dropdown is closed, tree is unchanged.
5. Force `renderSettingsPanel` to throw for a third probe type → panel shows the fallback string (§6.3), no uncaught error in the console, rest of the page unaffected.
6. Clean up completely: delete both probe registrations and any harness file, delete every test canvas/block row created, and confirm via a repo-wide mtime sweep that no file outside §5's map was touched.

---

## 11. Acceptance checklist

- [ ] `ui/20-settings-panel.js` and `ui/21-insert-menu.js` both load, in that alphabetical order, after `10-canvas.js`; `Object.keys(window)` diff across this part's evaluation is exactly `BuilderSettingsPanel, BuilderInsertMenu` — nothing else.
- [ ] `30-toolbar-interactions.css` loads after `20-canvas.css`; grep confirms no other CSS/JS file was modified.
- [ ] Both the open panel and the open dropdown are, at that moment, actual DOM descendants of `#builder-root` (`#builder-root.contains(el)` is `true` for both) — not children of `document.body`; with that confirmed, inspect computed styles and confirm `--builder-surface` etc. resolve to real values, not `initial`/empty, on both elements in both light and dark mode (§0.2 item 10).
- [ ] With no content type registered: selecting any structural slot or `column`/`column_container` never opens the panel (none of them can have `renderSettingsPanel`); triggering insert shows the empty state (§7.2) and nothing else.
- [ ] §10's `zz_settings_probe` / `zz_no_settings_probe` sequence passes exactly as described, including the "closes, doesn't just go blank" case and the "re-open calls the hook again, exactly once" case.
- [ ] The panel's header always reads `"تنظیمات " + <the live registry label>` — verified for at least two different fixture labels, proving it is not cached from first open.
- [ ] `updateBlockData` called from inside a fixture's settings form survives a reload of `BuilderCanvas.getTree()`, and the panel's own passed `node` is confirmed to be a deep copy (mutate it directly in the fixture and confirm the live tree is unaffected).
- [ ] The panel does **not** re-render (hook call count unchanged) when `updateBlockData` is called for the *same, still-selected* block from outside its own form.
- [ ] Panel closes on: its own close button, an outside click, and <kbd>Escape</kbd> — in every case selection is unchanged afterward (`BuilderCanvas.getSelected()` still returns the same block), proving §6.5's no-`clearSelection()` rule.
- [ ] Panel is a bottom sheet below 768 px and a side drawer at/above it; resizing across the breakpoint while open swaps the layout without closing the panel or losing its content.
- [ ] Insert dropdown lists exactly the registered content types, correct icon + label, in registration order; positions against whichever `anchorEl` it receives (test with both the toolbar's own insert button and an inline `.builder-block-add` affordance).
- [ ] Inserting succeeds for a valid position and auto-selects the new block; a forced depth-8 / 500-block rejection shows `Swal.fire` with the exact caught message and leaves the tree unchanged; dropdown closes either way.
- [ ] Removing the block an open dropdown's `anchorEl` belonged to (out from under it) closes the dropdown rather than positioning against a detached element.
- [ ] Dropdown closes on: an item click, outside click, Escape, a second `builder:insert-requested` (replaces, never stacks two), and any `builder:selection-changed` while it is open.
- [ ] Dark mode (`body.dark-mode #builder-root`) renders both mechanisms legibly, no hard-coded colours (grep for hex/rgb literals outside the CSS custom-property declarations themselves), correct at a 390 px viewport width.
- [ ] `window.BuilderSettingsPanel.isOpen()` / `getOpenUid()` and `window.BuilderInsertMenu.isOpen()` / `getAnchorParentUid()` all report correctly through open/close cycles of both mechanisms.
- [ ] Read-only session (`pages.view`-only account): confirm, by instrumenting both listeners, that `builder:selection-changed` and `builder:insert-requested` never fire at all in this mode (Part 2 emits neither in read-only), and therefore neither the panel nor the dropdown ever mounts — no separate read-only branch was needed or written in either file.
- [ ] All fixtures cleaned up per §10 step 6: zero leftover registrations, zero leftover canvas/block rows, zero files outside §5's map.

---

## 12. Deviations and decisions made by this prompt

Recorded so nobody re-derives or contradicts them later. These are decisions, not suggestions.

1. **Two JS globals instead of one** (§4) — the two sub-mechanisms are independently swappable, matching §5.3's own "neither depends on the other."
2. **One shared CSS file instead of two** (§4) — both mechanisms are simple chrome sharing the same five custom properties; splitting the CSS would only duplicate the shared base, and the combined name follows the pattern Part 1's own changelog already anticipated.
3. **"Beside the selected block" is built as a fixed side drawer / bottom sheet, not a pixel-tracked popover** (§6.4) — deterministic at every viewport width this roadmap tests at, doesn't duplicate or fight Part 2's own toolbar repositioning, matches how comparable block editors already solve this.
4. **The panel never re-renders itself after the first call for a given selection** (§6.2) — protects a form's live input state (focus, cursor position) from being wiped by every `updateBlockData` call, including its own.
5. **Neither the close button nor an outside click deselects the block on the canvas** (§6.5) — settings-panel visibility and canvas selection are kept as two independent pieces of state; this part never calls into Part 2's selection API to mutate it, only to read it. The accepted cost: manually dismissing the panel while the block stays selected means it does not reopen until a different block is selected first.
6. **<kbd>Escape</kbd> closes both the panel and the dropdown**, beyond §5.3's literal "outside click" wording (§6.5, §7.5) — a standard, low-risk addition, applied identically to both mechanisms for consistency.
7. **A freshly inserted block is auto-selected** (§7.3) — not stated by §5.3, added because it is the obvious next step for whoever just clicked "insert," and it is free: `select()` is already part of `BuilderCanvas`'s public surface.
8. **The insert dropdown is the one place this part reads `builder:tree-changed`**, narrowly, only to notice its own `anchorEl` has left the document (§7.4) — the panel deliberately does not read this event at all (decision 4), so this is not a contradiction, just a single, scoped exception with its own stated reason.
9. **All five Persian strings this part needs were authored fresh** (§9), since the roadmap gives Part 3 none verbatim — listed exhaustively so no later session re-invents or drifts from them.
10. **Both the panel and the dropdown are mounted inside `#builder-root`, not `document.body`** (§0.2 item 10, §6.1, §7.4) — a correction made on review of an earlier draft of this prompt, which put the dropdown under `document.body` for a real reason (escaping the canvas card's overflow) but never stated a mount point for the panel at all. `document.body` is not a descendant of `#builder-root`, so anything attached there cannot see the custom properties `10-shell.css` defines on it — the panel and dropdown would have rendered with the browser's default values for surface/border/muted/canvas-bg/sticky-top instead of the theme's, silently, in both light and dark mode. `#builder-root` still escapes the canvas card's own overflow (it is an ancestor of that card, not a descendant), so the original clipping concern is still satisfied.

---

## 13. Open items handed to a later part (do not build them now)

Record these in your changelog so they reach the roadmap's Appendix:

1. **`renderSettingsPanel(node, panelEl)`'s exact contract is now pinned** by §6.2 of this document — Parts 4–6 must be written against it as stated (deep-copy `node`, call-once-per-open, no auto-refresh), not against a looser reading of roadmap §5.3's one-line mention.
2. **`registerFormatCommand` / `setFormatState`** remain entirely Part 4's to define; this part touches neither.
3. **The "select a block, dismiss its panel, can't reopen without reselecting" limitation** (§12 decision 5) is accepted as-is. If real use makes it painful, that is a small follow-up to this part specifically (e.g. a dedicated re-open affordance), not something to fold into Parts 4–6.
4. **The two base-panel Change Requests from roadmap §8 remain open and untouched**: the dashboard `"تعداد ساخت سایت"` card still counts the legacy `pages` table, and `api/templates/activate.php` still does not sync a newly activated template's canvas title into `site_settings.site_name`.

---

## 14. Mandatory final step

Once you finish everything above, write a complete changelog of this work: every file you created (there should be exactly three — confirm with a repo-wide mtime sweep), every decision you made that this master prompt left open (there should be very few — if you had to make one, say exactly what and why), anything in this master prompt that turned out to be wrong, missing, or impossible to follow exactly as written and what you did instead, the result of every item in the §11 acceptance checklist, and the exact current state of everything future parts will depend on — the final shape of `window.BuilderSettingsPanel` and `window.BuilderInsertMenu` (every method signature and what each returns), the exact conditions under which the settings panel opens/closes/re-renders, the `renderSettingsPanel(node, panelEl)` contract exactly as Parts 4–6 must honour it, the CSS class namespaces you used and confirmation that `.builder-settings*` / `.builder-insert*` are now taken (so Parts 4–6 know to use their own type-prefixed names, not these), the breakpoint/positioning rules for both mechanisms, and the result of the §0.2 item 10 containing-block check (whether `#builder-root` or any ancestor carries `transform`/`filter`/`perspective`/`contain`/`will-change: transform`, and if so, exactly how positioning was adapted). Save it as `changelogs/changelog-part-3-block-toolbar-interactions.md`.
