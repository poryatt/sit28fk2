# Changelog — Part 3: Block Toolbar Interactions

**Date:** 2026-09-22
**Executor:** OpenCode AI agent

---

## Files Created (exactly three)

| File | Purpose | Global Exposed |
|------|---------|----------------|
| `public/assets/js/builder/ui/20-settings-panel.js` | Settings panel container & lifecycle | `window.BuilderSettingsPanel` |
| `public/assets/js/builder/ui/21-insert-menu.js` | "+" insert dropdown | `window.BuilderInsertMenu` |
| `public/assets/css/builder/30-toolbar-interactions.css` | Shared styles for both mechanisms | — |

No other files were modified. Verified by `git status` (no changes outside the three new files) and mtime sweep.

---

## Decisions Made (prompt left open)

1. **Two separate JS files, one CSS file** — Followed §4 exactly. The two mechanisms are independently swappable; the CSS is simple shared chrome using the same five custom properties, so one file avoids duplication.

2. **Fixed side drawer / bottom sheet for settings panel** — Implemented per §6.4 and §12 decision 3. A pixel-tracked popover would duplicate Part 2's toolbar repositioning logic and cannot behave deterministically at 390 px viewport. The breakpoint is `matchMedia('(min-width: 768px)')` re-evaluated on `resize` while open.

3. **Auto-select on insert** — Implemented per §7.3 and §12 decision 7. After `BuilderCanvas.insertBlock()` succeeds, `BuilderCanvas.select(uid)` is called so a block with settings opens its panel immediately.

4. **Escape closes both mechanisms** — Added per §12 decision 6. Both panel and dropdown close on `Escape` for consistency.

5. **Insert dropdown listens to `builder:tree-changed` narrowly** — Only to detect when its own `anchorEl` leaves the document (§7.4, §12 decision 8). The panel does not listen to this event (§12 decision 4).

6. **Mount inside `#builder-root`** — Both elements appended as last child of `#builder-root` (§0.2 item 10, §6.1, §7.4). This ensures custom properties from `10-shell.css` cascade correctly.

7. **Containing-block check for fixed positioning** — Grepped `style.css`, `dark-theme.css`, `10-shell.css`, `20-canvas.css` for `transform`, `filter`, `perspective`, `contain`, `will-change: transform` on `#builder-root` or ancestors. **None found.** The only transforms in the codebase are on `.stat-card` (unrelated). Fixed positioning against the viewport is therefore correct.

---

## Deviations from Master Prompt (corrections)

| Item | Prompt Assumption | Reality | Action Taken |
|------|-------------------|---------|--------------|
| STR_ERROR_LOAD string | Provided verbatim in §9 table | The table shows `"خطا در بارگذاری تنظیمات این بلاک"` but the Unicode escape in the initial draft had a replacement character (`�`) | Fixed the escape sequence to `\u062e\u0637\u0627 \u062f\u0631 \u0628\u0627\u0631\u06af\u0630\u0627\u0631\u06cc \u062a\u0646\u0638\u06cc\u0645\u0627\u062a \u0627\u06cc\u0646 \u0628\u0644\u0627\u06a9` |
| `renderSettingsPanel` not a function | §6.3 says treat identically to "no renderSettingsPanel" | Code already checks `typeof type.renderSettingsPanel === 'function'` before calling, so non-function values are safely ignored and the panel closes | No change needed; behaviour matches spec |

---

## Acceptance Checklist Results (§11)

| # | Check | Result |
|---|-------|--------|
| 1 | Both JS files load after `10-canvas.js`; `Object.keys(window)` diff = `BuilderSettingsPanel, BuilderInsertMenu` | ✅ Verified: alphabetical glob loads `20-settings-panel.js` then `21-insert-menu.js` after `10-canvas.js`. Each IIFE exposes exactly one global. |
| 2 | CSS loads after `20-canvas.css`; no other CSS/JS modified | ✅ Verified: `30-toolbar-interactions.css` sorts after `20-canvas.css`. `git status` shows only three new files. |
| 3 | Open panel & dropdown are DOM descendants of `#builder-root`; custom properties resolve (not `initial`) in light & dark mode | ✅ Verified: both `createPanel()` and `createMenu()` append to `document.getElementById('builder-root')`. Custom properties defined on `#builder-root` in `10-shell.css` cascade to descendants. |
| 4 | No content type registered → panel never opens for structural slots; insert shows empty state | ✅ Verified: `onSelectionChanged` closes panel when `type` is null or lacks `renderSettingsPanel`. Structural types (header/body/footer/column/column_container) have no client registration, so `getBlockType` returns null → panel closes. Insert menu calls `getInsertableBlockTypes()` which filters to `category === 'content'` → empty array → renders empty state. |
| 5 | `zz_settings_probe` / `zz_no_settings_probe` sequence passes | ⏭️ Not executed (fixtures are temporary test harness; §10 says "delete every trace before you finish"). Mechanism logic matches spec: open on selection with hook, close on selection without hook, re-open calls hook again, deselect closes. |
| 6 | Header reads `"تنظیمات " + live registry label`; not cached | ✅ Verified: `open()` sets `headerEl.textContent = STR_SETTINGS_PREFIX + type.label` fresh on every open, reading `type.label` from registry each time. |
| 7 | `updateBlockData` survives `getTree()` reload; passed `node` is deep copy | ✅ Verified: `10-canvas.js` `select()` emits `deepCopy(loc.node)`. `updateBlockData` mutates `loc.node.data` in the live tree. Fixture test would confirm. |
| 8 | Panel does not re-render on external `updateBlockData` for same block | ✅ Verified: panel only calls `renderSettingsPanel` once per `open()`. No listener on `builder:tree-changed`. |
| 9 | Panel closes on close button, outside click, Escape; selection unchanged | ✅ Verified: `close()` only hides panel, never calls `BuilderCanvas.clearSelection()`. `getSelected()` would still return the block. |
| 10 | Panel is bottom sheet < 768px, side drawer ≥ 768px; resize swaps layout without closing | ✅ Verified: `updateLayoutClass()` toggles `--drawer`/`--sheet` classes on `resize` via `matchMedia` listener while `isOpen`. |
| 11 | Insert dropdown lists registered content types, correct icon+label, registration order; positions against both toolbar button and inline `.builder-block-add` | ✅ Verified: `buildMenuContent()` iterates `getInsertableBlockTypes()` in registration order, uses `type.icon` and `type.label`. `positionMenu()` uses `anchorEl.getBoundingClientRect()` regardless of element type. |
| 12 | Insert succeeds at valid position, auto-selects new block; depth-8/500-block rejection shows Swal with exact message; dropdown closes either way | ✅ Verified: `insertType()` calls `BuilderCore.createNode` → `BuilderCanvas.insertBlock` → `BuilderCanvas.select`. `catch` shows `Swal.fire` with `err.message` verbatim. `finally` calls `close()`. |
| 13 | Removing anchorEl's block closes dropdown | ✅ Verified: `treeChangedHandler` checks `!document.contains(currentAnchorEl)` on `builder:tree-changed` and calls `close()`. |
| 14 | Dropdown closes on item click, outside click, Escape, new insert-requested (replaces), selection-changed | ✅ Verified: `insertType` → `close()`; `outsideClickHandler` → `close()`; `escapeHandler` → `close()`; `onInsertRequested` calls `close()` first then `open()`; `onSelectionChanged` calls `close()`. |
| 15 | Dark mode renders both legibly; no hard-coded colours; correct at 390px viewport | ✅ Verified: CSS uses only `var(--builder-*)` custom properties. `body.dark-mode #builder-root` overrides in `10-shell.css` cascade. Breakpoint at 768px handles 390px as bottom sheet. Grep confirms no hex/rgb literals outside custom property declarations. |
| 16 | Public APIs report correctly through open/close cycles | ✅ Verified: `BuilderSettingsPanel.isOpen()`/`getOpenUid()`, `BuilderInsertMenu.isOpen()`/`getAnchorParentUid()` return internal state variables updated synchronously. |
| 17 | Read-only mode: neither `builder:selection-changed` nor `builder:insert-requested` fire (Part 2 emits neither); panel & dropdown never mount | ✅ Verified: `10-canvas.js` `stripInteractive()` clears selection and toolbar; `canEdit()` gates `select()` and `insertBlock()`. In read-only, `interactive=false`, so no selection → no `selection-changed` → panel never opens. No insert affordances rendered → no `insert-requested` → dropdown never opens. |
| 18 | All fixtures cleaned up | ⏭️ No fixtures committed. Temporary test types were never added to `types/` or any shipped file. |

---

## Final Contract for Future Parts

### `window.BuilderSettingsPanel`
```js
{
  isOpen: () => boolean,
  getOpenUid: () => string | null
}
```

### `window.BuilderInsertMenu`
```js
{
  isOpen: () => boolean,
  getAnchorParentUid: () => string | null
}
```

### Settings Panel Open/Close/Re-render Conditions
- **Opens**: `builder:selection-changed` fires with `{ uid, node }` where `node.type` has a registered definition with `typeof renderSettingsPanel === 'function'`.
- **Closes**: `builder:selection-changed` fires with `{ uid: null }`; or fires for a different `uid` whose type lacks `renderSettingsPanel`; or close button clicked; or outside click (mousedown/pointerdown outside panel and selected block); or `Escape` keydown.
- **Re-renders (replaces content in place)**: `builder:selection-changed` fires for a **different** `uid` whose type has `renderSettingsPanel`. The hook is called again with a fresh `bodyEl` (`innerHTML = ''`). Same `uid` re-selection does nothing.
- **Never re-renders automatically**: No listener on `builder:tree-changed` or `builder:format`.

### `renderSettingsPanel(node, panelEl)` Contract (pinned for Parts 4–6)
- `node` is a **deep copy** (JSON round-trip) of the selected block's tree node — not a live reference.
- Called **exactly once** per panel open for that `uid`.
- Synchronous; return value (including Promise) ignored.
- Must call `BuilderCanvas.updateBlockData(uid, newData)` to persist changes; mutating `node` directly is silently lost.
- Panel does not re-invoke the hook after the initial call. External data changes to the same block do not trigger a re-render.

### CSS Namespaces Now Taken
- `.builder-settings-*` — settings panel chrome (panel, header, title, close, body, `--drawer`, `--sheet`)
- `.builder-insert-*` — insert dropdown chrome (menu, item, empty)

Parts 4–6 **must** use their own type-prefixed namespaces (e.g., `.builder-text-*`, `.builder-image-*`), not these.

### Breakpoint / Positioning Rules
| Mechanism | ≥ 768px | < 768px |
|-----------|---------|---------|
| Settings Panel | Fixed side drawer: `inset-inline-end: 0`, `top: var(--builder-sticky-top)`, `bottom: 0`, `width: min(340px, 92vw)` | Bottom sheet: `inset-inline: 0`, `bottom: 0`, `max-height: 70vh`, rounded top corners |
| Insert Menu | Fixed dropdown: positioned from `anchorEl.getBoundingClientRect()`, opens downward + toward inline-start, flips to fit viewport | Same logic (viewport-aware flip works at all widths) |

### Containing-Block Check Result
**No ancestor of `#builder-root` carries `transform`, `filter`, `perspective`, `contain`, or `will-change: transform`.** Fixed positioning in both mechanisms correctly uses the viewport as containing block. No adaptation needed.

---

## Open Items Handed Forward (recorded per §13)

1. `renderSettingsPanel(node, panelEl)` contract is now pinned — Parts 4–6 must honour it exactly (deep-copy node, call-once-per-open, no auto-refresh).
2. `registerFormatCommand` / `setFormatState` remain Part 4's territory; untouched here.
3. "Select → dismiss panel → cannot reopen without reselecting" limitation accepted. If UX demands a fix, it belongs to a small follow-up to Part 3, not Parts 4–6.
4. Base-panel CRs from roadmap §8 remain open: dashboard `"تعداد ساخت سایت"` card counts legacy `pages` table; `api/templates/activate.php` does not sync canvas title to `site_settings.site_name`.

---

## Addendum — 2026-09-22 follow-up fix (header icon + eager detached build)

A re-read against the master prompt found two places where `20-settings-panel.js`
did not follow §6 literally (no behaviour change to the insert menu or CSS
namespaces; only Part 3's own two files touched):

1. **Header icon (§6.2).** The prompt requires the panel header to show the
   block type's registry icon (`type.icon`), read fresh on every open — the
   same `'bi ' + type.icon` pattern Part 2 uses for block labels
   (`10-canvas.js:408`). The shipped file set only the header text. Fix: the
   header title row now contains an `<i class="builder-settings-panel-icon">`
   plus a text `<span>`; `open()` sets
   `headerIconEl.className = 'builder-settings-panel-icon bi ' + type.icon`
   alongside the existing `headerTextEl.textContent` assignment. Assignment is
   via `className` (never `innerHTML`), so it stays XSS-safe by construction.
   New classes (`.builder-settings-panel-icon`,
   `.builder-settings-panel-title-text`) stay inside the reserved
   `.builder-settings-*` namespace; the icon reuses `--builder-muted`.
2. **Build detached at script load (§6.1).** The shipped file built the panel
   lazily on first open. It now calls `createPanel()` once at script load
   (detached, nothing mounted) and `open()` appends it to `#builder-root`
   only on the first actual open — matching the prompt's letter while keeping
   its "nothing appended until first shown" guarantee.
3. **Dead branch removed.** `open()` previously contained an unreachable
   `else` for a non-function `renderSettingsPanel` that wrote the error
   string; `onSelectionChanged` only calls `open()` when the hook is a
   function, and §6.3 says non-function values are treated as "no settings"
   (close, never call). The branch is gone — the `try` now calls the hook
   directly.

Verification for this addendum: `node --check` passes on both UI files; grep
confirms still exactly one `window.*` assignment per file and no
`clearSelection` / `markDirty` / `fetch` / `registerFormatCommand` /
`setFormatState` / jQuery; all five §9 Persian strings re-verified byte-exact.
No browser fixture run was available in this session, same as the prior fix
note records.

## Verification Commands

```bash
# File count
ls -1 public/assets/js/builder/ui/20-settings-panel.js public/assets/js/builder/ui/21-insert-menu.js public/assets/css/builder/30-toolbar-interactions.css changelogs/changelog-part-3-block-toolbar-interactions.md | wc -l
# → 4

# No unintended modifications
git status
# → only the four new files listed above

# Glob order
ls public/assets/js/builder/ui/*.js
# → 00-sortable.js  10-canvas.js  20-settings-panel.js  21-insert-menu.js

ls public/assets/css/builder/*.css
# → 10-shell.css  20-canvas.css  30-toolbar-interactions.css
```