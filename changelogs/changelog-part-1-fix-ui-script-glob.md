# Changelog — Part 1 fix: builder UI script glob

Date: 2026-09-21. Fix-only amendment per the Part 1 UI-glob prompt
("serious — breaks an interface a later part depends on"). Verified by
execution against `php -S` + MySQL. Part 2 was not started.

## 1. Files modified (exactly two, nothing else)

- `modules/pages/index.php` — added the `ui/*.js` glob block (13 lines +
  4-line comment), nothing else changed.
- `public/modules/pages/index.php` — identical addition, byte-identical copy.
- New SHA256 (both files):
  `6F3912BD85B52ADFDA50B8E6AFC4B92F7CCED67D4329634709301BE1DD1D618C`
  (previous Part 1 hash was `8D15…`; the delta is only the glob block).
- `changelogs/changelog-part-1-fix-ui-script-glob.md` — this file.

A last-30-minutes mtime sweep of the repo (excluding `vendor/`) listed only
these two files. The temporary `assets/js/builder/ui/` proof folder was
removed (`Test-Path` confirms it is gone); no file was created inside it by
this fix. `builder-shell.js`, `10-shell.css`, every Part 0 file, and every
other Part 1 line (descriptor, layout, strings, read-only logic, CSS glob)
are untouched.

## 2. Exact glob added and its position

Sits after the `builder-shell.js` emit and before the footer include, i.e.
after the CSS glob and after the `BuilderAssets::clientScripts()` loop:

```php
// Builder UI scripts: alphabetical glob over PUBLIC_PATH/assets/js/builder/ui/*.js.
// Later parts drop NN-name.js files here (00-sortable.js, 10-canvas.js,
// 20-settings-panel.js, ...) so they load after window.BuilderShell exists
// without any edit to this screen. An empty or missing folder emits nothing.
$builderUiFiles = glob(PUBLIC_PATH . '/assets/js/builder/ui/*.js');
if (is_array($builderUiFiles)) {
    sort($builderUiFiles, SORT_STRING);
    foreach ($builderUiFiles as $uiFile) {
        $uiWeb = '/assets/js/builder/ui/' . basename($uiFile);
        echo '<script src="' . e($uiWeb) . '?v=' . (int) filemtime($uiFile) . '"></script>' . "\n";
    }
}
```

Conventions documented in the comment (so later parts follow without being
told): folder `assets/js/builder/ui/`, files named `NN-name.js`
(`00-sortable.js`, `10-canvas.js`, `20-settings-panel.js`, …) so plain
alphabetical order is the deterministic execution order; plain `<script>`
tags (no `defer`/`async`/`module`), each with `?v=filemtime`; `glob()`
returns `[]` for a missing/empty folder and the `is_array()` guard also
covers `false`, so nothing is emitted and no warning/notice is possible.
`window.BuilderShell` is defined by the preceding tag, so every UI script
runs after it exists.

## 3. §4 acceptance checklist — result of every item

- [x] Both copies byte-identical; new SHA256 `6F39…` stated above.
- [x] With `ui/` absent the page renders exactly as before: no PHP
  warning/notice/fatal in the HTML, no `/ui/` script tag, builder order
  `block-registry.js, builder-core.js, builder-shell.js` intact.
- [x] `ui/00-a.js` + `ui/10-b.js` (one `console.log` each) both appear after
  `builder-shell.js`, each with `?v=`; deleted → both disappear. No file
  edited for any of this.
- [x] `ui/99-z.js` alongside them appears last (sorted order 00, 10, 99).
- [x] CSS glob, `clientScripts()` loop, and `builder-shell.js` emit exactly
  as before, in the same order.
- [x] `"هنوز قالبی فعال نیست"` was not re-triggered live (active template
  present), but both blocking states render before any asset emission in the
  code path: `?canvas_id=abc` and `?buildable_type=x&new=1` return cards with
  zero `/assets/js/builder/` and zero `/assets/css/builder/` references,
  including no `ui/*.js` — verified over HTTP.
- [x] Read-only (`pages.view`-only fixture account, created and deleted for
  the test): banner renders, canvas assets (incl. `builder-shell.js`)
  render, no action buttons in the DOM.
- [x] Mtime sweep shows exactly the two `index.php` files modified, nothing
  else.

## 4. Prompt deviations / notes

Nothing in the prompt turned out wrong or impossible; implemented literally.
One test-harness note (not a product issue): the footer's own scripts
(Bootstrap bundle, SweetAlert2, `admin.js`, `notifications.js`) load after
`builder-shell.js` by pre-existing design — "shell last" was asserted among
builder scripts only, per §7.4 of the Part 1 prompt.

## 5. Confirmation

Nothing else in Part 1 changed: descriptor attributes, two-column layout,
all §13 strings, read-only gating, save/preview/publish wiring,
`builder-shell.js` (still sole global `window.BuilderShell`), `10-shell.css`,
the CSS glob, and the `window.BuilderPreview` hook contract are exactly as
the Part 1 changelog records them.
