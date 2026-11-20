# Changelog — Part 2: Canvas Engine

Date: 2026-09-21. Verified by execution in real headless Chrome (CDP-driven:
login, selection, toolbar, splits, synthetic native-DnD drags, saves,
reloads, read-only staff, dark mode, 390 px) plus Node static scans.
No PHP file created or modified.

## 1. Files created (exactly three, nothing modified)

- `public/assets/js/builder/ui/00-sortable.js` — NEW. SortableJS **1.15.7**
  (latest 1.15.x; verified via jsDelivr API — 1.15.3…1.15.7 list, no 2.x),
  minified UMD, fetched from
  `https://cdn.jsdelivr.net/npm/sortablejs@1.15.7/Sortable.min.js`,
  byte-for-byte unmodified, 45478 bytes.
  SHA256 `BF4241BC73FEF7F11C59A283A69FE8051CDD31C6D8FF5A2B9BA219E7831FCF76`.
  Vendored locally (not CDN) for offline XAMPP development, single-mechanism
  loading through the `ui/*.js` glob, and pinning — recorded as decision §15.2.
- `public/assets/js/builder/ui/10-canvas.js` — NEW. `window.BuilderCanvas`,
  the whole engine (IIFE, single global, ES5 syntax, no `switch`, no
  `getInsertableBlockTypes`, no `fetch`, no `BuilderShell.save()`, no
  execCommand/contenteditable/Selection).
  SHA256 `2FB23220750847D5B7DCDA5BE02165744723AF82023E4C587FFDC7C33DAD`.
- `public/assets/css/builder/20-canvas.css` — NEW. Canvas/chrome/toolbar/drag
  styles, RTL logical properties only, dark-mode block.
  SHA256 `3008AE79BC3031FFE89325A1658D3A14EAB04A509AADFF28C907D6B177D3DF3F`.
- `changelogs/changelog-part-2-canvas-engine.md` — this file.

A repo-wide mtime sweep confirms nothing else changed: no Part 0/1 file, no
`style.css`, nothing left in `types/`. `Sortable` is referenced only inside
post-`render()` functions (`ensureSortable`), never at parse time.

## 2. The §1 discrepancy (read this first)

**Part 0 shipped the five structural types server-side only.** The client
`BuilderRegistry` knows only what `public/assets/js/builder/types/*.js`
registers — today that folder holds just `.gitkeep`, so `getBlockType(
'header'|'body'|'footer'|'column_container'|'column')` returns `null` in the
browser (proven live: slots rendered with raw type + question icon).
Consequences, all handled without guessing:

- The prompt assumes registry labels/icons Depths for the slots (§6.1, §14
  item 5). Unachievable as specified: §3.1 forbids registering any block
  type and creating any file in `types/`, and §4 forbids hard-coding labels.
  Slots/columns/containers therefore render with the raw type string +
  generic icon until a client-side structural registration exists.
- Structural BEHAVIOR is fully implemented positionally (allowed by §3.13):
  `effParent()`/`effParentTypes()`/`typeKnown()` prefer the registry and
  fall back to the prompt's own §0.3 semantics (slots/columns unrestricted;
  container `["column"]`, 2–4); `makeContainer()`/`makeColumn()` try
  `BuilderCore.createColumnContainer/createNode` first (they throw today)
  and fall back to the specified node shape. If a client structural mirror
  ever lands, the registry branches win automatically — zero edits here.
- **Proposed follow-up (not built):** a tiny `types/structural.js` client
  mirror of Part 0's `structural.php` (labels/icons/defaultData, no render)
  would light up registry labels with no edits to any existing file.
- Validator strings were copied character-for-character from
  `includes/builder/tree_validator.php`, NOT from the prompt's §12 table:
  the file uses `میتواند`/`نمیتواند`/`بلاکها` (no ZWNJ) where the table
  prints `می‌تواند`/`نمی‌تواند`/`بلاک‌ها`. All 22 strings verified by
  codepoint scan.
- Test-harness notes (not product issues): shared-profile cookies made a
  staff login vacuous until cookies were cleared per login; CDP mouse events
  cannot start native HTML5 DnD, so drags were driven with staged synthetic
  `pointerdown`→`dragstart`→`dragover`→`drop`→`dragend` across event-loop
  turns (Sortable defers drag start to a timer); hidden handles report zero
  rects, so drags select first.

## 3. §14 acceptance checklist — result of every item

- [x] `ui/*.js` glob confirmed in `modules/pages/index.php` before starting.
- [x] Exactly three files created (mtime sweep); `types/` holds only
  `.gitkeep` again.
- [x] `00-sortable.js` unmodified upstream (version/URL/SHA256 in §1).
- [x] `Object.keys(window)` diff is exactly `BuilderCanvas`.
- [x] Blank canvas renders `header, body, footer` in order, each with
  empty-state text + inline affordance, no handles; no `switch` in the file;
  labels/icons fall back per §2 (registry-or-raw, never hard-coded).
- [x] `JSON.stringify(BuilderCanvas.getTree())` equals the `load.php`
  response tree.
- [x] `zz_probe` insert runs its `render()` exactly once; a second insert
  leaves the first block's call count at 1 (reconciliation moves elements).
- [x] `zz_box` (no `render()`) shows `"نمایش این بلاک هنوز اضافه نشده است"`;
  ghost shows `"این نوع بلاک شناخته نمی‌شود"` via `textContent`
  (XSS-safe by construction); ghost survives save→reload→save with
  `uid`/`type`/`data` intact and the raw `load.php` response byte-identical
  (the second save is correctly rejected `400 نوع بلاک نامعتبر است` by the
  server — the client keeps the ghost and changes nothing).
- [x] Click select / move / empty-canvas clear; `builder:selection-changed`
  with `{uid, node, el}` (nulls on clear), exactly once per change.
- [x] One toolbar element ever; positioned above the selection (below when
  no room), repositioned on scroll/resize/mutation; hidden on deselect.
- [x] Root selection hides the toolbar; `zz_probe` (flag true, zero
  commands) shows insert+split+delete, no format group; after
  `registerFormatCommand({id:'bold',…})` the group appears on `zz_probe`
  only; `setFormatState({bold:true})` marks it pressed.
- [x] Format click dispatches `builder:format {command:'bold', value, uid,
  node(deep copy)}`; DOM and tree byte-identical afterwards.
- [x] Insert control + every affordance dispatch `builder:insert-requested
  {parentUid, index, anchorEl∈document}` (toolbar index = sibling+1,
  affordance index = child count); no menu/panel elements exist.
- [x] `getInsertableBlockTypes` absent (grep).
- [x] Split of `zz_probe` in `body` → 3 yields `body → column_container →
  3 × column`, original in column 0, container `data: {}`.
- [x] 3 → 4 appends one empty column; only segments 1–4 exist.
- [x] 4 → 3 empty-trailing: no prompt. With content: `"حذف ستون‌های پر"`
  prompt; cancel → `JSON.stringify` identical.
- [x] → 1 removes container + column, splices column-0 children into the
  parent at the container's index, order preserved (validated: parent
  min/max, survivor placement; first column survives).
- [x] Split inside a column works (`cc → column → cc`); split control
  addresses the selection (probe → nested split; container → adjust);
  columns and root slots never offer it (columns hide the whole toolbar —
  no applicable group).
- [x] At depth 8 (`body→cc→col→cc→col→cc→col→zz_probe`) segments 2–4 render
  `disabled`; forced click rejects `"عمق تودرتویی بیش از حد مجاز است"`
  with zero tree change (toolbar refreshes on every mutation, so post-split
  staleness cannot re-enable them).
- [x] 501st insert throws `"تعداد بلاکها بیش از حد مجاز است"` (count stays
  500); `column`→`body`, `column_container`→`column_container`,
  `header`→anywhere, unknown type rejected with the file's exact strings.
- [x] Real drag by handle reorders siblings (order `1,2,0`,
  `reason: 'move'`); after Save, `builder_blocks.sort_order` is `0,1,2`.
- [x] Cross-parent drop denied (`put:false`): tree unchanged; no root
  handles; column↔column drag inside one container works.
- [x] 20 insert/remove cycles: no errors, lists/blocks counts exact,
  subsequent move correct with no jump-back (functional no-leak proof;
  every removal destroys its Sortable instances via `destroyDetached`).
- [x] Delete dialog `"حذف بلاک"`/`"حذف"`/`"انصراف"`; cancel unchanged;
  confirm removes the whole subtree; no delete on roots or `column`.
- [x] Every successful mutation calls `markDirty()` exactly once
  (wrapped-counter proof) with one `builder:tree-changed` carrying the
  right `reason` (`insert|remove|move|split|unsplit|data`); badge shows.
- [x] Rejected mutations: dirty counter and event log unchanged.
- [x] `updateBlockData(uid,{x:1})` replaces data, re-runs only that block's
  `render()` (call counts), marks dirty, survives save→reload.
- [x] E2E (3-level split tree): `getTree()` JSON identical across
  Save → full page reload.
- [x] Read-only (`pages.view`-only staff, real session): tree renders, no
  toolbar/handles/affordances/empties, clicks select nothing (no event),
  mutations reject `"شما اجازه‌ی ویرایش این بوم را ندارید."` (insert
  throws it, others return false). Downgrade on `builder:saved` strips
  chrome via `stripInteractive()`.
- [x] Dark mode legible (vars applied, `--builder-surface #1e1e2e`,
  visible non-white outline); `outline` (never `border`) so selection
  never shifts layout (offsetTop identical before/during/after);
  `#builder-root`/`#builder-canvas` fit 390 px. Page-level horizontal
  scroll at 390 px comes from the shared shell sidebar (fixed 260 px flex
  item — identical on dashboard, which has zero builder code) and is out
  of scope: shell files are Part 1/base-panel territory.
- [x] Fixtures cleaned: `zz_probe.js`, `zz_probe.php` deleted;
  `builder_canvases`/`builder_blocks` back to 0 rows; `tmpview2` staff +
  login attempts removed; admin theme restored to light. (Site name was
  already personalized to the owner's name before this part and was never
  written by it — all Part 2 saves used `page` canvases; grep confirms no
  `site_settings`/`site_name` reference in `10-canvas.js`.)

## 4. Contract for future parts (exact current state)

**`window.BuilderCanvas`** — reading: `getTree()` (deep copy),
`getNode(uid)` (deep copy|null), `getSelected()` (`{uid, node, el}`|null),
`getCanvasElement()` (`#builder-canvas`), `getToolbarElement()` (the single
toolbar el), `getBlockElement(uid)` (el|null); selection: `select(uid)`
(no-op unknown; silent when read-only or unchanged), `clearSelection()`;
mutations (validate with the §12/file strings via `Swal` `خطا`, `markDirty`,
reconcile, `builder:tree-changed`): `insertBlock(node, parentUid, index?)`
→ uid or throws (incl. read-only message); `removeBlock(uid)` → bool (root
→ `ساختار ریشه…`, min-violation → `تعداد فرزندها…`); `moveBlock(uid,
parentUid, index?)` → bool (same-spot → `true` silently); `updateBlockData(
uid, data-object)` → bool (throws on non-object); `refreshBlock(uid)` →
bool (no dirty/event); formatting: `registerFormatCommand({id
/^[a-z][a-z0-9_-]{0,31}$/ unique, icon, title, group?, value?})` (throws on
bad/dup), `setFormatState({id:bool})` (unknown ids ignored).
Rejections: insert throws `Error(message)`; others return `false`; tree
untouched in all cases.

**Events on `document`:** `builder:selection-changed {uid, node, el}`
(nulls on clear); `builder:insert-requested {parentUid, index, anchorEl}`
(index = sibling+1 from toolbar, child count from affordance; parentUid
always a real block — never a root insert); `builder:format {command,
value, uid, node(deep copy)}` (nothing else happens); `builder:tree-changed
{reason: 'insert'|'remove'|'move'|'split'|'unsplit'|'data'}` (after
`markDirty()`).

**`registerFormatCommand`/`setFormatState` (Part 4's contract):** zero
built-in commands ship; Part 4 registers what the text model supports with
its own Persian titles; buttons render in registration order; pressed state
syncs via `setFormatState`. Clicking only emits `builder:format`.

**Namespaces:** mine — `.builder-block*` (wrapper/handle/label/content/
children/selected/root/add), `.builder-toolbar*` (toolbar/groups/buttons/
split/active), `.builder-canvas-*` (empty/norender/ghost/drag states),
`.builder-col`, `.builder-children-horizontal`. Free — Part 3:
`.builder-settings*`, `.builder-insert*`; Parts 4–6: own type-prefixed
names. Never touched: `style.css`, `dark-theme.css`, anything outside
`#builder-canvas` + the toolbar element.

**Block DOM:** `.builder-block[data-uid,data-type,data-depth]` →
`span.builder-block-handle[title=جابه‌جایی]` (depth>1, editable only) +
`span.builder-block-label > i.bi + span` (always visible on roots, else
hover/selected) + `div.builder-block-content` (type's territory: `render(
node, el)` once at creation, re-run only by `refreshBlock`/`updateBlockData`;
content types without `render` get the no-renderer placeholder; unknown
types get the ghost text via `textContent`) + `div.builder-block-children`
(mine: reconciled by moving elements, never recreated; inline
`.builder-block-add` affordance + `.builder-canvas-empty` state for
unrestricted parents; horizontal flex for containers).

**`render()` guarantee:** a type's `render()` runs exactly once per element
(except explicit `refreshBlock`/`updateBlockData`); verified by call
counting (second insert leaves first block at 1).

**SortableJS:** 1.15.7 UMD from `ui/00-sortable.js` (SHA256 §1); one
instance per children list (`Map` parentUid→instance, created with the
list, destroyed with it); `handle: '.builder-block-handle'`,
`draggable: '.builder-block'`, sibling-only groups
`'builder-'+parentUid` (`pull:false, put:false`), no root instance,
`direction: 'horizontal'` under containers, `animation: 150`,
`ghostClass: 'builder-canvas-drag-ghost'`,
`chosenClass: 'builder-canvas-drag-chosen'`, `forceFallback: false`;
`onEnd` routes through `moveBlock()` (DOM never the truth; reconciler
restores on reject); instances disabled (destroyed) when read-only.

**`ui/NN-name.js` convention (next part):** alphabetical glob order =
execution order after `builder-shell.js`; `00-` reserved for vendored libs,
`10-canvas.js` is this engine — Part 3+ use `20-*`, `30-*`, … with the same
`NN-name` prefix pattern; exactly one `window.*` global per file; never
reference `Sortable` at parse time.

## 5. Open items handed forward (per §16)

1. Cross-container drag unimplemented (`put:false` denies it; `moveBlock`
   already validates cross-container — enabling is a `group` change + flag).
2. `registerFormatCommand`/`setFormatState` reserved for Part 4.
3. `builder:selection-changed` is Part 3's panel-open trigger; no toolbar
   button for it.
4. No block-level undo (recover by leaving without saving).
5. Base-panel CRs untouched: dashboard `"تعداد ساخت سایت"` counts legacy
   `pages`; `activate.php` does not sync canvas titles into `site_name`.
