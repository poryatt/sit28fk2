# Changelog — Part 0: Visual Page Builder Foundation (v2, incl. Part 0a)

Date: 2026-09-20. All work verified by execution (PHP CLI harnesses, Node vm
harness, live HTTP tests against `php -S` + MySQL/MariaDB). No UI of any kind
was built.

## 1. Files created (exact map from §5 — nothing else)

- `database/builder-schema.sql` — NEW. Additive migration, creates exactly
  `builder_canvases` + `builder_blocks`. Uses `IF NOT EXISTS` plus explicit
  `utf8mb4/utf8mb4_unicode_ci` (see §3 item 1 for why it differs slightly from
  the §6.1 text).
- `database/schema.sql` — MODIFIED (append only). The verbatim §6.1 statements
  appended at the end inside
  `-- ===== BEGIN: visual page builder (Part 0) =====` /
  `-- ===== END: visual page builder (Part 0) =====`. Every pre-existing
  statement is untouched.
- `includes/builder/block_registry.php` — NEW. `final class
  BuilderBlockRegistry` with `register/get/all/insertable/exists/defaultData`.
- `includes/builder/builder_assets.php` — NEW. `final class BuilderAssets`
  with `clientScripts()`.
- `includes/builder/bootstrap.php` — NEW. Single entry point; double-include
  safe via `BUILDER_BOOTSTRAP_LOADED`; loads types via sorted
  `glob(__DIR__ . '/types/*.php')` + `require_once`.
- `includes/builder/tree_validator.php` — NEW. `final class
  BuilderTreeValidator` with `validate(array $tree): array`. Iterative
  (explicit stack); depth checked while descending.
- `includes/builder/canvas_repository.php` — NEW. `final class
  BuilderCanvasRepository` with
  `defaultTree/load/findByTemplate/save/formatCanvas` (+ private `slugTaken`).
- `includes/builder/types/structural.php` — NEW. Registers exactly the five
  structural types (`header`, `body`, `footer`, `column_container`, `column`),
  metadata only, `default_data = []`, `sanitize` unset (identity).
- `api/builder/load.php` — NEW. `GET` endpoint.
- `api/builder/save.php` — NEW. `POST` endpoint (JSON body, 2 MB cap).
- `public/api/builder/load.php`, `public/api/builder/save.php` — NEW served
  copies, byte-identical (SHA256-verified, see §4 item 2 for the idiom).
- `public/assets/js/builder/block-registry.js` — NEW. IIFE, one global
  (`window.BuilderRegistry`), no DOM. Registers nothing itself.
- `public/assets/js/builder/builder-core.js` — NEW. IIFE, one global
  (`window.BuilderCore`), no DOM. Includes `createColumnContainer(count)`.
- `public/assets/js/builder/types/.gitkeep` — NEW. Empty drop-in folder for
  Parts 4–6.
- `changelogs/changelog-part-0-foundation.md` — this file.

No other file in the project was created or modified. In particular:
`modules/pages/index.php`, `modules/articles/index.php`,
`public/assets/js/block-editor.js`, `includes/csrf.php`, `api/templates/*`,
all legacy tables, and `media_usage.usable_type` are untouched.

## 2. Decisions the prompt left open (few, all minor)

1. `builder-schema.sql` uses `CREATE TABLE IF NOT EXISTS` and explicit
   charset/collate while the `schema.sql` banner keeps the verbatim §6.1 text.
   Reason: the migration must be re-runnable on an existing install.
2. Blank (unsaved) canvas shape for `load.php?new=1`: `title: ''`,
   `slug: null`, `status: 'draft'`, `published_at: null`, `updated_at: null`,
   `updated_at_jalali: '-'`. The prompt only fixes `id: null`; these are the
   neutral values chosen.
3. `load.php` with a non-GET method answers 405 `"متد مجاز نیست"` (symmetric
   with `save.php`; the prompt is silent for `load.php`).
4. `load.php?buildable_type=template&template_id=N` with a non-existent
   template answers 404 `"بوم یافت نشد"` (prompt silent).
5. `BuilderTreeValidator` caps the returned error list at 100 entries so a
   pathological payload (e.g. 500 malformed nodes) cannot build a megabyte
   response. `save.php` behaviour is unchanged (first error = `message`).
6. Update payloads may omit `buildable_type`/`template_id`/`status`
   (treated as "unchanged"); an explicitly posted `template_id` differing from
   stored is 400 even when the types would otherwise match.

## 3. Discrepancies / things impossible to follow literally

1. **Stale builder tables already existed** (a previous draft design:
   `buildable_id`, `parent_block_id`, `block_order`, `column_count`, no
   `template_id`/`slug`/`status`). Both held 0 rows. They were
   `DROP`ped and recreated exactly per §6.1. Verified post-hoc with
   `SHOW CREATE TABLE`: both InnoDB/utf8mb4, both `UNIQUE` keys present.
   (MariaDB renders the `JSON` column as `LONGTEXT ... CHECK (json_valid(...))`
   — exactly the aliasing §3 row 11 warns about; no JSON functions are used
   in any query.)
2. **The `upload.php` "byte-identical idiom" does not exist.** `api/media/
   upload.php` and `public/api/media/upload.php` are NOT byte-identical (their
   `require` depths differ: `../../` vs `../../../`, plus one path line).
   Therefore no idiom could be copied. Instead both builder endpoints locate
   the project root by walking up from `__DIR__` until
   `config/database.php` is found — identical bytes, correct at both depths.
   SHA256 of each pair matches (see §4).
3. **`requireLogin()` does not return JSON 403.** It `header('Location:
   /login.php'); exit;`. So an anonymous `save.php` caller gets a 302
   redirect, not the table's 403 — the endpoint still calls it first per
   §12.2 step 2. Authenticated-but-forbidden callers do get JSON 403 from
   `requirePermission()` (`"دسترسی غیر مجاز"`).
4. **`slugify()` never returns `''`.** The existing helper falls back to
   `'n-' . bin2hex(random_bytes(3))` (and keeps Persian chars, e.g.
   `"سایت تستی"` → `"سایت-تستی"`). The spec's empty-candidate branch is
   implemented literally but is dead code with this helper; the
   `candidate(-2…-50)` → `canvas-{id}` chain and the `UNIQUE` backstop behave
   as specified. `slugify()` itself was not modified.
5. **Rule-11 test subtlety (my harness, not the code):** a
   `column_container` nested as `cc → column → cc` is LEGAL
   (`column.allowed_child_types = []` = no restriction,
   `column_container.allowed_parent_types = []` = no restriction). Only a
   *direct* `cc → cc` child violates `allowed_child_types: ["column"]`. The
   first test was wrong; the corrected direct-nesting test passes.
6. **Environment:** the MySQL Windows service was stopped and could not be
   started via SCM (access denied); `mysqld.exe` was started directly and the
   database came back intact. Composer's `autoload.files` already includes
   `includes/functions.php`, so no file may `require` it twice.
7. **Known limitations carried over (not fixed, per §17):** activating a
   different template does not sync its canvas title to `site_name` until the
   next save of that canvas (`api/templates/activate.php` untouched); the
   dashboard `"تعداد ساخت سایت"` card still counts the legacy `pages` table;
   builder media usage is still untracked (`media_usage.usable_type` is still
   `ENUM('page','article')`, verified).

## 4. §16 acceptance checklist — result of every item

- [x] `database/builder-schema.sql` runs cleanly (applied via PDO; also
      re-applied after a MySQL restart), creates exactly the two tables,
      both InnoDB/utf8mb4.
- [x] Same statements appended to `database/schema.sql` in the marked banner;
      pre-existing statements untouched (append-only edit).
- [x] No existing table altered; `media_usage.usable_type` still
      `ENUM('page','article')` (verified via `SHOW COLUMNS`).
- [x] `uq_builder_canvases_slug` and `uq_builder_canvases_template` both
      present (verified via `SHOW CREATE TABLE`).
- [x] `bootstrap.php` includable twice, registers exactly five types.
- [x] Lego proof: temp file `includes/builder/types/zz_dummy.php` →
      `all()` returns 6; deleted → 5 again.
- [x] `register()` throws on duplicate, missing `supports_text_formatting`,
      `category='x'`, non-array `allowed_child_types`,
      `allows_children=false` + non-empty `allowed_child_types` (all five
      verified by execution).
- [x] `insertable()` is `[]` (all five are `structure`).
- [x] `defaultData('header')` is empty `stdClass`; `json_encode` is `{}`.
- [x] `GET load.php?buildable_type=page&new=1` → `id: null`, 3 nodes
      header/body/footer, distinct 32-hex uids, `data: {}`.
- [x] `GET load.php?canvas_id=999999` → 404 `"بوم یافت نشد"`.
- [x] Logged-in `POST save.php` without token → 403 `"توکن نامعتبر"`
      (anonymous gets the `requireLogin()` redirect — see §3 item 3).
- [x] Save with `body → column_container → 2 × column` → new id,
      exactly 6 rows, correct `parent_id`/`sort_order` (verified row by row).
- [x] Round-trip: `data: {}` stays `{}`, `{"settings": {}, "slides": []}`
      stays `"settings":{}` + `"slides":[]` — verified on the raw
      `load.php` response string (throwaway `dummy_text` type file, deleted).
- [x] Re-save replaces all rows (4 rows after second save, `COUNT(*)` check —
      no orphans; ghost-uid check confirms old uids gone).
- [x] Identical title/status + changed tree advances `updated_at`
      (`2026-09-20 20:54:35 → 20:54:37` observed).
- [x] Root `[body, header, footer]` → 400 with the exact rule-1 message and
      nothing written.
- [x] `"type": "definitely_not_a_type"` → 400 exact `"نوع بلاک نامعتبر است"`,
      nothing written (offending type only in server error log).
- [x] Container with 1 and with 5 columns → both rule-7 rejections.
- [x] `column` directly under `body` → rule 11; `column_container` directly
      under another `column_container` → rule 11 (see §3 item 5).
- [x] 9-level nesting → rule 9; 8-level fixture
      (`body → cc → col → cc → col → cc → col → dummy`) → valid. A hand-built
      10,000-deep payload hits rule 9 without stack exhaustion.
- [x] `template` canvas on the active template (`id=2`) with title
      `"سایت تستی"` sets `site_settings.site_name` to `"سایت تستی"`.
- [x] `template` canvas on non-active template (`id=1`) leaves
      `site_settings` unchanged.
- [x] `page` canvas leaves `site_settings` untouched.
- [x] Second `template` canvas for same `template_id` → 409
      `"برای این قالب قبلاً یک بوم ساخته شده است"`, nothing written
      (pre-check; the race backstop mapping 23000/1062 → 409 is also
      implemented and the repository-level throw verified).
- [x] Update with differing `template_id` → 400 `"ساختار ارسالی نامعتبر است"`.
- [x] `"tree": {}` → 400 `"ساختار ارسالی نامعتبر است"`, no `TypeError`.
- [x] Staff without `pages.edit` → 403 from `save.php`; `pages.edit` without
      `templates.edit` → 403 on `template` canvas, 200 on `page` canvas
      (verified over HTTP with two purpose-made staff accounts, deleted after).
- [x] Forced exception between inserts and commit (throwing `sanitize`
      probe) → canvas/block counts unchanged (full rollback).
- [x] `load.php` with an unknown `block_type` row returns the full tree
      (ghost block present, `data` falls back to `defaultData`).
- [x] `api/builder/*.php` ↔ `public/api/builder/*.php` byte-identical
      (SHA256 `AE36…5F36` for `load.php`, `DC48…D009` for `save.php`).
- [x] Both JS files: no `document` / `addEventListener` / `innerHTML`
      (regex scan), exactly one global each; registry loads with zero types.
- [x] `createNode('column_container')` returns fresh `data` per call.
- [x] `createColumnContainer(2)` → two `column` children; `(1)` and `(5)`
      throw (also verified: `(4)` works, non-integers throw).
- [x] No screen changed: placeholders unedited, no new `<script>` in any
      existing page (grep for the builder paths outside the two new files:
      no hits). Bonus checks that passed: JSON-body `csrf_token` fallback
      (200 with valid, 403 with bogus), >2 MB body → 413 with the exact
      message, `GET save.php` → 405, `published_at` stamped once on
      `published` save.

Cleanup after verification: all test canvases/blocks deleted (0 rows),
purpose-made staff accounts + their permission/login-attempt rows deleted,
`site_name` restored to `"وب‌سایت من"`, temp `zz_dummy.php` deleted,
 throwaway `boom_probe` type existed only in-process. Test harnesses live
outside the project (`%TEMP%\opencode\*.php/js`) — nothing to clean in-repo.

## 5. Final contract for future parts (exact current state)

**`BuilderBlockRegistry`** (`includes/builder/block_registry.php`):
`register(array $definition): void` (throws `InvalidArgumentException`),
`get(string $type): ?array`, `all(): array` (`type => definition`,
registration order), `insertable(): array` (`category === 'content'` only),
`exists(string $type): bool`, `defaultData(string $type): stdClass`
(`(object) $definition['default_data']`, empty → `new stdClass()`; unknown
type → `new stdClass()`).

**Server definition shape (§8.3, snake_case):** `type` (regex
`/^[a-z][a-z0-9_]{1,49}$/`), `label`, `icon`, `category`
(`structure|content`), `allows_children` (bool), `min_children`/`max_children`
(int ≥ 0, `0` = unlimited when children allowed; both `0` when not),
`supports_text_formatting` (bool, REQUIRED on every registration),
`allowed_child_types`/`allowed_parent_types` (required string arrays, `[]` =
no restriction; non-empty `allowed_child_types` with
`allows_children=false` rejected), `default_data` (required PHP array;
nested empty objects as `new stdClass()`), `sanitize` (optional
`callable(stdClass): stdClass`, default identity). Duplicate registration
throws; forward references in allowed_*_types are allowed (glob order).

**`BuilderCanvasRepository`**: `defaultTree(): array` (fresh uids,
`data` = `stdClass`); `load(PDO, int): ?array` (`['canvas','tree']`, corrupt
`data` → `defaultData`, unknown types kept, empty canvas → `defaultTree()`);
`findByTemplate(PDO, int): ?array`; `save(PDO, ?int $canvasId, array $payload,
int $userId): array` returning
`['canvas','block_count','buildable_type_ignored']`; `$payload` =
`buildable_type/template_id/title/status/tree(stdClass nodes)`.
Save = pre-select → transaction(insert-NULL-slug / update+`updated_at=NOW()`,
slug resolve, `DELETE` + pre-order insert with `sanitize`, ACTIVE-template-only
`site_name` write-through, `published_at` stamp once) → commit; rollback +
rethrow on any error.

**`BuilderTreeValidator`**: `validate(array $tree): array` (Persian errors,
`[]` = valid). 11 rules with the exact strings from §11; rule 4 logs the type
(server log only); limits 500 blocks / depth 8 / 64 KB per `data` / root =
`[header,body,footer]`.

**`BuilderAssets`**: `clientScripts(): string[]` =
`['/assets/js/builder/block-registry.js',
'/assets/js/builder/builder-core.js', ...globbed
'/assets/js/builder/types/*.js' sorted]`.

**`BuilderRegistry` (client):** `registerBlockType (throws`,
`getBlockType (null when unknown)`, `getAllBlockTypes` (copy),
`getInsertableBlockTypes` (`content` only, copy), `hasBlockType`.
Definition (camelCase): `type/label/icon/category/allowsChildren/
minChildren/maxChildren/supportsTextFormatting/allowedChildTypes/
allowedParentTypes/defaultData() (fresh object)/render?/renderSettingsPanel?`.

**`BuilderCore` (client):** `createUid()` (32 hex),
`createNode(type)` (throws unknown), `createColumnContainer(2–4)` (throws
otherwise; `count` fresh `column` children),
`defaultTree()` (`[header,body,footer]`),
`walkTree(tree, cb(node,parent,depth,index))` (pre-order, root depth 1),
`findByUid(tree,uid)` (node|null).

**Node JSON (§8.1):** `{"uid": "<32 hex>", "type": "<registered>",
"data": <object, stdClass in PHP>, "children": [<nodes>]}`.

**`GET load.php`:** `?canvas_id=N` | `?buildable_type=page|template&new=1`
(+ optional `template_id`) | `?buildable_type=template&template_id=N`
→ `{success,data:{canvas:{id,buildable_type,template_id,title,slug,status,
published_at,updated_at,updated_at_jalali},tree:[...]}}`;
400 `"شناسه نامعتبر"`, 404 `"بوم یافت نشد"`, 500 `"خطا در بارگذاری"`;
`pages.view` gate; no CSRF.

**`POST save.php`:** JSON body
`{canvas_id?,buildable_type,template_id?,title,status?,tree,csrf_token?}`
(≤ 2 MB), header `X-CSRF-TOKEN` or body `csrf_token`; order POST →
`requireLogin()` → CSRF → size → decode → `pages.edit` (+`templates.edit`
for template) → field/`template_id` checks → tree validation → save → log
(`logActivity('builder_save','pages',...)`, never fatal) → 200
`{success:true,message:"بوم با موفقیت ذخیره شد",
data:{canvas,block_count,buildable_type_ignored}}`.
Errors: 405 `"متد مجاز نیست"`, 403 `"توکن نامعتبر"`, 413 size message,
400 `"ساختار ارسالی نامعتبر است"` (or rule message + `errors[]`), 404
`"بوم یافت نشد"`, 409 duplicate-template message, 500 `"خطا در ذخیرهسازی"`.
`buildable_type`/`template_id` immutable after creation (mismatch →
ignored-flag / 400 respectively).

**Active-template-only `site_name` limitation:** the write-through fires iff
stored `buildable_type='template'` AND stored `template_id` equals
`site_settings.active_template_id`. Activating another template later does
NOT copy its canvas title — it syncs on that canvas's next save. Fix belongs
in `api/templates/activate.php` (base-panel CR, untouched here).
