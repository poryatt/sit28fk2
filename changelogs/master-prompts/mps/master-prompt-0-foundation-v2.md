# Master Prompt — Part 0: Visual Page Builder Foundation (includes Part 0a)

**Target executor:** an AI coding agent (OpenCode or equivalent) with write access to the existing panel project.
**Project:** Website & Business Management Panel (PHP 8 + PDO + MySQL, Persian/RTL) — see `PANEL-BLUEPRINT.md`.
**Feature:** Visual Page Builder, Part 0 (Foundation) with Part 0a (`supportsTextFormatting`) folded in.
**Deliverable of this part:** database tables, server-side block registry, client-side block registry, tree validation, canvas repository, and the generic `load.php` / `save.php` API. **No user interface of any kind.**

---

## 0. Changes from v1

This is v2. Every change below was applied deliberately; nothing was changed silently.

1. **§8.1 / §8.3 / §10.2 / §10.3 / §11 / §14 — JSON object preservation.** `data` is now a `stdClass` end-to-end (`json_decode(..., false)`, never `assoc=true`, never `JSON_FORCE_OBJECT`); registry `sanitize` is `callable(stdClass): stdClass`; `BuilderBlockRegistry::defaultData()` added; structural `default_data` shown as `{}`. *(Why: `json_decode('{}', true)` returns `[]` and `json_encode([])` emits `[]`, so every empty object silently became an array after the first round-trip.)*
2. **§10.3 step 3 and §16 — `updated_at`.** The existing-canvas `UPDATE` now includes `updated_at = NOW()`. *(Why: `ON UPDATE CURRENT_TIMESTAMP` does not fire when only child rows change.)*
3. **§6.1 / §6.2 / §10.3 / §12.2 / §16 — `template_id` unique and immutable.** Added `UNIQUE KEY uq_builder_canvases_template`; a second create for the same template → HTTP 409; `template_id` removed from the update and a differing posted value → HTTP 400. *(Why: two canvases per template made `findByTemplate()` non-deterministic.)*
4. **§10.3 step 3–4 — slug generation.** New canvases insert `slug = NULL` then set it after `lastInsertId()`; the `-2`…`-50` loop now has a defined fallback to `canvas-{id}`. *(Why: v1 left both the new-canvas slug and the post-`-50` case undefined.)*
5. **§12.2 — order of operations.** `requireLogin()` (step 2) now runs before the CSRF check (step 3), matching §12's intro; the CSRF fallback behavior is spelled out. *(Why: v1's step list contradicted its own intro.)*
6. **§10 signature / §10.3 / §12.2 — `buildable_type_ignored` owner.** `BuilderCanvasRepository::save()` now returns it. *(Why: v1's response required it but no code produced it.)*
7. **§11 rule 4 / §15 — no `e()` over JSON.** The rule-4 message is exactly `"نوع بلاک نامعتبر است"` (no `{type}`), the offending type goes to the server log, and `e()` is documented as HTML-only. *(Why: `htmlspecialchars` before `json_encode` corrupts the string the client sees.)*
8. **§1 — cross-reference fix.** "see §16" → "see §18".
9. **§7 / §8.3 / §8.4 / §9 / §11 / §14 / §17 — new `column` block type and new registry fields.** Five structural types now; `allowed_child_types` / `allowed_parent_types` are required registry fields; validator rule 11 added; nesting depth limit raised 5 → 8; `BuilderCore.createColumnContainer()` added. *(Why: v1 never defined what a `column_container` child is, so a column could hold only one block and adding `column` later would require migrating saved canvases.)*
10. **§10.3 step 7 / §12 / §16 — title write-through only for the active template.** *(Why: v1 renamed the whole site when editing an inactive template.)*
11. **§12.2 / §16 — `tree` must be a JSON array.** `save.php` rejects a non-array `tree` with 400 `"ساختار ارسالی نامعتبر است"` before calling the validator, guarding its `array $tree` parameter. *(Why: a client posting `tree: {}` would otherwise hit a PHP `TypeError` before rule 1/2 could report it.)*

Persian strings introduced by v2 (verbatim, nothing else is new): `"ستون"`, `"نوع بلاک نامعتبر است"`, `"این نوع بلاک در این محل مجاز نیست"`, `"برای این قالب قبلاً یک بوم ساخته شده است"`.

---

## 1. Read before you write anything

1. `PANEL-BLUEPRINT.md` — the base project you are extending. Everything in §3 (tech stack), §4 (permissions), §5 (folder layout), §6 (schema), §9 (endpoint conventions), §11 (security), §13 (localization), §17 (naming) of that document is binding here too.
2. The actual existing code, specifically:
   - `config/config.php`, `config/database.php` — for `BASE_PATH` / `PUBLIC_PATH` constants and `getPDO()`.
   - `includes/csrf.php` — for the **real** session key name and the **real** signature of the CSRF helpers. Do not assume names; read them.
   - `includes/auth.php` — for `requireLogin()`, `currentUser()`, and the **real** signature of `logActivity()`.
   - `includes/permissions.php` — for `requirePermission()`.
   - `includes/functions.php` — for `e()`, `jsonResponse()`, `slugify()`, `toJalaliDateTime()`, `toPersianDigits()`.
   - `api/media/upload.php` **and** `public/api/media/upload.php` — to copy the exact path-resolution idiom that lets those two files be byte-identical at two different directory depths. Use the same idiom in this part's endpoints. Do not invent a new one.
3. `database/schema.sql` — you will append to it; you must not modify any statement already in it.

If any of the above is missing or behaves differently from what the blueprint claims, **stop, do not guess, and record the discrepancy in your changelog** (see §18), then implement against the code that actually exists.

---

## 2. What this part is

This is the foundation layer of a Notion-style block canvas. Nothing here is visible to a user. What you build is the contract every later part depends on:

- A **canvas** is one editable document: either a **page** (`buildable_type = 'page'`) or a **site template** (`buildable_type = 'template'`).
- A canvas holds a **block tree**. The tree's root always has exactly three blocks — `header`, `body`, `footer` — in that order, always present, never reorderable.
- Blocks may nest: a `column_container` holds 2–4 `column` children, and each `column` holds any blocks.
- Every block type is described by a **registry entry**, on both the PHP side (validation, sanitization) and the JS side (rendering, settings forms — implemented by later parts, not by you).
- The API is **generic**: `load.php` and `save.php` know about canvases and trees, and know nothing about what any specific block type means.

New block types must be addable later by **dropping a new file into a folder** — never by editing a file you create in this part. This is the hard architectural requirement of the whole feature; if a design choice you are about to make breaks it, the choice is wrong.

---

## 3. Must NOT do

| # | Do not | Why |
|---|---|---|
| 1 | Do not build any UI: no builder screen, no canvas rendering, no toolbar, no settings panel, no "+" menu, no CSS | Those are Parts 1, 2, 3 |
| 2 | Do not write a `render()` implementation for any block type | Part 2 (structural types) and Parts 4–6 (content types) own rendering |
| 3 | Do not register the content block types (`text`, `image_video`, `button`, `slider`, `story`) | Parts 4, 5, 6 register their own; you only register the five structural types in §7 |
| 4 | Do not touch `modules/pages/index.php` or `modules/articles/index.php` | They stay empty placeholders until Part 1 |
| 5 | Do not modify, extend, or delete `public/assets/js/block-editor.js` | It is legacy dead code from the removed modules; a later part decides its fate |
| 6 | Do not modify or write to the legacy `pages`, `page_blocks`, `articles`, `article_blocks`, `article_categories` tables | The builder uses its own tables; the legacy ones stay at zero rows |
| 7 | Do not extend `media_usage.usable_type` with a new value, and do not write any `media_usage` row | Media is first referenced in Part 4, which owns that change |
| 8 | Do not modify any existing statement in `database/schema.sql` | You append only, inside a clearly marked banner |
| 9 | Do not add a rich-text library, a frontend framework, jQuery, or an ORM | Blueprint §3 forbids all of them |
| 10 | Do not add a third builder endpoint (create/delete/publish) | `save.php` handles creation; delete/publish belong to later parts |
| 11 | Do not use MySQL JSON functions (`JSON_EXTRACT`, `->>`, generated columns) in any query | MariaDB compatibility: `JSON` there is an alias for `LONGTEXT`. Read and write the column as a plain string; decode in PHP |
| 12 | Do not invent Persian strings | Every user-facing string you need is given verbatim in this document |

---

## 4. Language and library decisions (fixed — do not reconsider)

| Concern | Decision |
|---|---|
| Server code | PHP 8.0+, procedural style with small `final` classes for the registry/repository/validator (see §6 note), PDO prepared statements only, `PDO::ATTR_EMULATE_PREPARES = false` |
| Database | MySQL 8 / MariaDB, InnoDB, `utf8mb4` / `utf8mb4_unicode_ci` |
| Client code | One vanilla ES5-compatible JS file per registry concern, **zero DOM access, zero UI, no build step, no bundler, no npm package** |
| Third-party libraries in this part | **None.** Not SortableJS, not Swiper.js — those arrive in Parts 2 and 5 |
| API transport | JSON in, JSON out. `save.php` accepts a JSON request body (not a form post) |
| JSON handling | Decode with `json_decode($raw, false)` — objects become `stdClass`, JSON lists become PHP arrays. **Never** decode with `assoc = true`, and **never** use `JSON_FORCE_OBJECT` (it would turn `children` lists into objects). Encode with `json_encode($value, JSON_UNESCAPED_UNICODE)` |
| ID generation | 32-character lowercase hex, `bin2hex(random_bytes(16))` on the server, `crypto.randomUUID()` with hyphens stripped on the client (with a `Math.random` fallback for old browsers) |

**Note on "no JS" in the roadmap:** the roadmap's Part 0 row says "PHP 8 + PDO + MySQL only, no JS" while the same row promises a *client* registry. The resolution, decided here and binding: the client registry **is** JavaScript, but it is pure data and pure functions — it must not touch `document`, `window.addEventListener`, or produce any markup. "No JS" meant "no UI JavaScript".

---

## 5. File and folder map

Create exactly these files. One line of purpose each; nothing else is created by this part.

```text
panel/
  database/
    builder-schema.sql                          # NEW: additive migration — the builder tables only, runnable on an existing install
    schema.sql                                  # EXISTING: append the same CREATE TABLE statements at the end, inside a marked banner
  includes/
    builder/
      block_registry.php                        # NEW: BuilderBlockRegistry — register/get/all/insertable/exists/defaultData, plus definition validation
      builder_assets.php                        # NEW: BuilderAssets::clientScripts() — the ordered list of builder JS web paths, globbed
      bootstrap.php                             # NEW: single entry point — requires the registry, then auto-loads every includes/builder/types/*.php
      tree_validator.php                        # NEW: BuilderTreeValidator — validates a posted block tree against the registry and the limits in §11
      canvas_repository.php                     # NEW: BuilderCanvasRepository — load/save/defaultTree; owns the save transaction
      types/
        structural.php                          # NEW: registers the five structural types (header, body, footer, column_container, column) — server side only
  api/
    builder/
      load.php                                  # NEW: GET — returns one canvas plus its block tree as JSON
      save.php                                  # NEW: POST — creates or replaces one canvas plus its whole block tree, transactionally
  public/
    api/
      builder/
        load.php                                # NEW: served copy, byte-identical to api/builder/load.php
        save.php                                # NEW: served copy, byte-identical to api/builder/save.php
    assets/
      js/
        builder/
          block-registry.js                     # NEW: window.BuilderRegistry — registerBlockType/getBlockType/getAllBlockTypes/getInsertableBlockTypes/hasBlockType
          builder-core.js                       # NEW: window.BuilderCore — createUid/createNode/createColumnContainer/defaultTree/walkTree/findByUid; no DOM
          types/
            .gitkeep                            # NEW: empty folder where Parts 4–6 drop their client-side block type files
```

Rules that go with this map:

- `includes/` is **not** mirrored into `public/` (blueprint §5). Only `api/` and `modules/` are. The two endpoint copies must stay byte-identical; state in your changelog how you guaranteed that.
- `bootstrap.php` loads block types with `glob(__DIR__ . '/types/*.php')` sorted alphabetically, then `require_once` on each. This is the mechanism that lets Parts 4–6 add a block type without editing a single file you wrote. Do not replace it with a hard-coded list.
- `BuilderAssets::clientScripts()` returns, in order: `/assets/js/builder/block-registry.js`, `/assets/js/builder/builder-core.js`, then every `/assets/js/builder/types/*.js` sorted alphabetically (globbed from `PUBLIC_PATH`). Part 1 will loop over this to emit `<script>` tags — same reason: no hard-coded list.

---

## 6. Database schema

Write these statements into `database/builder-schema.sql`, and append the identical block to the end of `database/schema.sql` between banner comments:

```sql
-- ===== BEGIN: visual page builder (Part 0) =====
-- ...statements...
-- ===== END: visual page builder (Part 0) =====
```

### 6.1 Statements

```sql
CREATE TABLE builder_canvases (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    buildable_type  ENUM('page','template') NOT NULL,
    template_id     INT UNSIGNED DEFAULT NULL,
    title           VARCHAR(255) NOT NULL,
    slug            VARCHAR(255) DEFAULT NULL,
    status          ENUM('draft','published') NOT NULL DEFAULT 'draft',
    published_at    DATETIME DEFAULT NULL,
    created_by      INT UNSIGNED NOT NULL,
    updated_by      INT UNSIGNED DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_builder_canvases_slug (slug),
    UNIQUE KEY uq_builder_canvases_template (template_id),
    CONSTRAINT fk_builder_canvases_template
        FOREIGN KEY (template_id) REFERENCES templates(id) ON DELETE SET NULL,
    CONSTRAINT fk_builder_canvases_creator
        FOREIGN KEY (created_by) REFERENCES panel_users(id) ON DELETE CASCADE,
    CONSTRAINT fk_builder_canvases_updater
        FOREIGN KEY (updated_by) REFERENCES panel_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_builder_canvases_type_status ON builder_canvases (buildable_type, status);

CREATE TABLE builder_blocks (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    canvas_id    INT UNSIGNED NOT NULL,
    parent_id    INT UNSIGNED DEFAULT NULL,
    block_uid    CHAR(32) NOT NULL,
    block_type   VARCHAR(50) NOT NULL,
    sort_order   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    data         JSON NOT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_builder_blocks_uid (canvas_id, block_uid),
    CONSTRAINT fk_builder_blocks_canvas
        FOREIGN KEY (canvas_id) REFERENCES builder_canvases(id) ON DELETE CASCADE,
    CONSTRAINT fk_builder_blocks_parent
        FOREIGN KEY (parent_id) REFERENCES builder_blocks(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_builder_blocks_tree ON builder_blocks (canvas_id, parent_id, sort_order);
CREATE INDEX idx_builder_blocks_type ON builder_blocks (canvas_id, block_type);
```

### 6.2 Why the schema looks like this (do not "simplify" it)

- **A normalized block table, not one JSON document per canvas.** A single JSON blob is easier to save and useless afterwards: Part 7's renderer, "which canvases use media file #12", and any per-block-type query would all become full-table JSON scans. A row per block keeps those cheap and gives real referential integrity.
- **`block_type` is `VARCHAR(50)`, not an `ENUM`.** An `ENUM` would force an `ALTER TABLE` every time a later part adds a block type, which breaks the Lego rule at the database layer. Validity is enforced by the server registry instead (§9), which is where new types are declared anyway.
- **`parent_id` is a real self-referencing FK, not a stored `parent_uid` string.** The FK gives cascade deletion of subtrees for free and makes an orphan block impossible. The cost is that the save must insert in pre-order and map uid→id in PHP as it goes (§10.3); that cost is trivial for canvases of a few dozen blocks and buys integrity that a string reference cannot.
- **`block_uid` exists in addition to `id`** because saving replaces all rows (§10.3), so `id` is not stable across saves. The client needs a stable identity for a block across save/reload (selection state, undo, media usage mapping later). `block_uid` is that identity; it is generated by whichever side creates the block and is never rewritten.
- **`data` is a single JSON column**, not per-type columns. This is exactly the genericity the roadmap §6 relies on so Part 7 can be added later without a schema change.
- **`uq_builder_canvases_template` makes a template canvas 1:1 with its template.** A template has at most one layout canvas. In MySQL/MariaDB a `UNIQUE` key permits any number of `NULL`s, so page canvases (`template_id = NULL`) are completely unaffected, while `findByTemplate()` becomes deterministic instead of returning an arbitrary row.
- **`status` and `published_at` exist now** although publishing logic is Part 1/Part 7's: adding columns later to a table that already holds real canvases means a migration on live data, and Part 1's Save/Preview/Publish buttons need somewhere to write. This is an addition beyond the roadmap's literal text — see §17.
- **`slug` exists now** for the same reason: Part 7 turns a canvas into a real URL and will need a stable key. It is nullable so nothing breaks before Part 7 exists; `save.php` fills it in. Also an addition — see §17.
- **No seed rows.** This part inserts nothing.

---

## 7. The five structural block types (registered by this part)

Register these — and only these — in `includes/builder/types/structural.php`. They are metadata-only registrations: no `render`, no settings form. Part 2 renders them.

| `type` | `label` (Persian, exact) | `icon` | `category` | `allows_children` | `min_children` | `max_children` | `supports_text_formatting` | `allowed_child_types` | `allowed_parent_types` | `default_data` |
|---|---|---|---|---|---|---|---|---|---|---|
| `header` | `"هدر"` | `bi-layout-text-window-reverse` | `structure` | `true` | `0` | `0` (unlimited) | `false` | `[]` | `[]` | `{}` |
| `body` | `"بدنه"` | `bi-file-earmark-text` | `structure` | `true` | `0` | `0` (unlimited) | `false` | `[]` | `[]` | `{}` |
| `footer` | `"فوتر"` | `bi-layout-text-window` | `structure` | `true` | `0` | `0` (unlimited) | `false` | `[]` | `[]` | `{}` |
| `column_container` | `"ستونبندی"` | `bi-layout-three-columns` | `structure` | `true` | `2` | `4` | `false` | `["column"]` | `[]` | `{}` |
| `column` | `"ستون"` | `bi-layout-sidebar-inset` | `structure` | `true` | `0` | `0` (unlimited) | `false` | `[]` | `["column_container"]` | `{}` |

`max_children = 0` means unlimited. When `allows_children` is `false`, both `min_children` and `max_children` must be `0`.

`column_container`'s number of columns is the number of its `column` children — it is **not** duplicated into `data`. Storing the count in both places would create two sources of truth that can disagree, so `default_data` is `{}`. An empty `column` is legal (a freshly split container has empty columns).

**Why these five are registered here and not in Part 2:** Part 2's own "Must NOT do" forbids it from knowing about any specific block type, yet it must render the three root slots, the column container, and the columns. The only place those five can live without contradicting that is the registry, which this part owns. `category = 'structure'` is what keeps them out of the "+" insert menu in Part 3 (§11).

---

## 8. Canonical data contracts

These three shapes are the interface every later part is written against. Do not change their key names or nesting.

### 8.1 A block node (JSON, both directions)

```json
{
  "uid": "9f2c41ab7d0e4c6b8a13f5d27e0c6b44",
  "type": "column_container",
  "data": {},
  "children": []
}
```

- `uid` — required, exactly 32 lowercase hex characters, unique within the canvas.
- `type` — required, must exist in the server registry.
- `data` — required, a JSON **object** (may be empty `{}`); never `null`, never a scalar, never an array. In PHP it is a `stdClass` at every stage: request → validator → sanitizer → database → `load()` → response.
- `children` — required, a JSON **array** (empty when the type does not allow children). In PHP it is always a PHP array, i.e. a JSON list — never an object.

### 8.2 A canvas (response shape)

```json
{
  "id": 12,
  "buildable_type": "page",
  "template_id": null,
  "title": "صفحه درباره ما",
  "slug": "about-us",
  "status": "draft",
  "published_at": null,
  "updated_at": "2026-03-04 11:02:13",
  "updated_at_jalali": "۱۴۰۴/۱۲/۱۴ ۱۱:۰۲"
}
```

`id` is `null` for a not-yet-saved canvas returned by `load.php?new=1` (§12.1).

### 8.3 A server registry definition (PHP array)

```php
[
    'type'                 => 'text',            // required, matches /^[a-z][a-z0-9_]{1,49}$/
    'label'                => 'متن',             // required, Persian, shown to the end user
    'icon'                 => 'bi-fonts',        // required, a Bootstrap Icons 1.11.3 class
    'category'             => 'content',         // required, 'structure' or 'content'
    'allows_children'      => false,             // required, bool
    'min_children'         => 0,                 // required, int >= 0
    'max_children'         => 0,                 // required, int >= 0; 0 = unlimited when allows_children is true
    'supports_text_formatting' => true,          // required, bool — the Part 0a flag
    'allowed_child_types'  => [],                // required, array of type strings; [] = no restriction
    'allowed_parent_types' => [],                // required, array of type strings; [] = no restriction
    'default_data'         => ['html' => ''],    // required, PHP array (may be empty) — a nested empty object must be written as new stdClass(), never []
    'sanitize'             => null,              // optional, callable(stdClass $data): stdClass — defaults to identity
]
```

`supports_text_formatting` is **required on every registration from the start** — it is not an optional field with a default. Part 2's toolbar decides whether to show the formatting icons purely from this flag, so a missing value would silently mean "no formatting" and be hard to debug later.

`default_data` is declared as a PHP array at the top level, but a **nested empty object inside it must be written as `new stdClass()`**, never `[]`, because PHP cannot distinguish an empty array from an empty object at encode time. `BuilderBlockRegistry::defaultData($type)` converts the top level with `(object) $definition['default_data']`, so an empty top-level `default_data` becomes `{}` on the wire.

### 8.4 A client registry definition (JS object)

```js
{
  type: 'text',
  label: 'متن',
  icon: 'bi-fonts',
  category: 'content',                  // 'structure' | 'content'
  allowsChildren: false,
  minChildren: 0,
  maxChildren: 0,
  supportsTextFormatting: true,
  allowedChildTypes: [],                // required, array of type strings; [] = no restriction
  allowedParentTypes: [],               // required, array of type strings; [] = no restriction
  defaultData: function () { return { html: '' }; },   // required, returns a fresh object every call
  render: function (node, el) {},                      // optional here; required for 'content' — provided by Parts 4-6
  renderSettingsPanel: function (node, panelEl) {}     // optional; provided by Parts 4-6
}
```

The client uses camelCase and the server uses snake_case, matching blueprint §17.1. This is intentional; do not "harmonize" them.

---

## 9. Server registry — `includes/builder/block_registry.php`

```php
final class BuilderBlockRegistry
{
    public static function register(array $definition): void;   // throws InvalidArgumentException
    public static function get(string $type): ?array;           // null when unknown
    public static function all(): array;                        // ['type' => definition, ...] in registration order
    public static function insertable(): array;                 // same shape, only category === 'content'
    public static function exists(string $type): bool;
    public static function defaultData(string $type): stdClass; // (object) $definition['default_data']; new stdClass() when unknown
}
```

`register()` must throw `InvalidArgumentException` when:

- the `type` is already registered (duplicate registration is a bug, not an overwrite);
- any required key from §8.3 is missing, or has the wrong PHP type;
- `type` does not match `/^[a-z][a-z0-9_]{1,49}$/`;
- `category` is neither `structure` nor `content`;
- `allows_children` is `false` while `min_children` or `max_children` is non-zero;
- `max_children` is non-zero and smaller than `min_children`;
- `allowed_child_types` is not an array of strings, or any entry does not match `/^[a-z][a-z0-9_]{1,49}$/`;
- `allowed_parent_types` is not an array of strings, or any entry does not match `/^[a-z][a-z0-9_]{1,49}$/`;
- `allows_children` is `false` while `allowed_child_types` is non-empty;
- `sanitize` is set but not callable.

`register()` does **not** check that a referenced type in `allowed_child_types` / `allowed_parent_types` is already registered: types load from a glob in alphabetical order, so a definition may legitimately reference a type that registers later in the same bootstrap.

`register()` normalizes nothing else and fills in nothing else — a definition is either complete or rejected.

**Why a `final` class in a procedural codebase:** the registry needs one piece of module-level state (the type map) shared across several files. Plain functions would need a `static` inside a function or a global array, and five generic names (`register`, `get`, `all`, ...) in the global namespace is asking for a collision in a project that already defines dozens of global helpers. A class is the smallest safe container; it is not an ORM and not a framework, so blueprint §3 is untouched.

`includes/builder/bootstrap.php` must:

1. `require_once` `config/config.php` (which itself pulls in `vendor/autoload.php`) and `config/database.php` if not already loaded;
2. `require_once` `block_registry.php`, `tree_validator.php`, `canvas_repository.php`, `builder_assets.php`;
3. `foreach (glob(__DIR__ . '/types/*.php') as $file) { require_once $file; }` with the results sorted alphabetically;
4. be safe to include twice.

---

## 10. Canvas repository — `includes/builder/canvas_repository.php`

```php
final class BuilderCanvasRepository
{
    public static function defaultTree(): array;
    public static function load(PDO $pdo, int $canvasId): ?array;         // ['canvas' => [...], 'tree' => [...]] or null
    public static function findByTemplate(PDO $pdo, int $templateId): ?array;
    public static function save(PDO $pdo, ?int $canvasId, array $payload, int $userId): array;
    // save() returns ['canvas' => [...], 'block_count' => int, 'buildable_type_ignored' => bool]
}
```

### 10.1 `defaultTree()`

Returns exactly three nodes — `header`, `body`, `footer`, in that order — each with a freshly generated `uid`, `data` from `BuilderBlockRegistry::defaultData($type)` (a `stdClass`), and `children: []`.

### 10.2 `load()`

1. `SELECT * FROM builder_canvases WHERE id = ?`. Return `null` when there is no row.
2. `SELECT id, parent_id, block_uid, block_type, data FROM builder_blocks WHERE canvas_id = ? ORDER BY parent_id IS NULL DESC, parent_id ASC, sort_order ASC`.
3. Rebuild the nested tree in PHP (single pass: index rows by `id`, attach each row to its parent's `children`, collect parentless rows as roots). Do not recurse with one query per level.
4. `data` is decoded with `json_decode($row['data'], false)` (so it becomes a `stdClass`, or a PHP array for a JSON list). If decoding fails or does not yield a `stdClass`, substitute `BuilderBlockRegistry::defaultData($row['block_type'])`; if the type is not registered, substitute `new stdClass()`. Do **not** throw — a single corrupt block must not make a whole canvas unopenable. Never decode with `assoc = true`, because that would turn every `{}` into `[]` and destroy the object/array distinction on the very first round-trip.
5. If the canvas has zero blocks, return `defaultTree()` as the tree.
6. Blocks whose `block_type` is no longer registered are still returned unchanged. Dropping them silently would destroy a user's content just because a part was not deployed yet.
7. Each returned node is the PHP array `['uid' => string, 'type' => string, 'data' => stdClass, 'children' => array]`. An empty `children` stays `[]` (correct — it is a list).

### 10.3 `save()` — the transaction

Called only after validation (§11) has passed.

1. When `$canvasId` is not null: `SELECT id, slug, status, published_at, buildable_type, template_id FROM builder_canvases WHERE id = ?` — if there is no row, the caller returns 404 (`"بوم یافت نشد"`). Do this **before** `beginTransaction()`.
2. `$pdo->beginTransaction();`
3. Insert or update the canvas row:
   - **new canvas:** `INSERT INTO builder_canvases (buildable_type, template_id, title, slug, status, created_by, updated_by) VALUES (?,?,?,?,?,?,?)` with `slug = NULL`, then `$canvasId = (int) $pdo->lastInsertId();`. The slug is computed and written in step 4, once `$canvasId` exists.
   - **existing canvas:** `UPDATE builder_canvases SET title = ?, status = ?, updated_by = ?, updated_at = NOW() WHERE id = ?`. `updated_at = NOW()` is explicit and required: `ON UPDATE CURRENT_TIMESTAMP` does not fire when every other assigned value is unchanged (the common case where only the blocks changed). `buildable_type` is **immutable after creation**, and `template_id` is **immutable after creation**; neither appears in this `UPDATE`. If the posted `buildable_type` differs from the stored one, set a local `$buildableTypeIgnored = true` (surfaced in the response, §12.2) and ignore it. If the posted `template_id` differs from the stored one, the endpoint rejects the request with 400 `"ساختار ارسالی نامعتبر است"` before this method is called (see §12.2).
4. Slug:
   - If the stored slug is non-empty, it is never regenerated and never included in the update.
   - Otherwise compute the slug now. Candidate: `slugify($title)`; if the result is empty (an all-Persian title can slugify to an empty string), use `'canvas-' . $canvasId`. Check the candidate with the prepared `SELECT id FROM builder_canvases WHERE slug = ? AND id <> ?`; if taken, try `$candidate . '-2'`, `'-3'`, ... up to `'-50'`. **If all 51 candidates are taken, use `'canvas-' . $canvasId`**, which is unique because `$canvasId` is the primary key. Write it with `UPDATE builder_canvases SET slug = ? WHERE id = ?` inside the same transaction. The `UNIQUE KEY` remains the final backstop; a duplicate-key error on the slug is handled as a 500 like any other failure.
5. `DELETE FROM builder_blocks WHERE canvas_id = ?` — full replace. Cascade removes children rows automatically; this is intentional and the reason the tree must be written in one shot.
6. Insert the tree in pre-order, depth first. For each node, in sibling order: `INSERT INTO builder_blocks (canvas_id, parent_id, block_uid, block_type, sort_order, data) VALUES (?,?,?,?,?,?)` where `sort_order` is the node's zero-based index among its siblings and `data` is `json_encode($sanitized, JSON_UNESCAPED_UNICODE)`. `$sanitized` is the registry entry's `sanitize` callable applied to `$node->data` (a `stdClass` in, a `stdClass` out), or `$node->data` unchanged when no callable is registered. Capture `lastInsertId()` and pass it as `parent_id` to that node's children. Because `data` is a `stdClass`, `json_encode` emits `{}` for an empty object — never `[]`.
7. **Title write-through (roadmap §5.1, narrowed):** only when the canvas's `buildable_type === 'template'` **and** its `template_id` equals the value of `SELECT active_template_id FROM site_settings WHERE id = 1`, run `UPDATE site_settings SET site_name = ? WHERE id = 1` with the same title. Otherwise `site_settings` is untouched. Implement this as an explicit `if` on the stored values, not as a guess or a config flag. (A save of a *non-active* template canvas must never rename the site.)
8. If `status === 'published'` and the stored `published_at` is null, set `published_at = NOW()` in the same update. Once set, it is never cleared by this endpoint.
9. `$pdo->commit();` On any exception: `$pdo->rollBack();` and rethrow so the endpoint can answer with HTTP 500 and `"خطا در ذخیرهسازی"`.
10. Return the freshly re-read canvas row (formatted per §8.2), the number of inserted blocks, and `buildable_type_ignored`.

**Why full replace instead of a diff:** a diff needs per-block change detection, move detection across parents, and conflict handling — roughly ten times the code, for a document that is a few dozen rows. A full replace inside one transaction is atomic, impossible to leave half-applied, and trivially correct. `block_uid` preserves the identity that the delete would otherwise destroy.

---

## 11. Tree validator — `includes/builder/tree_validator.php`

```php
final class BuilderTreeValidator
{
    public static function validate(array $tree): array;   // list of Persian error strings; empty array = valid
}
```

The parameter stays typed `array`, so the caller (`save.php`) must confirm the posted `tree` is a JSON array (a PHP array after decoding with `assoc = false`) before calling this method; a non-array is rejected by the endpoint with 400 `"ساختار ارسالی نامعتبر است"` (§12.2). Inside the validator, nodes are `stdClass` objects, not arrays.

Rules, all enforced server-side regardless of what the client already checked:

| # | Rule | Error string returned (exact) |
|---|---|---|
| 1 | The root is an array of exactly 3 nodes, of types `header`, `body`, `footer`, in that order | `"ساختار ریشه باید دقیقاً شامل هدر، بدنه و فوتر باشد"` |
| 2 | Every node is a `stdClass` and has properties `uid`, `type`, `data`, `children` with the types from §8.1: `uid` string, `type` string, `data instanceof stdClass` (a JSON array or scalar `data` is rejected), `children` is a PHP array | `"ساختار ارسالی نامعتبر است"` |
| 3 | `uid` matches `/^[0-9a-f]{32}$/` and is unique across the whole tree | `"شناسه بلاک نامعتبر یا تکراری است"` |
| 4 | `type` exists in `BuilderBlockRegistry` | `"نوع بلاک نامعتبر است"` |
| 5 | `header`, `body`, `footer` appear **only** at the root; no other type appears at the root | `"بلاک ساختاری فقط میتواند در ریشه قرار بگیرد"` |
| 6 | A type with `allows_children = false` has `children === []` | `"این نوع بلاک نمیتواند فرزند داشته باشد"` |
| 7 | Child count respects `min_children` / `max_children` (0 = unlimited) | `"تعداد فرزندهای بلاک مجاز نیست"` |
| 8 | Total node count ≤ **500** | `"تعداد بلاکها بیش از حد مجاز است"` |
| 9 | Nesting depth ≤ **8** (a root slot is depth 1) | `"عمق تودرتویی بیش از حد مجاز است"` |
| 10 | `strlen(json_encode($node->data))` is ≤ **65536** bytes for any single node | `"حجم دادهی بلاک بیش از حد مجاز است"` |
| 11 | A node's parent type must be allowed by the node's `allowed_parent_types`, and the node's type must be allowed by its parent's `allowed_child_types` (an empty list means no restriction) | `"این نوع بلاک در این محل مجاز نیست"` |

For rule 4, the offending type string is **not** put into the response (it is no longer an error-message placeholder). Write it only to the server error log with `error_log()`, truncated to its first 50 characters with control characters stripped.

The validator must be **iterative or depth-limited recursive** — a hand-crafted payload nesting 10,000 levels deep must hit rule 9 rather than blow the PHP stack. Check depth as you descend, not after building anything.

Limits 8–10 are chosen here, not left open: 500 blocks is far beyond any realistic single page while still bounding a malicious payload; 64 KB per block is generous for rich text yet keeps one block from carrying a megabyte of pasted HTML. The depth limit is 8 rather than 5 because `column_container` and `column` are separate levels: `body(1) → column_container(2) → column(3) → block(4)` already uses four levels for a single split, so 8 allows three nested splits with content while still bounding recursion.

---

## 12. API endpoints

Both endpoints follow blueprint §9: JSON only, `requireLogin()` first, then permissions, then work. Both exist twice (source + served copy), byte-identical.

**Permission model for this feature (decided here):**

| Endpoint | Always | Additionally when `buildable_type = 'template'` |
|---|---|---|
| `load.php` | `requirePermission('pages','view')` | — |
| `save.php` | `requirePermission('pages','edit')` | `requirePermission('templates','edit')` |

Reasoning: the builder is the `"ساخت سایت"` (`pages`) module's screen, so `pages` is the natural gate. Saving a template canvas can rewrite `site_settings.site_name` (§10.3 step 7) — it changes site-wide state that belongs to the `templates`/site domain — so it demands template edit rights too, even though it now does so only for the active template. Reading a layout carries no such risk, so `load.php` stays on a single `pages.view` check.

### 12.1 `GET /api/builder/load.php`

Accepted query parameters (exactly one of the first two forms):

| Parameter | Type | Meaning |
|---|---|---|
| `canvas_id` | int > 0 | Load this canvas |
| `buildable_type` + `new=1` | `page`\|`template` | Return a blank, unsaved canvas (`id: null`) with `defaultTree()` |
| `template_id` | int > 0 | With `buildable_type=template`, load the canvas attached to that template, or a blank one when none exists yet |

Responses:

```json
{ "success": true, "data": { "canvas": { ...§8.2... }, "tree": [ ...§8.1... ] } }
```

| Situation | HTTP | Body |
|---|---|---|
| Missing/invalid `canvas_id` and no `new=1` | 400 | `{"success":false,"message":"شناسه نامعتبر"}` |
| No such canvas | 404 | `{"success":false,"message":"بوم یافت نشد"}` |
| Not logged in / no permission | 403 | handled by `requireLogin()` / `requirePermission()` (`"دسترسی غیر مجاز"`) |
| Unexpected failure | 500 | `{"success":false,"message":"خطا در بارگذاری"}` |

No CSRF token is required — this is a read, consistent with the existing `GET /api/media/upload.php`.

### 12.2 `POST /api/builder/save.php`

Request: `Content-Type: application/json`, raw body, **maximum 2 MB** (reject larger with 413 and `"حجم دادهی ارسالی بیش از حد مجاز است"` before decoding it).

```json
{
  "canvas_id": 12,
  "buildable_type": "page",
  "template_id": null,
  "title": "صفحه درباره ما",
  "status": "draft",
  "tree": [ { "uid": "...", "type": "header", "data": {}, "children": [] } ]
}
```

- `canvas_id`: `null`, absent, or `0` ⇒ create a new canvas. A positive int ⇒ update that canvas.
- `buildable_type`: required on create; on update it is **immutable** — a posted value that contradicts the stored one is ignored and the response carries `"buildable_type_ignored": true`.
- `template_id`: required (int > 0, must exist in `templates`) when `buildable_type = 'template'`; must be `null` when `buildable_type = 'page'`. It is **immutable after creation**: on update, a posted `template_id` that differs from the stored one is rejected with 400 `"ساختار ارسالی نامعتبر است"`. On **create** of a `template` canvas, if a canvas for that `template_id` already exists, respond with HTTP 409 `{"success":false,"message":"برای این قالب قبلاً یک بوم ساخته شده است"}` before opening any transaction.
- `title`: required, trimmed, 1–255 characters after trimming.
- `status`: `draft` or `published`; absent ⇒ `draft` on create, unchanged on update.
- `tree`: required, must be a JSON **array** (not an object, not a scalar). It is validated by §11. A non-array `tree` is rejected with 400 `"ساختار ارسالی نامعتبر است"` before the validator is called.

**CSRF:** the token is read from the `X-CSRF-TOKEN` request header, falling back to a `csrf_token` key inside the JSON body. Because the body is JSON, `$_POST` is empty and `includes/csrf.php`'s helper may not see the token — so: read `includes/csrf.php` first; if it exposes a verifier that accepts an explicit token argument, use it; if it only reads `$_POST`, compare directly in the endpoint with `hash_equals($_SESSION[<the session key that csrf.php actually uses>], $token)`. **Do not modify `includes/csrf.php`** and do not introduce a second token mechanism. Failure ⇒ HTTP 403, `{"success":false,"message":"توکن نامعتبر"}`.

Order of operations in the endpoint (this order matters — the cheapest and most security-critical gates run first, and the body is not parsed for an unauthenticated client):

1. Method must be POST, else 405 `"متد مجاز نیست"`.
2. `requireLogin()`.
3. CSRF check (header `X-CSRF-TOKEN`; JSON-body fallback rules above). Because the token may live inside the JSON body, when it is absent from the header the decision is deferred until after step 5, then verified; if it is still missing or invalid ⇒ 403 `"توکن نامعتبر"`.
4. Size guard: if `Content-Length` exceeds 2 MB, or the body read from `php://input` (read at most 2 MB + 1 byte) exceeds 2 MB ⇒ 413 `"حجم دادهی ارسالی بیش از حد مجاز است"`.
5. `json_decode($raw, false)`; malformed, or not a JSON object ⇒ 400 `"ساختار ارسالی نامعتبر است"`. (Now resolve the deferred CSRF decision from step 3.)
6. Permission checks from the §12 table (the `templates.edit` check uses the **stored** `buildable_type` on update and the **posted** one on create).
7. Scalar field validation (above), plus the `tree`-is-an-array check. First failure wins; respond 400 with that single message. The `template_id`-exists and `template_id`-immutable/duplicate checks also live here.
8. `BuilderTreeValidator::validate()`. On errors, respond 400 with the **first** error as `message` and the full list as `errors`.
9. `BuilderCanvasRepository::save()`.
10. `logActivity(...)` with `module_key = 'pages'` and an action type of `builder_save` — match the real signature in `includes/auth.php`. A logging failure must never fail the save; wrap it in `try/catch`.
11. Respond 200.

Success response:

```json
{
  "success": true,
  "message": "بوم با موفقیت ذخیره شد",
  "data": {
    "canvas": { "...": "§8.2 shape" },
    "block_count": 7,
    "buildable_type_ignored": false
  }
}
```

`save.php` passes the repository's `buildable_type_ignored` through unchanged; it is always `false` on create.

Failure: 500 with `{"success":false,"message":"خطا در ذخیرهسازی"}` after rollback. Never leak a PDO exception message to the client; log it server-side the way the existing endpoints do. A duplicate-key PDO exception (SQLSTATE `23000`, driver error `1062`) raised inside the transaction for a `template` canvas is caught, rolled back, and mapped to the same 409 as the pre-check in the `template_id` bullet above.

---

## 13. Client registry — `public/assets/js/builder/block-registry.js`

Exposes exactly one global, `window.BuilderRegistry`, an object with:

```js
BuilderRegistry.registerBlockType(definition)   // throws Error on duplicate type or missing required key
BuilderRegistry.getBlockType(type)              // definition object, or null
BuilderRegistry.getAllBlockTypes()              // array of definitions, registration order
BuilderRegistry.getInsertableBlockTypes()       // array, only category === 'content' — what Part 3's "+" menu lists
BuilderRegistry.hasBlockType(type)              // bool
```

- Validation mirrors §9: required keys present and of the right JS type, `type` matches `/^[a-z][a-z0-9_]{1,49}$/`, `category` is `structure` or `content`, `defaultData` is a function, `render`/`renderSettingsPanel` are functions when present. `allowedChildTypes` and `allowedParentTypes` are required and must be arrays of strings matching `/^[a-z][a-z0-9_]{1,49}$/`; `allowsChildren: false` with a non-empty `allowedChildTypes` throws.
- `getAllBlockTypes()` and `getInsertableBlockTypes()` return **shallow copies of the array** so a caller cannot reorder the registry by sorting the result in place.
- The file registers **nothing** itself and must run correctly when loaded before any block type file.
- The file must be an IIFE, must not define any other global, and must not reference `document` anywhere.

**Why `getInsertableBlockTypes()` exists** (an addition beyond the roadmap, §17): roadmap §5.3 has Part 3's "+" menu read `getAllBlockTypes()`, but that list now includes the five structural types, which must never appear in an insert menu. Filtering in Part 3 would force Part 3 to know that `header`/`body`/`footer`/`column_container`/`column` are special — exactly the knowledge its Must-Not-Do forbids. So the filter lives in the registry, and Part 3 calls the filtered accessor.

## 14. Client core — `public/assets/js/builder/builder-core.js`

Exposes `window.BuilderCore`:

```js
BuilderCore.createUid()                    // 32 lowercase hex chars
BuilderCore.createNode(type)               // { uid, type, data: <fresh defaultData()>, children: [] }; throws on unknown type
BuilderCore.createColumnContainer(count)   // count integer 2-4 (throws otherwise) → a column_container node whose children are `count` fresh column nodes
BuilderCore.defaultTree()                  // [header, body, footer] nodes — the client twin of §10.1
BuilderCore.walkTree(tree, callback)       // depth-first pre-order; callback(node, parentNode, depth, index)
BuilderCore.findByUid(tree, uid)           // node or null
```

Same constraints as §13: IIFE, one global, no DOM, no UI, no library. `createUid()` uses `crypto.randomUUID()` with hyphens removed, and falls back to a 32-hex string built from `crypto.getRandomValues()`, and only then to `Math.random()`.

`createColumnContainer(count)` exists because a bare `createNode('column_container')` produces a node with zero children, which violates the type's `min_children = 2`. Part 2 needs a valid split container to create one, and Part 2 is not allowed to edit Part 0's files, so the helper must live here.

---

## 15. Conventions you must honour (from the blueprint)

- Every query is a PDO prepared statement; no string interpolation of any value into SQL, ever.
- `e()` is for **HTML output only**. Never apply it to any string that is placed in a JSON response — JSON is produced only by `json_encode(..., JSON_UNESCAPED_UNICODE)` (or the existing `jsonResponse()` helper). The client is responsible for escaping a message when it inserts it into HTML.
- Any number that reaches a user-facing Persian string uses `toPersianDigits()`; any date uses `toJalaliDateTime()`.
- Every new file starts with `<?php` and has **no closing `?>` tag** (an accidental trailing newline after `?>` corrupts a JSON response).
- Every endpoint emits `Content-Type: application/json; charset=utf-8` and nothing else — no HTML, no warnings, no `var_dump`. Use the existing `jsonResponse()` helper if it exists; if it does not, write the header and `json_encode(..., JSON_UNESCAPED_UNICODE)` inline rather than inventing a new shared helper.
- Naming: `snake_case` for DB identifiers, `camelCase` for JS, the project's existing PHP style for functions (blueprint §17.1).
- Persian strings are stored and emitted as UTF-8 exactly as written in this document, including the ZWNJ characters (``) inside words like `"ستونبندی"`, `"ستون"`, and `"بلاکها"`. Do not normalize them away.

---

## 16. Acceptance checklist

Verify each of these yourself before declaring the part done, and report the result of each in your changelog.

- [ ] `database/builder-schema.sql` runs cleanly against an existing installed database and creates exactly `builder_canvases` and `builder_blocks`, both InnoDB/utf8mb4.
- [ ] The same statements are present at the end of `database/schema.sql` inside the marked banner, and every pre-existing statement in that file is byte-identical to before.
- [ ] No existing table was altered; `media_usage.usable_type` is still `ENUM('page','article')`.
- [ ] `builder_canvases` has both `uq_builder_canvases_slug` and `uq_builder_canvases_template`.
- [ ] `includes/builder/bootstrap.php` can be included twice with no error and registers exactly five block types.
- [ ] Dropping a new file into `includes/builder/types/` registers a new type with **no edit to any file created by this part**. Prove it: temporarily add a dummy type file, confirm `BuilderBlockRegistry::all()` returns six entries, then delete the dummy file and confirm it returns five again.
- [ ] `BuilderBlockRegistry::register()` throws on a duplicate type, on a missing `supports_text_formatting`, on `category = 'x'`, on a non-array `allowed_child_types`, and on `allows_children = false` with a non-empty `allowed_child_types`.
- [ ] `BuilderBlockRegistry::insertable()` returns an empty array right now (all five registered types are `structure`).
- [ ] `BuilderBlockRegistry::defaultData('header')` returns an empty `stdClass`, and `json_encode` of it is `{}` (not `[]`).
- [ ] `GET /api/builder/load.php?buildable_type=page&new=1` returns `canvas.id === null` and a three-node tree of `header`/`body`/`footer` with distinct 32-hex uids, each with `data` serialized as `{}`.
- [ ] `GET /api/builder/load.php?canvas_id=999999` returns HTTP 404 and `"بوم یافت نشد"`.
- [ ] `POST /api/builder/save.php` **as a logged-in user** with no CSRF token returns HTTP 403 and `"توکن نامعتبر"`. (An anonymous request gets the `requireLogin()` 403 first.)
- [ ] A save with a valid three-slot tree in which `body` contains one `column_container` with two `column` children creates the canvas, returns its new `canvas_id`, and writes exactly 6 rows into `builder_blocks` (3 root + 1 container + 2 columns) with correct `parent_id` and `sort_order` values.
- [ ] Round-trip test: save a tree whose `body` node has `data: {}` and a node with `data: {"settings": {}, "slides": []}` (use a throwaway content-type file that is deleted afterwards, the same way the dummy-type proof works); the raw `load.php` response string must contain `{}` for the empty `data`, `{}` for `settings`, and `[]` for `slides` — verified on the raw response, not after decoding it in PHP.
- [ ] Re-saving the same canvas with a reordered tree replaces all rows and leaves no orphan block for that canvas.
- [ ] Re-saving a canvas with an identical title/status but a changed tree advances `updated_at`.
- [ ] A save whose root is `[body, header, footer]` is rejected with HTTP 400 and `"ساختار ریشه باید دقیقاً شامل هدر، بدنه و فوتر باشد"`, and **nothing is written** to either table.
- [ ] A save containing `"type": "definitely_not_a_type"` is rejected with HTTP 400 and the exact message `"نوع بلاک نامعتبر است"`, and writes nothing.
- [ ] A `column_container` with 1 `column` child, and one with 5, are both rejected by rule 7.
- [ ] A `column` placed directly under `body` is rejected by rule 11; a `column_container` placed directly under another `column_container` is rejected by rule 11.
- [ ] A save with 9 levels of nesting is rejected by rule 9; an 8-level one succeeds. Build the 8-level fixture as `body → column_container → column → column_container → column → column_container → column → <a further block>` (use a throwaway type file for the further block if no content type exists yet, then delete it).
- [ ] Saving a canvas with `buildable_type = 'template'` whose `template_id` is the **active** template and title `"سایت تستی"` sets `site_settings.site_name` to `"سایت تستی"`.
- [ ] Saving a `template` canvas whose `template_id` is a **non-active** template leaves `site_settings` unchanged.
- [ ] Saving a `page` canvas leaves `site_settings` untouched.
- [ ] Creating a second `template` canvas for the same `template_id` returns HTTP 409 and `"برای این قالب قبلاً یک بوم ساخته شده است"`, and writes nothing.
- [ ] Updating a canvas with a `template_id` different from the stored one returns HTTP 400 and `"ساختار ارسالی نامعتبر است"`.
- [ ] Posting `"tree": {}` returns HTTP 400 and `"ساختار ارسالی نامعتبر است"` (no PHP `TypeError`).
- [ ] A staff account without `pages.edit` gets HTTP 403 from `save.php`; an account with `pages.edit` but without `templates.edit` gets HTTP 403 when saving a `template` canvas and succeeds on a `page` canvas.
- [ ] Forcing an exception between the block inserts and the commit leaves the canvas row and all block rows unchanged (transaction rolls back completely).
- [ ] `load.php` on a canvas whose blocks include an unknown `block_type` still returns the full tree instead of erroring or dropping the block.
- [ ] `api/builder/load.php` and `public/api/builder/load.php` are byte-identical (`diff` returns nothing); same for `save.php`.
- [ ] `block-registry.js` and `builder-core.js` contain no reference to `document`, `window.addEventListener`, `innerHTML`, or any UI construct, and define exactly one global each.
- [ ] `BuilderCore.createNode('column_container')` returns a fresh `data` object each call (mutating one node's `data` does not affect the next).
- [ ] `BuilderCore.createColumnContainer(2)` returns a node with exactly two `column` children; `createColumnContainer(1)` and `createColumnContainer(5)` both throw.
- [ ] No screen in the panel changed: `modules/pages/index.php` and `modules/articles/index.php` are still empty placeholders, and no new script tag was added to any existing page.

---

## 17. Deviations from the roadmap, decided by this prompt

Recorded here so nobody re-derives or contradicts them later. All of these are **decisions, not suggestions** — implement them as written.

1. **Part 0a is merged into Part 0.** `supports_text_formatting` / `supportsTextFormatting` is a required field of every registry definition from the first line of code, not a later patch. The roadmap's Part 0a row should be marked "absorbed into Part 0" in its Appendix.
2. **"No JS" is scoped to UI JavaScript** (§4). The client registry and core helpers are JS files with zero DOM access.
3. **The title write-through to `site_settings.site_name` is implemented here, in `save.php`/the repository — not in Part 1 — and only when the canvas's template is the active one** (§10.3 step 7). The roadmap §5.1 assigned the write-through to Part 1, but the save handler is a Part 0 file and letting Part 1 edit it would break the rule that a later part never modifies an earlier part's files; Part 1 now only builds the title input and posts `title`. Narrowing it to the active template prevents editing an unused template from renaming the whole site. **Known limitation (must appear in the changelog):** activating a different template later does not copy that template canvas's title to `site_name`; it syncs on the next save of that canvas. Fixing that belongs in `api/templates/activate.php`, a base-panel file Part 0 must not modify — it needs a separate base-panel Change Request. Do not touch `activate.php`.
4. **Five structural block types are registered in Part 0** (§7), because Part 2 is forbidden from knowing about specific block types yet must render the root slots, the column container, and the columns.
5. **`getInsertableBlockTypes()` added to the client registry** (§13) so Part 3's "+" menu never has to know that structural types are special. Part 3's master prompt must call this instead of `getAllBlockTypes()`.
6. **`BuilderAssets::clientScripts()` added** (§5) so Part 1 emits builder script tags from a glob instead of a hard-coded list — otherwise every new block type in Parts 4–6 would require editing Part 1's file.
7. **`slug`, `status`, `published_at` columns added now** even though publishing is Part 1/7 work, to avoid migrating a table that will by then hold real canvases (§6.2).
8. **`save.php` doubles as create**; no separate create endpoint, keeping the API surface at the two files the roadmap names.
9. **Validation limits (500 blocks, depth 8, 64 KB per block, 2 MB body) were chosen here** rather than left open (§11).
10. **`data` is handled as a JSON object end-to-end as a `stdClass`** (§4, §8, §10, §11): decoded with `json_decode(..., false)`, never `assoc = true`, never `JSON_FORCE_OBJECT`; registry `sanitize` is `callable(stdClass): stdClass`; `BuilderBlockRegistry::defaultData()` is the single place that turns a definition's `default_data` into an object. Consequence: a definition author must write a nested empty object as `new stdClass()`, never `[]`.
11. **The existing-canvas `UPDATE` sets `updated_at = NOW()` explicitly** (§10.3 step 3), because `ON UPDATE CURRENT_TIMESTAMP` does not fire when only the child block rows change.
12. **`template_id` is unique and immutable** (§6.1, §10.3, §12.2). A template's canvas is 1:1 with the template; a duplicate create is a 409, and a changed `template_id` on update is a 400.
13. **Slug generation is fully defined** (§10.3 step 4): new canvases insert `slug = NULL` then set it after `lastInsertId()`; the candidate falls back to `canvas-{id}` when `slugify()` is empty or when all 51 numbered candidates are taken.
14. **The endpoint order of operations is fixed** (§12.2): POST check → `requireLogin()` → deferred CSRF → size guard → JSON decode → permissions → field validation → tree validation → save → log → respond.
15. **`buildable_type_ignored` is produced and returned by `BuilderCanvasRepository::save()`** (§10, §12.2).
16. **No `e()` over any JSON-bound string, and rule 4's message has no `{type}` placeholder** (§11, §15). The offending type is written to the server error log only.
17. **A `column` structural type plus `allowed_child_types` / `allowed_parent_types`** (§7, §8.3, §8.4, §9, §11). A `column_container` holds 2–4 `columns`; a `column` holds any blocks and may appear only under a `column_container`. The depth limit is 8. `column_container`'s column count is its `column`-child count, so its `default_data` is `{}`, not `{"columns": 2}`. `BuilderCore.createColumnContainer(count)` builds a valid container.
18. **Part 2's column split/reduce behavior is fixed here and implemented later** (do not build it in Part 0): splitting a block into columns moves that block into the first new column and creates the remaining columns empty; reducing the column count removes trailing columns after confirming if they are non-empty; reducing to one column replaces the container with that column's children spliced into the container's own parent position.
19. **Deliberately left to a later part, and must not be done now:**
    - `media_usage.usable_type` has no value covering builder canvases, so media used inside a builder block is not usage-tracked yet. Part 4 (the first part that references media) owns that change.
    - The dashboard's `"تعداد ساخت سایت"` card still counts the legacy `pages` table and will therefore keep showing zero while canvases exist. Reconciling that is a base-panel change request, not this feature's job. Flag it in your changelog; do not fix it here.

---

## 18. Mandatory final step

Once you finish everything above, write a complete changelog of this work: every file you created or modified, every decision you made that this master prompt left open (there should be very few — if you had to make one, say exactly what and why), anything in this master prompt that turned out to be wrong, missing, or impossible to follow exactly as written and what you did instead, the result of every item in the §16 acceptance checklist (including every item added in v2), and the exact current state of everything future parts will depend on — the final signatures of `BuilderBlockRegistry` (including `defaultData`), `BuilderCanvasRepository`, `BuilderTreeValidator`, `BuilderAssets`, `BuilderRegistry`, `BuilderCore` (including `createColumnContainer`), the final registry definition shape (including `allowed_child_types` / `allowed_parent_types` and the `stdClass` `sanitize` signature), the final node JSON shape, the final request/response shape of both endpoints, and the exact active-template-only `site_name` limitation from §10.3 step 7 / §17 item 3. Save it as `changelogs/changelog-part-0-foundation.md` (create the `changelogs/` folder at the project root if it does not exist).
