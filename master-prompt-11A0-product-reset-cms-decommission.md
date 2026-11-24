# MASTER PROMPT 11A0 — Product reset and CMS decommission

> **Series:** Part 11 (modular admin application platform) · **Revision 2 of the series** · **File:** `master-prompt-11A0-product-reset-cms-decommission.md` · **Language of this file:** English (final message to the owner: Persian).

## Banner — what this is, and what was tested

| Field | Value |
|---|---|
| **Status** | `ready` (authored and tested by the author; not yet executed) |
| **Outcome** | The repository is no longer a website builder: every CMS file, route, API, permission key, table, test and asset is gone or generalised, and the panel contains only the generic platform (auth, shell, media, settings, staff, dashboard). |
| **Contract (true after this part)** | 1. `php tests/run_all.php` prints 13 lines, all `OK`. 2. `php database/tools/check_cms_removed.php` prints `cms leftovers: 0`. 3. `includes/nav.php` and `includes/access_map.php` contain only the five platform pages. 4. `public/api/media/delete.php` asks `file_references`, not the builder. 5. After the OWNER GATE: tables/columns/permission keys of the CMS are gone (`diagnose.php` D27 and D28 PASS). |
| **In scope** | CMS removal from code, UI, nav, access map, APIs, tests, assets, docs; generic `file_references`; generic dashboard; hand-off set `docs/ai/`; manifest tool; migrations 0009 + 0010 (applied only with owner approval). |
| **Out of scope** | HTTP kernel, module system, new features, schema redesign (11D), editing `database/schema.sql`, deleting CMS files that are stored on disk (storage/uploads), PHP 8.0 gate (11A). |
| **Requires** | Nothing before it. It is the first prompt of the series. |
| **Unblocks** | 11A (needs a CMS-free tree: logout/sidebar/topbar edits and API guards), and through it 11B … 11H. |
| **Runtime proof** | `php tests/run_all.php`, `php database/tools/check_cms_removed.php`, `php tools/gates/verify_manifest.php docs/ai/manifests/11A0.json`, `python tests/ui/check_ui_gates.py`, the UI-kit browser check, and (after the OWNER GATE) `php database/migrate.php --status`, `php database/tools/cms_inventory.php`, `php database/tools/diagnose.php`. |
| **Tested by the author** | Linux sandbox, PHP 8.3, SQLite. Whole sequence replayed on a clean copy of the repository (HEAD `38595df` application code): 13/13 PHP tests green, CMS gate 0 leftovers, UI gates 0 findings, page-coverage gate 7/7, UI-kit browser checks 67 passed / 0 failed, manifest 147 ok before and 155 ok after. Control flow of migration 0010 (guard, order, idempotency, seed-name replacement) tested on SQLite. |
| **NOT tested by the author** | MySQL execution of migrations 0009/0010 (the DDL `ALTER TABLE … DROP FOREIGN KEY`, `DROP TABLE`); rendering of dashboard / media / settings pages against a real database; Apache; PHP 8.0 runtime; Windows paths. Your report must say which of these you could and could not run. |

## 0. READ THIS FIRST — you are an OPERATOR, not an engineer

The prompt author (Claude) has made every engineering decision, written all code and tested it (the banner says what was and what was not tested). Your job is to **copy, replace, delete, run commands and report**. Nothing else.

1. **Do only what is written.** Where this prompt says "create", "replace", "delete" or "run", do exactly that. Do **not** design, refactor, rename, reformat, add comments, add error handling, "improve", "modernise", reorder, merge or split anything. A bug you believe you see in this prompt is **reported**, not fixed (rule 3).
2. **Copy byte for byte.** Content in code fences is the exact file content: UTF-8 without BOM, LF line endings, one final newline, no trailing spaces added or removed. The fence markers are not part of the file. Persian text is exact (Persian letters ی and ک only).
3. **Reconcile, do not improvise.** The prompt was written against a known repository state. Your copy may differ.
   - Before the first change you run the **pre-flight** (Step 1) and write the *real* baseline into the changelog.
   - If a step cannot be done exactly as written (a FIND text occurs 0 or 2+ times, a file that must exist is missing, a file that must be identical is not, a command fails, a check prints something else) → **stop that step and every step that depends on it**. Steps that do not depend on it continue; when in doubt, stop. Write what you saw (file, step, output) under "Deviations" in the changelog.
   - You *report* conflicts between this prompt and the repository. You never *resolve* them by writing your own code, by editing the prompt's content, or by guessing.
4. **Owner gates.** A step marked **OWNER GATE** can destroy data. You stop there, show the owner the facts, ask the question given in the step, and continue only on an explicit yes. You never approve for the owner, never set an approval variable yourself, and never run a database command outside an OWNER GATE step. This part has exactly one such gate: Step 9 (migrations 0009 and 0010). Work done before a gate stays done; without approval the part ends as `partial`.
5. **Touch only the files this prompt names.** Do not open other files of the owner for editing. Do not run `git` commands that change anything (the folder may not even be a git repository), `composer require/update/install`, or `npm`.
6. **Documentation duty.** The owner works with several AI agents one after another; the next one must see exactly what you did. The final steps (changelog, `docs/ai/state.json`, honest "Deviations" and "Gate outputs") are **mandatory**.
7. **Be honest.** Paste real command output. If a check was not run or could not run, write "not run" and why. Never write "passed" for something you did not run. Status words used in this series: `authored` (prompt written) → `reviewed` → `ready` (ready to execute) → `executed` (steps done) → `verified` (checks passed on the real stack) → `released`; plus `partial` (stopped or waiting for the owner) and `blocked`. A prompt file you receive is `ready`; after you finish it is at best `executed`. Only the owner moves a part to `verified`/`released`.
8. **Language.** Your final message to the owner is in **Persian**. File contents stay exactly as given here.
9. **Use your task list.** One task per numbered step below; mark it done only after its check passed.

## 1. Goal and scope

**Why this part exists.** The owner changed the product goal: the project stops being a Website Builder / CMS panel and becomes a generic **admin application platform** (a starter kit for admin apps made of Core Platform, Platform Services and Business Modules). The CMS must disappear completely so that no later prompt has to carry its dependencies. This is the owner's spec change; the earlier parts 11A–11C were written for the old goal and are revised (revision 2) to run **after** this one.

**What this part does**
1. Deletes the CMS: pages, articles, templates, licences pages; the page-builder (PHP, JS, CSS); the builder/templates/licences APIs; the private-file download endpoint; their tests, screenshots and documentation.
2. Rewrites the shared files that mentioned the CMS: navigation, access map, dashboard, general settings, media delete API, file rules/store, config constants, CSS (split of `45-pages.css`), UI test lists, docs, database tools.
3. Decouples `media_library` from pages/articles with a generic **File Reference** (`file_references` table + `includes/file_references.php`).
4. Adds migration 0009 (create `file_references`) and migration 0010 (remove the CMS from the database) — **applied only after the owner approves** (Step 9).
5. Adds the tools that make the removal checkable: `tools/gates/verify_manifest.php`, `database/tools/check_cms_removed.php`, `database/tools/cms_inventory.php`.
6. Creates the AI hand-off set `docs/ai/` and the removal record `docs/decommission-cms.md`.

**Decisions the author made for the owner (not in the brief — please read)**
- Removal, not hiding: nothing CMS-related stays "disabled".
- `site_settings.site_domain` and `active_template_id` are dropped (a website concept); `panel_plan` stays until 11D.
- The seed site name «وب‌سایت من» becomes «پنل مدیریت» only if the owner never changed it.
- Private file kinds become generic; `public/file.php` is deleted (a generic download endpoint comes with the file service in 11F2).
- The ZIP-entry and certificate helpers are deleted with their only users.
- `database/tools/diagnose.php` loses the CMS checks and gains D27/D28.
- The default owner password in `database/schema.sql` is **not** touched here (the baseline is frozen); it is recorded as release blocker RB-1 in `docs/ai/RELEASE-BLOCKERS.md` and closed by 11D/11H.

## 2. File tree (only what this part touches)

```text
.
├── changelogs/
│   └── changelog-part-11a0-product-reset-cms-decommission.md   [NEW]
├── config/
│   └── config.php   [CHANGED]
├── database/
│   ├── lib/
│   │   └── CmsInventory.php   [NEW]
│   ├── migrations/
│   │   ├── 0009_file_references_pre_code.php   [NEW]
│   │   └── 0010_cms_decommission_post_code.php   [NEW]
│   ├── tools/
│   │   ├── backfill_files.php   [REPLACED]
│   │   ├── check_cms_removed.php   [NEW]
│   │   ├── cms_inventory.php   [NEW]
│   │   ├── diagnose.php   [REPLACED]
│   │   ├── encrypt_existing_secrets.php   [DELETED]
│   │   └── gc_files.php   [CHANGED]
│   └── README.md   [CHANGED]
├── docs/
│   ├── ai/
│   │   ├── manifests/
│   │   │   ├── 11A0-baseline.json   [NEW]
│   │   │   └── 11A0.json   [NEW]
│   │   ├── DECISIONS.md   [NEW]
│   │   ├── FILE-MAP.md   [NEW]
│   │   ├── HANDOFF.md   [NEW]
│   │   ├── RELEASE-BLOCKERS.md   [NEW]
│   │   └── state.json   [NEW]
│   ├── builder-architecture.md   [DELETED]
│   ├── decommission-cms.md   [NEW]
│   ├── ui-guide.md   [CHANGED]
│   └── ui-pages.md   [REPLACED]
├── includes/
│   ├── layout/
│   │   └── sidebar.php   [CHANGED]
│   ├── access_map.php   [REPLACED]
│   ├── builder   [DELETED (whole folder)]
│   ├── dashboard_data.php   [REPLACED]
│   ├── file_references.php   [NEW]
│   ├── file_rules.php   [REPLACED]
│   ├── file_store.php   [CHANGED]
│   └── nav.php   [REPLACED]
├── public/
│   ├── api/
│   │   ├── media/
│   │   │   └── delete.php   [REPLACED]
│   │   ├── builder   [DELETED (whole folder)]
│   │   ├── licenses   [DELETED (whole folder)]
│   │   └── templates   [DELETED (whole folder)]
│   ├── assets/
│   │   ├── css/
│   │   │   ├── app/
│   │   │   │   ├── 00-tokens.css   [CHANGED]
│   │   │   │   ├── 20-shell.css   [CHANGED]
│   │   │   │   ├── 40-motion.css   [CHANGED]
│   │   │   │   └── 45-pages.css   [REPLACED]
│   │   │   └── builder   [DELETED (whole folder)]
│   │   └── js/
│   │       ├── builder   [DELETED (whole folder)]
│   │       └── pages-manager.js   [DELETED]
│   ├── modules/
│   │   ├── dashboard/
│   │   │   └── index.php   [REPLACED]
│   │   ├── media/
│   │   │   └── index.php   [CHANGED]
│   │   ├── settings/
│   │   │   └── general.php   [REPLACED]
│   │   ├── articles   [DELETED (whole folder)]
│   │   ├── licenses   [DELETED (whole folder)]
│   │   ├── pages   [DELETED (whole folder)]
│   │   └── templates   [DELETED (whole folder)]
│   ├── ui-kit/
│   │   └── index.html   [CHANGED]
│   └── file.php   [DELETED]
├── tests/
│   ├── browser/
│   │   ├── shots/
│   │   │   ├── builder-host-*.png   [DELETED]
│   │   │   ├── live-modules-articles-*.png   [DELETED]
│   │   │   ├── live-modules-dashboard-*.png   [DELETED]
│   │   │   ├── live-modules-licenses-*.png   [DELETED]
│   │   │   ├── live-modules-pages-*.png   [DELETED]
│   │   │   └── live-modules-templates-*.png   [DELETED]
│   │   ├── check_pages_semantics.py   [CHANGED]
│   │   ├── engine_contract.json   [DELETED]
│   │   ├── run_builder_checks.py   [DELETED]
│   │   ├── run_builder_checks_async.py   [DELETED]
│   │   └── run_ui_checks.py   [CHANGED]
│   ├── ui/
│   │   ├── check_pages_covered.py   [CHANGED]
│   │   └── check_ui_gates.py   [REPLACED]
│   ├── README.md   [REPLACED]
│   ├── builder_entry_test.php   [DELETED]
│   ├── builder_pages_list_test.php   [DELETED]
│   ├── builder_registry_test.php   [DELETED]
│   ├── cms_inventory_test.php   [NEW]
│   ├── cms_removed_test.php   [NEW]
│   ├── cms_shell_test.php   [NEW]
│   ├── dashboard_data_test.php   [REPLACED]
│   ├── file_references_test.php   [NEW]
│   ├── file_rules_test.php   [REPLACED]
│   ├── file_store_test.php   [CHANGED]
│   ├── html_sanitizer_test.php   [DELETED]
│   ├── js   [DELETED (whole folder)]
│   └── manifest_tool_test.php   [NEW]
└── tools/
    ├── gates/
    │   └── verify_manifest.php   [NEW]
    └── ui/
        └── migrate_builder_assets.py   [DELETED]
```

Counts: 101 files are deleted (including 36 screenshots), 15 files are replaced as a whole, 15 files get FIND/REPLACE edits, 21 files are created.

## 3. Database design (PK/FK) — migrations 0009 and 0010

### 3.1 New table `file_references` (migration 0009, additive, "pre-code")

| Column | Type | Why |
|---|---|---|
| `id` | `INT UNSIGNED AUTO_INCREMENT` **PK** | surrogate key; the same style as every other table of the project |
| `media_id` | `INT UNSIGNED NOT NULL` **FK → `media_library.id` ON DELETE RESTRICT** | a file that is still used cannot be deleted by anyone (the same rule `builder_media_refs` had); the API checks first and gives a Persian message, the FK is the second guard |
| `ref_type` | `VARCHAR(60)` ascii, NOT NULL | who owns the reference: `"<module>"` or `"<module>.<entity>"` (example `blog.post`); an identifier, never user text, so ascii keeps the index short |
| `ref_id` | `VARCHAR(64)` ascii, NOT NULL | the owner's record id as a string (integer, uuid or slug all fit) |
| `created_at` | `TIMESTAMP DEFAULT CURRENT_TIMESTAMP` | audit |
| `UNIQUE (media_id, ref_type, ref_id)` | | one row per (file, owner record); lets owners re-sync safely |
| `INDEX (ref_type, ref_id)` | | "all files of record X" and "delete all refs of record X" |

**Why there is no FK on `ref_id`:** the platform cannot know the tables of modules that do not exist yet. The owner module keeps its rows in step through `fileRefsSync()` / `fileRefsDeleteFor()`; `database/tools/diagnose.php` D28 checks that no row points to a missing media file.

### 3.2 Removal (migration 0010, "post-code", irreversible)

Order (children before parents; every step is guarded by "exists?" so the migration can be re-run after a failure):
1. Guard: `CmsInventory::userData()`; any user data and no `MIGRATE_DROP_CMS=1` → exception, nothing changed.
2. Delete grants (`panel_user_permissions`) and rows (`permissions`) of keys `pages, articles, templates, licenses` in one transaction.
3. `site_settings`: drop FK `fk_settings_template`, drop columns `active_template_id`, `site_domain`; replace the untouched seed name.
4. Drop tables in this order: `builder_media_refs → builder_blocks → builder_canvases → templates → template_categories → licenses → marketplace_connections → marketplaces → dashboard_snapshots → page_blocks → article_blocks → pages → articles → article_categories → media_usage` (the last six normally do not exist any more; migration 0006 removed them).

Relationships that explain the order: `builder_canvases.template_id → templates` (so canvases before templates), `templates.category_id → template_categories`, `builder_media_refs → builder_canvases (CASCADE) and → media_library (RESTRICT)`, `site_settings.active_template_id → templates (RESTRICT)` (so the FK goes first).

Kept untouched: `media_library`, `panel_users`, `permissions` (rows `dashboard`, `media`, `settings`), `panel_user_permissions`, `activity_logs`, `notifications`, `site_settings` (other columns).

**Rollback:** only by restoring the database backup taken in Step 9. There is no down-migration.

**Fresh installs:** run the frozen `schema.sql`, then migrations `0001 … 0010`. The CMS tables are created by the baseline and removed by 0010 (on a fresh install they hold only seed rows, so no approval variable is needed).

## 4. Library decision

**No new library, no Composer change.** Everything is plain PHP (the project target is PHP 8.0) and the existing test runner. Reason: this part removes code; the new code is small (a registry helper, an inventory class, a manifest verifier). `composer.json` and `composer.lock` must stay untouched.

## 5. "Must NOT do" table

| Do not | Why |
|---|---|
| Edit `database/schema.sql` | frozen baseline; schema changes are migrations |
| Run any database command outside Step 9 | destructive changes need the owner's decision |
| Set `MIGRATE_DROP_CMS=1` yourself | only the owner approves data loss |
| Delete files under `storage/`, `public/uploads/` | they may hold the owner's only copy of a certificate or template; the owner decides (`cms_inventory.php` lists them) |
| "Fix" a failing FIND by choosing another place | stop the step, report (rule 3) |
| Keep a CMS file "just in case" | the gate `check_cms_removed.php` must print 0 leftovers |
| Touch `composer.json`, `composer.lock`, `vendor/` | no library change |
| Run `git add/commit/reset/checkout` | the owner's folder may not be a repository; history is the owner's |
| Re-format or re-order any file content | byte-for-byte copy |

## 6. Prerequisites (check first)

Work from the **project root** (the folder that contains `composer.json`, `public/`, `includes/`, `tests/`).

1. These must **exist** (otherwise this is not the repository the prompt was written for → stop and tell the owner): `composer.json`, `database/schema.sql`, `database/migrate.php`, `database/lib/Migrator.php`, `includes/nav.php`, `includes/access_map.php`, `public/modules/dashboard/index.php`, `public/modules/media/index.php`, `public/api/media/delete.php`, `tests/run_all.php`, and the highest migration must be `database/migrations/0008_remove_sales_post_code.php`.
2. These must **not** exist (otherwise the part was applied before → stop and ask the owner): `docs/ai/`, `tools/gates/`, `database/migrations/0009_file_references_pre_code.php`, `database/migrations/0010_cms_decommission_post_code.php`, `includes/file_references.php`.
3. `php -v` → PHP 8.0 or newer. Record the version.
4. Run `php tests/run_all.php`. The author's copy printed 12 lines, all `OK`. Record the real number of lines and every line that is not `OK`. A red baseline does **not** stop this part (it deletes much of the tested code) but must be recorded.

## 7. Steps

Do them in this order.

### STEP 1 — pre-flight: create the verifier and check the baseline

Create the two files below, then run the baseline check. This is how you learn whether your repository is the one this prompt was written against.

### CREATE `tools/gates/verify_manifest.php`

Content (copy exactly; it ends with a newline):

```php
<?php
// tools/gates/verify_manifest.php — Part 11A0 (introduced here, used by every later part).
// Verifies files on disk against a manifest. Line endings are normalised to LF before hashing, so Windows checkouts compare equal.
//
//   php tools/gates/verify_manifest.php docs/ai/manifests/11A0-baseline.json
//
// Manifest: {"part":"…","files":{"path":"<sha256>"},"present":["path",…],"absent":["path",…]}
//   files   each file must exist and be byte-identical (MISSING / MISMATCH count as bad)
//   present informational: the path is expected to exist; ABSENT is printed but is NOT bad (somebody may have removed it already)
//   absent  the path must NOT exist; if it does, STILL_THERE counts as bad
// Exit code 0 = nothing bad, 1 = at least one bad line, 2 = usage / unreadable manifest. Plain PHP 8.0, no dependencies.

/** sha256 of the content with CRLF / CR converted to LF. */
function manifestHash(string $content): string {
    return hash('sha256', str_replace(["\r\n", "\r"], "\n", $content));
}

/** @return array{ok:int,bad:int,info:int,lines:string[]} */
function manifestVerify(string $root, array $manifest): array {
    $root = rtrim($root, '/\\');
    $ok = 0;
    $bad = 0;
    $info = 0;
    $lines = [];
    $files = $manifest['files'] ?? [];
    ksort($files, SORT_STRING);
    foreach ($files as $rel => $expected) {
        $path = $root . '/' . $rel;
        if (!is_file($path)) {
            $bad++;
            $lines[] = 'MISSING     ' . $rel;
            continue;
        }
        $actual = manifestHash((string) file_get_contents($path));
        if (hash_equals((string) $expected, $actual)) {
            $ok++;
            $lines[] = 'OK          ' . $rel;
        } else {
            $bad++;
            $lines[] = 'MISMATCH    ' . $rel . '  (expected ' . substr((string) $expected, 0, 12) . ', got ' . substr($actual, 0, 12) . ')';
        }
    }
    foreach ((array) ($manifest['present'] ?? []) as $rel) {
        if (file_exists($root . '/' . $rel)) {
            $ok++;
            $lines[] = 'PRESENT     ' . $rel;
        } else {
            $info++;
            $lines[] = 'ABSENT      ' . $rel . '  (info only)';
        }
    }
    foreach ((array) ($manifest['absent'] ?? []) as $rel) {
        if (file_exists($root . '/' . $rel)) {
            $bad++;
            $lines[] = 'STILL_THERE ' . $rel;
        } else {
            $ok++;
            $lines[] = 'GONE        ' . $rel;
        }
    }
    return ['ok' => $ok, 'bad' => $bad, 'info' => $info, 'lines' => $lines];
}

if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    $manifestPath = $argv[1] ?? '';
    if ($manifestPath === '' || !is_file($manifestPath)) {
        fwrite(STDERR, "usage: php tools/gates/verify_manifest.php <manifest.json>\n");
        exit(2);
    }
    $manifest = json_decode((string) file_get_contents($manifestPath), true);
    if (!is_array($manifest) || !isset($manifest['files']) || !is_array($manifest['files'])) {
        fwrite(STDERR, "manifest is not valid JSON with a \"files\" object\n");
        exit(2);
    }
    $result = manifestVerify(dirname(__DIR__, 2), $manifest);
    foreach ($result['lines'] as $line) {
        echo $line, "\n";
    }
    echo 'manifest ', (string) ($manifest['part'] ?? '?'), ': ', $result['ok'], ' ok, ', $result['bad'], ' bad, ', $result['info'], " info\n";
    exit($result['bad'] > 0 ? 1 : 0);
}
```

### CREATE `docs/ai/manifests/11A0-baseline.json`

Content (copy exactly; it ends with a newline):

```json
{
  "absent": [
    "database/lib/CmsInventory.php",
    "database/migrations/0009_file_references_pre_code.php",
    "database/migrations/0010_cms_decommission_post_code.php",
    "database/tools/check_cms_removed.php",
    "database/tools/cms_inventory.php",
    "docs/ai/DECISIONS.md",
    "docs/ai/FILE-MAP.md",
    "docs/ai/HANDOFF.md",
    "docs/ai/RELEASE-BLOCKERS.md",
    "docs/decommission-cms.md",
    "includes/file_references.php",
    "tests/cms_inventory_test.php",
    "tests/cms_removed_test.php",
    "tests/cms_shell_test.php",
    "tests/file_references_test.php",
    "tests/manifest_tool_test.php"
  ],
  "files": {
    "config/config.php": "2613e8c3bcbb56b2fc206ab99c00c4b829ad3573b96b86dfc347ed0994da84ea",
    "database/README.md": "9461c0f852af7c82ae3c9abe199ce8076e1a0a054ac9e7c5852bade5c99f0dd3",
    "database/tools/backfill_files.php": "b7e863814a952ad25677142fa38241d28dd2d82f304220c8228303463058c4b5",
    "database/tools/diagnose.php": "1ea1c6cc19e9abe36f29173d8cd9ed255a5304826c9bf1ec82d57cdb978ba686",
    "database/tools/gc_files.php": "d722e885fcd2a290b074bf96580cc582969cd50ea730dddb633c80d5c000ede1",
    "docs/ui-guide.md": "530621ae76718ff9e2445bd25f971d3bb9c309e2d552e3f9ab2de1426ff37c6f",
    "docs/ui-pages.md": "15f2e49bdffb754a56335207b34aee3e6a8a18d79c160da0ae27ecc0a449c5c7",
    "includes/access_map.php": "2f711402f607669e437c645361db42f22b5ced75fb66615fa26655c5e81978d4",
    "includes/dashboard_data.php": "4e2d7337a4dd7d0e32c28921768d216ff1145a96303e0037d27d768f7db3d16c",
    "includes/file_rules.php": "4038b2d41e9937aa437e1cfbe96d9e6b363f440b65de370b5fbadc7b747ffdf1",
    "includes/file_store.php": "229aa1062b0fde4ae1711402c56496bb4c9cdae972ca8956c68f43c902ea1ac0",
    "includes/layout/sidebar.php": "21b8c5db69b8920dedec60e96b2ec431fb1c5dba69c86860c6f61e7da7d08943",
    "includes/nav.php": "55418dc200e6ff5ee812c2c63d1356e5d9374ed185a949bb09af35f9122430f2",
    "public/api/media/delete.php": "82b714318b06c46f335923f761a449b06336f25001ab9582b7ab88913d8aa220",
    "public/assets/css/app/00-tokens.css": "5037a1d6767c8981e926d64e4ff6689ce177b3811d5c2ef6fdc08e012e25e19c",
    "public/assets/css/app/20-shell.css": "7ac04cbd5a96eed1e50df4aa664985723d11ccdd647fa254a83accb4991fccd8",
    "public/assets/css/app/40-motion.css": "f3b60143ada3470c2f9a8b86fad8aef27fd52c27f4b46f29330b02766dc692c0",
    "public/assets/css/app/45-pages.css": "828c2cdc4ebdd3cb9d96086fc1a3255c8e84f3145c793bfb8f9110eed76be781",
    "public/modules/dashboard/index.php": "3dda0a6245fdc63aeaec4ce5ad05463b9fbc3210ff1bb4fabd43c46dc67ecd1e",
    "public/modules/media/index.php": "dadc1e96e7e6d1f6467a9bf958e664b0c2f64b8519845b8e594a3e11c942144c",
    "public/modules/settings/general.php": "bee9839701d67181473b2d5b62a2893f48005b9bd06366c8ce93edc2f9c2e776",
    "public/ui-kit/index.html": "19234f3315f76a38608083671b569acebe7ffcaa9f3ce78ca2580ab3d67830cf",
    "tests/README.md": "34b24d740ba8c62ea60f83174c62889e7d49e5c80e304bb229499c90f411ccd6",
    "tests/browser/check_pages_semantics.py": "bb25a6d59cfbee6d63dd8d2e71525a78e2d0e6e78f02c67db57867ece852ca98",
    "tests/browser/run_ui_checks.py": "824bcec367d6dd422e7cb51d40ec83f4c45ffd8aabdc8a2821da143dda17be83",
    "tests/dashboard_data_test.php": "b8f4262d2eb4bdb155098088cbf8028808838a02d161ce5e4d957295b990ea55",
    "tests/file_rules_test.php": "a6b3f7f06b6276fcf8936e6166d8250101df2914d851448c3a47015f8b98d2f2",
    "tests/file_store_test.php": "6fe8f6880bd0d0bf62dd207671f600798c9c886a17dbfd3d63748483744af944",
    "tests/ui/check_pages_covered.py": "c5c63d0c028de959ad1a8d5a1ea9d20873f56b263985e0ee38033ae0db1052b7",
    "tests/ui/check_ui_gates.py": "feff49d319703ba189fc93016e5dfc041d42a3c47dc57de7a8d1737b3fb2a599"
  },
  "part": "11A0-baseline",
  "present": [
    "database/tools/encrypt_existing_secrets.php",
    "docs/builder-architecture.md",
    "includes/builder/block_registry.php",
    "includes/builder/bootstrap.php",
    "includes/builder/builder_assets.php",
    "includes/builder/canvas_repository.php",
    "includes/builder/entry.php",
    "includes/builder/html_sanitizer.php",
    "includes/builder/media_refs.php",
    "includes/builder/pages_list.php",
    "includes/builder/renderer.php",
    "includes/builder/tree_validator.php",
    "includes/builder/types/button.php",
    "includes/builder/types/image_video.php",
    "includes/builder/types/slider.php",
    "includes/builder/types/story.php",
    "includes/builder/types/structural.php",
    "includes/builder/types/text.php",
    "public/api/builder/delete.php",
    "public/api/builder/load.php",
    "public/api/builder/media.php",
    "public/api/builder/render.php",
    "public/api/builder/save.php",
    "public/api/licenses/update.php",
    "public/api/templates/activate.php",
    "public/api/templates/install.php",
    "public/api/templates/upload.php",
    "public/assets/css/builder/10-shell.css",
    "public/assets/css/builder/20-canvas.css",
    "public/assets/css/builder/30-toolbar-interactions.css",
    "public/assets/css/builder/40-content-blocks.css",
    "public/assets/css/builder/50-media-picker.css",
    "public/assets/js/builder/block-registry.js",
    "public/assets/js/builder/builder-core.js",
    "public/assets/js/builder/builder-shell.js",
    "public/assets/js/builder/builder-tree.js",
    "public/assets/js/builder/types/.gitkeep",
    "public/assets/js/builder/types/00-structural.js",
    "public/assets/js/builder/types/10-text.js",
    "public/assets/js/builder/types/11-image-video.js",
    "public/assets/js/builder/types/12-button.js",
    "public/assets/js/builder/types/20-slider.js",
    "public/assets/js/builder/types/21-story.js",
    "public/assets/js/builder/ui/00-sortable.js",
    "public/assets/js/builder/ui/10-canvas.js",
    "public/assets/js/builder/ui/20-settings-panel.js",
    "public/assets/js/builder/ui/21-insert-menu.js",
    "public/assets/js/builder/ui/30-preview.js",
    "public/assets/js/builder/ui/40-media-picker.js",
    "public/assets/js/builder/ui/50-page-actions.js",
    "public/assets/js/pages-manager.js",
    "public/file.php",
    "public/modules/articles/index.php",
    "public/modules/licenses/index.php",
    "public/modules/pages/index.php",
    "public/modules/templates/index.php",
    "tests/browser/engine_contract.json",
    "tests/browser/run_builder_checks.py",
    "tests/browser/run_builder_checks_async.py",
    "tests/browser/shots/builder-host-1280-dark.png",
    "tests/browser/shots/builder-host-1280-light.png",
    "tests/browser/shots/builder-host-360-dark.png",
    "tests/browser/shots/builder-host-360-light.png",
    "tests/browser/shots/builder-host-768-dark.png",
    "tests/browser/shots/builder-host-768-light.png",
    "tests/browser/shots/live-modules-articles-index-php-1280-dark.png",
    "tests/browser/shots/live-modules-articles-index-php-1280-light.png",
    "tests/browser/shots/live-modules-articles-index-php-360-dark.png",
    "tests/browser/shots/live-modules-articles-index-php-360-light.png",
    "tests/browser/shots/live-modules-articles-index-php-768-dark.png",
    "tests/browser/shots/live-modules-articles-index-php-768-light.png",
    "tests/browser/shots/live-modules-dashboard-index-php-1280-dark.png",
    "tests/browser/shots/live-modules-dashboard-index-php-1280-light.png",
    "tests/browser/shots/live-modules-dashboard-index-php-360-dark.png",
    "tests/browser/shots/live-modules-dashboard-index-php-360-light.png",
    "tests/browser/shots/live-modules-dashboard-index-php-768-dark.png",
    "tests/browser/shots/live-modules-dashboard-index-php-768-light.png",
    "tests/browser/shots/live-modules-licenses-index-php-1280-dark.png",
    "tests/browser/shots/live-modules-licenses-index-php-1280-light.png",
    "tests/browser/shots/live-modules-licenses-index-php-360-dark.png",
    "tests/browser/shots/live-modules-licenses-index-php-360-light.png",
    "tests/browser/shots/live-modules-licenses-index-php-768-dark.png",
    "tests/browser/shots/live-modules-licenses-index-php-768-light.png",
    "tests/browser/shots/live-modules-pages-index-php-1280-dark.png",
    "tests/browser/shots/live-modules-pages-index-php-1280-light.png",
    "tests/browser/shots/live-modules-pages-index-php-360-dark.png",
    "tests/browser/shots/live-modules-pages-index-php-360-light.png",
    "tests/browser/shots/live-modules-pages-index-php-768-dark.png",
    "tests/browser/shots/live-modules-pages-index-php-768-light.png",
    "tests/browser/shots/live-modules-templates-index-php-1280-dark.png",
    "tests/browser/shots/live-modules-templates-index-php-1280-light.png",
    "tests/browser/shots/live-modules-templates-index-php-360-dark.png",
    "tests/browser/shots/live-modules-templates-index-php-360-light.png",
    "tests/browser/shots/live-modules-templates-index-php-768-dark.png",
    "tests/browser/shots/live-modules-templates-index-php-768-light.png",
    "tests/builder_entry_test.php",
    "tests/builder_pages_list_test.php",
    "tests/builder_registry_test.php",
    "tests/html_sanitizer_test.php",
    "tests/js/builder_tree_test.js",
    "tools/ui/migrate_builder_assets.py"
  ]
}
```

Run:

```text
php -l tools/gates/verify_manifest.php
php tools/gates/verify_manifest.php docs/ai/manifests/11A0-baseline.json
```

Expected last line: `manifest 11A0-baseline: 147 ok, 0 bad, 0 info`.

- `MISMATCH` on a file means the owner's copy of that file differs from the one this prompt expects. **Do not apply any REPLACE WHOLE FILE or EDIT block for that file**, write the path in the changelog under "Deviations", and continue with the other steps.
- `MISSING` on a file means a file this prompt edits is not there: skip every block for it, record it.
- `ABSENT … (info only)` on a path to be deleted is fine (somebody removed it already): skip that deletion.
- `STILL_THERE` on a file this prompt creates means this part was applied before: **stop** and ask the owner.

Write the exact output of both commands, the PHP version and the baseline test output into the changelog later (Step 10).

### STEP 2 — backup (the only step where you choose the method)

Make a full copy of the project folder to `../panel-backups/pre-11A0/` (create the parent if needed). Exclude `vendor/`, `node_modules/` and `.git/`; **include** `storage/` and `public/uploads/`. Example (Windows): `robocopy . ..\panel-backups\pre-11A0 /E /XD vendor node_modules .git` (robocopy exit codes below 8 are success). If the target exists use `pre-11A0-2` and so on. If you cannot make the copy → stop and tell the owner. Write the backup path in the changelog. (The **database** backup is the owner's job in Step 9.)

### STEP 3 — delete the CMS files

Delete these **folders** (with everything inside):

```text
public/modules/pages
public/modules/articles
public/modules/templates
public/modules/licenses
public/api/builder
public/api/templates
public/api/licenses
includes/builder
public/assets/js/builder
public/assets/css/builder
tests/js
```

Delete these **files**:

```text
public/file.php
public/assets/js/pages-manager.js
docs/builder-architecture.md
tests/builder_entry_test.php
tests/builder_pages_list_test.php
tests/builder_registry_test.php
tests/html_sanitizer_test.php
tests/browser/engine_contract.json
tests/browser/run_builder_checks.py
tests/browser/run_builder_checks_async.py
tools/ui/migrate_builder_assets.py
database/tools/encrypt_existing_secrets.php
tests/browser/shots/builder-host-1280-dark.png
tests/browser/shots/builder-host-1280-light.png
tests/browser/shots/builder-host-360-dark.png
tests/browser/shots/builder-host-360-light.png
tests/browser/shots/builder-host-768-dark.png
tests/browser/shots/builder-host-768-light.png
tests/browser/shots/live-modules-articles-index-php-1280-dark.png
tests/browser/shots/live-modules-articles-index-php-1280-light.png
tests/browser/shots/live-modules-articles-index-php-360-dark.png
tests/browser/shots/live-modules-articles-index-php-360-light.png
tests/browser/shots/live-modules-articles-index-php-768-dark.png
tests/browser/shots/live-modules-articles-index-php-768-light.png
tests/browser/shots/live-modules-dashboard-index-php-1280-dark.png
tests/browser/shots/live-modules-dashboard-index-php-1280-light.png
tests/browser/shots/live-modules-dashboard-index-php-360-dark.png
tests/browser/shots/live-modules-dashboard-index-php-360-light.png
tests/browser/shots/live-modules-dashboard-index-php-768-dark.png
tests/browser/shots/live-modules-dashboard-index-php-768-light.png
tests/browser/shots/live-modules-licenses-index-php-1280-dark.png
tests/browser/shots/live-modules-licenses-index-php-1280-light.png
tests/browser/shots/live-modules-licenses-index-php-360-dark.png
tests/browser/shots/live-modules-licenses-index-php-360-light.png
tests/browser/shots/live-modules-licenses-index-php-768-dark.png
tests/browser/shots/live-modules-licenses-index-php-768-light.png
tests/browser/shots/live-modules-pages-index-php-1280-dark.png
tests/browser/shots/live-modules-pages-index-php-1280-light.png
tests/browser/shots/live-modules-pages-index-php-360-dark.png
tests/browser/shots/live-modules-pages-index-php-360-light.png
tests/browser/shots/live-modules-pages-index-php-768-dark.png
tests/browser/shots/live-modules-pages-index-php-768-light.png
tests/browser/shots/live-modules-templates-index-php-1280-dark.png
tests/browser/shots/live-modules-templates-index-php-1280-light.png
tests/browser/shots/live-modules-templates-index-php-360-dark.png
tests/browser/shots/live-modules-templates-index-php-360-light.png
tests/browser/shots/live-modules-templates-index-php-768-dark.png
tests/browser/shots/live-modules-templates-index-php-768-light.png
```

Check: none of the paths above exists any more (a path that did not exist before is only information). Do not delete anything else.

### STEP 4 — replace whole files

Each block replaces one file completely.

### REPLACE WHOLE FILE `includes/access_map.php`

The access map keeps only the five platform pages.

Delete the old content of the file and write exactly this (the baseline manifest in Step 1 already proved the old content is the one this prompt was written against):

```php
<?php
return [
    '/modules/dashboard/index.php'  => ['module' => 'dashboard', 'action' => 'view'],
    '/modules/media/index.php'      => ['module' => 'media',     'action' => 'view'],
    '/modules/settings/general.php' => ['module' => 'settings',  'action' => 'view'],
    '/modules/settings/profile.php' => ['login_only' => true],
    '/modules/settings/staff.php'   => ['owner_only' => true],
];
```

### REPLACE WHOLE FILE `includes/nav.php`

The sidebar menu keeps dashboard and media, then profile, general settings and staff.

Delete the old content of the file and write exactly this (the baseline manifest in Step 1 already proved the old content is the one this prompt was written against):

```php
<?php
// includes/nav.php — the sidebar menu as DATA. Adding a menu item later = one array row.
// visible: 'always' | 'owner' | 'perm:<module_key>' (hasPermission(key,'view')).
// match:   'module' (item key equals $currentModule) | 'path' (the request script path ends with url).
return [
    [
        'title' => 'مدیریت',
        'items' => [
            ['key' => 'dashboard', 'label' => 'پیشخوان',          'icon' => 'ti-layout-dashboard', 'url' => '/modules/dashboard/index.php', 'visible' => 'perm:dashboard', 'match' => 'module'],
            ['key' => 'media',     'label' => 'رسانه و عکس‌ها',   'icon' => 'ti-photo',            'url' => '/modules/media/index.php',     'visible' => 'perm:media',     'match' => 'module'],
        ],
    ],
    [
        'title' => 'تنظیمات پنل',
        'items' => [
            ['key' => 'profile',  'label' => 'پروفایل من',          'icon' => 'ti-user-circle',            'url' => '/modules/settings/profile.php', 'visible' => 'always',          'match' => 'path'],
            ['key' => 'general',  'label' => 'تنظیمات عمومی',       'icon' => 'ti-adjustments-horizontal', 'url' => '/modules/settings/general.php', 'visible' => 'perm:settings',   'match' => 'path'],
            ['key' => 'staff',    'label' => 'کاربران و دسترسی‌ها', 'icon' => 'ti-users',                  'url' => '/modules/settings/staff.php',   'visible' => 'owner',           'match' => 'path'],
        ],
    ],
];
```

### REPLACE WHOLE FILE `includes/dashboard_data.php`

Adds two data providers for the new dashboard (`dashboardMediaCount`, `dashboardActiveStaffCount`); the old function is unchanged.

Delete the old content of the file and write exactly this (the baseline manifest in Step 1 already proved the old content is the one this prompt was written against):

```php
<?php
// Dashboard data providers (Part 10C). Read-only, prepared/static SQL, no HTML, no rendering.
// dashboardActivityLabel() is pure and unit-testable without a database.

/**
 * Persian one-line label for an activity_logs row joined with permissions.module_label.
 * Rows of a removed module (old rows are kept as audit trail) have no module_label and get the generic fallback.
 */
function dashboardActivityLabel(array $row): string
{
    $desc = trim((string)($row['description'] ?? ''));
    if ($desc !== '') {
        return mb_substr($desc, 0, 140, 'UTF-8');
    }
    $module = trim((string)($row['module_label'] ?? ''));
    return $module !== '' ? 'فعالیت در «' . $module . '»' : 'فعالیت ثبت‌شده';
}

/** Owner-only recent-activity feed: newest first, no IP address, $limit clamped to 1..20. Caller checks isOwner(). */
function dashboardRecentActivity(PDO $pdo, int $limit = 6): array
{
    $limit = max(1, min(20, $limit));
    $stmt = $pdo->query("SELECT l.id, l.action_type, l.module_key, l.description, l.created_at,
                                u.full_name, p.module_label
                         FROM activity_logs l
                         JOIN panel_users u ON u.id = l.panel_user_id
                         LEFT JOIN permissions p ON p.module_key = l.module_key
                         ORDER BY l.id DESC
                         LIMIT $limit");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['label'] = dashboardActivityLabel($r);
    }
    unset($r);
    return $rows;
}

/** Number of files in the media library (purpose "library"; avatars and brand images are not counted). */
function dashboardMediaCount(PDO $pdo): int
{
    return (int)$pdo->query("SELECT COUNT(*) FROM media_library WHERE purpose = 'library'")->fetchColumn();
}

/** Number of active non-owner accounts. Owner-only widget: the caller checks isOwner(). */
function dashboardActiveStaffCount(PDO $pdo): int
{
    return (int)$pdo->query("SELECT COUNT(*) FROM panel_users WHERE role = 'staff' AND status = 'active'")->fetchColumn();
}
```

### REPLACE WHOLE FILE `public/modules/dashboard/index.php`

The generic dashboard.

Delete the old content of the file and write exactly this (the baseline manifest in Step 1 already proved the old content is the one this prompt was written against):

```php
<?php
// Dashboard (Part 11A0). Generic platform dashboard: system health, media count, active staff (owner), recent activity (owner).
// Data comes from includes/dashboard_data.php; this file only arranges it. Widgets become a registry in a later part.
$currentModule='dashboard'; $pageTitle='پیشخوان'; $breadcrumb='پیشخوان';
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/permissions.php';
require_once __DIR__ . '/../../../includes/dashboard_data.php';
requirePermission('dashboard','view');
$pdo=getPDO();

$jalaliToday=toJalali(date('Y-m-d'));
$userName=e($user['full_name']);
$health=true; $diskFree=@disk_free_space(PUBLIC_PATH); if($diskFree!==false && $diskFree < 100*1024*1024) $health=false;
try{ $pdo->query("SELECT 1"); }catch(Throwable $e){ $health=false; }

$isOwner=isOwner();
$mediaCount=dashboardMediaCount($pdo);
$staffCount=$isOwner ? dashboardActiveStaffCount($pdo) : 0;
$activity=$isOwner ? dashboardRecentActivity($pdo,6) : [];
$canUploadMedia=hasPermission('media','edit');
?>
<div class="page-head">
  <p class="page-head__lead">سلام <?= $userName ?> عزیز، خوش آمدید. امروز <?= toPersianDigits($jalaliToday) ?></p>
  <?php if($canUploadMedia): ?>
  <div class="page-head__actions">
    <a class="btn btn-outline-secondary" href="/modules/media/index.php"><i class="ti ti-upload" aria-hidden="true"></i>آپلود رسانه</a>
  </div>
  <?php endif; ?>
</div>

<div class="row g-3 stagger" id="stats">
  <div class="col-12 col-sm-6 col-xl-4"><div class="card stat <?= $health?'stat--success':'stat--warning' ?> hover-lift"><span class="stat__icon"><i class="ti <?= $health?'ti-heart-rate-monitor':'ti-alert-triangle' ?>" aria-hidden="true"></i></span><div class="stat__body"><p class="stat__label">وضعیت سیستم</p><p class="stat__value"><?= $health? 'عالی' : 'نیاز به بررسی' ?></p><p class="stat__hint"><?= $health?'همه چیز مرتب است':'فضای دیسک یا اتصال را بررسی کنید' ?></p></div></div></div>
  <div class="col-12 col-sm-6 col-xl-4"><div class="card stat stat--info hover-lift"><span class="stat__icon"><i class="ti ti-photo" aria-hidden="true"></i></span><div class="stat__body"><p class="stat__label">فایل‌های رسانه</p><p class="stat__value" data-countup="<?= (int)$mediaCount ?>"><?= toPersianDigits($mediaCount) ?></p><p class="stat__hint">تصویر و ویدیوی آپلودشده</p></div></div></div>
  <?php if($isOwner): ?>
  <div class="col-12 col-sm-6 col-xl-4"><div class="card stat hover-lift"><span class="stat__icon"><i class="ti ti-users" aria-hidden="true"></i></span><div class="stat__body"><p class="stat__label">همکاران فعال</p><p class="stat__value" data-countup="<?= (int)$staffCount ?>"><?= toPersianDigits($staffCount) ?></p><p class="stat__hint">حساب‌های فعال غیرمالک</p></div></div></div>
  <?php endif; ?>
</div>

<?php if($isOwner): ?>
<div class="row g-3">
  <div class="col-12">
    <section class="card" aria-labelledby="activity-title">
      <div class="card-head"><h2 class="card-head__title" id="activity-title">فعالیت‌های اخیر</h2></div>
      <?php if(!$activity): ?>
      <div class="empty-state">
        <span class="empty-state__icon"><i class="ti ti-history" aria-hidden="true"></i></span>
        <p class="empty-state__title">هنوز فعالیتی ثبت نشده است</p>
      </div>
      <?php else: ?>
      <ul class="activity-list">
        <?php foreach($activity as $a): ?>
        <li>
          <span class="activity-list__text"><?= e($a['label']) ?></span>
          <span class="activity-list__meta"><?= e($a['full_name']) ?> · <?= toPersianDigits(toJalali(substr((string)$a['created_at'],0,10))) ?> <bdi><?= toPersianDigits(substr((string)$a['created_at'],11,5)) ?></bdi></span>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </section>
  </div>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
```

### REPLACE WHOLE FILE `public/api/media/delete.php`

The delete API asks `file_references` instead of the builder.

Delete the old content of the file and write exactly this (the baseline manifest in Step 1 already proved the old content is the one this prompt was written against):

```php
<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/file_references.php';
require_once __DIR__ . '/../../../includes/file_store.php';
requireLogin(); requirePermission('media','delete');
if($_SERVER['REQUEST_METHOD']!=='POST') jsonResponse(['success'=>false,'message'=>'متد مجاز نیست'],405);
if(!csrf_verify($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')){ jsonResponse(['success'=>false,'message'=>'توکن نامعتبر'],403); }
$id=(int)($_POST['id']??0);
if(!$id) jsonResponse(['success'=>false,'message'=>'شناسه نامعتبر'],400);
$pdo=getPDO();

function mediaInUseResponse(PDO $pdo, int $id): void {
  $total=max(1,fileRefsCount($pdo,$id));
  jsonResponse(['success'=>false,'message'=>'این فایل در '.toPersianDigits($total).' مورد استفاده شده و قابل حذف نیست.'],409);
}

// Only library files can be deleted here: avatars and site assets are released by their own screens.
$m=$pdo->prepare("SELECT id, file_path FROM media_library WHERE id=? AND purpose='library'"); $m->execute([$id]); $row=$m->fetch();
if(!$row) jsonResponse(['success'=>false,'message'=>'یافت نشد'],404);
if(fileRefsCount($pdo,$id)>0) mediaInUseResponse($pdo,$id);
try{
  $pdo->prepare("DELETE FROM media_library WHERE id=? AND purpose='library'")->execute([$id]);
}catch(PDOException $e){
  if((int)($e->errorInfo[1]??0)===1451){ mediaInUseResponse($pdo,$id); }
  error_log('media/delete.php: '.$e->getMessage());
  jsonResponse(['success'=>false,'message'=>'خطا در حذف فایل'],500);
}
FileStore::remove((string)$row['file_path']);       // after the row is gone; logs (never throws) when the unlink fails
logActivity('media_delete','media','حذف رسانه '.$id);
jsonResponse(['success'=>true,'message'=>'فایل حذف شد']);
```

### REPLACE WHOLE FILE `public/modules/settings/general.php`

«هویت برنامه» instead of «هویت سایت»; the domain field is gone.

Delete the old content of the file and write exactly this (the baseline manifest in Step 1 already proved the old content is the one this prompt was written against):

```php
<?php
$currentModule='settings'; $pageTitle='تنظیمات عمومی'; $breadcrumb='تنظیمات پنل ‹ عمومی';
require_once __DIR__ . '/../../../includes/page_boot.php';
require_once __DIR__ . '/../../../includes/file_store.php';
require_once __DIR__ . '/../../../includes/media_registry.php';
requirePermission('settings','view');
$canEdit=hasPermission('settings','edit');
$pdo=getPDO();
$site=$pdo->query("SELECT id, site_name, logo_path, favicon_path FROM site_settings WHERE id=1")->fetch();
$error=null;

if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!$canEdit){ denyAccess(); }
  requireCsrf();
  $site_name=trim((string)($_POST['site_name']??''));
  if(mb_strlen($site_name,'UTF-8')<1 || mb_strlen($site_name,'UTF-8')>150) $error='نام برنامه باید بین ۱ تا ۱۵۰ کاراکتر باشد.';

  $stored=[];            // files written in this request (rolled back on any failure)
  $newPath=['logo_path'=>$site['logo_path'],'favicon_path'=>$site['favicon_path']];
  $oldPaths=[];
  if($error===null){
    try{
      foreach(['logo'=>'logo_path','favicon'=>'favicon_path'] as $field=>$col){
        $f=$_FILES[$field]??null;
        if(!is_array($f) || (int)($f['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) continue;
        if($field==='logo'){
          $s=FileStore::storeImage($f,'media');                     // jpg / png / webp, re-encoded; SVG is no longer accepted (H2)
        } else {
          $fi=finfo_open(FILEINFO_MIME_TYPE); $mime=$fi? (string)finfo_file($fi,(string)($f['tmp_name']??'')):''; if($fi) finfo_close($fi);
          if(isIcoMime($mime))            $s=FileStore::storeIco($f);
          elseif($mime==='image/png')     $s=FileStore::storeImage($f,'media');
          else throw new FileStoreException('فاوآیکون باید PNG یا ICO باشد');
        }
        $stored[$col]=[$s,(string)($f['name']??'file')];
      }
    }catch(FileStoreException $e){
      foreach($stored as $pair){ FileStore::remove($pair[0]['path']); }
      $stored=[]; $error=$e->getMessage();
    }
  }
  if($error===null){
    try{
      $pdo->beginTransaction();
      foreach($stored as $col=>$pair){
        $reg=mediaRegisterFile($pdo,$pair[0],$pair[1],'site_asset',(int)$_SESSION['user_id']);
        if($site[$col]!==$reg['path']) $oldPaths[]=$site[$col];
        $newPath[$col]=$reg['path'];
      }
      $pdo->prepare("UPDATE site_settings SET site_name=?, logo_path=?, favicon_path=? WHERE id=1")
          ->execute([$site_name,$newPath['logo_path'],$newPath['favicon_path']]);
      $pdo->commit();
    }catch(Throwable $e){
      if($pdo->inTransaction()) $pdo->rollBack();
      foreach($stored as $pair){ FileStore::remove($pair[0]['path']); }   // reused rows keep their older file (remove() only touches the new name)
      error_log('general.php: '.$e->getMessage());
      $error='خطا در ذخیره‌سازی.';
    }
  }
  if($error===null){
    foreach($oldPaths as $old){ mediaReleasePath($pdo,$old,'site_asset'); }   // only after the commit
    logActivity('site_settings_update','settings','ویرایش تنظیمات عمومی');
    header('Location: general.php?saved=1'); exit;
  }
  $site['site_name']=$site_name;                                              // keep what the user typed
}
require_once __DIR__ . '/../../../includes/header.php';
$dis=$canEdit?'':'disabled';
?>
<?php if(isset($_GET['saved'])): ?><div class="alert alert-success d-flex align-items-start gap-2" role="status"><i class="ti ti-circle-check" aria-hidden="true"></i><span>تنظیمات ذخیره شد.</span></div><?php endif; ?>
<?php if($error): ?><div class="alert alert-danger d-flex align-items-start gap-2" role="alert"><i class="ti ti-alert-circle" aria-hidden="true"></i><span><?= e($error) ?></span></div><?php endif; ?>
<?php if(!$canEdit): ?><div class="alert alert-info d-flex align-items-start gap-2" role="status"><i class="ti ti-lock" aria-hidden="true"></i><span>شما فقط اجازه‌ی مشاهده‌ی این تنظیمات را دارید.</span></div><?php endif; ?>
<div class="row">
  <div class="col-12 col-xl-8">
    <form method="post" enctype="multipart/form-data" class="card">
      <?= csrf_field() ?>
      <fieldset class="card-pad settings-fieldset"<?= $canEdit ? '' : ' disabled' ?>>
        <section class="settings-section" aria-labelledby="general-id-title">
          <h2 class="settings-section__title" id="general-id-title">هویت برنامه</h2>
          <div class="form-grid">
            <div class="field"><label class="field__label" for="general-name">نام برنامه</label><input id="general-name" name="site_name" value="<?= e($site['site_name']) ?>" class="form-control" maxlength="150"></div>
          </div>
        </section>
        <section class="settings-section" aria-labelledby="general-brand-title">
          <h2 class="settings-section__title" id="general-brand-title">تصاویر برند</h2>
          <div class="form-grid">
            <div class="field">
              <label class="field__label" for="general-logo">لوگو</label>
              <input id="general-logo" type="file" name="logo" accept="image/jpeg,image/png,image/webp" class="form-control" aria-describedby="general-logo-hint">
              <p class="field__hint" id="general-logo-hint">فرمت‌های مجاز: JPG، PNG یا WebP</p>
              <?php if($site['logo_path']): ?><div class="asset-preview"><img src="<?= e($site['logo_path']) ?>" alt="" class="asset-preview__img"><span class="asset-preview__cap">لوگوی فعلی</span></div><?php endif; ?>
            </div>
            <div class="field">
              <label class="field__label" for="general-favicon">فاوآیکون</label>
              <input id="general-favicon" type="file" name="favicon" accept="image/png,image/x-icon,image/vnd.microsoft.icon,.ico" class="form-control" aria-describedby="general-favicon-hint">
              <p class="field__hint" id="general-favicon-hint">فرمت‌های مجاز: PNG یا ICO</p>
              <?php if($site['favicon_path']): ?><div class="asset-preview"><img src="<?= e($site['favicon_path']) ?>" alt="" class="asset-preview__img asset-preview__img--icon"><span class="asset-preview__cap">فاوآیکون فعلی</span></div><?php endif; ?>
            </div>
          </div>
        </section>
      </fieldset>
      <?php if($canEdit): ?><div class="card-foot"><div class="form-actions"><button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy" aria-hidden="true"></i>ذخیره تنظیمات</button></div></div><?php endif; ?>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
```

### REPLACE WHOLE FILE `public/assets/css/app/45-pages.css`

The Templates and Licenses blocks are removed; media, staff, profile and general blocks stay.

Delete the old content of the file and write exactly this (the baseline manifest in Step 1 already proved the old content is the one this prompt was written against):

```css
/* ==========================================================================
   45-pages.css — page-specific layout (media library, staff, profile, general settings).
   Rules: tokens only (no color literal, no physical left/right), no !important, no new colors.
   Shared components stay in 35-components.css; anything used by two pages belongs there, not here.
   ========================================================================== */

/* ---- Media library --------------------------------------------------------- */
.media-grid { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(9.5rem, 1fr)); gap: var(--space-3); }
.media-tile { inline-size: 100%; display: flex; flex-direction: column; gap: var(--space-2); padding: var(--space-2); text-align: start; color: var(--text); background: var(--surface); border: 1px solid var(--border-strong); border-radius: var(--radius-md); cursor: pointer; transition: border-color var(--dur-1) var(--ease), box-shadow var(--dur-1) var(--ease); }
.media-tile:hover { border-color: var(--primary); box-shadow: var(--shadow-2); }
.media-tile__thumb { display: grid; place-items: center; aspect-ratio: 1 / 1; overflow: hidden; border-radius: var(--radius-sm); background: var(--surface-3); color: var(--text-muted); font-size: 2rem; }
.media-tile__thumb img { inline-size: 100%; block-size: 100%; object-fit: cover; }
.media-tile__name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: var(--fs-sm); font-weight: var(--fw-medium); }
.media-tile__meta { color: var(--text-muted); font-size: var(--fs-xs); }
.media-preview__el { display: block; margin-inline: auto; max-inline-size: 100%; max-block-size: 60vh; border-radius: var(--radius-sm); object-fit: contain; }
.upload-list { display: grid; gap: var(--space-2); margin-block-start: var(--space-3); }
.upload-list:empty { display: none; }
.upload-row { display: grid; grid-template-columns: minmax(0, 1fr) minmax(6rem, 14rem) 3.25rem; align-items: center; gap: var(--space-3); font-size: var(--fs-sm); }
.upload-row__name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.upload-row__pct { text-align: end; color: var(--text-muted); font-variant-numeric: tabular-nums; }
.upload-row progress { inline-size: 100%; block-size: 0.5rem; appearance: none; border: 0; border-radius: var(--radius-pill); background: var(--surface-3); overflow: hidden; }
.upload-row progress::-webkit-progress-bar { background: var(--surface-3); }
.upload-row progress::-webkit-progress-value { background: var(--primary); border-radius: var(--radius-pill); transition: inline-size var(--dur-1) var(--ease); }
.upload-row progress::-moz-progress-bar { background: var(--primary); border-radius: var(--radius-pill); }

/* ---- Staff ----------------------------------------------------------------- */
.perm-chips { display: flex; flex-wrap: wrap; gap: var(--space-2); }
.perm-chip { white-space: nowrap; display: inline-flex; align-items: baseline; gap: var(--space-1); padding: 0.1rem 0.65rem; border: 1px solid var(--border-strong); border-radius: var(--radius-pill); background: var(--surface-2); color: var(--text); font-size: var(--fs-xs); }
.perm-chip__rights { color: var(--text-muted); }
.perm-chip--none { border-style: dashed; color: var(--text-muted); }
.perm-table th, .perm-table td { text-align: center; vertical-align: middle; }
.perm-table th:first-child, .perm-table td:first-child { text-align: start; }
.perm-table .form-check-input { margin: 0; }

/* ---- Profile and general settings ------------------------------------------ */
.profile-avatar { display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-4); }
.avatar-lg--empty { display: grid; place-items: center; color: var(--text-muted); font-size: 2.75rem; }
.asset-preview { display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-3); padding: var(--space-3); border: 1px dashed var(--border-strong); border-radius: var(--radius-md); background: var(--surface-2); }
.asset-preview__img { max-block-size: 4rem; max-inline-size: 10rem; object-fit: contain; }
.asset-preview__img--icon { inline-size: 2rem; block-size: 2rem; }
.asset-preview__cap { color: var(--text-muted); font-size: var(--fs-sm); }
.settings-section + .settings-section { margin-block-start: var(--space-6); padding-block-start: var(--space-6); border-block-start: 1px solid var(--border); }
.settings-section__title { margin: 0 0 var(--space-4); font-size: var(--fs-md); font-weight: var(--fw-bold); color: var(--text-heading); }
.settings-fieldset { margin: 0; border: 0; min-inline-size: 0; }
```

### REPLACE WHOLE FILE `includes/file_rules.php`

Certificate and ZIP helpers removed; private path shape is generic.

Delete the old content of the file and write exactly this (the baseline manifest in Step 1 already proved the old content is the one this prompt was written against):

```php
<?php
// Pure rules for uploaded files. No DB, no filesystem writes. Unit-tested in tests/file_rules_test.php.

/** Persian message for a PHP UPLOAD_ERR_* code (never UPLOAD_ERR_OK). */
function uploadErrorMessage(int $code): string {
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE: return 'حجم فایل بیش از حد مجاز سرور است';
        case UPLOAD_ERR_PARTIAL:   return 'فایل به‌صورت ناقص ارسال شد. دوباره تلاش کنید';
        case UPLOAD_ERR_NO_FILE:   return 'فایلی ارسال نشده';
        default:                   return 'خطای سرور در دریافت فایل';
    }
}

function imageExtForMime(string $mime): ?string {
    $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    return $map[$mime] ?? null;
}

function videoExtForMime(string $mime): ?string {
    $map = ['video/mp4' => 'mp4', 'video/webm' => 'webm'];
    return $map[$mime] ?? null;
}

/** MIME types finfo may report for a real .ico file. */
function isIcoMime(string $mime): bool {
    return in_array($mime, ['image/vnd.microsoft.icon', 'image/x-icon'], true);
}

/** An .ico file starts with 00 00 01 00 (ICONDIR, type 1). */
function hasIcoMagic(string $firstBytes): bool {
    return strlen($firstBytes) >= 4 && substr($firstBytes, 0, 4) === "\x00\x00\x01\x00";
}

/** Public paths we ever write: /uploads/{media|avatars}/<32 hex>.<ext>. */
function isSafePublicUploadPath(string $path): bool {
    return (bool)preg_match('#^/uploads/(media|avatars)/[0-9a-f]{32}\.(jpg|png|webp|mp4|webm|ico)$#', $path);
}

/** Private paths are stored relative to storage/private: <kind>/<32 hex>.<ext>, kind = lower case letters, digits, underscore. */
function isSafePrivatePath(string $rel): bool {
    return (bool)preg_match('#^[a-z][a-z0-9_]{1,31}/[0-9a-f]{32}\.(pdf|jpg|png|webp)$#', $rel);
}

/** Image sanity limits before handing bytes to GD (decompression-bomb guard). */
function imageDimensionsAllowed(int $w, int $h): bool {
    return $w >= 1 && $h >= 1 && $w <= 10000 && $h <= 10000 && ($w * $h) <= 40000000;
}

/** True when the leading bytes match the magic number of the given validated image extension. */
function imageMagicMatches(string $firstBytes, string $ext): bool {
    if ($ext === 'jpg') {
        return strlen($firstBytes) >= 3 && substr($firstBytes, 0, 3) === "\xFF\xD8\xFF";
    }
    if ($ext === 'png') {
        return strlen($firstBytes) >= 8 && substr($firstBytes, 0, 8) === "\x89PNG\r\n\x1a\n";
    }
    if ($ext === 'webp') {
        return strlen($firstBytes) >= 12 && substr($firstBytes, 0, 4) === 'RIFF' && substr($firstBytes, 8, 4) === 'WEBP';
    }
    return false;
}

/** True when the bytes contain a server-script marker. Used only on the GD-less pass-through path. */
function containsServerScriptMarkers(string $bytes): bool {
    return (bool)preg_match('/<\?(?:php|=)|<script\b/i', $bytes);
}

/**
 * Image fallback policy when GD is missing: 'passthrough' | 'refuse'.
 * An explicit $envValue of either wins; otherwise production refuses and everything else passes through.
 */
function imageFallbackPolicy(?string $envValue, ?string $appEnv): string {
    if ($envValue === 'passthrough' || $envValue === 'refuse') {
        return $envValue;
    }
    return ($appEnv === 'production') ? 'refuse' : 'passthrough';
}
```

### REPLACE WHOLE FILE `tests/file_rules_test.php`

Test updated for the generic private path.

Delete the old content of the file and write exactly this (the baseline manifest in Step 1 already proved the old content is the one this prompt was written against):

```php
<?php
require __DIR__ . '/../includes/file_rules.php';
$fail = 0;
function check(string $label, $actual, $expected): void {
    global $fail;
    if ($actual !== $expected) { $fail++; fwrite(STDERR, "FAIL: $label\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n"); }
}
check('err ini', uploadErrorMessage(UPLOAD_ERR_INI_SIZE), 'حجم فایل بیش از حد مجاز سرور است');
check('err form', uploadErrorMessage(UPLOAD_ERR_FORM_SIZE), 'حجم فایل بیش از حد مجاز سرور است');
check('err nofile', uploadErrorMessage(UPLOAD_ERR_NO_FILE), 'فایلی ارسال نشده');
check('err cant write', uploadErrorMessage(UPLOAD_ERR_CANT_WRITE), 'خطای سرور در دریافت فایل');
check('img jpeg', imageExtForMime('image/jpeg'), 'jpg');
check('img svg refused', imageExtForMime('image/svg+xml'), null);
check('img gif refused', imageExtForMime('image/gif'), null);
check('video mp4', videoExtForMime('video/mp4'), 'mp4');
check('video avi refused', videoExtForMime('video/x-msvideo'), null);
check('ico mime', isIcoMime('image/x-icon'), true);
check('ico mime 2', isIcoMime('image/vnd.microsoft.icon'), true);
check('ico mime png', isIcoMime('image/png'), false);
check('ico magic', hasIcoMagic("\x00\x00\x01\x00\x01\x00"), true);
check('ico magic bad', hasIcoMagic("GIF89a"), false);
check('public ok', isSafePublicUploadPath('/uploads/media/' . str_repeat('a1', 16) . '.jpg'), true);
check('public avatar ok', isSafePublicUploadPath('/uploads/avatars/' . str_repeat('0f', 16) . '.png'), true);
check('public traversal', isSafePublicUploadPath('/uploads/media/../../config/config.php'), false);
check('public php ext', isSafePublicUploadPath('/uploads/media/' . str_repeat('a1', 16) . '.php'), false);
check('public uppercase hex', isSafePublicUploadPath('/uploads/media/' . str_repeat('A1', 16) . '.jpg'), false);
check('public other dir', isSafePublicUploadPath('/uploads/documents/' . str_repeat('a1', 16) . '.pdf'), false);
check('private ok', isSafePrivatePath('documents/' . str_repeat('a1', 16) . '.pdf'), true);
check('private zip refused', isSafePrivatePath('documents/' . str_repeat('a1', 16) . '.zip'), false);
check('private traversal', isSafePrivatePath('documents/../../x.pdf'), false);
check('private bad kind', isSafePrivatePath('Docs/' . str_repeat('a1', 16) . '.pdf'), false);
check('private abs', isSafePrivatePath('/etc/passwd'), false);
check('dims ok', imageDimensionsAllowed(1920, 1080), true);
check('dims zero', imageDimensionsAllowed(0, 10), false);
check('dims huge side', imageDimensionsAllowed(10001, 10), false);
check('dims bomb', imageDimensionsAllowed(9000, 9000), false);
// Part 9: magic bytes, script markers, fallback policy
check('magic jpg ok', imageMagicMatches("\xFF\xD8\xFF\xE0xxxx", 'jpg'), true);
check('magic jpg wrong ext', imageMagicMatches("\xFF\xD8\xFF\xE0xxxx", 'png'), false);
check('magic png ok', imageMagicMatches("\x89PNG\r\n\x1a\nrest", 'png'), true);
check('magic png wrong ext', imageMagicMatches("\x89PNG\r\n\x1a\nrest", 'jpg'), false);
check('magic webp ok', imageMagicMatches('RIFFxxxxWEBPVP8 ', 'webp'), true);
check('magic webp bad riff', imageMagicMatches('RIFXxxxxWEBPVP8 ', 'webp'), false);
check('magic truncated', imageMagicMatches("\xFF\xD8", 'jpg'), false);
check('magic empty', imageMagicMatches('', 'jpg'), false);
check('magic unknown ext', imageMagicMatches('whatever', 'gif'), false);
check('markers php', containsServerScriptMarkers('abc<?php echo 1;'), true);
check('markers short echo', containsServerScriptMarkers('a<?= 1 ?>'), true);
check('markers script', containsServerScriptMarkers('<SCRIPT src=x>'), true);
check('markers clean', containsServerScriptMarkers(str_repeat("\x01", 64)), false);
check('policy env passthrough', imageFallbackPolicy('passthrough', 'production'), 'passthrough');
check('policy env refuse', imageFallbackPolicy('refuse', 'local'), 'refuse');
check('policy prod', imageFallbackPolicy(null, 'production'), 'refuse');
check('policy local', imageFallbackPolicy(null, 'local'), 'passthrough');
check('policy null null', imageFallbackPolicy(null, null), 'passthrough');
check('policy garbage env', imageFallbackPolicy('whatever', 'local'), 'passthrough');
if ($fail > 0) { fwrite(STDERR, "$fail failure(s)\n"); exit(1); }
echo "file_rules_test: OK\n";
```

### REPLACE WHOLE FILE `tests/dashboard_data_test.php`

Test gains the two new providers.

Delete the old content of the file and write exactly this (the baseline manifest in Step 1 already proved the old content is the one this prompt was written against):

```php
<?php
// Part 10C tests for includes/dashboard_data.php (no bootstrap, CLI only).
// SQL part runs on SQLite; skips cleanly when pdo_sqlite is missing.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/dashboard_data.php';

$pass = 0; $fail = 0;
function ok(bool $cond, string $name): void {
    global $pass, $fail;
    if ($cond) { $pass++; echo "ok $name\n"; }
    else { $fail++; echo "FAIL $name\n"; }
}

// dashboardActivityLabel() is pure: description wins in full, module label gets a wrapper, blank/unknown gets the generic fallback.
ok(dashboardActivityLabel(['description' => 'ورود با موفقیت انجام شد']) === 'ورود با موفقیت انجام شد', 'label: description wins in full');
ok(dashboardActivityLabel(['description' => '', 'module_label' => 'رسانه']) === 'فعالیت در «رسانه»', 'label: module wrapper');
ok(dashboardActivityLabel([]) === 'فعالیت ثبت‌شده', 'label: generic fallback');
ok(dashboardActivityLabel(['description' => '   ']) === 'فعالیت ثبت‌شده', 'label: blank description falls back to generic');
ok(mb_strlen(dashboardActivityLabel(['description' => str_repeat('و', 500)]), 'UTF-8') === 140, 'label: clamped to 140 chars');

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "SKIP 7 SQL assertions: pdo_sqlite driver is not enabled (extension=pdo_sqlite in php.ini)\n";
} else {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    foreach ([
        'CREATE TABLE panel_users (id INTEGER PRIMARY KEY, full_name TEXT NOT NULL)',
        'CREATE TABLE permissions (module_key TEXT PRIMARY KEY, module_label TEXT NULL)',
        'CREATE TABLE activity_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, panel_user_id INTEGER NOT NULL,
            action_type TEXT NOT NULL, module_key TEXT NULL, description TEXT NULL, created_at TEXT NOT NULL)',
    ] as $sql) { $pdo->exec($sql); }
    $pdo->exec("INSERT INTO panel_users (id, full_name) VALUES (1, 'همکار'), (2, 'مدیر سیستم')");
    $pdo->exec("INSERT INTO permissions (module_key, module_label) VALUES ('media', 'رسانه')");
    $pdo->exec("INSERT INTO activity_logs (panel_user_id, action_type, module_key, description, created_at) VALUES
        (2, 'media_upload', 'media', 'آپلود رسانه ۱', '2026-01-02 10:00:00'),
        (2, 'login', NULL, NULL, '2026-01-03 09:00:00'),
        (1, 'media_upload', 'legacy_module', 'old row of a removed module', '2026-01-04 08:00:00')");
    $rows = dashboardRecentActivity($pdo, 2);
    ok(count($rows) === 2, 'limit 2 returns exactly 2 rows');
    ok($rows[0]['label'] === 'old row of a removed module' && $rows[1]['label'] === 'فعالیت ثبت‌شده', 'newest first + description wins');
    ok(array_keys($rows[0]) === ['id','action_type','module_key','description','created_at','full_name','module_label','label'], 'row shape: no ip_address column');
    for ($i = 4; $i <= 23; $i++) {
        $pdo->prepare("INSERT INTO activity_logs (panel_user_id, action_type, module_key, description, created_at) VALUES (2, 'media_upload', NULL, 'row $i', '2026-02-01 00:00:00')")->execute();
    }
    $all = dashboardRecentActivity($pdo, 25);
    ok(count($all) === 20, 'limit clamps to max 20 (23 rows in table)');
    ok(array_map('intval', array_column($all, 'id')) === range(23, 4), 'rows arrive newest first after clamping');

    $pdo->exec("CREATE TABLE media_library (id INTEGER PRIMARY KEY, purpose TEXT NOT NULL)");
    $pdo->exec("INSERT INTO media_library (id, purpose) VALUES (1, 'library'), (2, 'library'), (3, 'avatar'), (4, 'site_asset')");
    ok(dashboardMediaCount($pdo) === 2, 'media count: only purpose library');
    $pdo->exec("ALTER TABLE panel_users ADD COLUMN role TEXT NOT NULL DEFAULT 'staff'");
    $pdo->exec("ALTER TABLE panel_users ADD COLUMN status TEXT NOT NULL DEFAULT 'active'");
    $pdo->exec("UPDATE panel_users SET role = 'owner' WHERE id = 2");
    $pdo->exec("INSERT INTO panel_users (id, full_name, status) VALUES (3, 'تعلیق', 'suspended'), (4, 'فعال دوم', 'active')");
    ok(dashboardActiveStaffCount($pdo) === 2, 'staff count: active non-owner accounts only');
}

echo "passed: $pass / failed: $fail\n";
exit($fail === 0 ? 0 : 1);
```

### REPLACE WHOLE FILE `docs/ui-pages.md`

The page analysis without the removed pages; dashboard and general settings sections updated.

Delete the old content of the file and write exactly this (the baseline manifest in Step 1 already proved the old content is the one this prompt was written against):

```markdown
# Page-by-page UI analysis (Part 10B)

Written **before** any page markup is changed (step B1), then updated at the end of each page step with the "Result" line.
Eight dimensions per page: **(1)** purpose and user · **(2)** permissions and states · **(3)** problems found today (evidence) · **(4)** layout decision · **(5)** components used (Part 10A catalog only) · **(6)** RTL notes · **(7)** accessibility notes · **(8)** frozen hooks and behavior.
Every real page and every non-file page of the panel has an entry; `python tests/ui/check_pages_covered.py` fails when a page path is missing from this file.
Entries below are **pre-filled from reading the code**. In B1 you must re-read each page, correct anything that is not true of your copy, and add what you find. Mark every correction `[corrected]` and every addition `[added]`.

---

## `login.php`
1. **Purpose / user:** sign in with e-mail or username + password. Used by every person, signed out; the first impression of the product.
2. **Permissions / states:** public. States: default, error (`$error`), notice (`$notice`, for example a session that expired), submitting. No `data-theme-url` (nobody is signed in, so the theme is only remembered in `localStorage`).
3. **Problems:** two inline `style=""` attributes; purple hard-coded in the page; alerts are color-only; no theme control; the password cannot be revealed; no brand mark; not themed in dark mode.
4. **Layout:** one centered `.auth-card` on a tinted page: brand mark, `<h1>`, the form, the alerts above the form with icon + text, a theme toggle at the page corner (`.auth__theme`).
5. **Components:** `.auth`, `.auth-card`, `.field`, `.input-icon--action` + `data-password-toggle`, `.alert` (with icon), `.btn-primary`, `.icon-btn`.
6. **RTL:** identifier and password inputs are `dir="ltr"` content in an RTL layout (`.input-ltr`); the placeholder alignment stays at the inline-start; the eye button sits at the inline-end of the field.
7. **A11y:** visible labels (not placeholders), `autocomplete="username"` / `current-password`, alert `role`, the toggle has an `aria-label` that changes with its state, focus ring from A.
8. **Frozen:** field names `identifier`, `password`, `csrf_token`; ids `login-identifier`, `login-password`; form method/action; the PHP above the `<!DOCTYPE html>` line (login logic) is not touched.

**Result (B2):** `php -l` clean. Rendered signed-out in both themes at 360/768/1280: no horizontal overflow, card centered (`[20,340]` at 360, `[432,848]` at 1280). Theme toggle flips `data-bs-theme` both ways and the eye button switches the input to `type=text` and the label to «پنهان کردن رمز عبور». Wrong password against the clone DB shows `role="alert"` with the `ti-alert-circle` icon and text «نام کاربری یا رمز عبور اشتباه است.»; exactly one `<h1>`. `check_pages_semantics.py --pages /login.php` → 10 passed, 0 failed. `[corrected]` 10A needed the `data-theme-toggle` / `data-password-toggle` hooks that this page uses; after the 10A rebase they exist in `app.js`.

## `modules/dashboard/index.php`
1. **Purpose / user:** the first screen after sign-in: is the system healthy, how much is in the media library, who is on the team, what happened recently. Owner and staff.
2. **Permissions / states:** needs `dashboard.view` (the page guard stays). The staff-count card and the activity card are **owner only** (`isOwner()`). Empty state: no activity yet.
3. **Problems:** (rebuilt in Part 11A0 after the CMS was removed) the old stat cards counted pages and templates, which no longer exist.
4. **Layout:** page head (greeting line + `آپلود رسانه` when the person may edit media), up to three `.stat` cards (system health, media files, active staff), then the owner-only `فعالیت‌های اخیر` card.
5. **Components:** `.page-head`, `.stat`, `.activity-list`, `.empty-state`, `data-countup`.
6. **RTL:** stat order reads right-to-left; Jalali dates through `toPersianDigits(toJalali(...))`.
7. **A11y:** `<h2 class="card-head__title">` per card (the page `<h1>` is the topbar title), count-up respects reduced motion.
8. **Frozen:** permission keys; the data comes from `dashboardMediaCount`, `dashboardActiveStaffCount` and `dashboardRecentActivity` in `includes/dashboard_data.php`, never from new SQL in the page; no IP address is ever printed.
9. **Result (11A0):** page rewritten whole; widgets become a registry in a later part.

## `modules/media/index.php`
1. **Purpose / user:** upload, search, filter (image/video), preview and delete media. Editors; viewers can look.
2. **Permissions / states:** upload needs `media.edit`; delete needs `media.delete` (the preview modal shows the delete button only then); states: GD missing (warning or blocking), empty result, uploading, duplicate file.
3. **Problems:** `onclick` attribute on tiles; inline-width progress bars; `bg-white`/`bg-light` utilities; the delete button is visible to people the API would reject; the modal lacks labels; the filter tabs are plain links without a current-page mark.
4. **Layout:** page head lead → (editors) alert about GD if relevant → drop zone card → toolbar (filter pills + search) → responsive `.media-grid` of tiles → pagination → preview modal.
5. **Components:** `.drop-zone`, `.toolbar`, `.nav-pills`, `.input-icon`, `.kv`, `.empty-state`, `.alert` with icon, media-grid styles from `45-pages.css`, Bootstrap modal.
6. **RTL:** file names are bidi-isolated; sizes `کیلوبایت` after the number; the progress rows read right-to-left with the percentage at the inline-end.
7. **A11y:** tiles are `<button>` (keyboard and screen reader operable) with `aria-haspopup="dialog"`; focus returns to the tile when the modal closes; the drop zone is a keyboard-operable `role="button"`; progress uses `<progress>` with a label; `aria-live` for the upload list; deletion asks for confirmation.
8. **Frozen:** endpoints `/api/media/upload.php` and `/api/media/delete.php`; ids `mediaDrop`, `progressArea`, `previewModal`, `previewBody`, `btnDeleteMedia`; query parameters `type`, `q`, `page`.
9. **Result (B5-media):** tail replaced at `<div class="card p-4 mb-3">` (old `upload.js`/inline script gone with it); `php -l` clean. Live: empty state `فایلی یافت نشد` + upload control for the owner; with one seeded library PNG, the tile opens the preview modal, the image loads, the owner sees `#btnDeleteMedia`, `uit_media` (media.view only) does **not**, and Esc closes the modal returning focus to the tile. No JS errors; no overflow at 360. `[not run]` real uploads (writes to shared `uploads/`), GD-missing branch (GD is present), duplicate-file message, and the delete confirmation's API call; UI affordances exist and the API is unchanged. The seeded file/row were removed after the check.

## `modules/settings/profile.php`
1. **Purpose / user:** the signed-in person edits their own name, e-mail, phone, avatar, theme preference and password.
2. **Permissions / states:** any signed-in user; states: forced password change (`must_change_password`), saved (`?ok`), wrong current password (`?err`), avatar present/absent.
3. **Problems:** one long form without grouping; the password block is not explained; alerts are color only; the avatar has no preview shape; no sticky save.
4. **Layout:** two cards: account (avatar + 2-column field grid; 7/12) and security (password; 5/12) → one sticky `.form-actions` bar.
5. **Components:** `.card`, `.card-head`, `.form-grid`, `.field`, `.input-icon--action` with `data-password-toggle`, `.avatar-lg`, `.form-actions--sticky`, `.alert` with icon.
6. **RTL:** e-mail/phone are `ltr` inputs aligned at the inline-start of their box; the password eye at the inline-end.
7. **A11y:** labels, `autocomplete` tokens, alert roles, the password toggles have `aria-label`s.
8. **Frozen:** field names `full_name`, `email`, `phone`, `theme_preference`, `avatar`, `current_password`, `new_password`; `csrf_field()`; the POST handler above the cut.
9. **Result (B6-profile):** tail replaced at the `must_change_password` line; `php -l` clean. Owner and `uit_set`: two cards, sticky save bar, avatar input, two `data-password-toggle` buttons; clicking a toggle switches its input to `type=text` and `aria-pressed="true"`; no JS errors; no overflow at 360. A password save and avatar upload were not executed (they write to the real account's row in the clone and to shared `uploads/`); the POST handler above the cut is unchanged.

## `modules/settings/general.php`
1. **Purpose / user:** application name, logo, favicon. Owner/staff with `settings` access.
2. **Permissions / states:** view vs edit (`$canEdit`: the fieldset is disabled and the save bar hidden, with an info alert); saved (`?saved`), validation error (`$error`).
3. **Problems:** a flat form; previews are bare images; accepted formats are not stated; read-only mode only differs by missing button.
4. **Layout:** one 8/12 card with two titled sections (`هویت برنامه`, `تصاویر برند`), previews under each file input, the save bar in the card foot.
5. **Components:** `.card`, `.settings-section`, `.form-grid`, `.field` + `__hint`, `.asset-preview`, `.form-actions`, `.alert` with icon.
6. **RTL:** the previews align at the inline-start.
7. **A11y:** hints are tied with `aria-describedby`; a `<fieldset disabled>` for read-only; alert roles.
8. **Frozen:** field names `site_name`, `logo`, `favicon`; `csrf_field()`; the POST handler above the cut.
9. **Result (B6-general):** tail replaced at the `?saved` line; `php -l` clean. Owner: editable form (no disabled fieldset) with the save bar. `uit_set` (`settings.view` only): `alert-info` + disabled fieldset + **no** save bar. One `<h1>`; no JS errors; no overflow at 360. Saving (which writes `site_settings` and `uploads/`) not executed.

## `modules/settings/staff.php`
1. **Purpose / user:** the owner adds colleagues and decides, per module, who may view/edit/delete.
2. **Permissions / states:** **owner only** (everyone else gets a themed 403 card); states: no staff yet, active/suspended rows, the two modals.
3. **Problems:** the permission summary is not visible in the list (you have to open the modal for every person); `die`-style 403; native-looking modal tables; actions are unlabeled.
4. **Layout:** page head (lead + `افزودن همکار جدید`) → table card (name, e-mail, status badge, **permission chips** `module · rights`, actions) → invite modal → permission modal with a labelled checkbox table.
5. **Components:** `.page-head`, `.table-card`, `.badge-soft-*`, `.perm-chip`, `.cell-actions`, `.field`, `.input-icon--action`, Bootstrap modals.
6. **RTL:** e-mails `ltr`-isolated; the checkbox columns follow reading order.
7. **A11y:** every checkbox has an `aria-label` ("مشاهده <module>"); `<th scope="row">` in the permission table; modal titles are headings; icon-only remove button has a label.
8. **Frozen:** ids `inviteForm`, `permForm`, `permBody`, `permUserId`, `permUserName`, `inviteModal`, `permModal`; classes `btn-edit-perm`, `btn-status`, `btn-remove`; the checkbox names `perm[<id>][view|edit|delete]`; endpoints under `/api/staff/`; the cascade rules in the checkbox change handler.
9. **Result (B6-staff):** whole-file replacement after the diff (only the `p.module_label` select and the themed non-owner branch changed). `php -l` clean. Live owner: page head, 7 staff rows (clone has extra test staff), permission chips, scoped `th`; invite modal opens and closes with Esc; permission modal shows the person's name, exactly 7 module rows and 21 checkboxes **all with `aria-label`**; the cascade works both ways (checking delete checks view+edit; unchecking view clears edit+delete); Esc closes; no JS errors; no overflow at 360. Non-owner: the central `owner_only` guard (`includes/access_map.php`) answers first with the themed full-page 403 (`۴۰۳ - دسترسی غیر مجاز`), so the in-page branch is unreachable for non-owners but stays themed if the guard is ever relaxed.

## `includes/permissions.php` (the 403 page)
1. **Purpose / user:** what a person sees when a guard refuses them (`denyAccess()`).
2. **Permissions / states:** can be reached signed-out or half-loaded; two forms: a full document (the guard ran before any output) or a card (output already started). API requests keep the JSON 403.
3. **Problems:** a `die()` with inline-styled `<h1>` in Tahoma: no theme, no shell, no way back.
4. **Layout:** the `.auth-card` pattern with a lock icon, the same title/text as before, and a button back to the dashboard.
5. **Components:** `.auth`, `.auth-card`, `.empty-state`, `.btn-primary`.
6. **RTL:** document `dir="rtl"`.
7. **A11y:** one `<h1>`, a real link/button, no color-only meaning.
8. **Frozen:** the HTTP status 403, the JSON response for API requests, the exact Persian title and sentence.

**Result (B2):** `php -l` clean. Verified live against a clone DB (`business_panel_uitest`, real DB untouched): a staff user with no permissions gets HTTP 403 with the themed `.auth-card`, the lock icon, title «۴۰۳ - دسترسی غیر مجاز» and the «بازگشت به پیشخوان» button; a staff user with only `dashboard.view` sees the dashboard (200) and gets 403 on `/modules/media/index.php`. A permission-guarded API (`POST /api/media/delete.php` as that staff user) still answers `403 {"success":false,"message":"دسترسی غیر مجاز"}`; the owner-only `invite.php` keeps its own JSON 403 message. `[corrected]` the inline branch prints only a card when the shell already printed.
```

### REPLACE WHOLE FILE `database/tools/diagnose.php`

CMS-only checks removed; D27 and D28 added.

Delete the old content of the file and write exactly this (the baseline manifest in Step 1 already proved the old content is the one this prompt was written against):

```php
<?php
// Diagnostic suite (D01–D28). The CMS-only checks D07–D10, D14, D16–D18, D21, D23–D26 were removed in Part 11A0; D27/D28 verify the removal.
// CLI only. Read-only queries. Usage: php database/tools/diagnose.php [--phase=N]
// --phase=N runs only checks with since <= N. A check whose table/column does not
// exist yet is SKIP, not FAIL. Exit code 1 if any FAIL.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../lib/Migrator.php';

$phase = null;
foreach ($argv as $a) {
    if (preg_match('/^--phase=(\d+)$/', $a, $m)) { $phase = (int)$m[1]; }
}
if ($phase === null) { $phase = 99; }

try {
    $pdo = getPDO();
} catch (Throwable $e) {
    fwrite(STDERR, "Cannot connect to database.\n");
    exit(1);
}
$m = new Migrator($pdo);

$isMaria = stripos((string)$pdo->query("SELECT VERSION()")->fetchColumn(), 'mariadb') !== false;

$results = [];
function report(string $id, int $since, string $status, string $detail): void {
    global $results, $phase;
    if ($since > $phase) { return; }
    $results[] = [$id, $status, $detail];
    echo "$id [$status] $detail\n";
}
function skipIfMissingTable(string $id, int $since, string $title, string $table): bool {
    global $m;
    if (!$m->tableExists($table)) { report($id, $since, 'SKIP', "$title — table $table does not exist yet"); return true; }
    return false;
}

// ---- D01 (since 1): impossible permission rows → 0 rows ----
if (1 <= $phase) {
    if (!$m->tableExists('panel_user_permissions')) {
        report('D01', 1, 'SKIP', 'impossible permission rows — table panel_user_permissions does not exist yet');
    } else {
        $rows = (int)$pdo->query("SELECT COUNT(*) FROM panel_user_permissions WHERE (can_edit=1 AND can_view=0) OR (can_delete=1 AND can_edit=0)")->fetchColumn();
        report('D01', 1, $rows === 0 ? 'PASS' : 'FAIL', "impossible permission rows: $rows (expect 0)");
    }
}

// ---- D02 (since 1): staff with no permission row → 0 rows ----
if (1 <= $phase) {
    if (!$m->tableExists('panel_users') || !$m->tableExists('panel_user_permissions')) {
        report('D02', 1, 'SKIP', 'staff with no permission row — table missing');
    } else {
        $hasStatus = $m->columnExists('panel_users', 'status');
        $filter = $hasStatus ? "WHERE u.role='staff' AND u.status<>'removed'" : "WHERE u.role='staff'";
        $rows = $pdo->query("SELECT u.id, u.username FROM panel_users u LEFT JOIN panel_user_permissions p ON p.panel_user_id=u.id $filter GROUP BY u.id, u.username HAVING COUNT(p.id)=0")->fetchAll();
        $n = count($rows);
        $who = $n ? ' (' . implode(',', array_map(function ($r) { return $r['id'] . ':' . $r['username']; }, $rows)) . ')' : '';
        report('D02', 1, $n === 0 ? 'PASS' : 'FAIL', "staff with no permission row: $n (expect 0)$who");
    }
}

// ---- D03 (since 1): seed password still in use → WARN if rows ----
if (1 <= $phase) {
    if (!$m->tableExists('panel_users')) {
        report('D03', 1, 'SKIP', 'seed password — table panel_users does not exist yet');
    } else {
        $seed = '$2y$10$cnvXc0McHVEK5Nml0ZvMwepHQ8jERISPDuFgn9BuwBsO6CZ5utTO.';
        $st = $pdo->prepare("SELECT id, username FROM panel_users WHERE password_hash = ?");
        $st->execute([$seed]);
        $rows = $st->fetchAll();
        $n = count($rows);
        $who = $n ? ' (' . implode(',', array_map(function ($r) { return $r['id'] . ':' . $r['username']; }, $rows)) . ')' : '';
        report('D03', 1, $n === 0 ? 'PASS' : 'WARN', "accounts with seed password: $n$who");
    }
}

// ---- D04 (since 1): CHECK chk_pup_logic ----
if (1 <= $phase) {
    if (!$m->tableExists('panel_user_permissions')) {
        report('D04', 1, 'SKIP', 'chk_pup_logic — table panel_user_permissions does not exist yet');
    } elseif ($m->checkExists('panel_user_permissions', 'chk_pup_logic')) {
        report('D04', 1, 'PASS', 'CHECK chk_pup_logic present');
    } else {
        if (!$isMaria) {
            $v = (string)$pdo->query("SELECT VERSION()")->fetchColumn();
            if (preg_match('/^5\.7\./', $v)) {
                report('D04', 1, 'WARN', 'CHECK chk_pup_logic absent on MySQL 5.7 (parses and ignores CHECK)');
            } else {
                report('D04', 1, 'FAIL', 'CHECK chk_pup_logic absent (expect present)');
            }
        } else {
            report('D04', 1, 'FAIL', 'CHECK chk_pup_logic absent (expect present)');
        }
    }
}

// ---- D05 (since 2): usernames violating policy → WARN ----
if (2 <= $phase) {
    if (!$m->tableExists('panel_users')) {
        report('D05', 2, 'SKIP', 'username policy — table panel_users does not exist yet');
    } else {
        $hasStatus = $m->columnExists('panel_users', 'status');
        $rows = $pdo->query("SELECT id, username FROM panel_users" . ($hasStatus ? " WHERE status<>'removed'" : ""))->fetchAll();
        $bad = [];
        foreach ($rows as $r) {
            if (!preg_match('/^[A-Za-z0-9_.-]{3,30}$/D', (string)$r['username'])) { $bad[] = $r['id'] . ':' . $r['username']; }
        }
        report('D05', 2, empty($bad) ? 'PASS' : 'WARN', 'usernames violating policy: ' . count($bad) . ($bad ? ' (' . implode(',', $bad) . ')' : ''));
    }
}

// ---- D06 (since 2): login_attempts / activity_logs size + oldest → INFO ----
if (2 <= $phase) {
    $parts = [];
    if ($m->tableExists('login_attempts')) {
        $r = $pdo->query("SELECT COUNT(*), MIN(attempted_at) FROM login_attempts")->fetch(PDO::FETCH_NUM);
        $parts[] = "login_attempts={$r[0]} oldest=" . ($r[1] ?? '-');
    } else {
        $parts[] = "login_attempts table missing";
    }
    if ($m->tableExists('activity_logs')) {
        $r = $pdo->query("SELECT COUNT(*), MIN(created_at) FROM activity_logs")->fetch(PDO::FETCH_NUM);
        $parts[] = "activity_logs={$r[0]} oldest=" . ($r[1] ?? '-');
    } else {
        $parts[] = "activity_logs table missing";
    }
    report('D06', 2, 'INFO', implode('; ', $parts));
}

// ---- D11 (since 2/3/5): FK delete rules ----
if (2 <= $phase) {
    $map = [
        ['fk_media_uploader', 'media_library', 'RESTRICT', 2],
        ['fk_logs_user', 'activity_logs', 'SET NULL', 2],
        ['fk_fileref_media', 'file_references', 'RESTRICT', 11],
    ];
    $due = array_values(array_filter($map, function ($e) use ($phase) { return $e[3] <= $phase; }));
    if (empty($due)) {
        report('D11', 2, 'SKIP', 'no FK in the expected map is due at this phase yet');
    } else {
        $problems = [];
        foreach ($due as $e) {
            [$fk, $table, $want, $fkPhase] = $e;
            if (!$m->tableExists($table)) { $problems[] = "$fk: table $table missing"; continue; }
            $got = $m->fkRule($table, $fk);
            if ($got === null) { $problems[] = "$fk: missing (expect $want)"; }
            elseif (strtoupper($got) !== $want) { $problems[] = "$fk: $got (expect $want)"; }
        }
        report('D11', 2, empty($problems) ? 'PASS' : 'FAIL', empty($problems) ? 'FK delete rules match the expected map' : implode('; ', $problems));
    }
}

// ---- D12 (since 4): duplicate (purpose,sha256) → 0 rows ----
if (4 <= $phase) {
    if (!$m->tableExists('media_library') || !$m->columnExists('media_library', 'sha256') || !$m->columnExists('media_library', 'purpose')) {
        report('D12', 4, 'SKIP', 'duplicate (purpose,sha256) — columns do not exist yet');
    } else {
        $n = (int)$pdo->query("SELECT COUNT(*) FROM (SELECT purpose, sha256, COUNT(*) n FROM media_library WHERE sha256 IS NOT NULL GROUP BY purpose, sha256 HAVING n>1) t")->fetchColumn();
        report('D12', 4, $n === 0 ? 'PASS' : 'FAIL', "duplicate (purpose,sha256) groups: $n (expect 0)");
    }
}

// ---- D13 (since 4): media_library rows with NULL sha256 → INFO ----
if (4 <= $phase) {
    if (!$m->tableExists('media_library') || !$m->columnExists('media_library', 'sha256')) {
        report('D13', 4, 'SKIP', 'NULL sha256 — column does not exist yet');
    } else {
        $n = (int)$pdo->query("SELECT COUNT(*) FROM media_library WHERE sha256 IS NULL")->fetchColumn();
        report('D13', 4, 'INFO', "media rows with NULL sha256: $n (run the backfill tool to reduce)");
    }
}

// ---- D15 (since 5): legacy tables gone ----
if (5 <= $phase) {
    $legacy = ['pages', 'page_blocks', 'articles', 'article_blocks', 'article_categories', 'media_usage'];
    $present = [];
    foreach ($legacy as $t) { if ($m->tableExists($t)) { $present[] = $t; } }
    report('D15', 5, empty($present) ? 'PASS' : 'FAIL', empty($present) ? 'legacy tables gone' : 'legacy tables still present: ' . implode(',', $present));
}

// ---- D19 (since 2): LOGIN_DUMMY_HASH / SEED_PASSWORD_HASH are valid bcrypt ----
if (2 <= $phase) {
    if (!defined('LOGIN_DUMMY_HASH') || !defined('SEED_PASSWORD_HASH')) {
        report('D19', 2, 'SKIP', 'LOGIN_DUMMY_HASH / SEED_PASSWORD_HASH not defined yet (P2 adds them)');
    } else {
        $ok = true;
        $notes = [];
        foreach (['LOGIN_DUMMY_HASH' => LOGIN_DUMMY_HASH, 'SEED_PASSWORD_HASH' => SEED_PASSWORD_HASH] as $name => $h) {
            $info = password_get_info($h);
            if ($info['algoName'] !== 'bcrypt' || (int)($info['options']['cost'] ?? 0) !== 10) {
                $ok = false; $notes[] = "$name is not bcrypt cost 10";
            }
        }
        report('D19', 2, $ok ? 'PASS' : 'FAIL', $notes ? implode('; ', $notes) : 'both hashes are bcrypt cost 10');
    }
}

// ---- D20 (since 2): removed staff keep no permission rows and no avatar → 0 ----
if (2 <= $phase) {
    if (!$m->tableExists('panel_users')) {
        report('D20', 2, 'SKIP', 'removed staff — table panel_users does not exist yet');
    } elseif (!$m->columnExists('panel_users', 'status')) {
        report('D20', 2, 'SKIP', "removed staff — status enum has no 'removed' yet");
    } else {
        $rows = $pdo->query("SELECT u.id FROM panel_users u WHERE u.status='removed' AND (u.avatar_path IS NOT NULL OR EXISTS (SELECT 1 FROM panel_user_permissions p WHERE p.panel_user_id=u.id))")->fetchAll();
        $n = count($rows);
        report('D20', 2, $n === 0 ? 'PASS' : 'FAIL', "removed staff with avatar/permission rows: $n (expect 0)");
    }
}

// ---- D22 (since 4): public asset paths registered in media_library → INFO ----
if (4 <= $phase) {
    if (!$m->tableExists('media_library')) {
        report('D22', 4, 'SKIP', 'asset registration — table media_library does not exist yet');
    } else {
        $paths = [];
        if ($m->tableExists('site_settings')) {
            $s = $pdo->query("SELECT logo_path, favicon_path FROM site_settings WHERE id=1")->fetch();
            if ($s) { foreach (['logo_path', 'favicon_path'] as $c) { if (!empty($s[$c]) && strpos($s[$c], '/uploads/') === 0) { $paths[] = $s[$c]; } } }
        }
        if ($m->tableExists('panel_users')) {
            foreach ($pdo->query("SELECT avatar_path FROM panel_users WHERE avatar_path LIKE '/uploads/%'")->fetchAll(PDO::FETCH_COLUMN) as $p) { $paths[] = $p; }
        }
        $paths = array_values(array_unique($paths));
        $unreg = [];
        if ($paths) {
            $reg = $pdo->query("SELECT file_path FROM media_library")->fetchAll(PDO::FETCH_COLUMN);
            $regSet = array_flip($reg);
            foreach ($paths as $p) { if (!isset($regSet[$p])) { $unreg[] = $p; } }
        }
        report('D22', 4, 'INFO', $unreg ? ('unregistered asset paths: ' . count($unreg) . ' (' . implode(',', $unreg) . ') — run backfill_files.php') : ('all ' . count($paths) . ' public asset path(s) registered'));
    }
}

// ---- D27 (since 11): the CMS is gone from the database ----
if (11 <= $phase) {
    $left = [];
    foreach (['builder_media_refs', 'builder_blocks', 'builder_canvases', 'templates', 'template_categories', 'licenses',
              'marketplace_connections', 'marketplaces', 'dashboard_snapshots'] as $t) {
        if ($m->tableExists($t)) { $left[] = $t; }
    }
    foreach (['active_template_id', 'site_domain'] as $c) {
        if ($m->tableExists('site_settings') && $m->columnExists('site_settings', $c)) { $left[] = 'site_settings.' . $c; }
    }
    if ($m->tableExists('permissions')) {
        $n = (int)$pdo->query("SELECT COUNT(*) FROM permissions WHERE module_key IN ('pages','articles','templates','licenses')")->fetchColumn();
        if ($n > 0) { $left[] = "permissions rows: $n"; }
    }
    report('D27', 11, empty($left) ? 'PASS' : 'FAIL', empty($left) ? 'CMS tables, columns and permission keys are gone' : 'still present: ' . implode(', ', $left));
}

// ---- D28 (since 11): file_references exists and points only at existing media → 0 orphans ----
if (11 <= $phase) {
    if (!$m->tableExists('file_references') || !$m->tableExists('media_library')) {
        report('D28', 11, 'SKIP', 'file_references or media_library does not exist yet');
    } else {
        $n = (int)$pdo->query("SELECT COUNT(*) FROM file_references r LEFT JOIN media_library m ON m.id = r.media_id WHERE m.id IS NULL")->fetchColumn();
        report('D28', 11, $n === 0 ? 'PASS' : 'FAIL', "file_references rows with a missing media_id: $n (expect 0)");
    }
}

$fail = 0;
foreach ($results as $r) { if ($r[1] === 'FAIL') { $fail++; } }
if (empty($results)) { echo "No checks due at this phase.\n"; }
echo "summary: " . count($results) . " check(s), $fail FAIL\n";
exit($fail > 0 ? 1 : 0);
```

### REPLACE WHOLE FILE `database/tools/backfill_files.php`

The certificate and template-package sections removed.

Delete the old content of the file and write exactly this (the baseline manifest in Step 1 already proved the old content is the one this prompt was written against):

```php
<?php
// One-off backfill for the P4 file registry. CLI only. Dry-run by default (prints what it would do).
//   php database/tools/backfill_files.php                 → dry run
//   php database/tools/backfill_files.php --apply         → performs the changes
//   php database/tools/backfill_files.php --apply --dedupe → additionally repoints duplicate assets
// Uses getPDO() and the app constants; never touches a file outside UPLOAD_BASE.
// Take a fresh backup of public/uploads/ and the database before --apply.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/file_store.php';

$apply = in_array('--apply', $argv, true);
$dedupe = in_array('--dedupe', $argv, true);
if ($dedupe && !$apply) { fwrite(STDERR, "--dedupe needs --apply\n"); exit(2); }
echo $apply ? "MODE: apply" . ($dedupe ? " + dedupe" : "") . "\n" : "MODE: dry run (use --apply to write)\n";

$pdo = getPDO();
$counts = ['ok' => 0, 'skip' => 0, 'problem' => 0];
function say(string $kind, string $msg): void {
    global $counts;
    $counts[strtolower($kind)]++;
    echo "$kind $msg\n";
}
/** Absolute path under UPLOAD_BASE, or null when outside / missing. */
function uploadAbs(?string $webPath): ?string {
    if ($webPath === null || strpos($webPath, '/uploads/') !== 0 || strpos($webPath, '..') !== false) { return null; }
    $abs = UPLOAD_BASE . substr($webPath, strlen('/uploads'));
    $base = realpath(UPLOAD_BASE);
    $real = realpath($abs);
    if ($base === false || $real === false || strpos($real, $base . DIRECTORY_SEPARATOR) !== 0) { return null; }
    return $real;
}

// ---- 1) hash legacy library rows ----
// Documented limitation: legacy hashes are of the STORED bytes (re-encoded images), not the original upload bytes.
// Dedupe (UNIQUE purpose+sha256) therefore protects new uploads only (Appendix B.6).
echo "--- 1) legacy library hashes ---\n";
$rows = $pdo->query("SELECT id, file_path FROM media_library WHERE sha256 IS NULL")->fetchAll();
foreach ($rows as $r) {
    $abs = uploadAbs($r['file_path']);
    if ($abs === null) { say('SKIP', "media#{$r['id']}: file missing ({$r['file_path']})"); continue; }
    $hash = hash_file('sha256', $abs);
    if ($hash === false) { say('PROBLEM', "media#{$r['id']}: cannot hash"); continue; }
    if (!$apply) { say('SKIP', "media#{$r['id']}: would set sha256 (dry run)"); continue; }
    try {
        $pdo->prepare("UPDATE media_library SET sha256 = ? WHERE id = ?")->execute([$hash, (int)$r['id']]);
        say('OK', "media#{$r['id']}: hashed");
    } catch (PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) === 1062) {
            $dup = $pdo->prepare("SELECT id FROM media_library WHERE sha256 = ? AND id <> ? LIMIT 1");
            $dup->execute([$hash, (int)$r['id']]);
            say('PROBLEM', "media#{$r['id']}: duplicate content of media#" . $dup->fetchColumn() . " (human decides)");
        } else { say('PROBLEM', "media#{$r['id']}: " . $e->getMessage()); }
    }
}

// ---- 2) register unregistered public assets ----
echo "--- 2) unregistered public assets ---\n";
$ownerId = (int)$pdo->query("SELECT id FROM panel_users WHERE role='owner' ORDER BY id LIMIT 1")->fetchColumn();
$assets = []; // path => ['purpose'=>..., 'user'=>...]
foreach ($pdo->query("SELECT id, avatar_path FROM panel_users WHERE avatar_path LIKE '/uploads/%'")->fetchAll() as $u) {
    $assets[$u['avatar_path']] = ['purpose' => 'avatar', 'user' => (int)$u['id']];
}
$site = $pdo->query("SELECT logo_path, favicon_path FROM site_settings WHERE id=1")->fetch();
if ($site) {
    foreach (['logo_path', 'favicon_path'] as $c) {
        if (!empty($site[$c]) && strpos($site[$c], '/uploads/') === 0 && !isset($assets[$site[$c]])) {
            $assets[$site[$c]] = ['purpose' => 'site_asset', 'user' => $ownerId];
        }
    }
}
$registered = $pdo->query("SELECT file_path FROM media_library")->fetchAll(PDO::FETCH_COLUMN);
$regSet = array_flip($registered);
$finfo = finfo_open(FILEINFO_MIME_TYPE);
foreach ($assets as $path => $meta) {
    if (isset($regSet[$path])) { say('SKIP', "asset $path: already registered"); continue; }
    $abs = uploadAbs($path);
    if ($abs === null) { say('PROBLEM', "asset $path: file missing"); continue; }
    $mime = $finfo ? (string)finfo_file($finfo, $abs) : '';
    $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
    $w = null; $h = null;
    if ($ext !== 'ico') { $gi = @getimagesize($abs); if ($gi) { $w = (int)$gi[0]; $h = (int)$gi[1]; } }
    if (!$apply) { say('SKIP', "asset $path: would register as {$meta['purpose']} (dry run)"); continue; }
    try {
        $pdo->prepare("INSERT INTO media_library (file_name, file_path, file_type, purpose, mime_type, file_size_kb, width, height, uploaded_by) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([basename($path), $path, 'image', $meta['purpose'], $mime, (int)ceil(filesize($abs) / 1024), $w, $h, $meta['user']]);
        say('OK', "asset $path: registered as {$meta['purpose']}");
    } catch (PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) !== 1062) { say('PROBLEM', "asset $path: " . $e->getMessage()); continue; }
        // Same bytes already registered under another path.
        if (!$dedupe) { say('PROBLEM', "asset $path: duplicate asset (use --dedupe)"); continue; }
        $st = $pdo->prepare("SELECT file_path FROM media_library WHERE purpose = ? AND sha256 = ? LIMIT 1");
        $st->execute([$meta['purpose'], hash_file('sha256', $abs)]);
        $existing = $st->fetchColumn();
        if (!$existing) { say('PROBLEM', "asset $path: duplicate but existing row not found"); continue; }
        $pdo->prepare("UPDATE panel_users SET avatar_path = ? WHERE avatar_path = ?")->execute([$existing, $path]);
        $pdo->prepare("UPDATE site_settings SET logo_path = ? WHERE logo_path = ?")->execute([$existing, $path]);
        $pdo->prepare("UPDATE site_settings SET favicon_path = ? WHERE favicon_path = ?")->execute([$existing, $path]);
        @unlink($abs);
        say('OK', "asset $path: deduped to $existing");
    }
}
if ($finfo) { finfo_close($finfo); }
```

### REPLACE WHOLE FILE `tests/ui/check_ui_gates.py`

The builder exceptions removed.

Delete the old content of the file and write exactly this (the baseline manifest in Step 1 already proved the old content is the one this prompt was written against):

```python
#!/usr/bin/env python3
"""Part 10B static gates. Run from the project root:  python tests/ui/check_ui_gates.py [--root .] [--verbose]

Every gate prints its hits as  <gate> <file>:<line>  <text>  and a final table. Exit 0 only when all gates are at 0.
Scanned: .php .js .css under public/ and includes/ (vendor, uploads, node_modules, ui-kit, tests, docs, changelogs, .git,
storage are skipped).

G1 inline-style     style="..." in PHP or in a JS string, or .style. / cssText / setAttribute('style') in JS.
                    Allowed: a JS line that carries the marker comment `dynamic geometry` (a value that only exists at run time).
G2 color-literal    #hex / rgb() / rgba() / hsl() in CSS other than app/00-tokens.css (`rgba(var(--rgb-text), .3)` is allowed: it reads a token). app/30-bootstrap-bridge.css may keep them
                    only on lines that contain `data:image/svg` or `--bs-backdrop-bg`.
G3 legacy-utility   bg-white bg-light bg-dark text-dark text-white text-white-50 border-secondary text-bg-* and `badge bg-*` in PHP/JS.
G4 old-dark-mode    `dark-mode` or `dark-theme.css` anywhere.
G5 swal-colour      confirmButtonColor / cancelButtonColor.
G6 inline-handler   on<event>="..." attributes in PHP.
G7 physical-css     left / right / margin-left|right / padding-left|right / border-left|right / text-align:left|right / float in app CSS
                    (use logical properties). Allowed on a line that contains `data-align` (user content alignment) or the marker `/* physical */`.
G8 emoji            pictographs in PHP/JS/CSS strings (the panel uses icons, not emoji).
G10 legacy-helper   thumb-140 thumb-160 thumb-cover thumb-icon cursor-pointer hidden-init pages-slug media-progress-bar stat-card in PHP/JS
                    (the helper classes 10A kept only until the pages were rebuilt; at 0 the helpers are deleted from 35-components.css).
G9 arabic-letters   U+064A (Arabic yeh) or U+0643 (Arabic kaf) anywhere in the scanned files.
"""
import argparse
import re
import sys
from pathlib import Path

SKIP_DIRS = {"vendor", "uploads", "node_modules", "ui-kit", "tests", "docs", "changelogs", ".git", "storage"}
SKIP_FILES = set()

RE_STYLE_ATTR = re.compile(r'\bstyle\s*=\s*["\\\']')
RE_JS_STYLE = re.compile(r"\.style\.|\.style\[|style\.cssText|setAttribute\(\s*['\"]style['\"]")
RE_COLOR = re.compile(r"#[0-9a-fA-F]{3,8}\b|\brgba?\s*\((?!\s*var\()|\bhsla?\s*\(")
RE_LEGACY = re.compile(r"(?<![\w-])(bg-white|bg-light|bg-dark|text-dark|text-white(?:-50)?|border-secondary|text-bg-[a-z]+)(?![\w-])|badge\s+bg-[a-z]+")
RE_DARK = re.compile(r"dark-mode|dark-theme\.css")
RE_SWAL = re.compile(r"confirmButtonColor|cancelButtonColor")
RE_HANDLER = re.compile(r"\son(?:click|change|submit|input|load|keyup|keydown|focus|blur|error|mouse[a-z]+)\s*=", re.I)
RE_PHYS = re.compile(r"(?<![\w-])(?:left|right)\s*:|(?:margin|padding|border)-(?:left|right)\b|text-align\s*:\s*(?:left|right)|float\s*:\s*(?:left|right)|border-(?:top|bottom)-(?:left|right)-radius")
RE_EMOJI = re.compile("[\U0001F300-\U0001FAFF☀-➿⭐⭕️]")
RE_HELPER = re.compile(r"(?<![\w-])(thumb-140|thumb-160|thumb-cover|thumb-icon|cursor-pointer|hidden-init|pages-slug|media-progress-bar|stat-card)(?![\w-])")
RE_ARABIC = re.compile("[\u064a\u0643]")


def files(root):
    for base in ("public", "includes"):
        d = root / base
        if not d.is_dir():
            continue
        for p in sorted(d.rglob("*")):
            if not p.is_file() or p.suffix.lower() not in (".php", ".js", ".css"):
                continue
            rel = p.relative_to(root).as_posix()
            parts = set(p.relative_to(root).parts[:-1])
            if parts & SKIP_DIRS or p.name in SKIP_FILES:
                continue
            yield p, rel


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--root", default=".")
    ap.add_argument("--verbose", action="store_true")
    args = ap.parse_args()
    root = Path(args.root).resolve()
    hits = {k: [] for k in ["G1", "G2", "G3", "G4", "G5", "G6", "G7", "G8", "G9", "G10"]}
    scanned = 0
    for p, rel in files(root):
        scanned += 1
        ext = p.suffix.lower()
        text = p.read_text(encoding="utf-8", errors="replace")
        lines = text.splitlines()
        is_public_renderer = False
        in_css_scope = ext == ".css" and (rel.startswith("public/assets/css/"))
        for i, line in enumerate(lines, 1):
            prev = lines[i - 2] if i > 1 else ""
            marked = "dynamic geometry" in line or "dynamic geometry" in prev
            if ext == ".php" and not is_public_renderer and RE_STYLE_ATTR.search(line):
                hits["G1"].append((rel, i, line))
            if ext == ".js" and (RE_JS_STYLE.search(line) or RE_STYLE_ATTR.search(line)) and not marked and not is_public_renderer:
                hits["G1"].append((rel, i, line))
            if in_css_scope and not rel.endswith("app/00-tokens.css") and RE_COLOR.search(line):
                if rel.endswith("app/30-bootstrap-bridge.css") and ("data:image/svg" in line or "--bs-backdrop-bg" in line):
                    pass
                else:
                    hits["G2"].append((rel, i, line))
            if ext in (".php", ".js") and not is_public_renderer and RE_LEGACY.search(line):
                hits["G3"].append((rel, i, line))
            if RE_DARK.search(line):
                hits["G4"].append((rel, i, line))
            if ext in (".php", ".js") and RE_SWAL.search(line):
                hits["G5"].append((rel, i, line))
            if ext == ".php" and not is_public_renderer and RE_HANDLER.search(line):
                hits["G6"].append((rel, i, line))
            if in_css_scope and rel.startswith("public/assets/css/app/") \
                    and RE_PHYS.search(line) and "data-align" not in line and "/* physical */" not in line:
                if not re.match(r"\s*(/\*|\*)", line):
                    hits["G7"].append((rel, i, line))
            if ext in (".php", ".js") and RE_HELPER.search(line):
                hits["G10"].append((rel, i, line))
            if RE_EMOJI.search(line):
                hits["G8"].append((rel, i, line))
            if RE_ARABIC.search(line):
                hits["G9"].append((rel, i, line))
    names = {"G1": "inline-style", "G2": "color-literal", "G3": "legacy-utility", "G4": "old-dark-mode", "G5": "swal-colour",
             "G6": "inline-handler", "G7": "physical-css", "G8": "emoji", "G9": "arabic-letters", "G10": "legacy-helper"}
    bad = 0
    for g, items in hits.items():
        for rel, i, line in items[: (10_000 if args.verbose else 6)]:
            print(f"{g} {rel}:{i}  {line.strip()[:110]}")
        if len(items) > (10_000 if args.verbose else 6):
            print(f"{g} ... {len(items) - 6} more (use --verbose)")
        bad += len(items)
    print()
    for g in hits:
        print(f"{g} {names[g]:<15} {len(hits[g])}")
    print(f"scanned {scanned} files; total findings {bad}")
    return 1 if bad else 0


if __name__ == "__main__":
    sys.exit(main())
```

### REPLACE WHOLE FILE `tests/README.md`

The new test commands.

Delete the old content of the file and write exactly this (the baseline manifest in Step 1 already proved the old content is the one this prompt was written against):

````markdown
# Tests

```
php tests/run_all.php
php database/tools/check_cms_removed.php
python tests/ui/check_ui_gates.py
```

- `php tests/run_all.php` — all `tests/*_test.php` (non-zero exit on failure; `SKIP` = optional extension such as `gd`/`sodium` absent).
- `php database/tools/check_cms_removed.php` — gate: no trace of the removed CMS in live code (also run by `tests/cms_removed_test.php`).
- `python tests/ui/check_ui_gates.py` — static UI gates (tokens, inline styles, physical CSS, Arabic letters).
- `python tests/browser/run_ui_checks.py` — rendered checks; needs a running server + Chrome.
````

### STEP 5 — FIND / REPLACE edits

For every block: open the file, find the FIND text (it must occur **exactly once**), replace it with the REPLACE text. A FIND text that occurs 0 or 2+ times → stop that block (rule 3).

### EDIT `includes/layout/sidebar.php` — E1

Brand sub-title fallback no longer says «my website».

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```php
    <span class="app-brand__text"><strong>پنل مدیریت</strong><small><?= e($siteSettings['site_name'] ?? 'وب‌سایت من') ?></small></span>
```

**REPLACE IT WITH:**

```php
    <span class="app-brand__text"><strong>پنل مدیریت</strong><small><?= e($siteSettings['site_name'] ?? 'پنل مدیریت') ?></small></span>
```

### EDIT `public/modules/media/index.php` — E2

Media page lead text without «site».

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```php
  <p class="page-head__lead"><?= $canEditMedia ? 'تصویرها و ویدیوهای سایت را آپلود، جستجو و مدیریت کنید.' : 'تصویرها و ویدیوهای سایت را جستجو و مشاهده کنید.' ?></p>
```

**REPLACE IT WITH:**

```php
  <p class="page-head__lead"><?= $canEditMedia ? 'تصویرها و ویدیوها را آپلود، جستجو و مدیریت کنید.' : 'تصویرها و ویدیوها را جستجو و مشاهده کنید.' ?></p>
```

### EDIT `includes/file_store.php` — E3

Private-file docblock example.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```php
     * Private file (outside public/): returns the path RELATIVE to STORAGE_PRIVATE, e.g. "licenses/<hex>.pdf".
```

**REPLACE IT WITH:**

```php
     * Private file (outside public/): returns the path RELATIVE to STORAGE_PRIVATE, e.g. "documents/<hex>.pdf".
```

### EDIT `includes/file_store.php` — E4

Private kinds are no longer a fixed CMS list: any lower-case identifier of 2-32 characters.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```php
        if (!in_array($kind, ['licenses', 'templates'], true)) {
```

**REPLACE IT WITH:**

```php
        if (!preg_match('/^[a-z][a-z0-9_]{1,31}$/', $kind)) {
```

### EDIT `tests/file_store_test.php` — E5

private-file test uses a neutral kind.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```php
$p = FileStore::storePrivate(fileOf($pdf, 'c.pdf'), 'licenses', ['application/pdf' => 'pdf'], 1024 * 1024);
check('private rel shape', isSafePrivatePath($p['rel']), true);
check('private under storage', strpos($p['abs'], STORAGE_PRIVATE . '/licenses/') === 0, true);
```

**REPLACE IT WITH:**

```php
$p = FileStore::storePrivate(fileOf($pdf, 'c.pdf'), 'documents', ['application/pdf' => 'pdf'], 1024 * 1024);
check('private rel shape', isSafePrivatePath($p['rel']), true);
check('private under storage', strpos($p['abs'], STORAGE_PRIVATE . '/documents/') === 0, true);
```

### EDIT `tests/file_store_test.php` — E6

private html refused test.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```php
check('private html refused', rejects(function () { FileStore::storePrivate(fileOf('<html></html>', 'c.pdf'), 'licenses', ['application/pdf' => 'pdf'], 1024); }, 'فرمت فایل مجاز نیست'), true);
```

**REPLACE IT WITH:**

```php
check('private html refused', rejects(function () { FileStore::storePrivate(fileOf('<html></html>', 'c.pdf'), 'documents', ['application/pdf' => 'pdf'], 1024); }, 'فرمت فایل مجاز نیست'), true);
```

### EDIT `tests/file_store_test.php` — E7

removePrivate traversal test.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```php
check('removePrivate traversal refused', FileStore::removePrivate('licenses/../../x.pdf'), false);
```

**REPLACE IT WITH:**

```php
check('removePrivate traversal refused', FileStore::removePrivate('documents/../../x.pdf'), false);
```

### EDIT `config/config.php` — E8

Remove the four limits that only the CMS (licence certificates, template zips) used.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```php
define('MAX_VIDEO_SIZE', 50 * 1024 * 1024); // 50MB
define('MAX_CERT_BYTES', 5 * 1024 * 1024);                       // licence certificate (PDF/JPG/PNG/WEBP)
define('MAX_TEMPLATE_ZIP_BYTES', 50 * 1024 * 1024);              // the old code allowed 20 MB; raised to 50 MB (author's decision, record it)
define('MAX_ZIP_FILES', 500);                                    // entries inside a template zip
define('MAX_ZIP_UNPACKED_BYTES', 100 * 1024 * 1024);             // sum of uncompressed sizes (zip-bomb guard)

```

**REPLACE IT WITH:**

```php
define('MAX_VIDEO_SIZE', 50 * 1024 * 1024); // 50MB

```

### EDIT `public/assets/css/app/00-tokens.css` — E9

Token comment without the CMS reference.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```css
  /* media viewer (fullscreen story / slider preview in the builder): ALWAYS dark, in both themes, because it is a lightbox over the page */
```

**REPLACE IT WITH:**

```css
  /* media viewer (fullscreen lightbox): ALWAYS dark, in both themes, because it is a lightbox over the page */
```

### EDIT `public/assets/css/app/00-tokens.css` — E10

Token comment (dark theme).

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```css
  /* media viewer (fullscreen story / slider preview in the builder): same values as the light theme */
```

**REPLACE IT WITH:**

```css
  /* media viewer (fullscreen lightbox): same values as the light theme */
```

### EDIT `public/assets/css/app/00-tokens.css` — E11

Token comment.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```css
  --topbar-h: 4rem;              /* 64px: builder sticky offset derives from this */
```

**REPLACE IT WITH:**

```css
  --topbar-h: 4rem;              /* 64px: sticky offsets derive from this */
```

### EDIT `public/assets/css/app/00-tokens.css` — E12

z-index comment.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```css
     tooltip 1080. Builder chrome: settings 1040, insert 1045, toolbar 1050, media picker 1070, story viewer 2000.
     SweetAlert2 uses 1060. */
```

**REPLACE IT WITH:**

```css
     tooltip 1080. Media viewer (lightbox): 2000.
     SweetAlert2 uses 1060. */
```

### EDIT `public/assets/css/app/20-shell.css` — E13

Shell comment.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```css
   Do not put transform / filter / backdrop-filter / will-change on any ancestor of #builder-root or of a modal:
```

**REPLACE IT WITH:**

```css
   Do not put transform / filter / backdrop-filter / will-change on any ancestor of a modal or of fixed overlays:
```

### EDIT `public/assets/css/app/40-motion.css` — E14

Motion comment.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```css
   stacking context or containing block is created. A page that holds #builder-root or fixed overlays adds
```

**REPLACE IT WITH:**

```css
   stacking context or containing block is created. A page that holds fixed overlays adds
```

### EDIT `tests/browser/run_ui_checks.py` — E15

UI checker usage text.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```python
    options: --pages /modules/dashboard/index.php,/modules/articles/index.php   (default list below)
```

**REPLACE IT WITH:**

```python
    options: --pages /modules/dashboard/index.php,/modules/media/index.php   (default list below)
```

### EDIT `tests/browser/run_ui_checks.py` — E16

UI checker default page list.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```python
    "/modules/dashboard/index.php",
    "/modules/pages/index.php",
    "/modules/articles/index.php",
    "/modules/templates/index.php",
    "/modules/media/index.php",
    "/modules/licenses/index.php",
    "/modules/settings/profile.php",
```

**REPLACE IT WITH:**

```python
    "/modules/dashboard/index.php",
    "/modules/media/index.php",
    "/modules/settings/profile.php",
```

### EDIT `tests/browser/check_pages_semantics.py` — E17

Semantics checker usage text.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```python
    python tests/browser/check_pages_semantics.py --base-url http://127.0.0.1:8099 --no-login --pages /login.php,/modules/articles/index.php
```

**REPLACE IT WITH:**

```python
    python tests/browser/check_pages_semantics.py --base-url http://127.0.0.1:8099 --no-login --pages /login.php,/modules/media/index.php
```

### EDIT `tests/browser/check_pages_semantics.py` — E18

Semantics checker default page list.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```python
    "/modules/dashboard/index.php",
    "/modules/pages/index.php",
    "/modules/articles/index.php",
    "/modules/templates/index.php",
    "/modules/media/index.php",
    "/modules/licenses/index.php",
    "/modules/settings/profile.php",
```

**REPLACE IT WITH:**

```python
    "/modules/dashboard/index.php",
    "/modules/media/index.php",
    "/modules/settings/profile.php",
```

### EDIT `tests/ui/check_pages_covered.py` — E19

Gate docstring.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```python
Plus the two non-file pages every panel has and the builder chrome (listed in EXTRA).
A page counts as covered when its path (for example `modules/articles/index.php`) appears in docs/ui-pages.md.
```

**REPLACE IT WITH:**

```python
Plus the non-file page every panel has (the 403 page, listed in EXTRA).
A page counts as covered when its path (for example `modules/media/index.php`) appears in docs/ui-pages.md.
```

### EDIT `tests/ui/check_pages_covered.py` — E20

Gate EXTRA list.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```python
EXTRA = ["includes/permissions.php", "builder chrome"]  # 403 page lives in permissions.php; the builder chrome is JS-rendered
```

**REPLACE IT WITH:**

```python
EXTRA = ["includes/permissions.php"]  # the 403 page lives in permissions.php
```

### EDIT `docs/ui-guide.md` — E21

UI guide: page classes of removed pages.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```markdown
- **قالب‌ها:** `.template-card` + `--active`/`__thumb`/`__body`/`__title`/`__ver`/`__actions` و `.template-active__name`.
- **رسانه:** `.media-grid`، `.media-tile` (+`__thumb`/`__name`/`__meta`)، `.media-preview__el`، و ردیف‌های آپلود `.upload-list`/`.upload-row` (+`__name`/`__pct`، `<progress>`).
- **مجوزها:** `.license-card` (+`__form`/`__fields`/`__link`/`__actions`).
- **همکاران:** `.perm-chips`، `.perm-chip` (+`__rights`/`--none`)، `.perm-table`.
```

**REPLACE IT WITH:**

```markdown
- **رسانه:** `.media-grid`، `.media-tile` (+`__thumb`/`__name`/`__meta`)، `.media-preview__el`، و ردیف‌های آپلود `.upload-list`/`.upload-row` (+`__name`/`__pct`، `<progress>`).
- **همکاران:** `.perm-chips`، `.perm-chip` (+`__rights`/`--none`)، `.perm-table`.
```

### EDIT `database/README.md` — E22

README: page list.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```markdown
2. **Manual console check** (needed when `node` is missing, and always once after any JS change): open DevTools → Console, reload each of: dashboard, pages, templates, media, licenses, settings/general, settings/profile, settings/staff, login — **no red errors** (also none about `integrity`/SRI).
```

**REPLACE IT WITH:**

```markdown
2. **Manual console check** (needed when `node` is missing, and always once after any JS change): open DevTools → Console, reload each of: dashboard, media, settings/general, settings/profile, settings/staff, login — **no red errors** (also none about `integrity`/SRI).
```

### EDIT `database/README.md` — E23

README: module count.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```markdown
3. On the staff page: click «ویرایش دسترسی» (a modal with 7 modules opens), click «تعلیق» (a confirmation dialog opens), click «افزودن همکار جدید» (a modal opens).
```

**REPLACE IT WITH:**

```markdown
3. On the staff page: click «ویرایش دسترسی» (a modal with 3 modules opens), click «تعلیق» (a confirmation dialog opens), click «افزودن همکار جدید» (a modal opens).
```

### EDIT `database/README.md` — E24

README: tool list.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```markdown
- `php database/tools/encrypt_existing_secrets.php` — one-off secret encryption, dry-run by default (P6).
- `php database/tools/gen_secret_key.php` — prints a fresh `APP_SECRET_KEY`; writes nothing (P6).
```

**REPLACE IT WITH:**

```markdown
- `php database/tools/cms_inventory.php` — read-only report of what migration 0010 (CMS removal) would delete; run it BEFORE `migrate.php` (Part 11A0).
- `php database/tools/check_cms_removed.php` — gate: exit 0 only when no CMS file or term is left in live code (Part 11A0).
- `php database/tools/gen_secret_key.php` — prints a fresh `APP_SECRET_KEY`; writes nothing (P6).
```

### EDIT `database/tools/gc_files.php` — E25

gc_files: private kinds list emptied.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```php
$privShapes = [
    'licenses' => ['#^[0-9a-f]{32}\.(pdf|jpg|png|webp)$#', "SELECT certificate_file FROM licenses", 'certificate'],
    'templates' => ['#^[0-9a-f]{32}\.zip$#', "SELECT package_path FROM templates", 'package'],
];
```

**REPLACE IT WITH:**

```php
// No private file kind is in use since Part 11A0 (licence certificates and template packages were the only ones).
// A module that stores private files adds its kind here: kind => [file-name shape, SQL returning the stored relative paths, label].
$privShapes = [];
```

### EDIT `public/ui-kit/index.html` — E26

UI kit demo sidebar: generic labels instead of the removed CMS menu.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```html
        <li><a class="nav-link-item" href="#forms" data-tip="ساخت سایت"><i class="ti ti-layout-grid"></i><span class="nav-label">ساخت سایت</span></a></li>
        <li><a class="nav-link-item" href="#tables" data-tip="صفحات ساخته شده"><i class="ti ti-files"></i><span class="nav-label">صفحات ساخته شده</span></a></li>
        <li><a class="nav-link-item" href="#feedback" data-tip="قالب سایت"><i class="ti ti-palette"></i><span class="nav-label">قالب سایت</span></a></li>
        <li><a class="nav-link-item" href="#upload" data-tip="رسانه و عکس‌ها"><i class="ti ti-photo"></i><span class="nav-label">رسانه و عکس‌ها</span></a></li>
        <li><a class="nav-link-item" href="#settings" data-tip="مجوزات"><i class="ti ti-certificate"></i><span class="nav-label">مجوزات</span></a></li>
      </ul>
```

**REPLACE IT WITH:**

```html
        <li><a class="nav-link-item" href="#forms" data-tip="فرم‌ها"><i class="ti ti-layout-grid"></i><span class="nav-label">فرم‌ها</span></a></li>
        <li><a class="nav-link-item" href="#tables" data-tip="جدول‌ها"><i class="ti ti-files"></i><span class="nav-label">جدول‌ها</span></a></li>
        <li><a class="nav-link-item" href="#feedback" data-tip="بازخورد"><i class="ti ti-palette"></i><span class="nav-label">بازخورد</span></a></li>
        <li><a class="nav-link-item" href="#upload" data-tip="رسانه و عکس‌ها"><i class="ti ti-photo"></i><span class="nav-label">رسانه و عکس‌ها</span></a></li>
        <li><a class="nav-link-item" href="#settings" data-tip="تنظیمات"><i class="ti ti-adjustments-horizontal"></i><span class="nav-label">تنظیمات</span></a></li>
      </ul>
```

### EDIT `public/ui-kit/index.html` — E27

UI kit demo stat card label.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```html
        <div class="col-12 col-sm-6 col-xl-3"><div class="card stat hover-lift"><span class="stat__icon"><i class="ti ti-files"></i></span><div class="stat__body"><p class="stat__label">صفحات ساخته شده</p><p class="stat__value" data-countup="12">۱۲</p><p class="stat__hint">+۲ عدد نسبت به هفته گذشته</p></div></div></div>
        <div class="col-12 col-sm-6 col-xl-3"><div class="card stat stat--success hover-lift"><span class="stat__icon"><i class="ti ti-circle-check"></i></span><div class="stat__body"><p class="stat__label">صفحات منتشرشده</p><p class="stat__value" data-countup="9">۹</p><p class="stat__hint">از ۱۲ صفحه</p></div></div></div>
```

**REPLACE IT WITH:**

```html
        <div class="col-12 col-sm-6 col-xl-3"><div class="card stat hover-lift"><span class="stat__icon"><i class="ti ti-files"></i></span><div class="stat__body"><p class="stat__label">رکوردهای ثبت‌شده</p><p class="stat__value" data-countup="12">۱۲</p><p class="stat__hint">+۲ عدد نسبت به هفته گذشته</p></div></div></div>
        <div class="col-12 col-sm-6 col-xl-3"><div class="card stat stat--success hover-lift"><span class="stat__icon"><i class="ti ti-circle-check"></i></span><div class="stat__body"><p class="stat__label">صفحات منتشرشده</p><p class="stat__value" data-countup="9">۹</p><p class="stat__hint">از ۱۲ صفحه</p></div></div></div>
```

### EDIT `tests/browser/run_ui_checks.py` — E28

Sidebar tooltip test: use the UI kit item «بازخورد» (the old CMS item no longer exists).

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```python
        rep.check("collapsed: active item still visible (bar + tinted pill)", await page.evaluate("""() => { const a = document.querySelector('#app-sidebar [aria-current=page]'); const r = a.getBoundingClientRect(); return r.width > 40 && getComputedStyle(a, '::before').transform !== 'matrix(1, 0, 0, 0, 0, 0)'; }"""))
        await page.hover('#app-sidebar .nav-link-item[data-tip="قالب سایت"]')
        await page.wait_for_timeout(450)
        tip = await page.evaluate("""() => { const t = document.getElementById('app-tip'); if (!t) return null; const r = t.getBoundingClientRect(); const a = document.querySelector('#app-sidebar .nav-link-item[data-tip="قالب سایت"]').getBoundingClientRect(); return {text: t.textContent, vis: t.classList.contains('is-visible'), toLeft: r.right <= a.left + 1, inView: r.left >= 0 && r.right <= innerWidth && r.top >= 0 && r.bottom <= innerHeight}; }""")
```

**REPLACE IT WITH:**

```python
        rep.check("collapsed: active item still visible (bar + tinted pill)", await page.evaluate("""() => { const a = document.querySelector('#app-sidebar [aria-current=page]'); const r = a.getBoundingClientRect(); return r.width > 40 && getComputedStyle(a, '::before').transform !== 'matrix(1, 0, 0, 0, 0, 0)'; }"""))
        await page.hover('#app-sidebar .nav-link-item[data-tip="بازخورد"]')
        await page.wait_for_timeout(450)
        tip = await page.evaluate("""() => { const t = document.getElementById('app-tip'); if (!t) return null; const r = t.getBoundingClientRect(); const a = document.querySelector('#app-sidebar .nav-link-item[data-tip="قالب سایت"]').getBoundingClientRect(); return {text: t.textContent, vis: t.classList.contains('is-visible'), toLeft: r.right <= a.left + 1, inView: r.left >= 0 && r.right <= innerWidth && r.top >= 0 && r.bottom <= innerHeight}; }""")
```

### EDIT `tests/browser/run_ui_checks.py` — E29

Sidebar tooltip test: use the UI kit item «بازخورد» (the old CMS item no longer exists).

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```python
        tip = await page.evaluate("""() => { const t = document.getElementById('app-tip'); if (!t) return null; const r = t.getBoundingClientRect(); const a = document.querySelector('#app-sidebar .nav-link-item[data-tip="قالب سایت"]').getBoundingClientRect(); return {text: t.textContent, vis: t.classList.contains('is-visible'), toLeft: r.right <= a.left + 1, inView: r.left >= 0 && r.right <= innerWidth && r.top >= 0 && r.bottom <= innerHeight}; }""")
        rep.check("collapsed: tooltip shows the item name on hover, toward the content, inside the viewport", bool(tip) and tip["vis"] and tip["text"] == "قالب سایت" and tip["toLeft"] and tip["inView"], str(tip))
        await page.mouse.move(600, 400)
```

**REPLACE IT WITH:**

```python
        tip = await page.evaluate("""() => { const t = document.getElementById('app-tip'); if (!t) return null; const r = t.getBoundingClientRect(); const a = document.querySelector('#app-sidebar .nav-link-item[data-tip="بازخورد"]').getBoundingClientRect(); return {text: t.textContent, vis: t.classList.contains('is-visible'), toLeft: r.right <= a.left + 1, inView: r.left >= 0 && r.right <= innerWidth && r.top >= 0 && r.bottom <= innerHeight}; }""")
        rep.check("collapsed: tooltip shows the item name on hover, toward the content, inside the viewport", bool(tip) and tip["vis"] and tip["text"] == "بازخورد" and tip["toLeft"] and tip["inView"], str(tip))
        await page.mouse.move(600, 400)
```

### EDIT `tests/browser/run_ui_checks.py` — E30

Sidebar tooltip test: use the UI kit item «بازخورد» (the old CMS item no longer exists).

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```python
        rep.check("expanding restores 272px and the same icon position", abs(back["w"] - 272) <= 1 and abs(back["iconOff"] - before["iconOff"]) <= 1, f"{back['w']:.1f}px")
        await page.hover('#app-sidebar .nav-link-item[data-tip="قالب سایت"]')
        await page.wait_for_timeout(450)
        rep.check("expanded: sidebar items show no tooltip", not await page.evaluate("(() => { const t = document.getElementById('app-tip'); return !!t && t.classList.contains('is-visible'); })()"))
```

**REPLACE IT WITH:**

```python
        rep.check("expanding restores 272px and the same icon position", abs(back["w"] - 272) <= 1 and abs(back["iconOff"] - before["iconOff"]) <= 1, f"{back['w']:.1f}px")
        await page.hover('#app-sidebar .nav-link-item[data-tip="بازخورد"]')
        await page.wait_for_timeout(450)
        rep.check("expanded: sidebar items show no tooltip", not await page.evaluate("(() => { const t = document.getElementById('app-tip'); return !!t && t.classList.contains('is-visible'); })()"))
```

### STEP 6 — create the new code, tools and tests

Check after the step: `php -l` on each PHP file prints `No syntax errors detected`.

### CREATE `database/lib/CmsInventory.php`

Content (copy exactly; it ends with a newline):

```php
<?php
// database/lib/CmsInventory.php — Part 11A0. One place that knows what the decommissioned CMS left in the database.
// Used by database/tools/cms_inventory.php (read-only report) and by migration 0010 (the guard). Read-only queries.
// $tableExists / $columnExists are callables so the class works with Migrator (MySQL) and with the SQLite test fixture.

final class CmsInventory
{
    /** Permission keys of the removed modules. */
    public static function permissionKeys(): array
    {
        return ['pages', 'articles', 'templates', 'licenses'];
    }

    /** Every CMS table, in an order that is safe to DROP one by one (children before parents). */
    public static function tables(): array
    {
        return [
            'builder_media_refs', 'builder_blocks', 'builder_canvases',
            'templates', 'template_categories',
            'licenses',
            'marketplace_connections', 'marketplaces',
            'dashboard_snapshots',
            'page_blocks', 'article_blocks', 'pages', 'articles', 'article_categories', 'media_usage',
        ];
    }

    /**
     * Rows that hold USER data (seed rows that every install has are NOT counted).
     * @return array<string,int> label => number of rows
     */
    public static function userData(PDO $pdo, callable $tableExists, callable $columnExists): array
    {
        $out = [];
        $count = function (string $label, string $table, string $where = '') use ($pdo, $tableExists, &$out): void {
            if (!$tableExists($table)) { return; }
            $sql = 'SELECT COUNT(*) FROM ' . $table . ($where !== '' ? ' WHERE ' . $where : '');
            $out[$label] = (int)$pdo->query($sql)->fetchColumn();
        };
        $count('builder_canvases (pages and template layouts)', 'builder_canvases');
        $count('builder_blocks', 'builder_blocks');
        $count('builder_media_refs', 'builder_media_refs');
        $uploaded = "source = 'uploaded'";
        if ($tableExists('templates') && $columnExists('templates', 'package_path')) {
            $uploaded .= ' OR package_path IS NOT NULL';
        }
        $count('templates (uploaded by a person)', 'templates', $uploaded);
        $count('licenses (filled in)', 'licenses', "status <> 'not_connected' OR credentials IS NOT NULL OR certificate_file IS NOT NULL");
        $count('marketplace_connections (connected or holding keys)', 'marketplace_connections',
            "is_connected = 1 OR (api_key IS NOT NULL AND api_key <> '') OR (api_secret IS NOT NULL AND api_secret <> '')");
        foreach (['page_blocks', 'article_blocks', 'pages', 'articles', 'article_categories', 'media_usage'] as $legacy) {
            $count($legacy . ' (legacy)', $legacy);
        }
        if ($tableExists('site_settings') && $columnExists('site_settings', 'site_domain')) {
            $out['site_settings.site_domain (non-empty)'] = (int)$pdo->query("SELECT COUNT(*) FROM site_settings WHERE site_domain IS NOT NULL AND site_domain <> ''")->fetchColumn();
        }
        return $out;
    }

    /**
     * Files the CMS left on disk. The migration never deletes files; the owner decides.
     * @return string[] project-relative paths
     */
    public static function leftoverFiles(string $root): array
    {
        $found = [];
        foreach (['storage/private/licenses', 'storage/private/templates', 'public/uploads/templates'] as $rel) {
            $dir = rtrim($root, '/\\') . '/' . $rel;
            if (!is_dir($dir)) { continue; }
            foreach (scandir($dir) ?: [] as $name) {
                if ($name === '.' || $name === '..' || $name === '.gitkeep' || $name === '.htaccess') { continue; }
                $found[] = $rel . '/' . $name;
            }
        }
        sort($found, SORT_STRING);
        return $found;
    }
}
```

### CREATE `database/migrations/0009_file_references_pre_code.php`

Content (copy exactly; it ends with a newline):

```php
<?php
// Part 11A0 — [pre-code]: purely additive. Creates the generic file-usage registry that replaces builder_media_refs.
// Columns: ref_type ("<module>" or "<module>.<entity>") + ref_id (owner's record id as a string) are ascii on purpose:
// they are identifiers, never user text, and ascii keeps the unique index short.
// FK media_id → media_library.id is ON DELETE RESTRICT: a file that is still referenced cannot be deleted (same rule the old
// builder_media_refs had). There is no FK on ref_id: the platform cannot know every module's tables; owners keep the rows
// in step through includes/file_references.php.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

return [
    'description' => 'Create file_references (generic media usage registry, RESTRICT on media)',
    'up' => function (PDO $pdo, Migrator $m): void {
        if ($m->tableExists('file_references')) {
            echo "file_references already exists: nothing to do\n";
            return;
        }
        $pdo->exec("CREATE TABLE file_references (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            media_id    INT UNSIGNED NOT NULL,
            ref_type    VARCHAR(60) CHARACTER SET ascii NOT NULL,
            ref_id      VARCHAR(64) CHARACTER SET ascii NOT NULL,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_fileref (media_id, ref_type, ref_id),
            KEY idx_fileref_owner (ref_type, ref_id),
            CONSTRAINT fk_fileref_media FOREIGN KEY (media_id) REFERENCES media_library (id) ON DELETE RESTRICT
        ) ENGINE=InnoDB");
        echo "created file_references\n";
    },
];
```

### CREATE `database/migrations/0010_cms_decommission_post_code.php`

Content (copy exactly; it ends with a newline):

```php
<?php
// Part 11A0 — [post-code]: removes the CMS from the database (page builder, pages/articles, templates, licenses, marketplaces,
// dashboard stat snapshots), the four CMS permission keys, and the two site_settings columns that only the CMS used.
// IRREVERSIBLE. The backup taken before this runs is the only way back (docs/decommission-cms.md).
//
// Guard (same idea as 0006 / 0008): when CmsInventory finds rows that hold USER data (a page someone built, an uploaded template,
// a filled-in licence, a connected marketplace, a typed site domain) the migration refuses to run unless the owner has decided:
//   backup first, then   MIGRATE_DROP_CMS=1 php database/migrate.php
// Seed rows that every install has (the three sample templates, template categories, empty licences, marketplaces) do not count.
// Files on disk are never touched here (database/tools/cms_inventory.php lists them for the owner).
// The closure takes $m without a type hint on purpose: tests pass a SQLite stand-in with the same methods.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../lib/CmsInventory.php';

return [
    'description' => 'Part 11A0: drop CMS tables, delete CMS permission keys, drop site_settings.active_template_id and site_domain',
    'up' => function (PDO $pdo, $m): void {
        if (!$m->tableExists('file_references')) {
            throw new RuntimeException('Migration 0009 must be applied first (table file_references is missing).');
        }

        $counts = CmsInventory::userData($pdo, [$m, 'tableExists'], [$m, 'columnExists']);
        $total = array_sum($counts);
        foreach ($counts as $label => $n) {
            echo '  ' . $label . ': ' . $n . "\n";
        }
        if ($total > 0 && getenv('MIGRATE_DROP_CMS') !== '1') {
            throw new RuntimeException('CMS user data found (' . $total . ' row(s) in total, listed above). Nothing was changed. '
                . 'Take a database backup, get the owner\'s explicit approval, then re-run with MIGRATE_DROP_CMS=1.');
        }

        // 1) permission keys and the grants that point at them (portable sub-select; FK CASCADE would do it too)
        if ($m->tableExists('permissions')) {
            $keys = CmsInventory::permissionKeys();
            $in = implode(',', array_fill(0, count($keys), '?'));
            $pdo->beginTransaction();
            try {
                $g = 0;
                if ($m->tableExists('panel_user_permissions')) {
                    $d = $pdo->prepare('DELETE FROM panel_user_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE module_key IN (' . $in . '))');
                    $d->execute($keys);
                    $g = $d->rowCount();
                }
                $p = $pdo->prepare('DELETE FROM permissions WHERE module_key IN (' . $in . ')');
                $p->execute($keys);
                $pdo->commit();
                echo 'permission keys deleted: ' . $p->rowCount() . ', grants deleted: ' . $g . "\n";
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                throw $e;
            }
        }

        // 2) site_settings: the FK to templates must go before the templates table can; then the two CMS-only columns
        if ($m->tableExists('site_settings')) {
            if ($m->fkExists('site_settings', 'fk_settings_template')) {
                $pdo->exec('ALTER TABLE site_settings DROP FOREIGN KEY fk_settings_template');
            }
            foreach (['active_template_id', 'site_domain'] as $col) {
                if ($m->columnExists('site_settings', $col)) {
                    $pdo->exec('ALTER TABLE site_settings DROP COLUMN ' . $col);
                    echo 'dropped site_settings.' . $col . "\n";
                }
            }
            // only the untouched seed value is replaced; a name the owner typed is never changed
            $u = $pdo->prepare('UPDATE site_settings SET site_name = ? WHERE id = 1 AND site_name = ?');
            $u->execute(['پنل مدیریت', 'وب‌سایت من']);
        }

        // 3) tables, children before parents (CmsInventory::tables() is that order)
        foreach (CmsInventory::tables() as $table) {
            if ($m->tableExists($table)) {
                $pdo->exec('DROP TABLE ' . $table);
                echo 'dropped table ' . $table . "\n";
            }
        }
        echo "CMS decommission done.\n";
    },
];
```

### CREATE `database/tools/check_cms_removed.php`

Content (copy exactly; it ends with a newline):

```php
<?php
// Part 11A0 gate: the decommissioned CMS must leave no trace in live code. CLI only. Exit 0 = clean, 1 = leftovers.
//   php database/tools/check_cms_removed.php            (scans the project root)
//   php database/tools/check_cms_removed.php --root=DIR (scans another tree; used by tests/cms_removed_test.php)
// Allowed leftovers (history, or the record of the removal itself): database/migrations/*, database/schema.sql (frozen baseline),
// changelogs/*, docs/decommission-cms.md, docs/ai/*, the master-prompt-*.md files in the project root, tests/browser/shots/*,
// and the files that must name the removed things to remove or verify them (this gate, database/lib/CmsInventory.php,
// database/tools/cms_inventory.php, database/tools/diagnose.php, database/tools/check_removed.php, tests/cms_*).
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = realpath(__DIR__ . '/../..');
foreach ($argv as $a) {
    if (strpos($a, '--root=') === 0) { $root = realpath(substr($a, 7)); }
}
if (!$root || !is_dir($root)) { fwrite(STDERR, "bad --root\n"); exit(2); }

$skipDirs = ['vendor', 'uploads', 'node_modules', 'changelogs', '.git', 'storage', 'shots'];
$allowPrefixes = ['database/migrations/', 'docs/ai/', 'master-prompt', 'tests/cms_'];
$allowFiles = ['database/schema.sql', 'docs/decommission-cms.md', 'database/tools/check_cms_removed.php', 'database/lib/CmsInventory.php',
               'database/tools/cms_inventory.php', 'database/tools/diagnose.php', 'database/tools/check_removed.php'];

// 1) paths that must not exist (relative to the root). A trailing / means "directory"; * is a file-name wildcard.
$mustNotExist = [
    'public/modules/pages', 'public/modules/articles', 'public/modules/templates', 'public/modules/licenses',
    'public/api/builder', 'public/api/templates', 'public/api/licenses', 'public/file.php',
    'includes/builder', 'public/assets/js/builder', 'public/assets/css/builder', 'public/assets/js/pages-manager.js',
    'docs/builder-architecture.md', 'tests/js', 'tests/html_sanitizer_test.php', 'tests/dashboard_snapshots',
    'tests/browser/engine_contract.json', 'tests/browser/run_builder_checks.py', 'tests/browser/run_builder_checks_async.py',
    'tools/ui/migrate_builder_assets.py', 'database/tools/encrypt_existing_secrets.php',
];
$wild = ['tests/builder_*_test.php'];

// 2) content patterns (case-insensitive where it makes sense)
$patterns = [
    '/\bbuilder_(canvases|blocks|media_refs)\b/i', '/\bBuilder[A-Z][A-Za-z]+/', '/\bbuildable_type\b/',
    '/\bactive_template_id\b/', '/\btemplate_categories\b/', '/\bdashboard_snapshots\b/', '/\bmarketplace/i',
    '/\b(zarinpal|enamad)\b/i', '/\/api\/(builder|templates|licenses)\//', '/\/modules\/(pages|articles|templates|licenses)\//',
    '/\bincludes\/builder\b/', '/(?:perm:|requirePermission\(\s*\'|hasPermission\(\s*\')(pages|articles|templates|licenses)\b/',
    '/ساخت سایت/u', '/صفحات ساخته شده/u', '/قالب سایت/u', '/مجوزات/u', '/صفحه‌?ساز/u',
];

$hits = 0;
foreach ($mustNotExist as $rel) {
    if (file_exists($root . '/' . $rel)) { $hits++; echo "$rel still exists\n"; }
}
foreach ($wild as $w) {
    foreach (glob($root . '/' . $w) ?: [] as $f) { $hits++; echo substr($f, strlen($root) + 1) . " still exists\n"; }
}

$it = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    function (SplFileInfo $f) use ($skipDirs) {
        return !($f->isDir() && in_array($f->getFilename(), $skipDirs, true));
    }
));
foreach ($it as $file) {
    /** @var SplFileInfo $file */
    if (!$file->isFile() || !in_array(strtolower($file->getExtension()), ['php', 'js', 'css', 'html', 'md', 'sql', 'py', 'json'], true)) { continue; }
    $rel = str_replace('\\', '/', substr($file->getRealPath(), strlen($root) + 1));
    if (in_array($rel, $allowFiles, true)) { continue; }
    foreach ($allowPrefixes as $p) { if (strpos($rel, $p) === 0) { continue 2; } }
    $lines = file($file->getRealPath(), FILE_IGNORE_NEW_LINES) ?: [];
    foreach ($lines as $i => $line) {
        foreach ($patterns as $re) {
            if (preg_match($re, $line)) {
                $hits++;
                echo $rel . ':' . ($i + 1) . '  [' . $re . ']  ' . mb_substr(trim($line), 0, 100) . "\n";
                break;
            }
        }
    }
}
echo 'cms leftovers: ' . $hits . "\n";
exit($hits === 0 ? 0 : 1);
```

### CREATE `database/tools/cms_inventory.php`

Content (copy exactly; it ends with a newline):

```php
<?php
// Part 11A0 — READ-ONLY report of what the CMS decommission (migration 0010) would remove. CLI only. Changes nothing.
//   php database/tools/cms_inventory.php
// Prints: user-data rows per CMS table, the CMS files that are still on disk, and the verdict
//   NEEDS OWNER APPROVAL (user data exists → 0010 needs MIGRATE_DROP_CMS=1 after a backup)  or  NO USER DATA (0010 runs without the variable).
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../lib/Migrator.php';
require_once __DIR__ . '/../lib/CmsInventory.php';

try {
    $pdo = getPDO();
} catch (Throwable $e) {
    fwrite(STDERR, "Cannot connect to the database.\n");
    exit(2);
}
$m = new Migrator($pdo);
$counts = CmsInventory::userData($pdo, [$m, 'tableExists'], [$m, 'columnExists']);
echo "== CMS rows that hold user data ==\n";
if (!$counts) { echo "  (no CMS table exists any more)\n"; }
foreach ($counts as $label => $n) { echo '  ' . $label . ': ' . $n . "\n"; }
$present = [];
foreach (CmsInventory::tables() as $t) { if ($m->tableExists($t)) { $present[] = $t; } }
echo "== CMS tables still present ==\n  " . ($present ? implode(', ', $present) : '(none)') . "\n";
$files = CmsInventory::leftoverFiles(dirname(__DIR__, 2));
echo "== CMS files still on disk (migration 0010 never deletes files) ==\n";
if (!$files) { echo "  (none)\n"; }
foreach ($files as $f) { echo '  ' . $f . "\n"; }
$total = array_sum($counts);
echo $total > 0 ? "VERDICT: NEEDS OWNER APPROVAL ($total row(s)) — backup first, then MIGRATE_DROP_CMS=1\n" : "VERDICT: NO USER DATA — migration 0010 runs without MIGRATE_DROP_CMS\n";
exit(0);
```

### CREATE `includes/file_references.php`

Content (copy exactly; it ends with a newline):

```php
<?php
// includes/file_references.php — Part 11A0. The generic "who uses this file" registry (table file_references).
//
// A module that stores a media_library id inside its own records calls fileRefsSync() in ITS OWN transaction when it saves
// the record, and fileRefsDeleteFor() when it deletes the record. public/api/media/delete.php refuses to delete a file that
// still has references; the foreign key file_references.media_id → media_library.id (ON DELETE RESTRICT) is the second guard.
//
// ref_type  = "<module>" or "<module>.<entity>", lower case ascii (example: "blog.post").
// ref_id    = the owner's record id as a string (an integer id, a uuid or a slug all fit; max 64 chars [A-Za-z0-9_-]).
// There is deliberately NO foreign key from ref_id to the owner's table: the platform cannot know every module's tables.
// Portable SQL only (MySQL and SQLite), so tests/file_references_test.php runs without a MySQL server.

function fileRefsValidType(string $type): bool {
    return strlen($type) <= 60 && (bool)preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)?$/', $type);
}

function fileRefsValidId(string $id): bool {
    return (bool)preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $id);
}

/** @return int[] unique positive integers, order kept */
function fileRefsNormalizeIds(array $ids): array {
    $out = [];
    foreach ($ids as $id) {
        $i = (int)$id;
        if ($i > 0) { $out[$i] = $i; }
    }
    return array_values($out);
}

/** @return int[] the subset of $ids that exist in media_library */
function fileRefsExistingIds(PDO $pdo, array $ids): array {
    $ids = fileRefsNormalizeIds($ids);
    if (!$ids) { return []; }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare('SELECT id FROM media_library WHERE id IN (' . $in . ')');
    $stmt->execute($ids);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Replaces the whole set of media ids that ONE record uses. Runs inside the caller's transaction.
 * Throws InvalidArgumentException for a bad type/id or an unknown media id (nothing is written in that case).
 * @param int[] $mediaIds
 */
function fileRefsSync(PDO $pdo, string $refType, string $refId, array $mediaIds): void {
    if (!fileRefsValidType($refType)) { throw new InvalidArgumentException('Bad ref_type'); }
    if (!fileRefsValidId($refId)) { throw new InvalidArgumentException('Bad ref_id'); }
    $mediaIds = fileRefsNormalizeIds($mediaIds);
    if ($mediaIds && count(fileRefsExistingIds($pdo, $mediaIds)) !== count($mediaIds)) {
        throw new InvalidArgumentException('Unknown media id');
    }
    $pdo->prepare('DELETE FROM file_references WHERE ref_type = ? AND ref_id = ?')->execute([$refType, $refId]);
    if ($mediaIds) {
        $insert = $pdo->prepare('INSERT INTO file_references (media_id, ref_type, ref_id) VALUES (?, ?, ?)');
        foreach ($mediaIds as $mediaId) {
            $insert->execute([$mediaId, $refType, $refId]);
        }
    }
}

/** Call when the owner deletes its record. */
function fileRefsDeleteFor(PDO $pdo, string $refType, string $refId): void {
    $pdo->prepare('DELETE FROM file_references WHERE ref_type = ? AND ref_id = ?')->execute([$refType, $refId]);
}

function fileRefsCount(PDO $pdo, int $mediaId): int {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM file_references WHERE media_id = ?');
    $stmt->execute([$mediaId]);
    return (int)$stmt->fetchColumn();
}

/** @return array<int, array{ref_type:string,ref_id:string}> at most $limit (1..50) users of one file, oldest first */
function fileRefsUsages(PDO $pdo, int $mediaId, int $limit = 6): array {
    $limit = max(1, min(50, $limit));
    $stmt = $pdo->prepare('SELECT ref_type, ref_id FROM file_references WHERE media_id = ? ORDER BY id LIMIT ' . $limit);
    $stmt->execute([$mediaId]);
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[] = ['ref_type' => (string)$row['ref_type'], 'ref_id' => (string)$row['ref_id']];
    }
    return $rows;
}
```

### CREATE `tests/cms_inventory_test.php`

Content (copy exactly; it ends with a newline):

```php
<?php
// Part 11A0: CmsInventory (the guard data) and migrations 0009/0010 control flow, on SQLite.
// NOT covered here (needs a real MySQL server): the MySQL-only DDL (DROP FOREIGN KEY, information_schema guards).
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) { echo "SKIP: pdo_sqlite driver is not enabled\n"; exit(0); }
require_once __DIR__ . '/../database/lib/CmsInventory.php';

$pass = 0; $fail = 0;
function ok(bool $cond, string $name): void {
    global $pass, $fail;
    if ($cond) { $pass++; echo "ok $name\n"; } else { $fail++; echo "FAIL $name\n"; }
}

/** The five Migrator methods the code under test uses, answered from SQLite. */
final class SqliteMigrator {
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }
    public function tableExists(string $t): bool {
        $s = $this->pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = ?"); $s->execute([$t]);
        return (int)$s->fetchColumn() > 0;
    }
    public function columnExists(string $t, string $c): bool {
        if (!$this->tableExists($t)) { return false; }
        foreach ($this->pdo->query('PRAGMA table_info(' . $t . ')')->fetchAll(PDO::FETCH_ASSOC) as $r) { if ($r['name'] === $c) { return true; } }
        return false;
    }
    public function fkExists(string $t, string $n): bool { return false; }
}

function freshDb(): array {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    foreach ([
        'CREATE TABLE panel_users (id INTEGER PRIMARY KEY)',
        'CREATE TABLE permissions (id INTEGER PRIMARY KEY, module_key TEXT, module_label TEXT)',
        'CREATE TABLE panel_user_permissions (id INTEGER PRIMARY KEY, panel_user_id INTEGER, permission_id INTEGER)',
        'CREATE TABLE site_settings (id INTEGER PRIMARY KEY, site_name TEXT, site_domain TEXT, active_template_id INTEGER, logo_path TEXT)',
        'CREATE TABLE media_library (id INTEGER PRIMARY KEY)',
        'CREATE TABLE file_references (id INTEGER PRIMARY KEY, media_id INTEGER, ref_type TEXT, ref_id TEXT)',
        'CREATE TABLE template_categories (id INTEGER PRIMARY KEY, slug TEXT)',
        'CREATE TABLE templates (id INTEGER PRIMARY KEY, source TEXT NOT NULL DEFAULT \'system\', package_path TEXT)',
        'CREATE TABLE builder_canvases (id INTEGER PRIMARY KEY, title TEXT)',
        'CREATE TABLE builder_blocks (id INTEGER PRIMARY KEY)',
        'CREATE TABLE builder_media_refs (id INTEGER PRIMARY KEY)',
        'CREATE TABLE licenses (id INTEGER PRIMARY KEY, status TEXT NOT NULL DEFAULT \'not_connected\', credentials TEXT, certificate_file TEXT)',
        'CREATE TABLE marketplaces (id INTEGER PRIMARY KEY)',
        'CREATE TABLE marketplace_connections (id INTEGER PRIMARY KEY, is_connected INTEGER NOT NULL DEFAULT 0, api_key TEXT, api_secret TEXT)',
        'CREATE TABLE dashboard_snapshots (id INTEGER PRIMARY KEY, pages_count INTEGER)',
    ] as $sql) { $pdo->exec($sql); }
    $seedName = file_get_contents(__DIR__ . '/../database/migrations/0010_cms_decommission_post_code.php');
    preg_match("/->execute\(\['پنل مدیریت', '([^']+)'\]\)/u", $seedName, $mm);
    $pdo->exec("INSERT INTO permissions (id, module_key, module_label) VALUES (1,'dashboard','a'),(2,'pages','b'),(3,'articles','c'),(4,'templates','d'),(5,'media','e'),(6,'licenses','f'),(7,'settings','g')");
    $pdo->exec("INSERT INTO panel_user_permissions (panel_user_id, permission_id) VALUES (1,1),(1,2),(1,6),(1,5)");
    $pdo->exec("INSERT INTO site_settings (id, site_name, site_domain, active_template_id) VALUES (1, '" . ($mm[1] ?? 'x') . "', NULL, 1)");
    $pdo->exec("INSERT INTO template_categories (id, slug) VALUES (1,'store')");
    $pdo->exec("INSERT INTO templates (id, source) VALUES (1,'system'),(2,'system')");
    $pdo->exec("INSERT INTO licenses (id) VALUES (1),(2),(3)");
    $pdo->exec("INSERT INTO marketplaces (id) VALUES (1),(2)");
    return [$pdo, new SqliteMigrator($pdo)];
}
function inv(PDO $pdo, SqliteMigrator $m): array {
    return CmsInventory::userData($pdo, [$m, 'tableExists'], [$m, 'columnExists']);
}
function runMigration(PDO $pdo, SqliteMigrator $m): ?string {
    ob_start();
    try {
        $def = require __DIR__ . '/../database/migrations/0010_cms_decommission_post_code.php';
        $def['up']($pdo, $m);
        ob_end_clean();
        return null;
    } catch (Throwable $e) {
        ob_end_clean();
        return $e->getMessage();
    }
}

// 1) seed rows only -> zero user data
[$pdo, $m] = freshDb();
$c = inv($pdo, $m);
ok(array_sum($c) === 0, 'inventory: seed rows only = 0 user rows');
ok(isset($c['builder_canvases (pages and template layouts)']) && isset($c['site_settings.site_domain (non-empty)']), 'inventory: keys present for existing tables');

// 2) each kind of user data is counted
$pdo->exec("INSERT INTO builder_canvases (id, title) VALUES (1, 'Home')");
ok(inv($pdo, $m)['builder_canvases (pages and template layouts)'] === 1, 'inventory: a canvas counts');
$pdo->exec("UPDATE templates SET source = 'uploaded' WHERE id = 2");
ok(inv($pdo, $m)['templates (uploaded by a person)'] === 1, 'inventory: an uploaded template counts');
$pdo->exec("UPDATE licenses SET status = 'verified' WHERE id = 1");
$pdo->exec("UPDATE licenses SET credentials = '{}' WHERE id = 2");
ok(inv($pdo, $m)['licenses (filled in)'] === 2, 'inventory: filled licences count');
$pdo->exec("INSERT INTO marketplace_connections (id, is_connected, api_key) VALUES (1, 0, ''), (2, 0, 'k'), (3, 1, NULL)");
ok(inv($pdo, $m)['marketplace_connections (connected or holding keys)'] === 2, 'inventory: connected or key-holding marketplace rows count');
$pdo->exec("UPDATE site_settings SET site_domain = 'example.com' WHERE id = 1");
ok(inv($pdo, $m)['site_settings.site_domain (non-empty)'] === 1, 'inventory: a typed domain counts');

// 3) migration refuses without the owner's variable, changes nothing
putenv('MIGRATE_DROP_CMS');
$msg = runMigration($pdo, $m);
ok($msg !== null && strpos($msg, 'MIGRATE_DROP_CMS=1') !== false, 'migration 0010 refuses when user data exists and the variable is not set');
ok($m->tableExists('builder_canvases') && $m->columnExists('site_settings', 'site_domain') && (int)$pdo->query('SELECT COUNT(*) FROM permissions')->fetchColumn() === 7, 'refusal changed nothing');

// 4) with the variable it runs completely
putenv('MIGRATE_DROP_CMS=1');
$msg = runMigration($pdo, $m);
ok($msg === null, 'migration 0010 runs with MIGRATE_DROP_CMS=1' . ($msg ? " ($msg)" : ''));
$gone = true;
foreach (CmsInventory::tables() as $t) { if ($m->tableExists($t)) { $gone = false; } }
ok($gone, 'every CMS table is dropped');
ok(!$m->columnExists('site_settings', 'site_domain') && !$m->columnExists('site_settings', 'active_template_id') && $m->columnExists('site_settings', 'logo_path'), 'site_settings: the two CMS columns dropped, logo_path kept');
$keys = $pdo->query('SELECT module_key FROM permissions ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
ok($keys === ['dashboard', 'media', 'settings'], 'permissions: only dashboard, media, settings remain');
ok((int)$pdo->query('SELECT COUNT(*) FROM panel_user_permissions')->fetchColumn() === 2, 'grants of the removed keys are deleted, the others kept');
ok($m->tableExists('file_references') && $m->tableExists('media_library'), 'file_references and media_library untouched');
putenv('MIGRATE_DROP_CMS');

// 5) seed-only database: runs WITHOUT the variable; site name is replaced only when it is still the seed value
[$pdo, $m] = freshDb();
$before = $pdo->query('SELECT site_name FROM site_settings WHERE id = 1')->fetchColumn();
$msg = runMigration($pdo, $m);
ok($msg === null, 'seed-only database: migration runs without the variable' . ($msg ? " ($msg)" : ''));
ok($pdo->query('SELECT site_name FROM site_settings WHERE id = 1')->fetchColumn() === 'پنل مدیریت' && $before !== 'پنل مدیریت', 'seed site name replaced by «پنل مدیریت»');
$msg = runMigration($pdo, $m);
ok($msg === null, 'second run is a no-op (idempotent)');

[$pdo, $m] = freshDb();
$pdo->exec("UPDATE site_settings SET site_name = 'شرکت من' WHERE id = 1");
runMigration($pdo, $m);
ok($pdo->query('SELECT site_name FROM site_settings WHERE id = 1')->fetchColumn() === 'شرکت من', 'a site name the owner typed is never changed');

// 6) 0009 prerequisite
[$pdo, $m] = freshDb();
$pdo->exec('DROP TABLE file_references');
$msg = runMigration($pdo, $m);
ok($msg !== null && strpos($msg, '0009') !== false, 'migration 0010 refuses when 0009 was not applied');

// 7) file shape of both migrations
foreach (['0009_file_references_pre_code.php', '0010_cms_decommission_post_code.php'] as $f) {
    $src = (string)file_get_contents(__DIR__ . '/../database/migrations/' . $f);
    ok(strpos($src, 'PHP_SAPI') !== false && strpos($src, "'description'") !== false && strpos($src, "'up'") !== false, "$f: has CLI guard, description and up()");
}
$sql9 = (string)file_get_contents(__DIR__ . '/../database/migrations/0009_file_references_pre_code.php');
ok(strpos($sql9, 'ON DELETE RESTRICT') !== false && strpos($sql9, 'UNIQUE KEY uq_fileref (media_id, ref_type, ref_id)') !== false, '0009: FK RESTRICT and the unique key are present');

// 8) leftover files report
$tmp = sys_get_temp_dir() . '/cmsinv_' . bin2hex(random_bytes(4));
mkdir($tmp . '/storage/private/licenses', 0777, true);
file_put_contents($tmp . '/storage/private/licenses/abc.pdf', 'x');
file_put_contents($tmp . '/storage/private/licenses/.gitkeep', '');
ok(CmsInventory::leftoverFiles($tmp) === ['storage/private/licenses/abc.pdf'], 'leftoverFiles lists real files, not .gitkeep');
unlink($tmp . '/storage/private/licenses/abc.pdf'); unlink($tmp . '/storage/private/licenses/.gitkeep');
rmdir($tmp . '/storage/private/licenses'); rmdir($tmp . '/storage/private'); rmdir($tmp . '/storage'); rmdir($tmp);

echo "passed: $pass / failed: $fail\n";
exit($fail === 0 ? 0 : 1);
```

### CREATE `tests/cms_removed_test.php`

Content (copy exactly; it ends with a newline):

```php
<?php
// Part 11A0: runs the gate database/tools/check_cms_removed.php on the real project (must be clean)
// and on small temporary trees (must catch a leftover file, a leftover term, and must allow the exempt places).
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$pass = 0; $fail = 0;
function ok(bool $cond, string $name): void {
    global $pass, $fail;
    if ($cond) { $pass++; echo "ok $name\n"; } else { $fail++; echo "FAIL $name\n"; }
}
$gate = __DIR__ . '/../database/tools/check_cms_removed.php';
function runGate(string $gate, ?string $root = null): array {
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($gate) . ($root !== null ? ' ' . escapeshellarg('--root=' . $root) : '') . ' 2>&1';
    exec($cmd, $out, $code);
    return [$code, implode("\n", $out)];
}
function put(string $root, string $rel, string $content): void {
    $abs = $root . '/' . $rel;
    if (!is_dir(dirname($abs))) { mkdir(dirname($abs), 0777, true); }
    file_put_contents($abs, $content);
}
function rrmdir(string $d): void {
    foreach (scandir($d) ?: [] as $f) {
        if ($f === '.' || $f === '..') { continue; }
        is_dir("$d/$f") ? rrmdir("$d/$f") : unlink("$d/$f");
    }
    rmdir($d);
}

[$code, $out] = runGate($gate);
ok($code === 0 && strpos($out, 'cms leftovers: 0') !== false, 'real project: no CMS leftovers' . ($code !== 0 ? "\n$out" : ''));

$tmp = sys_get_temp_dir() . '/cmsgate_' . bin2hex(random_bytes(4));
mkdir($tmp, 0777, true);
put($tmp, 'includes/clean.php', "<?php\necho 'hello';\n");
[$code, $out] = runGate($gate, $tmp);
ok($code === 0, 'temp tree: clean tree passes');

put($tmp, 'public/modules/pages/index.php', "<?php\n");
[$code, $out] = runGate($gate, $tmp);
ok($code === 1 && strpos($out, 'public/modules/pages still exists') !== false, 'temp tree: a leftover CMS directory is caught');
rrmdir($tmp . '/public/modules');

put($tmp, 'includes/nav.php', "<?php\nreturn [['visible' => 'perm:articles']];\n");
[$code, $out] = runGate($gate, $tmp);
ok($code === 1 && strpos($out, 'includes/nav.php:2') !== false, 'temp tree: a CMS permission key in code is caught');
unlink($tmp . '/includes/nav.php');

put($tmp, 'includes/x.php', "<?php\n\$q = 'SELECT * FROM builder_canvases';\n");
[$code, $out] = runGate($gate, $tmp);
ok($code === 1 && strpos($out, 'includes/x.php:2') !== false, 'temp tree: a CMS table name in code is caught');
unlink($tmp . '/includes/x.php');

put($tmp, 'includes/y.php', "<?php\n// ساخت سایت\n");
[$code, $out] = runGate($gate, $tmp);
ok($code === 1, 'temp tree: a removed Persian label is caught');
unlink($tmp . '/includes/y.php');

put($tmp, 'database/migrations/0099_x.php', "<?php\n// builder_canvases is dropped here\n");
put($tmp, 'changelogs/c.md', "builder_canvases\n");
put($tmp, 'docs/decommission-cms.md', "builder_canvases\n");
put($tmp, 'docs/ai/HANDOFF.md', "builder_canvases\n");
put($tmp, 'master-prompt-11A0-x.md', "builder_canvases\n");
[$code, $out] = runGate($gate, $tmp);
ok($code === 0, 'temp tree: migrations, changelogs, decommission record, docs/ai and master prompts are exempt');
rrmdir($tmp);

echo "passed: $pass / failed: $fail\n";
exit($fail === 0 ? 0 : 1);
```

### CREATE `tests/cms_shell_test.php`

Content (copy exactly; it ends with a newline):

```php
<?php
// Part 11A0: the shell data (nav + access map) and the files under public/modules must agree, and must contain no removed module.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$pass = 0; $fail = 0;
function ok(bool $cond, string $name): void {
    global $pass, $fail;
    if ($cond) { $pass++; echo "ok $name\n"; } else { $fail++; echo "FAIL $name\n"; }
}
$root = dirname(__DIR__);
$map = require $root . '/includes/access_map.php';
$nav = require $root . '/includes/nav.php';

ok(array_keys($map) === ['/modules/dashboard/index.php', '/modules/media/index.php', '/modules/settings/general.php', '/modules/settings/profile.php', '/modules/settings/staff.php'], 'access_map: exactly the five platform pages');
$mapFilesExist = true;
foreach (array_keys($map) as $url) { if (!is_file($root . '/public' . $url)) { $mapFilesExist = false; } }
ok($mapFilesExist, 'access_map: every mapped page exists as a file');

$onDisk = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/public/modules', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->isFile() && substr($f->getFilename(), -4) === '.php') { $onDisk[] = substr($f->getPathname(), strlen($root . '/public')); }
}
sort($onDisk, SORT_STRING);
$mapped = array_keys($map); sort($mapped, SORT_STRING);
ok($onDisk === $mapped, 'public/modules: every page file is in the access map (default-deny would block it otherwise)');

$keys = []; $urls = []; $perms = [];
foreach ($nav as $group) {
    foreach ($group['items'] as $item) {
        $keys[] = $item['key']; $urls[] = $item['url'];
        if (strncmp((string)$item['visible'], 'perm:', 5) === 0) { $perms[] = substr($item['visible'], 5); }
    }
}
ok($keys === ['dashboard', 'media', 'profile', 'general', 'staff'], 'nav: exactly the five platform entries');
ok(!array_diff($urls, array_keys($map)), 'nav: every url is in the access map');
ok(!array_diff($perms, ['dashboard', 'media', 'settings']), 'nav: only platform permission keys');

$src = (string)file_get_contents($root . '/includes/access_map.php') . (string)file_get_contents($root . '/includes/nav.php');
ok(!preg_match("/pages|articles|templates|licenses/", $src), 'nav and access map mention no removed module');

echo "passed: $pass / failed: $fail\n";
exit($fail === 0 ? 0 : 1);
```

### CREATE `tests/file_references_test.php`

Content (copy exactly; it ends with a newline):

```php
<?php
// Part 11A0: includes/file_references.php. SQLite stand-in for the two tables; skips when pdo_sqlite is missing.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/file_references.php';

$pass = 0; $fail = 0;
function ok(bool $cond, string $name): void {
    global $pass, $fail;
    if ($cond) { $pass++; echo "ok $name\n"; } else { $fail++; echo "FAIL $name\n"; }
}
function throwsInvalid(callable $fn): bool {
    try { $fn(); } catch (InvalidArgumentException $e) { return true; }
    return false;
}

// pure helpers
ok(fileRefsValidType('blog.post') && fileRefsValidType('blog') && fileRefsValidType('a_b.c_d'), 'type: valid shapes');
ok(!fileRefsValidType('Blog.post') && !fileRefsValidType('blog.') && !fileRefsValidType('1blog') && !fileRefsValidType('a.b.c') && !fileRefsValidType(''), 'type: invalid shapes');
ok(!fileRefsValidType('a' . str_repeat('b', 60)), 'type: longer than 60 refused');
ok(fileRefsValidId('42') && fileRefsValidId('a-b_C9') && !fileRefsValidId('') && !fileRefsValidId('a b') && !fileRefsValidId(str_repeat('x', 65)), 'id: shapes');
ok(fileRefsNormalizeIds([3, '3', 0, -1, 'x', 5, 3, 2]) === [3, 5, 2], 'normalize: unique positive ints, order kept');

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "SKIP SQL assertions: pdo_sqlite driver is not enabled\n";
} else {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE media_library (id INTEGER PRIMARY KEY)');
    $pdo->exec('INSERT INTO media_library (id) VALUES (1), (2), (3)');
    $pdo->exec('CREATE TABLE file_references (id INTEGER PRIMARY KEY AUTOINCREMENT, media_id INTEGER NOT NULL, ref_type TEXT NOT NULL, ref_id TEXT NOT NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE (media_id, ref_type, ref_id))');

    ok(fileRefsExistingIds($pdo, [1, 2, 9, 0]) === [1, 2], 'existingIds: unknown and zero ignored');
    ok(fileRefsExistingIds($pdo, []) === [], 'existingIds: empty');

    fileRefsSync($pdo, 'blog.post', '7', [1, 2, 2]);
    ok(fileRefsCount($pdo, 1) === 1 && fileRefsCount($pdo, 2) === 1 && fileRefsCount($pdo, 3) === 0, 'sync: duplicates collapse, counts per media');
    fileRefsSync($pdo, 'blog.post', '8', [1]);
    ok(fileRefsCount($pdo, 1) === 2, 'two records can use one file');
    fileRefsSync($pdo, 'blog.post', '7', [3]);
    ok(fileRefsCount($pdo, 1) === 1 && fileRefsCount($pdo, 2) === 0 && fileRefsCount($pdo, 3) === 1, 'sync replaces the whole set of one record only');
    ok(fileRefsUsages($pdo, 1) === [['ref_type' => 'blog.post', 'ref_id' => '8']], 'usages: shape and content');

    ok(throwsInvalid(function () use ($pdo) { fileRefsSync($pdo, 'blog.post', '7', [1, 99]); }), 'sync: unknown media id throws');
    ok(fileRefsCount($pdo, 3) === 1 && fileRefsCount($pdo, 1) === 1, 'sync: nothing written when it throws');
    ok(throwsInvalid(function () use ($pdo) { fileRefsSync($pdo, 'Bad Type', '7', [1]); }), 'sync: bad type throws');
    ok(throwsInvalid(function () use ($pdo) { fileRefsSync($pdo, 'blog.post', 'a b', [1]); }), 'sync: bad id throws');

    fileRefsSync($pdo, 'blog.post', '7', []);
    ok(fileRefsCount($pdo, 3) === 0, 'sync with an empty set clears the record');
    fileRefsSync($pdo, 'crm.contact', 'u-1', [1, 2]);
    fileRefsDeleteFor($pdo, 'crm.contact', 'u-1');
    ok(fileRefsCount($pdo, 2) === 0 && fileRefsCount($pdo, 1) === 1, 'deleteFor removes only that record');
    for ($i = 1; $i <= 60; $i++) { fileRefsSync($pdo, 'bulk', (string)$i, [2]); }
    ok(count(fileRefsUsages($pdo, 2, 500)) === 50 && count(fileRefsUsages($pdo, 2, 0)) === 1, 'usages: limit clamped to 1..50');
}

echo "passed: $pass / failed: $fail\n";
exit($fail === 0 ? 0 : 1);
```

### CREATE `tests/manifest_tool_test.php`

Content (copy exactly; it ends with a newline):

```php
<?php
// Part 11A0: tools/gates/verify_manifest.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../tools/gates/verify_manifest.php';

$pass = 0; $fail = 0;
function ok(bool $cond, string $name): void {
    global $pass, $fail;
    if ($cond) { $pass++; echo "ok $name\n"; } else { $fail++; echo "FAIL $name\n"; }
}
$tmp = sys_get_temp_dir() . '/manifest_' . bin2hex(random_bytes(4));
mkdir($tmp, 0777, true);
file_put_contents($tmp . '/a.txt', "line1\nline2\n");
file_put_contents($tmp . '/crlf.txt', "line1\r\nline2\r\n");

ok(manifestHash("a\r\nb\rc\n") === manifestHash("a\nb\nc\n"), 'hash: CRLF and CR are normalised to LF');
$h = manifestHash("line1\nline2\n");
$r = manifestVerify($tmp, ['files' => ['a.txt' => $h, 'crlf.txt' => $h]]);
ok($r['ok'] === 2 && $r['bad'] === 0, 'verify: identical files (also with CRLF) are OK');
$r = manifestVerify($tmp, ['files' => ['a.txt' => str_repeat('0', 64), 'nope.txt' => $h]]);
ok($r['bad'] === 2 && strpos(implode("\n", $r['lines']), 'MISMATCH    a.txt') !== false && strpos(implode("\n", $r['lines']), 'MISSING     nope.txt') !== false, 'verify: MISMATCH and MISSING are bad');
$r = manifestVerify($tmp, ['files' => [], 'present' => ['a.txt', 'gone.txt'], 'absent' => ['gone.txt', 'a.txt']]);
ok($r['ok'] === 2 && $r['bad'] === 1 && $r['info'] === 1, 'verify: present (info when missing) and absent (bad when still there)');
ok(strpos(implode("\n", $r['lines']), 'STILL_THERE a.txt') !== false && strpos(implode("\n", $r['lines']), 'ABSENT      gone.txt') !== false, 'verify: line labels');

$manifest = $tmp . '/m.json';
file_put_contents($manifest, json_encode(['part' => 'T', 'files' => ['a.txt' => $h]]));
$code = 0;
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../tools/gates/verify_manifest.php') . ' ' . escapeshellarg($manifest) . ' 2>&1', $out, $code);
ok($code === 1, 'cli: exit 1 because the manifest is verified against the PROJECT root, where a.txt does not exist');
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../tools/gates/verify_manifest.php') . ' 2>&1', $out2, $code2);
ok($code2 === 2, 'cli: no argument = usage, exit 2');

unlink($tmp . '/a.txt'); unlink($tmp . '/crlf.txt'); unlink($manifest); rmdir($tmp);
echo "passed: $pass / failed: $fail\n";
exit($fail === 0 ? 0 : 1);
```

### STEP 7 — create the record, the hand-off set, the manifest and the changelog

Create `docs/ai/` and `docs/ai/manifests/` if needed, then these files. (`docs/ai/manifests/11A0-baseline.json` was created in Step 1.)

### CREATE `docs/decommission-cms.md`

Content (copy exactly; it ends with a newline):

```markdown
# CMS decommission record (Part 11A0)

Why: the product is now a generic admin application platform (see `docs/ai/HANDOFF.md`). This file is the complete list of what the CMS was, what happened to each piece, and what was kept on purpose. A later AI agent must not look for these things or bring them back by accident.

## 1. Files removed

### 1.1 Pages (public/modules)
- `public/modules/articles/index.php`
- `public/modules/licenses/index.php`
- `public/modules/pages/index.php`
- `public/modules/templates/index.php`

### 1.2 Server-side CMS code (includes/builder)
- `includes/builder/block_registry.php`
- `includes/builder/bootstrap.php`
- `includes/builder/builder_assets.php`
- `includes/builder/canvas_repository.php`
- `includes/builder/entry.php`
- `includes/builder/html_sanitizer.php`
- `includes/builder/media_refs.php`
- `includes/builder/pages_list.php`
- `includes/builder/renderer.php`
- `includes/builder/tree_validator.php`
- `includes/builder/types/button.php`
- `includes/builder/types/image_video.php`
- `includes/builder/types/slider.php`
- `includes/builder/types/story.php`
- `includes/builder/types/structural.php`
- `includes/builder/types/text.php`

### 1.3 Other files
- `public/file.php`
- `docs/builder-architecture.md`
- `tools/ui/migrate_builder_assets.py`
- `database/tools/encrypt_existing_secrets.php`

## 2. APIs removed (URL → file)

| URL | File | Purpose (was) |
|---|---|---|
| `/api/builder/load.php`, `save.php`, `delete.php`, `render.php`, `media.php` | `public/api/builder/*` | page builder canvas load/save/delete/preview, media picker feed |
| `/api/templates/activate.php`, `install.php`, `upload.php` | `public/api/templates/*` | template activation, installation, ZIP upload |
| `/api/licenses/update.php` | `public/api/licenses/update.php` | licence / payment-gateway form |
| `/file.php?type=license|template&id=N` | `public/file.php` | private download of certificates and template packages |

Kept (generic): `/api/media/{upload,delete,info}.php`, `/api/notifications/mark_read.php`, `/api/staff/*`.
`/api/media/delete.php` was rewritten to ask `file_references` instead of `builder_media_refs`.

## 3. Permission keys

| Key | Label (was) | Action |
|---|---|---|
| `pages` | ساخت سایت | deleted by migration 0010 (with every grant that pointed at it) |
| `articles` | صفحات ساخته شده | deleted by migration 0010 |
| `templates` | قالب سایت | deleted by migration 0010 |
| `licenses` | مجوزات | deleted by migration 0010 |
| `sales` | افزایش فروش | already deleted by migration 0008 (Part 10C) |
| `dashboard`, `media`, `settings` | — | **kept** |

Old `activity_logs` rows with these module keys are kept as audit trail (the dashboard already shows a generic label for unknown modules).

## 4. Tables and columns

| Object | Action | Migration | Data policy |
|---|---|---|---|
| `builder_media_refs`, `builder_blocks`, `builder_canvases` | dropped | 0010 | guard: any row = user data → needs `MIGRATE_DROP_CMS=1` |
| `templates` | dropped | 0010 | guard: uploaded templates only (3 system sample rows are seed) |
| `template_categories` | dropped | 0010 | seed only |
| `licenses` | dropped | 0010 | guard: filled-in licences only |
| `marketplace_connections`, `marketplaces` | dropped if still present | 0010 (0008 dropped them when the owner approved) | guard: connected / key-holding rows |
| `dashboard_snapshots` | dropped | 0010 | derived statistics (pages/articles counts), not user data |
| `page_blocks`, `article_blocks`, `pages`, `articles`, `article_categories`, `media_usage` | dropped if still present (0006 normally did) | 0010 | guard: any row |
| `site_settings.active_template_id` (+ FK `fk_settings_template`) | column dropped | 0010 | — |
| `site_settings.site_domain` | column dropped | 0010 | guard: a non-empty domain counts as user data |
| `file_references` | **created** (generic replacement of `builder_media_refs`) | 0009 | — |
| `media_library` | **kept** | — | decoupled from pages/articles |

## 5. Migrations

| File | Kind | What it does |
|---|---|---|
| `database/migrations/0009_file_references_pre_code.php` | pre-code, additive | creates `file_references` (FK `media_id` → `media_library.id` RESTRICT, UNIQUE `(media_id, ref_type, ref_id)`) |
| `database/migrations/0010_cms_decommission_post_code.php` | post-code, **irreversible** | guard + permission keys + site_settings columns + table drops (see section 4) |
| `database/schema.sql` | frozen baseline | **not edited**; a fresh install runs the baseline and then migrations 0001–0010 |

Rollback = restore the backup taken before 0010. There is no down-migration.

## 6. Tests

| Test | Action |
|---|---|
| `tests/browser/engine_contract.json` | deleted |
| `tests/browser/run_builder_checks.py` | deleted |
| `tests/browser/run_builder_checks_async.py` | deleted |
| `tests/builder_entry_test.php` | deleted |
| `tests/builder_pages_list_test.php` | deleted |
| `tests/builder_registry_test.php` | deleted |
| `tests/html_sanitizer_test.php` | deleted |
| `tests/js/builder_tree_test.js` | deleted |
| `tests/file_rules_test.php`, `tests/file_store_test.php`, `tests/dashboard_data_test.php` | edited (no CMS names; private kind `documents`; two new dashboard providers) |
| `tests/cms_removed_test.php`, `tests/cms_shell_test.php`, `tests/cms_inventory_test.php`, `tests/file_references_test.php`, `tests/manifest_tool_test.php` | **new** |
| `tests/ui/check_ui_gates.py`, `check_pages_covered.py`, `tests/browser/run_ui_checks.py`, `check_pages_semantics.py` | edited (page lists, builder exceptions) |

## 7. Assets

| Asset | Action |
|---|---|
| `public/assets/js/builder/block-registry.js` | deleted |
| `public/assets/js/builder/builder-core.js` | deleted |
| `public/assets/js/builder/builder-shell.js` | deleted |
| `public/assets/js/builder/builder-tree.js` | deleted |
| `public/assets/js/builder/types/.gitkeep` | deleted |
| `public/assets/js/builder/types/00-structural.js` | deleted |
| `public/assets/js/builder/types/10-text.js` | deleted |
| `public/assets/js/builder/types/11-image-video.js` | deleted |
| `public/assets/js/builder/types/12-button.js` | deleted |
| `public/assets/js/builder/types/20-slider.js` | deleted |
| `public/assets/js/builder/types/21-story.js` | deleted |
| `public/assets/js/builder/ui/00-sortable.js` | deleted |
| `public/assets/js/builder/ui/10-canvas.js` | deleted |
| `public/assets/js/builder/ui/20-settings-panel.js` | deleted |
| `public/assets/js/builder/ui/21-insert-menu.js` | deleted |
| `public/assets/js/builder/ui/30-preview.js` | deleted |
| `public/assets/js/builder/ui/40-media-picker.js` | deleted |
| `public/assets/js/builder/ui/50-page-actions.js` | deleted |
| `public/assets/js/pages-manager.js` | deleted |
| `public/assets/css/builder/10-shell.css` | deleted |
| `public/assets/css/builder/20-canvas.css` | deleted |
| `public/assets/css/builder/30-toolbar-interactions.css` | deleted |
| `public/assets/css/builder/40-content-blocks.css` | deleted |
| `public/assets/css/builder/50-media-picker.css` | deleted |
| `public/assets/css/app/45-pages.css` | **split**: the Templates and Licenses blocks removed; media, staff, profile and general blocks kept |
| `public/assets/css/app/00-tokens.css` | kept (the `--viewer-*` lightbox tokens stay; only comments changed) |
| `tests/browser/shots/` | 36 CMS/dashboard screenshots deleted (old output of the UI checker; regenerate with `tests/browser/run_ui_checks.py`) |

## 8. Kept on purpose (generic platform parts)

Auth / session / CSRF / permission helpers; `includes/file_rules.php`, `file_store.php` (private storage generalised), `media_registry.php`; the media library page and APIs; shell/layout, design tokens and generic CSS; migration infrastructure; test runner and UI gates; notifications and activity log (audit); `includes/secrets.php`.

## 9. Left for the owner

- Files that may still be on disk: `storage/private/licenses/`, `storage/private/templates/`, `public/uploads/templates/` (see `php database/tools/cms_inventory.php`). Nothing deletes them automatically.
- `site_settings.panel_plan` stays until the settings part (11D).
```

### CREATE `docs/ai/HANDOFF.md`

Content (copy exactly; it ends with a newline):

```markdown
# HANDOFF — read this first (any AI agent continuing this project)

## 1. What this project is
A PHP 8.0 / MySQL admin application for XAMPP on Windows (Apache DocumentRoot = `public/`). Since Part 11A0 it is **not** a website builder or CMS any more: it is the seed of a **modular admin application platform** (a starter kit for admin apps), in three layers:
1. **Core Platform** — auth, session, CSRF, permissions, shell/layout, design tokens, migrations, audit log, file storage.
2. **Platform Services** — settings, notifications, media/files, dashboard widgets, audit, feature flags (some exist, some arrive in later parts).
3. **Business Modules** — optional, installed later (none exist yet; the CMS was removed, and no HRM or other business module is a prerequisite of anything).

## 2. How the work is organised
- The owner (Pouya) works with several AI agents one after another. A **prompt author** (Claude) writes English master prompts (`master-prompt-*.md` in the project root). An **executor** (the OpenCode agent) only copies, replaces, deletes and runs commands; it does no engineering.
- Order of the series: 11A0 → 11A → 11B → 11C → 11D → 11E1…11E5 → 11F1 → 11F2 → 11G → 11H. Read `master-prompt-11-ROADMAP.md` for the goal of each part.
- Every part leaves: a changelog in `changelogs/`, an entry in `docs/ai/state.json`, and (from 11A0 on) a sha256 manifest in `docs/ai/manifests/`.

## 3. Rules for any AI continuing the work
1. Read this file, `DECISIONS.md`, `FILE-MAP.md`, `RELEASE-BLOCKERS.md`, then the newest changelog, before you touch anything.
2. Never edit `database/schema.sql` (frozen baseline). Every schema change is a new migration in `database/migrations/`.
3. Never delete or rewrite a file that is in a manifest unless a master prompt tells you to. A manifest is a snapshot taken at the end of its part: run it right after that part, and as the pre-flight of the next part (before that part changes anything). Later parts legitimately change files, so an old manifest reports them as `MISMATCH`; that is history, not an error. The mutable hand-off files (`HANDOFF.md`, `DECISIONS.md`, `FILE-MAP.md`, `RELEASE-BLOCKERS.md`, `state.json`) are never in a manifest.
4. Destructive database or data steps need the owner's explicit approval and a backup. Never set an approval variable (for example `MIGRATE_DROP_CMS=1`) on your own.
5. Persian UI strings use Persian letters only (ی ک), never Arabic look-alikes.
6. If this repository and a prompt disagree, report the conflict in the changelog under "Deviations"; do not invent a fix.
7. Status words: `authored` (prompt written) → `reviewed` → `ready` (ready to execute) → `executed` → `verified` (checks run on the real stack) → `released`; `partial` and `blocked` for stopped work.

## 4. Current state (updated by each part)
- 11A0 — product reset and CMS decommission: see `state.json` and the 11A0 changelog. The CMS code, pages, APIs, tests and assets are removed; the database part (migrations 0009 and 0010) needs the owner's approval — `state.json` says whether it was applied.

## 5. Known limits of verification (be honest about them)
- The prompt author tested in a Linux sandbox: PHP 8.3, SQLite, no MySQL, no Apache, no Windows. MySQL-only DDL (dropping foreign keys and tables), Apache rewrite behaviour and the PHP 8.0 runtime are **not** verified by the author.
- Pages that need a database (dashboard, media, settings) were linted and unit-tested through their data providers, but not rendered against a real MySQL by the author.
```

### CREATE `docs/ai/DECISIONS.md`

Content (copy exactly; it ends with a newline):

```markdown
# DECISIONS (append-only; newest at the bottom)

| ID | Decision | Reason |
|---|---|---|
| D-001 | The product is a generic admin application platform, not a CMS / website builder. | Owner's product reset (review brief). |
| D-002 | The CMS is **removed**, not hidden or disabled: code, routes, nav entries, permission keys, tables, tests, assets. | Dead code keeps its dependencies alive and confuses later AI agents. The record of what was removed is `docs/decommission-cms.md`. |
| D-003 | `media_library` stays; its "who uses this file" link moves from `builder_media_refs` to the generic `file_references` table (`ref_type`, `ref_id`, FK `media_id` ON DELETE RESTRICT). | A file service must not know any module's tables. `ref_id` has no FK on purpose; owners keep rows in step through `includes/file_references.php`. |
| D-004 | The CMS database removal is a **new** migration (0010, post-code) plus an additive one (0009, pre-code). `database/schema.sql` is not edited. | The baseline is frozen history; migrations are the only way the schema changes. |
| D-005 | Migration 0010 refuses to run when user data exists (a built page, an uploaded template, a filled licence, a connected marketplace, a typed domain) unless the owner sets `MIGRATE_DROP_CMS=1` after a backup. Seed rows do not count. | Same pattern as migrations 0006 and 0008; the destructive decision belongs to the owner. |
| D-006 | `site_settings.active_template_id` and `site_settings.site_domain` are dropped; `site_name`, `logo_path`, `favicon_path`, `panel_plan` stay until the settings part (11D) decides the generic settings model. The untouched seed name «وب‌سایت من» becomes «پنل مدیریت». | Those two columns only made sense for a website. |
| D-007 | Private file kinds are no longer a fixed list (`licenses`, `templates`); a kind is any lower-case identifier of 2–32 characters. The private-file download endpoint (`public/file.php`) is removed; a generic one arrives with the file service (11F2). | Nothing stores private files now; the mechanism is kept, the CMS names are gone. |
| D-008 | The ZIP-entry safety helpers (`zipEntryIsSafe`, `zipEntryExtensionAllowed`) and the licence-certificate helper are removed with their only users. | Re-introduce them, with tests, if module packages are ever uploaded (11G). |
| D-009 | The dashboard is a minimal generic page (system health, media count, active staff, recent activity). Widgets become a registry in 11E3. | The old numbers (pages, templates) no longer exist. |
| D-010 | `database/tools/diagnose.php` loses the CMS-only checks (D07–D10, D14, D16–D18, D21, D23–D26) and gains D27 (CMS gone) and D28 (file_references has no orphan). | A diagnostic must not query dropped tables. |
| D-011 | CMS files on disk (`storage/private/licenses`, `storage/private/templates`, `public/uploads/templates`) are never deleted by a migration or by the executor; the owner decides. | Files may hold the owner's only copy of a certificate or template. |
```

### CREATE `docs/ai/FILE-MAP.md`

Content (copy exactly; it ends with a newline):

```markdown
# FILE-MAP (after Part 11A0)

| Path | What it is | Layer |
|---|---|---|
| `config/` | constants, DB connection, local env loader | Core |
| `includes/auth.php`, `session.php`, `csrf.php`, `permissions.php`, `permission_rules.php`, `auth_rules.php`, `page_boot.php`, `access_map.php` | login, session, CSRF, permission checks, central page guard (default-deny) | Core |
| `includes/header.php`, `layout/*`, `layout_helpers.php`, `nav.php`, `ui_assets.php`, `vendor.php`, `forbidden_page.php` | shell: head, sidebar, topbar, navigation data | Core |
| `includes/file_rules.php`, `file_store.php`, `image_support.php`, `media_registry.php` | upload rules, storage, image handling, media registry | Platform service (files) |
| `includes/file_references.php` | who uses which media file (table `file_references`) | Platform service (files) |
| `includes/dashboard_data.php` | dashboard data providers | Platform service (dashboard) |
| `includes/functions.php`, `jdf.php`, `secrets.php` | helpers, Jalali dates, encryption helper | Core |
| `public/index.php`, `login.php`, `logout.php` | entry redirect, sign-in, sign-out | Core |
| `public/modules/dashboard`, `media`, `settings/{general,profile,staff}.php` | the five platform pages | Core / services |
| `public/api/media/*`, `notifications/*`, `staff/*` | JSON endpoints (legacy shape `{success,message,data}`) | Core / services |
| `public/assets/css/app/*`, `js/app.js`, `admin.js`, `notifications.js` | design system and UI runtime | Core |
| `public/ui-kit/` | developer-only component sheet | Core (dev) |
| `database/schema.sql` | frozen baseline (never edit) | Core |
| `database/migrations/`, `migrate.php`, `lib/Migrator.php` | migration runner and history (0001–0010) | Core |
| `database/lib/CmsInventory.php`, `tools/cms_inventory.php`, `tools/check_cms_removed.php` | CMS removal inventory and gate | Core (history/gate) |
| `database/tools/*` | operator tools (diagnose, purge, env check, backfill, gc…) | Core |
| `tests/` | PHP unit tests (`php tests/run_all.php`), UI gates and browser checks | Core |
| `tools/gates/verify_manifest.php` | sha256 manifest verifier | Core (process) |
| `docs/` | UI guide/pages, decommission record, `ai/` hand-off set | process |
| `changelogs/` | one file per executed part | process |
| `master-prompt-*.md` | prompts written for the executor | process |

Removed in 11A0 (do not look for them): page builder, pages, articles, templates, licenses, `public/file.php`. See `docs/decommission-cms.md`.
```

### CREATE `docs/ai/RELEASE-BLOCKERS.md`

Content (copy exactly; it ends with a newline):

```markdown
# RELEASE BLOCKERS — nothing here may be shipped to production until each item is closed

| ID | Blocker | Closed by | State |
|---|---|---|---|
| RB-1 | `database/schema.sql` seeds the owner account `admin` with a published password. Today it is mitigated (migration 0002 forces a password change, `config/config.php` refuses the seed hash) but the seed still exists in the frozen baseline. | 11D (installer creates the first owner with a generated password) + 11H (release gate) | open |
| RB-2 | The code base is only **statically** checked for PHP 8.0 (token scan, from 11A). No PHP 8.0 runtime run exists. | 11H (CI matrix on real PHP 8.0) | open |
| RB-3 | Legacy PHP files under `public/` are reachable directly by URL (Apache serves existing files). | 11B (legacy gateway, single entry point) | open |
| RB-4 | No test has run on a real Apache + MySQL stack. | 11H | open |
| RB-5 | CMS files may still be on disk (`storage/private/licenses`, `storage/private/templates`, `public/uploads/templates`). | owner decision after `php database/tools/cms_inventory.php` | open |
| RB-6 | Inline scripts/styles make a strict Content-Security-Policy impossible today. | 11E1 | open |
```

### CREATE `docs/ai/state.json`

Content (copy exactly; it ends with a newline):

```json
{
  "series": "Part 11 - Modular Admin Application Platform",
  "schema": 2,
  "status_vocabulary": [
    "authored",
    "reviewed",
    "ready",
    "executed",
    "verified",
    "released",
    "partial",
    "blocked"
  ],
  "parts": {
    "11A0": {
      "status": "partial",
      "database": "pending-owner-approval",
      "changelog": "changelogs/changelog-part-11a0-product-reset-cms-decommission.md",
      "manifest": "docs/ai/manifests/11A0.json"
    }
  }
}
```

### CREATE `docs/ai/manifests/11A0.json`

Content (copy exactly; it ends with a newline):

```json
{
  "absent": [
    "database/tools/encrypt_existing_secrets.php",
    "docs/builder-architecture.md",
    "includes/builder/block_registry.php",
    "includes/builder/bootstrap.php",
    "includes/builder/builder_assets.php",
    "includes/builder/canvas_repository.php",
    "includes/builder/entry.php",
    "includes/builder/html_sanitizer.php",
    "includes/builder/media_refs.php",
    "includes/builder/pages_list.php",
    "includes/builder/renderer.php",
    "includes/builder/tree_validator.php",
    "includes/builder/types/button.php",
    "includes/builder/types/image_video.php",
    "includes/builder/types/slider.php",
    "includes/builder/types/story.php",
    "includes/builder/types/structural.php",
    "includes/builder/types/text.php",
    "public/api/builder/delete.php",
    "public/api/builder/load.php",
    "public/api/builder/media.php",
    "public/api/builder/render.php",
    "public/api/builder/save.php",
    "public/api/licenses/update.php",
    "public/api/templates/activate.php",
    "public/api/templates/install.php",
    "public/api/templates/upload.php",
    "public/assets/css/builder/10-shell.css",
    "public/assets/css/builder/20-canvas.css",
    "public/assets/css/builder/30-toolbar-interactions.css",
    "public/assets/css/builder/40-content-blocks.css",
    "public/assets/css/builder/50-media-picker.css",
    "public/assets/js/builder/block-registry.js",
    "public/assets/js/builder/builder-core.js",
    "public/assets/js/builder/builder-shell.js",
    "public/assets/js/builder/builder-tree.js",
    "public/assets/js/builder/types/.gitkeep",
    "public/assets/js/builder/types/00-structural.js",
    "public/assets/js/builder/types/10-text.js",
    "public/assets/js/builder/types/11-image-video.js",
    "public/assets/js/builder/types/12-button.js",
    "public/assets/js/builder/types/20-slider.js",
    "public/assets/js/builder/types/21-story.js",
    "public/assets/js/builder/ui/00-sortable.js",
    "public/assets/js/builder/ui/10-canvas.js",
    "public/assets/js/builder/ui/20-settings-panel.js",
    "public/assets/js/builder/ui/21-insert-menu.js",
    "public/assets/js/builder/ui/30-preview.js",
    "public/assets/js/builder/ui/40-media-picker.js",
    "public/assets/js/builder/ui/50-page-actions.js",
    "public/assets/js/pages-manager.js",
    "public/file.php",
    "public/modules/articles/index.php",
    "public/modules/licenses/index.php",
    "public/modules/pages/index.php",
    "public/modules/templates/index.php",
    "tests/browser/engine_contract.json",
    "tests/browser/run_builder_checks.py",
    "tests/browser/run_builder_checks_async.py",
    "tests/browser/shots/builder-host-1280-dark.png",
    "tests/browser/shots/builder-host-1280-light.png",
    "tests/browser/shots/builder-host-360-dark.png",
    "tests/browser/shots/builder-host-360-light.png",
    "tests/browser/shots/builder-host-768-dark.png",
    "tests/browser/shots/builder-host-768-light.png",
    "tests/browser/shots/live-modules-articles-index-php-1280-dark.png",
    "tests/browser/shots/live-modules-articles-index-php-1280-light.png",
    "tests/browser/shots/live-modules-articles-index-php-360-dark.png",
    "tests/browser/shots/live-modules-articles-index-php-360-light.png",
    "tests/browser/shots/live-modules-articles-index-php-768-dark.png",
    "tests/browser/shots/live-modules-articles-index-php-768-light.png",
    "tests/browser/shots/live-modules-dashboard-index-php-1280-dark.png",
    "tests/browser/shots/live-modules-dashboard-index-php-1280-light.png",
    "tests/browser/shots/live-modules-dashboard-index-php-360-dark.png",
    "tests/browser/shots/live-modules-dashboard-index-php-360-light.png",
    "tests/browser/shots/live-modules-dashboard-index-php-768-dark.png",
    "tests/browser/shots/live-modules-dashboard-index-php-768-light.png",
    "tests/browser/shots/live-modules-licenses-index-php-1280-dark.png",
    "tests/browser/shots/live-modules-licenses-index-php-1280-light.png",
    "tests/browser/shots/live-modules-licenses-index-php-360-dark.png",
    "tests/browser/shots/live-modules-licenses-index-php-360-light.png",
    "tests/browser/shots/live-modules-licenses-index-php-768-dark.png",
    "tests/browser/shots/live-modules-licenses-index-php-768-light.png",
    "tests/browser/shots/live-modules-pages-index-php-1280-dark.png",
    "tests/browser/shots/live-modules-pages-index-php-1280-light.png",
    "tests/browser/shots/live-modules-pages-index-php-360-dark.png",
    "tests/browser/shots/live-modules-pages-index-php-360-light.png",
    "tests/browser/shots/live-modules-pages-index-php-768-dark.png",
    "tests/browser/shots/live-modules-pages-index-php-768-light.png",
    "tests/browser/shots/live-modules-templates-index-php-1280-dark.png",
    "tests/browser/shots/live-modules-templates-index-php-1280-light.png",
    "tests/browser/shots/live-modules-templates-index-php-360-dark.png",
    "tests/browser/shots/live-modules-templates-index-php-360-light.png",
    "tests/browser/shots/live-modules-templates-index-php-768-dark.png",
    "tests/browser/shots/live-modules-templates-index-php-768-light.png",
    "tests/builder_entry_test.php",
    "tests/builder_pages_list_test.php",
    "tests/builder_registry_test.php",
    "tests/html_sanitizer_test.php",
    "tests/js/builder_tree_test.js",
    "tools/ui/migrate_builder_assets.py",
    "public/modules/pages",
    "public/modules/articles",
    "public/modules/templates",
    "public/modules/licenses",
    "public/api/builder",
    "public/api/templates",
    "public/api/licenses",
    "includes/builder",
    "public/assets/js/builder",
    "public/assets/css/builder",
    "tests/js"
  ],
  "files": {
    "config/config.php": "f3a769c8ed7f8153abd027caa6f6d81d3c756315e6783262c3b257bc51a27a12",
    "database/README.md": "8effec0e68f43fe36b66a8b37b5a61c5d3eead561c1c47ae140218f69bcb0982",
    "database/lib/CmsInventory.php": "894e04b9f2a1f2664fc7caf7410cd1ba89ef5a35bcaea60073bdf0c64b88eb4e",
    "database/migrations/0009_file_references_pre_code.php": "8b3fcc4b71af79f55849dba9ee7790e46fca12a9251d79368ef66e62b459c818",
    "database/migrations/0010_cms_decommission_post_code.php": "83a46637313941de832a3530f36cc73889833bb90ca735695778161b781ca623",
    "database/tools/backfill_files.php": "609f1b0fb61f50925530189d95b33a1f2c37655f908f958f2b9254b085db5f0a",
    "database/tools/check_cms_removed.php": "0a5ecf646877ed69a2256dd5d675e974d40ee4dc0d1cec8f232f4b6fb7f5611a",
    "database/tools/cms_inventory.php": "a3d086f4ae33fd1e32874598d46146e660fc9d46fca77fe35093bbe2e2345474",
    "database/tools/diagnose.php": "11d202a45b951ef83c5b479874df887fd6c31a8a88491716e0c956117597b52b",
    "database/tools/gc_files.php": "6b1af04effed1560e692494972587b8e88174c8d572414bed2ddcfaa43779de4",
    "docs/decommission-cms.md": "2443bf1067667f575c5347c6a1b7eb07f7dbe968998798b04c0d1529e2b67f7b",
    "docs/ui-guide.md": "a3e8a6b30f243ff74262c6838cdd794c5dc504dedd88531f05c314cdc971f34f",
    "docs/ui-pages.md": "de2c6cf8d21a6a6619c042acaf26a85fb984060a8be1c0863a69050c0d1d3a3f",
    "includes/access_map.php": "32e0683be95983f18e20c0339c17052e0bcfc9d7de1f6f039ab41067a0ad9860",
    "includes/dashboard_data.php": "1f9808cde2914eb6fb7e24452870b80ec75fc2abc06508c81ea4165380fd21eb",
    "includes/file_references.php": "4cf9fae3a250c70f8253d7f88da13e46cf291fc1f429ea19214ca03065da6b75",
    "includes/file_rules.php": "0b7b39a4d524d13e2cfcebde2ae72585e5e35a649715cf702da19f8ecc400e49",
    "includes/file_store.php": "59e18800bf3b2672ecb8cd6c566cb96cb9d0913711865917e1b140622d521bc8",
    "includes/layout/sidebar.php": "072248f4c98773690c4fe30f8cddeaca6daa00b965d6a6c9c6c42344f109c892",
    "includes/nav.php": "ddadc754f2557e56e046657ab6a882b6083e773861108bd9163472609f90c7e6",
    "public/api/media/delete.php": "5bfab084c078b33531f48abc9cfc10c2f8e63cce2ebb6d839609015a0586b336",
    "public/assets/css/app/00-tokens.css": "19520cce62904660a385ab0cf2c457657eb545170855a058d3ef18b89c10de1c",
    "public/assets/css/app/20-shell.css": "f891a97570ae3241ff610d37ab0ff17eee25c9fc10610c054370301a1f6ee0bf",
    "public/assets/css/app/40-motion.css": "b54fccba98db9071ea914d8ddf2b4ed2794401c0e6962d6d6bd51dd7c2b57639",
    "public/assets/css/app/45-pages.css": "59952618eb26c16243924b0d33e30dfcb42e96b29e4f03865e7ff108cb79d98b",
    "public/modules/dashboard/index.php": "8386f6411cf23bee52b5be9143d87b3205a7929582b94451b65a21c863fe02a6",
    "public/modules/media/index.php": "0d1e1d8572aa4b0f143dcc53ef900590aef566c564b3c509127087cb06bdefa3",
    "public/modules/settings/general.php": "f52a383e417af0b3c5916ea99121313e7c5d7b38e3ed6db51c5cf553d0383f27",
    "public/ui-kit/index.html": "e4309e177ecdfbe32d02fafa329bc381741b7a2be1ba506aa3b11a5f8f022c94",
    "tests/README.md": "235839721a224865c6f5b3c9bc666d9e3dd41bf7c72ba0dd9ab1c8226f17f83b",
    "tests/browser/check_pages_semantics.py": "151c5e2dc57364c5256c379a1c3cf3dffcf9be0d95ddf2397ec87b9ccc42ec9c",
    "tests/browser/run_ui_checks.py": "a30cede817801ab9179bfb54f1a80f1f51a1a7d97697853e3934de4d1253e64b",
    "tests/cms_inventory_test.php": "e1695a63273331e1f6ec23a9a9109911b3f1cdcbbec6d7f685ca6117f2173515",
    "tests/cms_removed_test.php": "0eb41a4622f66f34d4268224ab978628f6309b86644cb4e4e9e2732dbfc30c5b",
    "tests/cms_shell_test.php": "e4e4f234060ad68b3e5b13c7aca0b958439b6f2230f497aa796081872058dfdd",
    "tests/dashboard_data_test.php": "339762690945c720b7f91cedd047110f2c3434431cc749695873fe43e8e2701e",
    "tests/file_references_test.php": "12d6bcfdd1107f469a5037a49c71cf36a2d68eca9a952722a29b0fb209083b6f",
    "tests/file_rules_test.php": "5cf8264161ce70cf5023cb4c6fc5675abb09ea62cc13e461461302db3308c3d6",
    "tests/file_store_test.php": "2be9bc730d1e616dfa2fbeefbe7b2b42b8ddc3432391a7a917ad0e4598f7011c",
    "tests/manifest_tool_test.php": "504c19377ed468366ffbacc04330c20bd90cd15e760557043b44300610c911bf",
    "tests/ui/check_pages_covered.py": "e523e1ace15deaefc9852463ffa2d61b439090e240ee333988e951498ae087c3",
    "tests/ui/check_ui_gates.py": "383e7cfbb90144e8ab45b6da53707d9b33e466d2f16d8de123eb4e59c58d8d5c",
    "tools/gates/verify_manifest.php": "97f608ce0035a7425a5d9e44623bb5c8ec13bfec78e9faac7136bd30c20f277b"
  },
  "part": "11A0"
}
```

### CREATE `changelogs/changelog-part-11a0-product-reset-cms-decommission.md`

Content (copy exactly; it ends with a newline):

```markdown
# changelog — Part 11A0 (product reset and CMS decommission)

**Written from:** `master-prompt-11A0-product-reset-cms-decommission.md`. Author of the code: the prompt author (tested in a Linux sandbox, PHP 8.3, SQLite, no MySQL). The executing agent copied files, ran the checks and asked the owner for the database decision; it did no engineering.

## 1. What changed
- The product is now a generic admin application platform. The CMS (page builder, pages, articles, templates, licences, marketplace leftovers) was removed from the UI, navigation, access map, APIs, runtime code, tests, assets and documentation. The full record is `docs/decommission-cms.md`.
- `includes/nav.php` and `includes/access_map.php` now list only the five platform pages (dashboard, media, settings general/profile/staff).
- The dashboard was rewritten (system health, media count, active staff, recent activity). Two new providers live in `includes/dashboard_data.php`.
- `media_library` was decoupled from the builder: `includes/file_references.php` + table `file_references` (migration 0009) replace `builder_media_refs`; `public/api/media/delete.php` uses them.
- Private storage kinds are generic (`includes/file_store.php`, `includes/file_rules.php`); `public/file.php` and the ZIP/certificate helpers were removed.
- `database/tools/diagnose.php` lost the CMS-only checks and gained D27/D28; `backfill_files.php`, `gc_files.php`, README and UI guide/pages docs were cleaned.
- New: `database/lib/CmsInventory.php`, `database/tools/cms_inventory.php`, `database/tools/check_cms_removed.php`, `tools/gates/verify_manifest.php`, migrations 0009 and 0010, five new tests, the hand-off set `docs/ai/` and manifests.

## 2. Database
- Migration 0009 (additive) and 0010 (CMS removal, irreversible) are in `database/migrations/`. They are applied only after the owner's explicit approval and a backup (Step 9 of the prompt). The state is recorded in `docs/ai/state.json` (`database`: `applied` | `pending-owner-approval` | `not-reachable`).

## 3. Not verified by the author (be honest)
MySQL behaviour of migrations 0009/0010 (the author tested their control flow on SQLite only); rendering of dashboard, media and settings pages against a real database; Apache; PHP 8.0 runtime.

## Backup and baseline
(The executing agent writes the backup path and the pre-flight output here.)

## Deviations from the prompt
(The executing agent replaces this line with `none`, or with one bullet per deviation: what, why, which file.)

## Owner decisions recorded
(The executing agent writes here: the inventory verdict, what the owner answered, and whether 0009/0010 were applied.)

## Gate outputs (verbatim)
(The executing agent pastes below this line the real output of every command the prompt's Acceptance Checklist asks for, unedited.)
```

### STEP 8 — verification (run all, record real output)

Run `php -l` on every PHP file this part created or changed:

```text
config/config.php
database/lib/CmsInventory.php
database/migrations/0009_file_references_pre_code.php
database/migrations/0010_cms_decommission_post_code.php
database/tools/backfill_files.php
database/tools/check_cms_removed.php
database/tools/cms_inventory.php
database/tools/diagnose.php
database/tools/gc_files.php
includes/access_map.php
includes/dashboard_data.php
includes/file_references.php
includes/file_rules.php
includes/file_store.php
includes/layout/sidebar.php
includes/nav.php
public/api/media/delete.php
public/modules/dashboard/index.php
public/modules/media/index.php
public/modules/settings/general.php
tests/cms_inventory_test.php
tests/cms_removed_test.php
tests/cms_shell_test.php
tests/dashboard_data_test.php
tests/file_references_test.php
tests/file_rules_test.php
tests/file_store_test.php
tests/manifest_tool_test.php
tools/gates/verify_manifest.php
```

(no output other than `No syntax errors detected` for each). Then:

```text
php tools/gates/verify_manifest.php docs/ai/manifests/11A0.json
php tests/run_all.php
php database/tools/check_cms_removed.php
python tests/ui/check_ui_gates.py
python tests/ui/check_pages_covered.py
```

Expected:
- manifest: last line `manifest 11A0: 155 ok, 0 bad, 0 info`. A `MISMATCH` on a file you copied means a copy error: re-copy that file from this prompt (never edit the manifest). A `MISMATCH` on a file you skipped in Step 1 is expected: record it.
- tests: **13 lines**, every one `OK`, no `FAIL` (`cms_inventory`, `cms_removed`, `cms_shell`, `file_references`, `manifest_tool` are new; the four `builder_*`/`html_sanitizer` tests are gone). A `SKIP` caused by a missing PHP extension (for example `pdo_sqlite`, `gd`) is acceptable: write which one.
- `check_cms_removed.php`: last line `cms leftovers: 0`.
- `check_ui_gates.py`: last line `scanned … total findings 0`; `check_pages_covered.py`: last line `7 covered, 0 missing`. If Python is not installed → write "not run".
- Optional if Python + Playwright + Chrome are available (UI kit only, no database needed; the folder to serve is the project root): `python tests/browser/run_ui_checks.py --kit . --kit-path /ui-kit/index.html`. Expected: `0 failed`. If it cannot run → write "not run".

### STEP 9 — OWNER GATE: apply migrations 0009 and 0010 (database)

This is the only step that touches the database, and 0010 **cannot be undone**.

**9.1 Look (read-only).** Run `php database/tools/cms_inventory.php`.
- Exit code 2 / "Cannot connect to the database" → write `not-reachable` as the database state (Step 10), **skip 9.2–9.6**, and tell the owner in the final message that the migrations are waiting.
- Otherwise copy its whole output.

**9.2 Ask the owner (stop here until they answer).** Show the inventory output and ask, in Persian, exactly this:

```text
آیا از دیتابیس نسخه پشتیبان (Backup) گرفته‌ای و اجازه می‌دهی مایگریشن‌های 0009 و 0010 اجرا شوند؟
این کار جدول‌های CMS (صفحه‌ساز، قالب‌ها، مجوزها و …) و دو ستون تنظیمات سایت را برای همیشه حذف می‌کند و فقط با بازگرداندن Backup برمی‌گردد.
خروجی بالا نشان می‌دهد چه داده‌هایی وجود دارد. پاسخ بده: «بله، Backup دارم و اجازه می‌دهم» یا «نه».
```

Without the answer «بله، Backup دارم و اجازه می‌دهم» (or an unmistakable equivalent) → do **not** run anything else; the database state is `pending-owner-approval`; go to Step 10.

**9.3 Check the last line of 9.1.**
- `VERDICT: NO USER DATA …` → run `php database/migrate.php`.
- `VERDICT: NEEDS OWNER APPROVAL …` → the owner's «بله» in 9.2 was given after seeing these rows, so run it with the variable: Windows `cmd`: `set MIGRATE_DROP_CMS=1 && php database/migrate.php`; PowerShell: `$env:MIGRATE_DROP_CMS='1'; php database/migrate.php`; bash: `MIGRATE_DROP_CMS=1 php database/migrate.php`.

**9.4 If the run stops with an error** → copy the error into the changelog, do **not** retry with different commands, set the database state to `partial`-with-error in your report (the migration is re-runnable by the owner; every step checks "exists?" first).

**9.5 Check after success.**

```text
php database/migrate.php --status
php database/tools/cms_inventory.php
php database/tools/diagnose.php
```

Expected: `--status` shows `[applied] 0009_file_references_pre_code` and `[applied] 0010_cms_decommission_post_code`; the inventory prints `(no CMS table exists any more)` and `(none)` for tables; `diagnose.php` shows `D27` and `D28` as `PASS` (record every other line that is not PASS as information; this part does not fix them).

**9.6** Tell the owner that CMS files may still be on disk (the inventory lists them) and that **you did not delete them**.

### STEP 10 — finish the changelog and the state file

1. In `changelogs/changelog-part-11a0-product-reset-cms-decommission.md` fill the five placeholder sections: "Backup and baseline" (backup path, PHP version, baseline test output, Step 1 output), "Deviations from the prompt" (`none` or one bullet per deviation: what, why, which file), "Owner decisions recorded" (the 9.1 verdict, the owner's exact answer, and what ran), "Gate outputs (verbatim)" (the real output of Step 8 and, if run, Step 9.5).
2. Update `docs/ai/state.json` with ONE edit (the file starts with `"status": "partial"`, `"database": "pending-owner-approval"` for 11A0):

**FIND** (exactly once):

```json
      "status": "partial",
      "database": "pending-owner-approval",
```

Replace it with the block that is **true**:

- Everything done and migrations applied (9.5 expected results seen):

```json
      "status": "executed",
      "database": "applied",
```

- Everything done but the owner has not approved yet, or said no: make **no edit** (it already says `partial` / `pending-owner-approval`).
- The database was not reachable:

```json
      "status": "partial",
      "database": "not-reachable",
```

- Any step stopped for another reason: make no edit and say so in "Deviations".

## 8. Acceptance Checklist

- [ ] Pre-flight run; `manifest 11A0-baseline: 147 ok, 0 bad, 0 info` (or every MISMATCH listed in Deviations); backup path recorded.
- [ ] All paths of Step 3 are gone; no `builder`, `pages`, `articles`, `templates`, `licenses` page or API remains under `public/`.
- [ ] `includes/nav.php` has exactly the entries dashboard, media, profile, general, staff; `includes/access_map.php` has exactly five pages.
- [ ] `php tests/run_all.php`: 13 lines, all `OK` (SKIPs explained).
- [ ] `php database/tools/check_cms_removed.php`: `cms leftovers: 0`.
- [ ] `php tools/gates/verify_manifest.php docs/ai/manifests/11A0.json`: `155 ok, 0 bad, 0 info` (or every MISMATCH explained).
- [ ] `check_ui_gates.py`: 0 findings; `check_pages_covered.py`: 7 covered, 0 missing (or "not run" + why).
- [ ] `composer.json`, `composer.lock`, `database/schema.sql` unchanged.
- [ ] Step 9 done in the correct way: either the owner approved and 9.5 shows PASS for D27/D28 **or** the database state in `state.json` honestly says `pending-owner-approval` / `not-reachable`.
- [ ] No CMS file on disk (storage/uploads) was deleted by you.
- [ ] Changelog complete (backup, baseline, Deviations, owner decisions, verbatim outputs); `state.json` is true.
- [ ] No file outside the tree in section 2 was modified.

## 9. Final message to the owner (Persian)

Write a short Persian report: what was done (by step), the real outputs (manifest lines, number of test lines, CMS gate line), every deviation, the database state (`applied` / `pending-owner-approval` / `not-reachable`), and what you could **not** run. Then give these **manual checks for the owner** (you cannot do them):

1. وارد پنل شو: پیشخوان باید «وضعیت سیستم»، «فایل‌های رسانه» و (برای مالک) «همکاران فعال» و «فعالیت‌های اخیر» را نشان بدهد.
2. منوی کناری فقط این‌ها را دارد: پیشخوان، رسانه و عکس‌ها، پروفایل، تنظیمات عمومی، همکاران.
3. آدرس `/modules/pages/index.php` باید باز نشود (خطای 404).
4. در «رسانه و عکس‌ها» یک تصویر آپلود و سپس حذف کن؛ هر دو باید کار کنند.
5. در «تنظیمات عمومی» نام برنامه را عوض و ذخیره کن؛ فیلد دامنه دیگر وجود ندارد.
6. در «همکاران» پنجره دسترسی فقط سه ماژول (پیشخوان، رسانه، تنظیمات) را نشان بدهد (بعد از اجرای مایگریشن 0010).
7. پوشه‌های `storage/private/licenses`، `storage/private/templates` و `public/uploads/templates` را خودت بررسی و در صورت نیاز آرشیو/حذف کن؛ این prompt چیزی از آن‌ها حذف نکرد.
