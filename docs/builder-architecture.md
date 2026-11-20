# Builder architecture

Scope: the visual page builder that ships with this panel (Parts 0–9). This
document is the contract future work (including a GrapesJS adapter) must follow.

## 1. Layers

```
PHP  builder_registry (types/*.php)   tree_validator.php   html_sanitizer.php
     canvas_repository.php            renderer.php         entry.php
        |                                   ^
        v                                   |
     JSON API  /api/builder/{load,save,delete,activate,render,media}.php
        |                                   ^
        v                                   |
JS   BuilderShell  (load / save / publish / optimistic version)
        |  setCanvasController({render,getTree})
        v
     engine = canvas controller  (built in: ui/10-canvas.js -> window.BuilderCanvas)
        |
        v
     chrome modules: toolbar (inside canvas, ui/10-canvas.js)
                     settings panel (ui/20-settings-panel.js)
                     insert menu    (ui/21-insert-menu.js)
                     media picker   (ui/40-media-picker.js)
                     page actions   (ui/50-page-actions.js)
                     shared pure tree rules (builder-tree.js -> window.BuilderTree)
```

One global per JS file. No cross-file calls at load time; modules talk through
`window.Builder*` and `document` events. `builder-tree.js` has no DOM and no
module state: every function takes the tree (or a node) as an argument, and it
only reads `window.BuilderRegistry` at call time.

Two further globals are documented by the contract manifest and expose no
methods (they render outside the editor): `BuilderPreview` (`ui/30-preview.js`,
opens `api/builder/render.php`) and `BuilderStoryViewer` (`types/21-story.js`,
the canvas story renderer).

## 2. Tree format and invariants

A node is `{ "uid": string, "type": string, "data": object, "children": [] }`.
The tree is a JSON array of root nodes.

- Exactly three structural roots, in order: `header`, `body`, `footer`.
  Roots are detected by position (depth 1), never by name.
- `uid`: 32 lowercase hex chars, unique within a canvas (`bin2hex(random_bytes(16))`).
- `type`: one of the registered block types (below), or the structural set.
- Limits (mirrored from `tree_validator.php`): max 500 blocks, max depth 8.
- `column_container` holds 2–4 `column` children; a structural root or a
  `column` holds content blocks.
- Who validates what: the browser (`BuilderTree.checkInsert`) gives immediate
  feedback only; **the server is the source of truth**
  (`includes/builder/tree_validator.php` + `html_sanitizer.php`). A client that
  skips the checks still cannot store an invalid tree.

## 3. Engine contract

The shell installs a canvas controller once it has loaded a canvas:

```js
BuilderShell.setCanvasController({
  render(mountEl, tree, canvas),   // draw the tree into mountEl
  getTree()                        // return the (possibly edited) tree
});
```

Whether the controller installs is decided by `#builder-root`'s
`data-engine` attribute: the built-in blocks engine installs when it is
`"blocks"` or absent. A different engine (e.g. a future GrapesJS adapter) ships
its own file and installs itself the same way when the attribute holds its name.
This document and the tree stay the single source of truth in every case.

### `BuilderCanvas` API v1 (built-in engine)

| Method | One line |
|---|---|
| `getTree()` | Returns the current tree (a deep copy). |
| `getNode(uid)` | Returns a deep copy of the node with that uid, or `null`. |
| `getSelected()` | Returns `{uid, node, el}` for the selected block, or `null`. |
| `getCanvasElement()` | Returns the mount element the tree is rendered into. |
| `getToolbarElement()` | Returns the in-canvas toolbar element. |
| `getBlockElement(uid)` | Returns the DOM element rendering that block, or `null`. |
| `select(uid)` | Selects the block (and shows its toolbar). |
| `clearSelection()` | Clears the current selection. |
| `insertBlock(type, parentUid, index)` | Inserts a new block, returns its uid or `null`. |
| `removeBlock(uid)` | Removes a block (and its subtree). |
| `moveBlock(uid, parentUid, index)` | Moves a block in the tree. |
| `updateBlockData(uid, patch)` | Merges `patch` into the node's `data`. |
| `refreshBlock(uid)` | Re-renders one block in place. |
| `registerFormatCommand(def)` | Registers one inline-format command (`{id,icon,title,group?,value?}`). |
| `setFormatState(state)` | Updates the toolbar's pressed state (`{commandId:bool}`). |

### Events

All events are `CustomEvent`s on `document`; names are prefixed `builder:`.

| Event | Emitter | Payload | Listeners |
|---|---|---|---|
| `loaded` | shell | `{canvas, tree}` | page actions |
| `before-save` | shell | `{}` | text block (flush pending edits) |
| `saved` | shell | `{canvas, block_count, buildable_type_ignored, published_now}` | canvas (re-render), page actions |
| `published` (cancelable) | shell | `{canvas}` | page actions |
| `error` | shell | `{stage, status, message}` | chrome (optional) |
| `selection-changed` | canvas | `{uid, node, el}` (or all `null`) | settings panel, insert menu |
| `tree-changed` | canvas | `{reason}` | insert menu |
| `insert-requested` | canvas | `{parentUid, index, anchorEl}` | insert menu |
| `settings-requested` | canvas | `{uid}` | settings panel |
| `format` | canvas | `{command, value, uid, node}` | text block |

## 4. Block-type contract

A block type is declared twice, kept in sync by hand:

- Server: `includes/builder/types/<name>.php` returns the definition array
  (schema in `includes/builder/block_registry.php`: `type, label, icon,
  category, allows_children, min_children, max_children,
  supports_text_formatting, allowed_child_types, allowed_parent_types,
  default_data`).
- Client: `public/assets/js/builder/types/<nn-name>.js` calls
  `window.BuilderRegistry.registerBlockType({...})` and renders the block and
  its settings panel.

Content types shipped: `text`, `image_video`, `button`, `slider`, `story`.
Structural types: `header`, `body`, `footer`, `column_container`, `column`.

To add a block type:
1. Add `includes/builder/types/<name>.php` with the definition and `default_data`.
2. Add `public/assets/js/builder/types/<nn-name>.js`: register the type, and draw
   the block (`render(node, el)`) and its settings panel (`renderSettingsPanel`).
   Both files are auto-discovered (glob), no manifest edit needed.
3. If it accepts a `media_id`, wire it through `BuilderMediaPicker` and make sure
   `includes/builder/types/<name>.php` feeds `builder_media_refs` (see §5).
4. Run the guard rails (§9): the browser smoke test inserts and selects every
   type; the PHP registry test validates the definition shape.

## 5. Media contract

- `BuilderMediaPicker.open({kind, title, onSelect})` — modal picker; `kind` is
  `image|video|any`; `onSelect(info)` receives `{id, path, ...}`.
- `BuilderMediaPicker.getInfo(id, cb)` — resolves one library file by id.
- `BuilderMediaPicker.upload(file, onProgress, cb)` — uploads through
  `POST /api/media/upload.php` (gated by `media.edit`).
- `BuilderMediaPicker.field(...)` — inline picker field for settings panels.
- `GET /api/builder/media.php` — read-only list/one-by-id for the picker; gated
  by `pages.view` (so page editors without the media module can still pick);
  returns only `purpose='library'` rows.
- Which files a canvas uses is tracked in `builder_media_refs`
  (`canvas_id, media_id`), refreshed on save by
  `includes/builder/media_refs.php`. `fk_bmr_media` is `ON DELETE RESTRICT`, so
  the database itself refuses to delete a file a page still uses.

## 6. Site map

Built from `includes/access_map.php`, `includes/sidebar.php`, `database/schema.sql`
and the permission seed.

| Module key | Menu label | URL | Permission | DB tables | API endpoints |
|---|---|---|---|---|---|
| `dashboard` | پیشخوان | `/modules/dashboard/index.php` | view | `dashboard_snapshots`, counts over `builder_canvases` | — |
| `pages` | ساخت سایت | `/modules/pages/index.php` | view, edit | `builder_canvases`, `builder_blocks`, `builder_media_refs` | `/api/builder/{load,save,delete,activate,render,media}.php` |
| `articles` | صفحات ساخته شده | `/modules/articles/index.php` | view | `builder_canvases` (read-only list) | — |
| `templates` | قالب سایت | `/modules/templates/index.php` | view, edit, delete | `templates`, `template_categories`, `builder_canvases` (template canvases) | `/api/builder/activate.php` |
| `media` | رسانه و عکس‌ها | `/modules/media/index.php` | view, edit, delete | `media_library`, `builder_media_refs` | `/api/media/upload.php`, `/api/builder/media.php` |
| `licenses` | مجوزات | `/modules/licenses/index.php` | view | `licenses` | — |
| `settings` | تنظیمات پنل | `/modules/settings/{general,profile,staff}.php` | view (staff = owner-only, profile = login-only) | `site_settings`, `panel_users`, `panel_user_permissions`, `permissions` | — |

Permission actions map to the `panel_user_permissions` columns: `view` →
`can_view`, `edit` → `can_edit`, `delete` → `can_delete`.

Two things to know when reading the code:

- The module key **`articles`** is the **page-list** module ("صفحات ساخته شده");
  it lists builder canvases of `buildable_type='page'`. It is unrelated to the
  legacy `articles` table.
- The legacy `pages`, `page_blocks`, `articles`, `article_blocks`,
  `article_categories`, `media_usage` tables (still printed in the baseline
  `database/schema.sql`) were **dropped by migration `0006`**; the application no
  longer references them. `dashboard_snapshots.articles_count` is vestigial.

## 7. GrapesJS adapter plan (not built in this part)

Decisions, not options:

- The tree stays the single source of truth. A GrapesJS integration is *a canvas
  controller* (`render`, `getTree`) plus chrome replacements. It never stores
  HTML as truth and always saves through `/api/builder/save.php`.
- Mapping: GrapesJS component `type` ↔ `node.type`, component
  attributes/traits ↔ `node.data`, `components` ↔ `children`. Each existing
  block type becomes one custom GrapesJS component type; **unknown components are
  refused by the adapter**, because the PHP validators and
  `includes/builder/html_sanitizer.php` are the whitelist.
- Free-form HTML from GrapesJS needs a new block type (e.g. `custom_html`)
  validated server-side by `html_sanitizer.php` first — not before.
- Asset Manager → `BuilderMediaPicker.open({kind, onSelect})` and
  `/api/builder/media.php`.
- Storage Manager → a thin adapter around `BuilderShell.save()` /
  `load.php`, keeping the optimistic `version` check.
- Vendor files live in `public/assets/vendor/<name>/<version>/` (local copies, no
  CDN) — see `public/assets/vendor/README.md`.
- GrapesJS renders in an iframe; this builder's CSS is scoped by `#builder-root`
  / `#builder-canvas` and the `.builder-` prefix, which keeps the two worlds
  apart. Keep it that way: no new bare element selectors.

## 8. Known limits and next refactor

- The built-in engine keeps the toolbar and column split/unsplit inside
  `ui/10-canvas.js` (one file by decision). Splitting those into their own
  modules is the next clean-up.
- No public route serves published pages to visitors yet; published canvases are
  stored, not served.
- `builder_canvases` has no SEO fields (the old `pages` table had
  `meta_title`/`meta_description`); the public site will need them — that
  requires a migration.
- GrapesJS integration, undo, cross-container drag, touch swipe on the rendered
  slider, and Imagick are explicitly out of scope for now.
