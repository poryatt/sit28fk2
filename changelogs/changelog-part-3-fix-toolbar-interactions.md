# Changelog — Part 3 Fix: Block Toolbar Interactions

**Date:** 2026-09-22
**Executor:** Claude (direct fix, applied by reading and editing the actual Part 3 files — not routed through a master prompt / OpenCode this time, at Pouya's request)
**Scope:** Corrects 3 real defects found by review against `master-prompt-3-block-toolbar-interactions.md` and the Part 3 changelog. No other part's files were touched.

---

## Files Changed (exactly two)

| File | What changed |
|------|--------------|
| `public/assets/js/builder/ui/21-insert-menu.js` | Fixed a corrupted Persian string; rewrote `positionMenu()`'s flip math |
| `public/assets/css/builder/30-toolbar-interactions.css` | Replaced a hidden dependency on Part 2's CSS variable with a local one; filled in the dead dark-mode rule |

Verified against a byte-diff of the whole repo: no file outside this table was modified.

---

## Fixes

### 1. Corrupted Persian string (§9)

`STR_EMPTY` in `21-insert-menu.js` contained an extra, meaningless word:

- Was: `"هنوز نوع بلاکی برای افزودن ثبت رجست نشده است"`
- Now: `"هنوز نوع بلاکی برای افزودن ثبت نشده است"` — matches §9's table exactly (verified with a byte-level comparison against the master prompt).

### 2. `positionMenu()` flip logic used the wrong edge (§7.4)

The function decides whether to right-align or left-align the dropdown (and whether to open up or down) by comparing available space against the menu's size. It compared the space **on the anchor's own side** (`vw - rect.right`, i.e. room to the anchor's right) when the decision that actually matters is whether there's room **on the side the menu would occupy** (`rect.right`, the room to the left of the anchor's right edge, since a right-aligned menu extends leftward from there). Same mistake, mirrored, on the vertical axis (`vh - rect.top` was used instead of `vh - rect.bottom` for "room below").

Concretely, with a viewport 1000px wide, an anchor at `rect.left=800, rect.right=850`, and a 220px-wide menu: the old code checked `right = vw - rect.right = 150`, saw `150 < 220` and `150 < 800`, and fell back to left-aligning the menu at `x=800`, which then extended to `x=1020` — 20px past the edge of the viewport. The correct check (`rect.right = 850 >= 220`) shows the right-aligned position fits perfectly, which is what the fixed code now does. The same class of bug affected the vertical flip near the bottom of the viewport with a tall menu.

The fix keeps the same defaults the master prompt specifies (open toward the inline-start/right side and downward first, per §7.4) and only changes which distances are compared, plus adds simple clamping so a fallback position can never land at a negative coordinate.

### 3. Hidden dependency on Part 2's CSS + one dead rule (§8, Lego architecture)

`30-toolbar-interactions.css` used `var(--builder-canvas-hover)` for both hover states (settings-panel close button, insert-menu items). That variable is defined in `20-canvas.css` (Part 2's own file), not among the four variables §8 names as reusable (`--builder-surface`, `--builder-border`, `--builder-muted`, `--builder-canvas-bg`). That made Part 3 silently depend on a Part 2 file staying exactly as-is — exactly the cross-part coupling the roadmap's Lego principle exists to prevent.

Fix: added a new module-local variable, `--builder-toolbar-hover-bg`, defined in this file's own `#builder-root` block (light) and `body.dark-mode #builder-root` block (dark) — the same two color values Part 2 uses, so the visual result is unchanged, but Part 3 no longer reaches into another part's file for it. This also gave the previously-empty `body.dark-mode #builder-root { }` rule (dead code) real content instead of just deleting it.

---

## Not Changed

- The §10 test fixtures were not re-run as part of this fix (no browser environment available here). The three fixes above are logic/string corrections verifiable by direct inspection (byte-compare for the string; worked numeric examples for the positioning math; grep for the CSS variable's definition site). If you want the §10 fixture sequence actually exercised in a browser before moving to Part 4, that's still worth doing separately.
- Everything else in the original Part 3 changelog (`changelog-part-3-block-toolbar-interactions.md`) stands: the file count, the mount points, the containing-block check, the public API shape, and the four other Persian strings were all independently re-verified as correct during this review and needed no change.
