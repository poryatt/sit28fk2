# Master Prompt — Part 2: Visual Page Builder — Canvas Engine

**Target executor:** an AI coding agent (OpenCode or equivalent) with write access to the existing panel project.
**Project:** Website & Business Management Panel (PHP 8 + PDO + MySQL, Persian/RTL) — see `PANEL-BLUEPRINT.md`.
**Feature:** Visual Page Builder, Part 2 (Canvas Engine). Roadmap: `roadmap-page-builder.md` (v4), §4 row 2 and §5.2.
**Deliverable of this part:** the canvas — rendering the tree, selecting a block, the per-block toolbar chrome (four controls), column splitting and unsplitting, deleting, sibling drag-and-drop reordering, and handing the live tree back to Part 1's shell for saving. **No block type content, no insert dropdown, no settings panel.**

---

## 0. Before you start

### 0.1 Hard dependency — verify it, do not assume it

This part's JavaScript reaches the page through a glob over `PUBLIC_PATH/assets/js/builder/ui/*.js` that was added to `modules/pages/index.php` by a separate fix prompt (`changelogs/changelog-part-1-fix-ui-script-glob.md`).

**First action of this part:** open `modules/pages/index.php` and confirm that glob exists and emits after `builder-shell.js`. If it is not there, **stop immediately and report it** — do not add it yourself (it is Part 1's file), and do not work around it by putting your files in `assets/js/builder/types/`. That folder is the block-type folder; abusing it would corrupt the meaning of `BuilderAssets::clientScripts()` for Parts 4–6.

### 0.2 State inherited from Parts 0 and 1

Both parts shipped. Their changelogs report no defect that blocks this part, and **no fix is required beyond §0.1**. Facts from those changelogs that change how you write this part:

1. **`load.php` keeps unknown block types in the tree.** A block whose type file was deleted comes back as a "ghost" node with `defaultData` for its `data`. Your renderer must draw it without crashing and must keep it in the tree, or saving would silently destroy the user's data.
2. **Every content type is a ghost today.** Parts 4–6 have not shipped, so `BuilderRegistry.getInsertableBlockTypes()` currently returns `[]` and no registered type has a `render()`. The "no renderer" path is the normal path right now, not an edge case — see §13 for how you are expected to test this part.
3. **`column_container` nested directly inside another `column_container` is rejected** by validator rule 11 (`allowed_child_types: ["column"]`), but `cc → column → cc` is legal. Your column-split control must respect that.
4. **The shell hands `render()` deep copies** of the tree and canvas, so the tree you receive is yours to own and mutate. Nothing else holds a reference to it.
5. **`BuilderShell.getTree()` returns `controller.getTree()` once your controller is installed.** From that moment the canvas is the single source of truth for what gets saved.
6. **`admin.js` has no Persian-digit helper** (Part 1's changelog §3.2). If you need one, write a private one inside your IIFE.
7. **Part 0's `bootstrap.php` assigns `$builderBase`, `$builderConfig`, `$builderDatabase`, `$builderTypeFiles` into the including scope** — irrelevant here (this part writes no PHP) but recorded so you do not introduce a PHP file that collides.

### 0.3 Part 0 and Part 1 surface this part consumes

```js
// Part 0 — pure data/logic, no DOM
BuilderRegistry.getBlockType(type)            // definition or null
BuilderRegistry.getAllBlockTypes()            // copy of the array
BuilderRegistry.getInsertableBlockTypes()     // content-category only (Part 3 uses this, not you)
BuilderRegistry.hasBlockType(type)            // bool
BuilderCore.createUid()                       // 32 lowercase hex
BuilderCore.createNode(type)                  // { uid, type, data, children }; throws on unknown type
BuilderCore.createColumnContainer(count)      // 2..4; throws otherwise
BuilderCore.defaultTree()                     // [header, body, footer]
BuilderCore.walkTree(tree, cb)                // pre-order, cb(node, parent, depth, index), root depth 1
BuilderCore.findByUid(tree, uid)              // node or null

// Part 1 — the shell
BuilderShell.setCanvasController({ render, getTree })   // once per page; throws on a second call
BuilderShell.getCanvas()                                // canvas meta, deep copy
BuilderShell.canEdit()                                  // bool
BuilderShell.markDirty() / markClean() / isDirty()
BuilderShell.save(options)                              // you never call this
// document events: builder:loaded {canvas, tree}, builder:saved {...}, builder:error {...}
```

Client registry definition shape (camelCase, Part 0 §8.4): `type`, `label`, `icon`, `category` (`structure`|`content`), `allowsChildren`, `minChildren`, `maxChildren`, `supportsTextFormatting`, `allowedChildTypes`, `allowedParentTypes`, `defaultData()`, optional `render(node, el)`, optional `renderSettingsPanel(node, panelEl)`.

Node shape (Part 0 §8.1): `{ uid: "<32 hex>", type: "<registered>", data: {object}, children: [nodes] }`.

The five structural types registered by Part 0: `header`, `body`, `footer`, `column_container` (`allowedChildTypes: ["column"]`, 2–4 children), `column` (`allowedParentTypes: ["column_container"]`).

DOM this part attaches to (Part 1's changelog §5): `#builder-canvas` — the mount element, guaranteed empty, guaranteed to exist before `render()` is called.

---

## 1. Read before you write anything

1. `roadmap-page-builder.md` — §3 (product vision, especially the depth-8 rule), §4 row 2, **§5.2 in full** (it is the specification of the four toolbar controls and the exact column-split semantics), §5.3 (Part 3's scope — the boundary you must not cross), §6, and §9's Appendix entries for Parts 0 and 1.
2. `PANEL-BLUEPRINT.md` — §3 (tech stack), §12 (UI/UX conventions, dark mode, SweetAlert2 for destructive confirms), §13 (localization), §17 (naming).
3. The real code, all of it read before you write:
   - `public/assets/js/builder/block-registry.js` and `builder-core.js` — the exact behaviour of every function in §0.3, including what `walkTree`'s callback receives and whether the registry rejects definitions carrying extra keys.
   - `public/assets/js/builder/builder-shell.js` — the controller contract, when `render()` is called, what `getTree()` is expected to return, how `markDirty()` surfaces.
   - `modules/pages/index.php` — the asset globs (§0.1) and the DOM around `#builder-canvas`.
   - `public/assets/css/builder/10-shell.css` — the custom properties already defined on `#builder-root` (`--builder-surface`, `--builder-border`, `--builder-muted`, `--builder-canvas-bg`, `--builder-sticky-top`) and the dark-mode override block. Reuse them; do not redefine them.
   - `includes/builder/tree_validator.php` — the eleven rules and their **exact** Persian error strings. You mirror a subset of them client-side and must reuse those strings verbatim.

If anything above behaves differently from what this document claims, stop, do not guess, record it in your changelog (§15), and implement against the code that actually exists.

---

## 2. What this part is

After this part, the builder screen stops being a title box with a hole in it and becomes a canvas:

- The three root slots render with their registry labels and icons, always in the order `header`, `body`, `footer`, never reorderable relative to each other.
- Clicking a block selects it; one block is selected at a time; a floating toolbar attaches itself to the selection.
- The toolbar carries four controls (§6): insert, text formatting, column split, delete.
- Blocks reorder among their siblings by dragging a handle.
- Splitting turns a block into a two-to-four column layout; reducing to one column dissolves the layout again.
- Every structural change marks the shell dirty, and the shell's Save posts exactly what is on screen.

And it stays a shell in one specific sense: **it never knows what any content block contains.** A content type is drawn by calling that type's own `render()`. Until Part 4 ships, every content block draws as a neutral placeholder, and that is the correct, expected behaviour — not a bug.

---

## 3. Must NOT do

| # | Do not | Why |
|---|---|---|
| 1 | Do not register any block type, and do not create a file in `assets/js/builder/types/` | Parts 4–6. Part 2 ships zero block types |
| 2 | Do not build the "+" dropdown, its list of types, its positioning, or its click-to-insert behaviour | Part 3. You emit an event with an anchor element and stop |
| 3 | Do not build the settings panel — no panel element, no open/close/position logic, no call to `renderSettingsPanel()` | Part 3 |
| 4 | Do not call `BuilderRegistry.getInsertableBlockTypes()` anywhere | That accessor exists for Part 3's menu. Using it here means you are building the menu |
| 5 | Do not implement any text-formatting behaviour: no `document.execCommand`, no `Selection`/`Range` manipulation, no `contenteditable` attribute on anything | Part 4 owns the text model. You render the toolbar group and emit commands (§6.2) |
| 6 | Do not edit any Part 0 file (`includes/builder/*`, `api/builder/*`, both builder JS files, the schema files) or any Part 1 file (`modules/pages/index.php` + served copy, `builder-shell.js`, `10-shell.css`) | The Lego rule. §0.1 is the one dependency, and it is delivered by its own prompt, not by you |
| 7 | Do not call `fetch`, do not touch `load.php`/`save.php`, do not read or write the CSRF token | Part 1 owns transport. You mark dirty; the shell saves |
| 8 | Do not call `BuilderShell.save()`, and do not add an autosave | Saving is a user action on Part 1's buttons |
| 9 | Do not implement cross-container drag (moving a block out of its parent into another parent) | Out of this part's scope (§14 item 4). Sibling reordering only |
| 10 | Do not implement undo/redo, copy/paste, duplicate-block, keyboard block navigation, or multi-select | None are in the roadmap. Each is a part-sized feature |
| 11 | Do not modify `public/assets/css/style.css` or `dark-theme.css`, and do not restyle anything outside `#builder-canvas` and your own toolbar element | Shared files |
| 12 | Do not add jQuery, a frontend framework, a build step, or any library other than the one vendored SortableJS file | Blueprint §3 |
| 13 | Do not render a block by a `switch` on its type, and do not special-case any type name other than the five structural ones Part 0 registered | Roadmap §5.2's rendering rule: the five structural types are the only block-type knowledge this part has |
| 14 | Do not drop, rewrite, or "clean up" a node whose type is unknown | Ghost blocks must survive a load → save round trip intact (§0.2 item 1) |
| 15 | Do not extend `media_usage`, do not touch `api/templates/activate.php`, do not fix the dashboard card | Base-panel CRs, roadmap §8 |
| 16 | Do not invent Persian strings | §12 gives every string you need |

---

## 4. Language and library decisions (fixed — do not reconsider)

| Concern | Decision |
|---|---|
| Canvas code | One vanilla JS file, IIFE, **exactly one new global** (`window.BuilderCanvas`). No PHP file in this part at all |
| Drag and drop | **SortableJS**, vendored locally (§5). Sibling reordering only |
| Styling | One new CSS file, `20-canvas.css`, picked up automatically by Part 1's CSS glob |
| Dialogs | SweetAlert2 (`Swal.fire`) for the two confirmations in §12. Never `window.confirm` |
| Icons | Bootstrap Icons 1.11.3 classes, already loaded globally. Block icons come from the registry's `icon` field — never hard-coded |
| Labels | Block labels come from the registry's `label` field — never hard-coded, so the ZWNJ inside `"ستون‌بندی"` can never be mangled by this part |
| Other libraries | **None** |

---

## 5. File and folder map

Create exactly these three files. Nothing else. No PHP file is created or modified by this part.

```text
panel/
  public/
    assets/
      js/
        builder/
          ui/
            00-sortable.js        # NEW: vendored SortableJS, UMD build, unmodified
            10-canvas.js          # NEW: window.BuilderCanvas — the whole canvas engine
      css/
        builder/
          20-canvas.css           # NEW: canvas, block chrome, toolbar, drag states, dark mode
```

**SortableJS vendoring.** Use the latest 1.15.x UMD build (1.15.7 at the time this prompt was written — check for a newer 1.15.x and use it if one exists; do not jump to a 2.x should one appear). Save the minified UMD bundle **byte-for-byte unmodified** as `00-sortable.js`, and record in your changelog: the exact version, the URL you fetched it from, and its SHA256. If you cannot reach the network, stop and say so rather than hand-writing a drag-and-drop implementation.

This deviates from the base panel's CDN convention for Bootstrap, Bootstrap Icons and SweetAlert2 (blueprint §3), deliberately and for three reasons: the builder must work on an offline Laragon/XAMPP machine, which is where this project is developed; the `ui/*.js` glob is the only script mechanism available to this part and it serves local files only, so a CDN tag would require a second mechanism and an edit to a Part 1 file; and a pinned local file cannot change under the project without a commit. Record this as a decision in your changelog.

The `00-` prefix guarantees SortableJS is parsed before `10-canvas.js`. Even so, **never reference `Sortable` at parse time** — only inside functions that run after `render()` — so a load-order regression degrades to "drag stops working" rather than a page-breaking `ReferenceError`.

---

## 6. The block chrome and the toolbar

### 6.1 What renders around every block

Every node in the tree, of every type, is drawn inside a wrapper element this part owns:

```text
<div class="builder-block" data-uid data-type data-depth>
  <span class="builder-block-handle" title="جابه‌جایی">        ← drag handle; absent when not draggable
  <span class="builder-block-label">                          ← registry label + icon; see below
  <div class="builder-block-content">                         ← the type's own territory
  <div class="builder-block-children">                        ← only when the type allows children
</div>
```

- `.builder-block-content` is **the type's territory**. For a content type with a `render()`, call `definition.render(node, contentEl)` once, when the element is first created, and never read or write inside that element again. For a type with no `render()` — including every content type until Part 4 ships — draw the placeholder from §12. For a type that is not in the registry at all (a ghost, §0.2 item 1), draw the unknown-type placeholder with the raw type string HTML-escaped.
- `.builder-block-children` is **your territory**. The type never touches it. It exists only when `allowsChildren` is true.
- The label strip shows the registry `icon` and `label`. On the three root slots it is always visible; on every other block it appears on hover or when selected, so the canvas does not look like a debug view.
- `data-depth` is the node's depth with a root slot counting as 1 — the same numbering `walkTree` and the server validator use.

### 6.2 The toolbar

**One** toolbar element for the whole page, not one per block. It is created once, lives as a direct child of `#builder-canvas`'s offset parent, is `position: absolute`, and is moved and shown above the selected block's top edge (falling back to below when there is no room above). Reposition it on canvas scroll, window scroll, and window resize. Hide it when nothing is selected, and whenever the selection has no applicable control group at all — a root slot shows none (see the insert row below), so selecting one leaves the toolbar hidden.

It carries exactly four control groups, in this RTL reading order:

| # | Control | Rendered when | Behaviour |
|---|---|---|---|
| 1 | **Insert** (`bi-plus-lg`, title `"افزودن بلاک"`) | The selected block is **not** a root slot (depth greater than 1), its parent allows an unrestricted child (`allowsChildren === true` and `allowedChildTypes` is empty), and the tree holds fewer than 500 nodes (validator rule 8 — at the limit the control is hidden, not disabled) | Dispatches `builder:insert-requested` with `{parentUid, index, anchorEl}` where `index` is the selected block's index **plus one** (insert as the next sibling). Nothing else. No menu. `parentUid` is always the uid of a real block — root slots never emit this event, so Part 3 never has to handle a root-level insert (which validator rules 1 and 5 would reject) |
| 2 | **Formatting group** | The selected block's definition has `supportsTextFormatting === true` **and** at least one format command is registered (§6.3) | Renders one button per registered command; each dispatches `builder:format`. Empty group is not rendered at all |
| 3 | **Column split** (`bi-layout-three-columns`, title `"ستون‌بندی"`) | The selected block is not a root slot, not a `column`, and the split would not exceed depth 8 | A segmented control of 1–4 (§7). Disabled values are visibly disabled, not hidden |
| 4 | **Delete** (`bi-trash`, title `"حذف بلاک"`) | The selected block is not a root slot and not a `column` | Confirms (§12), then removes the node and its subtree |

A `column` gets no delete and no split: its count is governed by its container's split control, and deleting one directly would break the container's `minChildren: 2`. A root slot gets no insert, no delete, no split, and no drag handle — children are added to a slot only through its inline affordance (§6.4), never as a root-level sibling (validator rules 1 and 5 forbid a fourth root node). All of this is derived from position (depth 1 = root slot) and from the registry, not from a hard-coded list of type names — except `column` and `column_container`, which §3 rule 13 explicitly permits this part to know.

### 6.3 Format commands — a registration point, not a fixed icon set

Roadmap §5.2 describes the formatting group as "bold, italic, lists, alignment, etc.". **This part ships zero built-in commands** and instead exposes:

```js
BuilderCanvas.registerFormatCommand({
  id:    'bold',          // required, /^[a-z][a-z0-9_-]{0,31}$/, unique
  icon:  'bi-type-bold',  // required, a Bootstrap Icons class
  title: 'درشت',          // required, Persian, used as the button's title attribute
  group: 'inline',        // optional string; buttons with the same group render adjacent
  value: undefined        // optional, passed straight through in the event detail
});
BuilderCanvas.setFormatState({ bold: true, italic: false });   // sets/clears the pressed state per command id
```

Clicking a command button dispatches `builder:format` with `{ command, value, uid, node }` — where `node` is a deep copy — and does nothing else.

**Why this deviates from a literal reading of §5.2:** a fixed icon set would force this part to know which formatting operations a text block supports, which is exactly the content knowledge its Must-Not-Do forbids, and every command Part 4 later wants to add or drop would mean editing this file. It also spares this part from inventing a dozen Persian labels for behaviour it does not implement. Part 4 registers the commands it actually supports, with its own labels, and keeps the pressed states in sync via `setFormatState()`. The toolbar mechanism, its gating by `supportsTextFormatting`, and its position in the toolbar all stay here, exactly as §5.2 requires. Record this decision in your changelog so Part 4's prompt can be written against it.

### 6.4 Inline insert affordances

The toolbar's insert control only helps when a block is already selected. An empty slot has nothing to select, so also render a low-profile "add here" affordance (`bi-plus`, label `"افزودن بلاک"`) at the **end of the children list** of every block whose type allows unrestricted children — that is, the three root slots, every `column`, and any future content type that allows children. Not on `column_container`, whose children may only be `column`s.

It dispatches the same `builder:insert-requested` event with `index` equal to the current child count. When the children list is empty, it renders inside the empty-state placeholder from §12 instead of after it.

---

## 7. Column split and unsplit

The exact semantics come from roadmap §5.2 item 3 and are not open to reinterpretation:

- **Split (1 → N, N in 2..4).** Build the container with `BuilderCore.createColumnContainer(N)` — never `createNode('column_container')`, which produces an invalid zero-child node. Replace the selected block in its parent's children array, at the same index, with the container. Move the original block into `container.children[0].children`. The remaining columns stay empty. Select the original block again afterwards, so the user keeps their place.
- **Increase (N → N+1, up to 4).** Append fresh `column` nodes. A fresh column is `BuilderCore.createNode('column')`.
- **Decrease (N → M, M ≥ 2).** Remove trailing columns. If any column being removed has children, confirm first (§12); on cancel, change nothing at all.
- **Decrease to 1.** Remove the container entirely and splice the surviving column's children into the container's position in its parent, preserving their order. The container and the column both disappear. Confirm first if any removed column had children.
- **Depth.** Splitting adds two levels above the block's own subtree (`container` then `column`). Allow it only when `deepest depth within the selected block's subtree + 2 ≤ 8`. When it would exceed, the split control's values are disabled — and if the operation is somehow still attempted through the public API, reject it with the validator's own depth message (§12).
- **`cc → cc` is illegal, `cc → column → cc` is legal.** Splitting a block that sits inside a column is therefore fine. Splitting a `column_container` itself is not offered (§6.2).

The split control reads its current value as: the number of `column` children when the selected block **is** a `column_container`; otherwise 1.

---

## 8. Drag and drop

- One SortableJS instance per **children list** element, created when that list is rendered and destroyed when it is removed. Keep a `Map` of them and destroy properly — a leaked instance on a removed element is the classic source of "the block jumps back" bugs.
- `handle: '.builder-block-handle'`. Dragging the block body must stay free for text selection and for whatever Part 4 puts inside `.builder-block-content`.
- **Sibling-only:** `group: { name: 'builder-' + <parent uid>, pull: false, put: false }`. A unique group name per parent is what makes a cross-list drop impossible. Cross-container moves are out of scope (§14 item 4).
- **No Sortable on the root list.** `header`, `body`, `footer` are fixed in order and have no handles.
- A `column_container`'s children list is horizontal (`direction: 'horizontal'`); reordering columns within their container is allowed. Everything else is vertical.
- `animation: 150`, a `ghostClass` and a `chosenClass` you style in `20-canvas.css`, and `forceFallback: false`.
- On `onEnd`, do **not** trust the DOM as the new truth. Read `evt.oldIndex` / `evt.newIndex`, apply the move to the tree through `BuilderCanvas.moveBlock()` (the single mutation path, §9), then let the reconciler put the DOM back in agreement. If the move is rejected, the reconciler restores the previous order by itself.
- Disable every instance when `BuilderShell.canEdit()` is false (§10).

---

## 9. `window.BuilderCanvas` — the API Part 3 and Parts 4–6 are written against

Implement exactly these signatures. Part 3's master prompt will be written from them, so report any deviation loudly in your changelog.

```js
window.BuilderCanvas = {
  // reading
  getTree: function () {},              // deep copy of the whole tree
  getNode: function (uid) {},           // deep copy of one node, or null
  getSelected: function () {},          // { uid, node: <deep copy>, el: <element> } or null
  getCanvasElement: function () {},     // #builder-canvas
  getToolbarElement: function () {},    // the single toolbar element
  getBlockElement: function (uid) {},   // the .builder-block element for a uid, or null

  // selection
  select: function (uid) {},            // no-op for an unknown uid
  clearSelection: function () {},

  // mutation — every one of these validates (§9.1), marks dirty, reconciles the DOM,
  // and dispatches builder:tree-changed
  insertBlock: function (node, parentUid, index) {},   // → uid
  removeBlock: function (uid) {},                      // → bool; no confirmation, the caller confirms
  moveBlock: function (uid, parentUid, index) {},      // → bool
  updateBlockData: function (uid, data) {},            // replaces node.data wholesale, refreshes that block
  refreshBlock: function (uid) {},                     // re-runs the type's render() for one block

  // formatting toolbar
  registerFormatCommand: function (def) {},
  setFormatState: function (state) {}
};
```

`updateBlockData` and `refreshBlock` exist for Part 3 and Parts 4–6: when a settings form changes a value, the block type calls `updateBlockData(uid, newData)` rather than reaching into this part's internals. Without them, every later part would mutate the tree behind the canvas's back and the dirty flag would silently stop working.

### 9.1 Client-side validation, using the server's own messages

Every mutation checks these before touching the tree, and **rejects with the validator's exact Persian string** (read them from `includes/builder/tree_validator.php`; they are also listed in §12):

| Check | Mirrors validator rule |
|---|---|
| `header`/`body`/`footer` may exist only at the root, and the root always stays exactly those three in that order | 1, 5 |
| The target type must exist in `BuilderRegistry` | 4 |
| A type with `allowsChildren === false` never receives children | 6 |
| `minChildren` / `maxChildren` of the receiving parent (0 = unlimited) | 7 |
| Total node count after the operation ≤ 500 | 8 |
| Resulting depth ≤ 8, counting a root slot as 1 | 9 |
| The parent's `allowedChildTypes` and the node's `allowedParentTypes` (empty = no restriction) | 11 |

These are a convenience, not a security boundary — the server re-checks all eleven rules regardless. The point is that the user sees the same sentence from the UI that they would have seen from the endpoint.

A rejected mutation changes nothing, returns `false` (or throws from `insertBlock`, which returns a uid on success), and shows the message via `Swal.fire` with the `"خطا"` title.

### 9.2 Events

Dispatched on `document` with `CustomEvent`:

| Event | `detail` | When |
|---|---|---|
| `builder:selection-changed` | `{ uid, node, el }` — all three `null` when cleared | After the selection changes and the toolbar has been repositioned |
| `builder:insert-requested` | `{ parentUid, index, anchorEl }` | On the toolbar's insert control or an inline affordance. Part 3 listens |
| `builder:format` | `{ command, value, uid, node }` | On a format command button. Part 4 listens |
| `builder:tree-changed` | `{ reason: 'insert'\|'remove'\|'move'\|'split'\|'unsplit'\|'data' }` | After any successful mutation, after `markDirty()` |

### 9.3 Reconciliation, not re-rendering

Keep a `Map<uid, HTMLElement>`. After a mutation, walk the affected parent's children list and **move existing elements rather than recreating them**; create elements only for new uids; remove elements whose uid left the tree (destroying their Sortable instances).

This is not an optimisation, it is a correctness requirement: recreating a block element re-runs the type's `render()`, which from Part 4 onward means destroying a `contenteditable` mid-edit and losing the caret, and from Part 5 onward means tearing down a Swiper instance. **A type's `render()` runs exactly once per element**, plus once more each time `refreshBlock(uid)` is called explicitly.

---

## 10. Integration with the shell

- Install the controller at file-parse time: `BuilderShell.setCanvasController({ render, getTree })`. Do not wait for `builder:loaded` — the shell is explicitly order-independent and calls `render` when both it and the tree are ready.
- `render(mountEl, tree, canvas)` stores the tree as this part's own state and draws it into `mountEl`. It is called once.
- `getTree()` returns a deep copy of the current tree in the exact node shape of §0.3 — `data` an object, `children` an array, `uid` untouched. Never return internal references, never return DOM-derived data.
- Call `BuilderShell.markDirty()` after every successful mutation. Never call `markClean()` — the shell owns that.
- **Read-only:** when `BuilderShell.canEdit()` is `false`, render the tree normally but with no toolbar, no handles, no inline affordances, no Sortable instances, and no selection. The public mutation methods reject with the read-only message (§12). Re-check `canEdit()` on `builder:saved` as well, since Part 1 can downgrade to read-only after a load on a `?canvas_id=N` entry.
- Never listen for `builder:loaded` to get the tree — the controller's `render()` is the contract. Listening to both is how you end up rendering twice.

---

## 11. CSS

Create `public/assets/css/builder/20-canvas.css`. Part 1's glob loads it automatically after `10-shell.css`.

- Reuse the custom properties already defined on `#builder-root` by `10-shell.css`. Add your own only for canvas-specific needs, defined on `#builder-root` in the same pattern, with a `body.dark-mode #builder-root` override block.
- RTL-first: logical properties only (`margin-inline-start`, `padding-inline-end`, `inset-inline-start`), never `left`/`right`.
- Style only `#builder-canvas`'s subtree and your toolbar element. Never `style.css`, never `dark-theme.css`, never another part's namespace.
- Reserve these class namespaces for yourself and document them in your changelog: `.builder-block*`, `.builder-toolbar*`, `.builder-canvas-*`, `.builder-col*`. Part 3 must use `.builder-settings*` and `.builder-insert*`; Parts 4–6 use their own type-prefixed names.
- The selected block needs an unmistakable outline; hover needs a lighter one. Both must be visible in dark mode. A block must never shift position when selected — use `outline` (which does not affect layout), not `border`.
- The `ghostClass` and `chosenClass` from §8 need drag styling, and a horizontal container needs its columns to lay out with `display: flex` and equal widths (`flex: 1 1 0`).

---

## 12. Persian strings (verbatim — invent nothing)

Copy exactly, including every ZWNJ (`‌`). Block labels and icons are **never** in this list — they come from the registry.

| Context | String |
|---|---|
| Insert control / inline affordance | `افزودن بلاک` |
| Column split control | `ستون‌بندی` |
| Delete control | `حذف بلاک` |
| Drag handle | `جابه‌جایی` |
| Empty children list | `هنوز بلاکی اضافه نشده است` |
| Content type with no renderer yet | `نمایش این بلاک هنوز اضافه نشده است` |
| Unknown / ghost type | `این نوع بلاک شناخته نمی‌شود` |
| Delete confirm — title | `حذف بلاک` |
| Delete confirm — text | `این بلاک و همه‌ی محتوای داخل آن حذف می‌شود.` |
| Delete confirm — confirm button | `حذف` |
| Delete confirm — cancel button | `انصراف` |
| Column reduce confirm — title | `حذف ستون‌های پر` |
| Column reduce confirm — text | `ستون‌هایی که حذف می‌شوند محتوا دارند. ادامه می‌دهید؟` |
| Read-only rejection | `شما اجازه‌ی ویرایش این بوم را ندارید.` |
| Error dialog title | `خطا` |

These five come from `includes/builder/tree_validator.php` — read them from the file and copy them character for character rather than from this table, in case the shipped file differs:

| Context | String (as specified in Part 0 §11) |
|---|---|
| Unknown type | `نوع بلاک نامعتبر است` |
| Structural type off-root | `بلاک ساختاری فقط می‌تواند در ریشه قرار بگیرد` |
| Children not allowed / count wrong | `تعداد فرزندهای بلاک مجاز نیست` |
| Too many blocks | `تعداد بلاک‌ها بیش از حد مجاز است` |
| Too deep | `عمق تودرتویی بیش از حد مجاز است` |
| Illegal placement | `این نوع بلاک در این محل مجاز نیست` |

The blueprint's `"حذف قطعی"` label (§12) is for permanent server-side deletion, such as removing a media file. Removing a block from an unsaved tree is not that — it is undone by leaving without saving — so `"حذف"` is the correct confirm label here. Do not substitute one for the other.

---

## 13. How to test this part before Parts 3 and 4 exist

There is no insert menu and there are no content types yet, so the canvas starts with three empty slots and nothing to drag. Do what Parts 0 and 1 did: build throwaway fixtures, then delete them.

1. Create a temporary block type file at `public/assets/js/builder/types/zz_probe.js` registering a `content` type — `type: 'zz_probe'`, `supportsTextFormatting: true`, `allowsChildren: false`, a `defaultData()` returning `{}`, and a `render(node, el)` that writes a short marker string (use `textContent`, never `innerHTML`). Create a second, `zz_box`, with `allowsChildren: true` and no `render()`, to exercise the nesting and no-renderer paths.
2. Drive the canvas from the console with `BuilderCanvas.insertBlock(BuilderCore.createNode('zz_probe'), <bodyUid>, 0)` and the other public methods.
3. Exercise the ghost path by saving a tree containing `zz_probe`, deleting the type file, and reloading — the block must come back as an unknown-type placeholder and survive another save unchanged.
4. Delete both fixture files, and delete every canvas and block row you created, when you are done. State in your changelog that you did.

---

## 14. Acceptance checklist

Verify each item by execution and report the result of each in your changelog.

- [ ] `modules/pages/index.php` contains the `ui/*.js` glob before you start (§0.1); if it did not, you stopped and reported instead of adding it.
- [ ] Exactly three files created, no file modified. `git status` (or mtimes) shows nothing else — in particular no Part 0 or Part 1 file, no `style.css`, no file in `types/` left behind.
- [ ] `00-sortable.js` is the unmodified upstream UMD bundle; version, source URL and SHA256 are in the changelog.
- [ ] `window.BuilderCanvas` is the only global `10-canvas.js` defines (compare `Object.keys(window)` before and after).
- [ ] A blank canvas renders three root slots labelled and iconed from the registry, in the order `header`, `body`, `footer`, each with the empty-state text and an inline add affordance. No `switch` on type names exists in the file for anything other than the five structural types.
- [ ] `BuilderShell.getTree()` on an untouched canvas returns a tree byte-identical to what `load.php` returned (compare `JSON.stringify` of both).
- [ ] Inserting `zz_probe` into `body` calls its `render()` exactly once; a second insert does not re-run the first block's `render()` (count the calls).
- [ ] A registered content type **without** `render()` shows `"نمایش این بلاک هنوز اضافه نشده است"`; an unregistered ghost type shows `"این نوع بلاک شناخته نمی‌شود"` with the type string escaped, and survives a save → reload → save round trip with its `uid`, `type` and `data` unchanged (compare the raw `load.php` response before and after).
- [ ] Clicking a block selects it; clicking another moves the selection; clicking empty canvas clears it. `builder:selection-changed` fires with the right detail each time, and exactly once per change.
- [ ] The toolbar appears above the selected block, follows it on scroll and on window resize, and hides on deselect. Only one toolbar element exists in the DOM regardless of how many blocks are selected over time.
- [ ] Toolbar composition: selecting a root slot shows no toolbar at all (no insert, split, delete, or formatting group — verify the toolbar element stays hidden); a `zz_probe` block (formatting flag true, but no commands registered) shows insert, split and delete but **no** formatting group; after `BuilderCanvas.registerFormatCommand({id:'bold',icon:'bi-type-bold',title:'درشت'})` the group appears on `zz_probe` and stays absent on a block whose flag is false. `setFormatState({bold:true})` marks it pressed.
- [ ] Clicking a format button dispatches `builder:format` with `{command:'bold', uid, node}` and changes nothing in the DOM or the tree.
- [ ] The insert control and every inline affordance dispatch `builder:insert-requested` with a `parentUid`, an `index`, and an `anchorEl` that is in the document. No menu opens — nothing else happens at all.
- [ ] `getInsertableBlockTypes` appears nowhere in the file (grep).
- [ ] Splitting a `zz_probe` inside `body` into 3 produces `body → column_container → 3 × column`, with the original block inside the first column, built by `createColumnContainer` (verified by the resulting node shape: a container with exactly three `column` children and `data: {}`).
- [ ] Raising 3 → 4 appends one empty column; 4 is the maximum offered.
- [ ] Reducing 4 → 3 with an empty trailing column does not prompt. Reducing when the trailing column has content prompts with `"حذف ستون‌های پر"`; cancelling leaves the tree **exactly** as it was (compare `JSON.stringify` before and after).
- [ ] Reducing to 1 removes both the container and the column, splicing the surviving column's children into the container's old index in its parent, order preserved.
- [ ] Splitting a block inside a column works (`cc → column → cc` is legal); the split control is never offered on a `column_container` or a `column` or a root slot.
- [ ] At the depth limit the split values are disabled, and calling the API anyway is rejected with `"عمق تودرتویی بیش از حد مجاز است"` and no tree change. Build the fixture with nested splits until `body(1) → cc(2) → col(3) → cc(4) → col(5) → cc(6) → col(7) → zz_probe(8)`.
- [ ] Inserting a 501st node is rejected with `"تعداد بلاک‌ها بیش از حد مجاز است"`; inserting a `column` into `body` and a `column_container` directly into a `column_container` are both rejected with `"این نوع بلاک در این محل مجاز نیست"`; inserting a `header` anywhere is rejected.
- [ ] Dragging a block by its handle reorders it among its siblings; `builder:tree-changed` fires with `reason: 'move'`; the saved tree reflects the new order (verify the `sort_order` column in `builder_blocks` after a save).
- [ ] Dragging is impossible between two different parents (attempt it and observe the block returns), impossible on the three root slots, and possible between `column` siblings inside one container.
- [ ] Removing a block that had a Sortable instance leaves no leaked instance (track created/destroyed counts over 20 insert/remove cycles; they must match).
- [ ] Delete prompts with `"حذف بلاک"` / `"حذف"` / `"انصراف"`; cancelling changes nothing; confirming removes the node and its whole subtree; delete is absent on root slots and on `column`.
- [ ] Every successful mutation calls `markDirty()` exactly once — the `"ذخیره نشده"` badge appears — and `builder:tree-changed` fires once with the right `reason`.
- [ ] A rejected mutation does **not** mark dirty and does not fire `builder:tree-changed`.
- [ ] `updateBlockData(uid, {x: 1})` replaces that node's `data`, marks dirty, re-runs only that block's `render()`, and the new data survives a save → reload.
- [ ] Full end-to-end: build a tree of at least three nesting levels with a split, press Save, reload the page, and confirm the canvas comes back identical — same uids, same order, same nesting (compare `JSON.stringify(BuilderCanvas.getTree())` before and after the reload).
- [ ] Read-only (a `pages.view`-only account): the canvas renders, no toolbar, no handles, no affordances, no selection on click, and every mutation method rejects with `"شما اجازه‌ی ویرایش این بوم را ندارید."`.
- [ ] Dark mode renders the canvas, chrome, toolbar and drag states legibly; the layout does not shift when a block is selected; the canvas is usable at a 390 px viewport width.
- [ ] All fixtures cleaned up: `zz_probe.js`, `zz_box.js`, and every test canvas/block row deleted.

---

## 15. Deviations and decisions made by this prompt

Recorded so nobody re-derives or contradicts them later. These are decisions, not suggestions.

1. **The formatting group is a registration point with zero built-in commands** (§6.3), instead of the fixed bold/italic/list/align icon set a literal reading of roadmap §5.2 implies. The gating flag, the group's place in the toolbar and the chrome all stay in this part; the command list moves to whoever owns the text model.
2. **SortableJS is vendored locally rather than loaded from a CDN** (§5), against the base panel's convention for Bootstrap/Icons/SweetAlert2, for offline development, single-mechanism loading, and pinning.
3. **Drag and drop is sibling-only** (§8), the conservative reading of "SortableJS for sibling reordering" in roadmap §5.2. Every move nevertheless routes through one `moveBlock(uid, parentUid, index)` that validates as if the move were cross-container, so enabling cross-container drag later is a Sortable `group` configuration change plus a flag — not a rewrite (§14 item 4 below hands it forward).
4. **The settings panel opens from selection, not from a fifth toolbar button.** Roadmap §5.2 fixes the toolbar at four controls and §5.3 says the panel "closes on deselect or outside click", which only makes sense if selection is what opens it. This part therefore ships `builder:selection-changed` as that trigger and builds no panel.
5. **`updateBlockData()` and `refreshBlock()` are added beyond the roadmap's text** (§9), because Part 3's settings forms and Parts 4–6's blocks need a sanctioned way to write into the tree. Without them each later part would mutate this part's state directly and the dirty flag would quietly stop working.
6. **Reconciliation instead of re-rendering** (§9.3), with the guarantee that a type's `render()` runs once per element. This is a hard contract for Parts 4–6, not an optimisation.
7. **The toolbar is a single floating element**, not one per block: fewer DOM nodes, one positioning code path, and a single anchor for Part 3 to align its panel against.
8. **`column` blocks get neither delete nor split** (§6.2). Their count is a property of their container, and a direct delete would violate `minChildren: 2` — a rule this part cannot fix, since the registry is Part 0's.
9. **Client-side validation reuses the server validator's exact strings** (§9.1) rather than friendlier UI copy, so one rule never produces two different sentences.
10. **The toolbar insert control is hidden on root slots** (§6.2). Inserting "after" a root slot would mean inserting a fourth node at the tree root, which validator rules 1 and 5 reject — so the control does not exist there, children reach a slot only through its inline affordance, and `builder:insert-requested` always carries the uid of a real parent block.

---

## 16. Open items handed to a later part (do not build them now)

Record these in your changelog so they reach the roadmap's Appendix:

1. **Cross-container drag** (moving a block from `body` into a `column`, or between columns) is not implemented. `moveBlock()` already validates for it; enabling it is a Sortable `group` change plus drop-time validation. It should be scoped explicitly, most naturally as a small addition alongside Part 3.
2. **`window.BuilderCanvas.registerFormatCommand` is the agreed contract for Part 4's formatting commands**, and `setFormatState` the agreed way to sync pressed states. Part 4 defines the commands; nothing else may.
3. **Selection is the settings panel's open trigger** (§15 item 4) — Part 3 listens to `builder:selection-changed` and must not add a toolbar button.
4. **No block-level undo.** A mis-drag or a mistaken delete is recoverable only by leaving without saving. If this becomes painful in real use, it is its own part, not a patch to this one.
5. **The two base-panel Change Requests from roadmap §8 remain open** and untouched: the dashboard `"تعداد ساخت سایت"` card still counts the legacy `pages` table, and `api/templates/activate.php` still does not sync a newly activated template's canvas title into `site_settings.site_name`.

---

## 17. Mandatory final step

Once you finish everything above, write a complete changelog of this work: every file you created or modified, every decision you made that this master prompt left open (there should be very few — if you had to make one, say exactly what and why), anything in this master prompt that turned out to be wrong, missing, or impossible to follow exactly as written and what you did instead, the result of every item in the §14 acceptance checklist, and the exact current state of everything future parts will depend on — the final shape of `window.BuilderCanvas` (every method signature, what each returns, and exactly which Persian message each rejection produces), the four `document` events and their `detail` shapes, the `registerFormatCommand` / `setFormatState` contract, the CSS class namespaces you reserved and the ones you left free for Parts 3–6, the DOM structure of a rendered block (the wrapper, the handle, the label, `.builder-block-content` as the type's territory and `.builder-block-children` as yours), the guarantee about how many times a type's `render()` is called and what triggers a re-run, the SortableJS version and how it is loaded, and the exact `ui/NN-name.js` naming convention the next part must follow to get its own script on the page. Save it as `changelogs/changelog-part-2-canvas-engine.md`.
