# MASTER PROMPT 11A — Baseline safety, PHP 8.0 gate and AI tooling

> **Series:** Part 11 (modular admin application platform) · **Revision 2** (rewritten after the product reset; it now runs **after 11A0**) · **File:** `master-prompt-11A-baseline-safety.md` · **Language of this file:** English (final message to the owner: Persian).

| Field | Value |
|---|---|
| **Status** | `ready` (authored and tested by the author; not yet executed) |
| **Outcome** | The three baseline security holes of the panel are closed (GET logout, API endpoints without a method guard, a GET+POST mixed endpoint), and the process tooling that every later part uses exists (PHP 8.0 static gate, state-file tool, Persian-letters test). |
| **Contract (true after this part)** | 1. `POST /logout.php` with a valid CSRF token logs out; `GET /logout.php` never logs out (303 redirect). 2. Every PHP file under `public/api/` contains a method guard (`tests/api_method_guards_test.php`). 3. `public/api/media/upload.php` accepts only POST; `public/api/media/info.php` (GET, `media` view) returns the file info. 4. `php tools/gates/php80_gate.php` prints `violations: 0`. 5. `php tests/run_all.php` prints 17 lines (13 + 4), all `OK`. |
| **In scope** | Logout; method guards; `info.php`; `tools/gates/php80_gate.php`; `tools/ai/mark_part.php`; four tests; `.gitignore`; deletion of two tracked `__pycache__` folders; extension of the hand-off docs; manifest `11A.json`. |
| **Out of scope** | HTTP kernel and single entry point (11B), modules (11C), removal of the seeded default password (11D), CSP and inline-script removal (11E1), a real PHP 8.0 run (11H), any database change. |
| **Requires** | **11A0 executed** (the CMS is gone; `docs/ai/` and `tools/gates/verify_manifest.php` exist). It does **not** need the 11A0 database step to be applied: this part never touches the database. |
| **Unblocks** | 11B (it relies on `tools/ai/mark_part.php`, the PHP 8.0 gate and the method-guard test). |
| **Runtime proof** | `php tests/run_all.php`, `php tools/gates/php80_gate.php`, `php tools/gates/verify_manifest.php docs/ai/manifests/11A.json`; the three manual logout checks (section 9). |
| **Tested by the author** | Linux sandbox, PHP 8.3, SQLite. Replayed literally on a clean copy of the post-11A0 tree: 17/17 tests green, gate 0 violations, manifest 15 ok. Over HTTP (PHP built-in server): GET `/logout.php` and POST without a token both answer 303 and do not log out; GET on `mark_read.php` and `invite.php` answer 405 with `Allow: POST`; POST on `info.php` answers 405 with `Allow: GET`. |
| **NOT tested by the author** | MySQL behaviour of `info.php` and `upload.php`; the logged-in path of logout (it writes `activity_logs`); the look of the two logout buttons in a browser; PHP 8.0 runtime (the gate is a static scan); Apache; Windows paths. |

## 0. READ THIS FIRST — you are an OPERATOR, not an engineer

The prompt author (Claude) has made every engineering decision, written all code and tested it (the banner says what was and what was not tested). Your job is to **copy, replace, delete, run commands and report**. Nothing else.

1. **Do only what is written.** Where this prompt says "create", "replace", "delete" or "run", do exactly that. Do **not** design, refactor, rename, reformat, add comments, add error handling, "improve", "modernise", reorder, merge or split anything. A bug you believe you see in this prompt is **reported**, not fixed (rule 3).
2. **Copy byte for byte.** Content in code fences is the exact file content: UTF-8 without BOM, LF line endings, one final newline, no trailing spaces added or removed. The fence markers are not part of the file. Persian text is exact (Persian letters ی and ک only).
3. **Reconcile, do not improvise.** The prompt was written against a known repository state. Your copy may differ.
   - Before the first change you run the **pre-flight** (Step 1) and write the *real* baseline into the changelog.
   - If a step cannot be done exactly as written (a FIND text occurs 0 or 2+ times, a file that must exist is missing, a file that must be identical is not, a command fails, a check prints something else) → **stop that step and every step that depends on it**. Steps that do not depend on it continue; when in doubt, stop. Write what you saw (file, step, output) under "Deviations" in the changelog.
   - You *report* conflicts between this prompt and the repository. You never *resolve* them by writing your own code, by editing the prompt's content, or by guessing.
4. **Owner gates.** A step marked **OWNER GATE** can destroy data. You stop there, show the owner the facts, ask the question given in the step, and continue only on an explicit yes. You never approve for the owner, never set an approval variable yourself, and never run a database command outside an OWNER GATE step. This part has no database step at all. Work done before a gate stays done; without approval the part ends as `partial`.
5. **Touch only the files this prompt names.** Do not open other files of the owner for editing. Do not run `git` commands that change anything (the folder may not even be a git repository), `composer require/update/install`, or `npm`.
6. **Documentation duty.** The owner works with several AI agents one after another; the next one must see exactly what you did. The final steps (changelog, `docs/ai/state.json`, honest "Deviations" and "Gate outputs") are **mandatory**.
7. **Be honest.** Paste real command output. If a check was not run or could not run, write "not run" and why. Never write "passed" for something you did not run. Status words used in this series: `authored` (prompt written) → `reviewed` → `ready` (ready to execute) → `executed` (steps done) → `verified` (checks passed on the real stack) → `released`; plus `partial` (stopped or waiting for the owner) and `blocked`. A prompt file you receive is `ready`; after you finish it is at best `executed`. Only the owner moves a part to `verified`/`released`.
8. **Language.** Your final message to the owner is in **Persian**. File contents stay exactly as given here.
9. **Use your task list.** One task per numbered step below; mark it done only after its check passed.

## 1. Goal and scope

Part 11A does three things:
1. **Closes three baseline security items:** logout becomes POST + CSRF; two API endpoints get a method guard; the media upload endpoint stops mixing GET and POST.
2. **Adds the tooling** the series depends on: a PHP 8.0 static gate, a state-file tool with the status vocabulary, and a test that forbids Arabic look-alike letters.
3. **Extends the AI hand-off set** created in 11A0 (`docs/ai/`) and adds the manifest `11A.json`.

**Changes the author made against the earlier version of this prompt and the brief (stated openly)**
- This prompt now runs **after 11A0**. The old version edited `public/api/templates/activate.php` (a CMS file): that edit is gone because the file is gone. `tools/gates/verify_manifest.php` and the `docs/ai/` set now come from 11A0, so this part *extends* them instead of creating them.
- The brief criticised "default credential `Admin123!` in `schema.sql`". It is **not fixed here**, deliberately: the baseline file is frozen, and the right fix is the installer in 11D (first owner with a generated password) plus the release gate in 11H. It is tracked as blocker **RB-1** in `docs/ai/RELEASE-BLOCKERS.md` (created in 11A0). Today it is mitigated (migration 0002 forces a password change; `config/config.php` refuses the seed hash).
- The brief criticised that the PHP 8.0 gate is only static. It stays static (the author's sandbox has only a newer PHP), and this is now written as decision D-015 and blocker **RB-2** instead of being hidden. The real PHP 8.0 run is a required release gate in 11H.
- `tools/ai/mark_part.php` now knows the new status vocabulary (`authored, reviewed, ready, executed, verified, released, partial, blocked`). The executor can write only `executed`, `partial`, `blocked`; it keeps the other keys of a part's entry (for example `database`).
- Mutable hand-off files are edited with FIND/REPLACE (they exist since 11A0); they are not in any manifest.

## 2. File tree (only what this part touches)

```text
.
├── changelogs/
│   └── changelog-part-11a-baseline-safety.md   [NEW]
├── docs/
│   └── ai/
│       ├── manifests/
│       │   └── 11A.json   [NEW]
│       ├── DECISIONS.md   [CHANGED]
│       ├── FILE-MAP.md   [CHANGED]
│       ├── HANDOFF.md   [CHANGED]
│       └── state.json   [CHANGED (by the tool)]
├── includes/
│   └── layout/
│       ├── sidebar.php   [CHANGED]
│       └── topbar.php   [CHANGED]
├── public/
│   ├── api/
│   │   ├── media/
│   │   │   ├── info.php   [NEW]
│   │   │   └── upload.php   [CHANGED]
│   │   ├── notifications/
│   │   │   └── mark_read.php   [CHANGED]
│   │   └── staff/
│   │       └── invite.php   [CHANGED]
│   └── logout.php   [CHANGED]
├── tests/
│   ├── browser/
│   │   └── __pycache__   [DELETED (folder)]
│   ├── ai_tools_test.php   [NEW]
│   ├── api_method_guards_test.php   [NEW]
│   ├── persian_letters_test.php   [NEW]
│   └── php80_gate_test.php   [NEW]
├── tools/
│   ├── ai/
│   │   └── mark_part.php   [NEW]
│   ├── gates/
│   │   └── php80_gate.php   [NEW]
│   └── ui/
│       └── __pycache__   [DELETED (folder)]
└── .gitignore   [CHANGED]
```

`[NEW]` created · `[CHANGED]` existing file edited · `[DELETED]` removed. Nothing else changes.

## 3. Database design (PK/FK) — **no schema change**

No table, column, index, PK or FK is created, altered or dropped in this part and there is no migration; do not open `database/`. Reason: every change is request handling, tooling or documentation. The keys that matter here stay as they are: `media_library.id` (PK, read by `info.php`) and `panel_users.id`, referenced by `activity_logs`, which `logActivity()` writes on logout. The state of the series lives in files (`docs/ai/state.json`, manifests) so that it exists before the database part and cannot be lost by a database restore.

## 4. Library decision

**No library, no Composer change.** `composer.json` and `composer.lock` stay untouched. All tools are plain PHP 8.0 using core functions only (`token_get_all`, `hash`, `json_*`). Reason: they must run on the owner's XAMPP with no installation step; a static gate is about a hundred lines and a package would add risk for no benefit.

## 5. "Must NOT do" table

| Do not | Why |
|---|---|
| Edit, "fix" or reformat any file not named here | other files belong to verified parts; the file map relies on this |
| Change any CSS | `.nav-link-item` already resets button styling and Bootstrap's `.dropdown-item` supports `<button>`; the logout buttons need no CSS |
| Keep a GET path that logs out "for convenience" | that is the vulnerability; GET must only redirect |
| Add a CSRF check to `info.php` | it is a read-only GET; CSRF protects state changes |
| Add or change method guards on other APIs | every other API already has one (the new test proves it) |
| Delete the POST code of `upload.php` | only the `GET ?id=` branch is removed |
| Fix a PHP 8.1+ construct that the gate reports in an existing owner file | report it under Deviations (file, line, message) instead |
| Touch `database/`, `composer.*`, `vendor/`, `tests/run_all.php` | no such step exists; `run_all.php` finds tests by itself |
| Run `git`, `composer`, `npm` or any database command | not needed; the folder may not be a repository |
| Write `verified` or `released` into `state.json` | only the owner may, after checks on the real stack |

## 6. Prerequisites (check first)

Work from the **project root**.

1. These must **exist**: `composer.json`, `includes/auth.php`, `includes/csrf.php`, `includes/layout/sidebar.php`, `includes/layout/topbar.php`, `public/login.php`, `public/logout.php`, `public/api/media/upload.php`, `public/api/notifications/mark_read.php`, `public/api/staff/invite.php`, `tests/run_all.php`, and (from 11A0) `tools/gates/verify_manifest.php`, `docs/ai/state.json`, `docs/ai/manifests/11A0.json`, `changelogs/changelog-part-11a0-product-reset-cms-decommission.md`. If a 11A0 file is missing → **stop**: 11A0 must be executed first.
2. These must **not exist** (otherwise this part was applied before → stop and ask the owner): `tools/gates/php80_gate.php`, `tools/ai/`, `public/api/media/info.php`, `docs/ai/manifests/11A.json`.
3. `php -v` → PHP 8.0 or newer. Record the version.
4. Read `docs/ai/state.json`: the entry `11A0` must exist with status `executed` or `partial`. If its `database` value is `pending-owner-approval` or `not-reachable`, continue anyway (this part does not use the database) and write the value in the changelog.

## 7. Steps

Do them in this order.

### STEP 1 — pre-flight (read-only)

```text
php tools/gates/verify_manifest.php docs/ai/manifests/11A0.json
php tests/run_all.php
```

Expected: `manifest 11A0: 155 ok, 0 bad, 0 info` (a `MISMATCH` on a file this part edits is a hint that the owner changed it after 11A0: continue, the FIND texts in Step 4 are the real safety net); `php tests/run_all.php` prints 13 lines, all `OK`. Record the real outputs (they are the baseline). A red baseline does not stop this part but must be written in the changelog.

### STEP 2 — backup

Copy the whole project folder to `../panel-backups/pre-11A/` (create the parent if needed), excluding `vendor/`, `node_modules/` and `.git/`, **including** `storage/` and `public/uploads/`. Example (Windows): `robocopy . ..\panel-backups\pre-11A /E /XD vendor node_modules .git` (exit codes below 8 are success). If the target exists use `pre-11A-2`, and so on. If you cannot copy → stop and tell the owner. Write the path in the changelog.

### STEP 3 — create the two tools

Check: `php -l` on each prints `No syntax errors detected`.

### CREATE `tools/gates/php80_gate.php`

Content (copy exactly; it ends with a newline):

```php
<?php
// tools/gates/php80_gate.php — Part 11A
// Static gate: finds PHP 8.1+ syntax / functions in project code. The project must run on PHP 8.0 (composer.json: php >=8.0).
//
//   php tools/gates/php80_gate.php            # scans the default folders
//   php tools/gates/php80_gate.php app tools  # scans the given folders/files (relative to the project root)
//
// It is a token scan, NOT a PHP 8.0 runtime. It catches: enum, readonly, `never`, first-class callable syntax `f(...)`,
// `0o` octal literals, `final const`, typed class constants (8.3), `new Fiber`, and calls to functions added after 8.0.
// It does NOT catch: `new` in initializers, pure intersection types, DNF types. Those are covered by code review.
// Exit code 0 = no violations, 1 = violations found.

/** Functions that do not exist in PHP 8.0 (added in 8.1–8.4). */
function php80GateForbiddenFunctions(): array {
    return [
        'array_is_list', 'enum_exists', 'fsync', 'fdatasync',
        'ini_parse_quantity', 'memory_reset_peak_usage', 'mysqli_execute_query', 'curl_upkeep',
        'json_validate', 'mb_str_pad', 'str_increment', 'str_decrement',
        'array_find', 'array_find_key', 'array_any', 'array_all',
        'mb_trim', 'mb_ltrim', 'mb_rtrim', 'mb_ucfirst', 'mb_lcfirst',
    ];
}

/** @return string[] human readable violations "line N: message" */
function php80GateScan(string $code): array {
    $tokens = token_get_all($code);
    // Keep only significant tokens, remembering the line of each.
    $sig = [];
    $line = 1;
    foreach ($tokens as $t) {
        if (is_array($t)) {
            $line = $t[2];
            if (in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                $line += substr_count($t[1], "\n");
                continue;
            }
            $sig[] = ['id' => $t[0], 'text' => $t[1], 'line' => $t[2]];
            $line += substr_count($t[1], "\n");
        } else {
            $sig[] = ['id' => null, 'text' => $t, 'line' => $line];
        }
    }

    $v = [];
    $forbidden = array_flip(php80GateForbiddenFunctions());
    $n = count($sig);
    for ($i = 0; $i < $n; $i++) {
        $t = $sig[$i];
        $prev = $i > 0 ? $sig[$i - 1] : ['id' => null, 'text' => '', 'line' => 0];
        $next = $i + 1 < $n ? $sig[$i + 1] : ['id' => null, 'text' => '', 'line' => 0];

        if (defined('T_ENUM') && $t['id'] === T_ENUM) {
            $v[] = 'line ' . $t['line'] . ': enum (PHP 8.1)';
        }
        if (defined('T_READONLY') && $t['id'] === T_READONLY) {
            $v[] = 'line ' . $t['line'] . ': readonly (PHP 8.1)';
        }
        if ($t['id'] === T_STRING && strtolower($t['text']) === 'never' && $prev['text'] === ':') {
            $v[] = 'line ' . $t['line'] . ': "never" return type (PHP 8.1)';
        }
        if ($t['id'] === T_ELLIPSIS && $prev['text'] === '(' && $next['text'] === ')') {
            $v[] = 'line ' . $t['line'] . ': first-class callable syntax f(...) (PHP 8.1)';
        }
        if ($t['id'] === T_LNUMBER && preg_match('/^0[oO][0-7_]+$/', $t['text'])) {
            $v[] = 'line ' . $t['line'] . ': explicit octal literal 0o (PHP 8.1)';
        }
        if ($t['id'] === T_FINAL && $next['id'] === T_CONST) {
            $v[] = 'line ' . $t['line'] . ': final class constant (PHP 8.1)';
        }
        if ($t['id'] === T_CONST) {
            // const NAME = ...  (name only)  vs  const TYPE NAME = ...  (typed constant, PHP 8.3)
            $j = $i + 1;
            $names = 0;
            while ($j < $n && $sig[$j]['text'] !== '=' && $sig[$j]['text'] !== ';') {
                if (in_array($sig[$j]['id'], [T_STRING, T_ARRAY, T_STATIC, T_NS_SEPARATOR, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                    $names++;
                }
                $j++;
            }
            if ($names > 1) {
                $v[] = 'line ' . $t['line'] . ': typed class constant (PHP 8.3)';
            }
        }
        if ($t['id'] === T_STRING && strtolower($t['text']) === 'fiber' && $prev['id'] === T_NEW) {
            $v[] = 'line ' . $t['line'] . ': Fiber (PHP 8.1)';
        }
        if ($t['id'] === T_STRING && $next['text'] === '(' && isset($forbidden[strtolower($t['text'])])
            && !in_array($prev['id'], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW], true)) {
            $v[] = 'line ' . $t['line'] . ': function ' . $t['text'] . '() does not exist in PHP 8.0';
        }
    }
    return $v;
}

/** @return string[] absolute paths of *.php files under the given paths (skips vendor, storage, uploads, node_modules, .git) */
function php80GateCollect(string $root, array $paths): array {
    $skip = ['vendor', 'storage', 'uploads', 'node_modules', '.git', '__pycache__'];
    $out = [];
    foreach ($paths as $p) {
        $abs = rtrim($root, '/\\') . '/' . ltrim($p, '/\\');
        if (is_file($abs)) {
            $out[] = $abs;
            continue;
        }
        if (!is_dir($abs)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS),
            function ($cur) use ($skip) {
                return !($cur->isDir() && in_array($cur->getFilename(), $skip, true));
            }
        ));
        foreach ($it as $f) {
            if ($f->isFile() && strtolower($f->getExtension()) === 'php') {
                $out[] = $f->getPathname();
            }
        }
    }
    sort($out, SORT_STRING);
    return array_values(array_unique($out));
}

if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    $root = dirname(__DIR__, 2);
    $paths = array_slice($argv, 1);
    if (!$paths) {
        $paths = ['app', 'bin', 'config', 'database', 'includes', 'modules', 'public', 'routes', 'tests', 'tools'];
    }
    $files = php80GateCollect($root, $paths);
    $total = 0;
    foreach ($files as $file) {
        $found = php80GateScan((string) file_get_contents($file));
        foreach ($found as $msg) {
            $total++;
            echo substr($file, strlen($root) + 1), ': ', $msg, "\n";
        }
    }
    echo 'php80 gate: scanned ', count($files), ' file(s), violations: ', $total, "\n";
    exit($total > 0 ? 1 : 0);
}
```

### CREATE `tools/ai/mark_part.php`

Content (copy exactly; it ends with a newline):

```php
<?php
// tools/ai/mark_part.php — Part 11A (revision 2)
// Records the status of one master-prompt part in docs/ai/state.json (the machine-readable hand-off for the next AI).
//
//   php tools/ai/mark_part.php 11A executed "short factual note"
//
// The executing agent may write only: executed, partial, blocked. The other words of the status vocabulary
// (authored, reviewed, ready, verified, released) are for the prompt author and the owner. The existing keys of a part
// (for example "database", "changelog", "manifest") are kept; only status, updated_utc and note are written.
// The timestamp is UTC. Parts are kept sorted so diffs stay small.

/** The full status vocabulary of the series. */
function markPartVocabulary(): array {
    return ['authored', 'reviewed', 'ready', 'executed', 'verified', 'released', 'partial', 'blocked'];
}

/** Statuses an executing agent may write. */
function markPartExecutorStatuses(): array {
    return ['executed', 'partial', 'blocked'];
}

/** Returns the updated state array. Throws InvalidArgumentException on bad input. */
function markPartApply(array $state, string $part, string $status, string $note, string $nowUtc, bool $executorOnly = false): array {
    if (!preg_match('/^11[A-Z][0-9]?$/D', $part)) {
        throw new InvalidArgumentException('part must look like 11A, 11A0 or 11E3');
    }
    $allowed = $executorOnly ? markPartExecutorStatuses() : markPartVocabulary();
    if (!in_array($status, $allowed, true)) {
        throw new InvalidArgumentException('status must be one of: ' . implode(', ', $allowed));
    }
    if (!isset($state['parts']) || !is_array($state['parts'])) {
        $state['parts'] = [];
    }
    $entry = isset($state['parts'][$part]) && is_array($state['parts'][$part]) ? $state['parts'][$part] : [];
    $entry['status'] = $status;
    $entry['updated_utc'] = $nowUtc;
    $entry['note'] = $note;
    $state['parts'][$part] = $entry;
    ksort($state['parts'], SORT_STRING);
    return $state;
}

if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    $file = dirname(__DIR__, 2) . '/docs/ai/state.json';
    if (!is_file($file)) {
        fwrite(STDERR, "docs/ai/state.json not found\n");
        exit(2);
    }
    $state = json_decode((string) file_get_contents($file), true);
    if (!is_array($state)) {
        fwrite(STDERR, "docs/ai/state.json is not valid JSON\n");
        exit(2);
    }
    try {
        $state = markPartApply($state, (string) ($argv[1] ?? ''), (string) ($argv[2] ?? ''), (string) ($argv[3] ?? ''), gmdate('Y-m-d\TH:i:s\Z'), true);
    } catch (InvalidArgumentException $e) {
        fwrite(STDERR, $e->getMessage() . "\nusage: php tools/ai/mark_part.php <11A> <executed|partial|blocked> \"note\"\n");
        exit(2);
    }
    file_put_contents($file, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
    echo 'state.json: ', $argv[1], ' = ', $argv[2], "\n";
}
```

### STEP 4 — security edits to existing files

Apply each edit. Every FIND text must occur **exactly once** (0 or 2+ → stop that edit, rule 3).

### EDIT `public/logout.php` — E1

Logout becomes POST + CSRF (the whole old file is replaced).

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```php
<?php
require_once __DIR__ . '/../includes/auth.php';
logActivity('logout', 'auth', 'خروج از پنل');
logoutUser();
header('Location: /login.php'); exit;
```

**REPLACE IT WITH:**

```php
<?php
// Part 11A: logout is a state-changing action, so it is POST + CSRF only (a GET link or an <img> must never log anyone out).
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    // Old bookmarks and prefetchers land here: send them somewhere harmless, do not log out.
    header('Allow: POST');
    header('Location: ' . (isLoggedIn() ? '/modules/dashboard/index.php' : '/login.php'), true, 303);
    exit;
}
if (!isLoggedIn()) {
    header('Location: /login.php', true, 303);
    exit;
}
if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('توکن امنیتی نامعتبر است.');
}
logActivity('logout', 'auth', 'خروج از پنل');
logoutUser();
header('Location: /login.php', true, 303);
exit;
```

### EDIT `includes/layout/sidebar.php` — E2

Logout link becomes a POST form with CSRF.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```php
    <a class="nav-link-item nav-link-item--danger" href="/logout.php" data-tip="خروج از حساب"><i class="ti ti-logout" aria-hidden="true"></i><span class="nav-label">خروج از حساب</span></a>
  </div>
```

**REPLACE IT WITH:**

```php
    <form method="post" action="/logout.php" class="app-sidebar__logout">
      <?= csrf_field() ?>
      <button type="submit" class="nav-link-item nav-link-item--danger" data-tip="خروج از حساب"><i class="ti ti-logout" aria-hidden="true"></i><span class="nav-label">خروج از حساب</span></button>
    </form>
  </div>
```

### EDIT `includes/layout/topbar.php` — E3

User-menu logout becomes a POST form with CSRF.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```php
        <li><a class="dropdown-item" href="/logout.php"><i class="ti ti-logout" aria-hidden="true"></i>خروج</a></li>
      </ul>
```

**REPLACE IT WITH:**

```php
        <li><form method="post" action="/logout.php"><?= csrf_field() ?><button type="submit" class="dropdown-item"><i class="ti ti-logout" aria-hidden="true"></i>خروج</button></form></li>
      </ul>
```

### EDIT `public/api/notifications/mark_read.php` — E4

POST-only guard (inserted before requireLogin).

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```php
requireLogin();
if(!csrf_verify($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')){ jsonResponse(['success'=>false,'message'=>'توکن نامعتبر'],403); }
```

**REPLACE IT WITH:**

```php
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){ header('Allow: POST'); jsonResponse(['success'=>false,'message'=>'متد مجاز نیست'],405); }
requireLogin();
if(!csrf_verify($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')){ jsonResponse(['success'=>false,'message'=>'توکن نامعتبر'],403); }
```

### EDIT `public/api/staff/invite.php` — E5

POST-only guard (inserted before requireLogin).

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```php
requireLogin(); if(!isOwner()) jsonResponse(['success'=>false,'message'=>'فقط مالک'],403);
```

**REPLACE IT WITH:**

```php
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){ header('Allow: POST'); jsonResponse(['success'=>false,'message'=>'متد مجاز نیست'],405); }
requireLogin(); if(!isOwner()) jsonResponse(['success'=>false,'message'=>'فقط مالک'],403);
```

### EDIT `public/api/media/upload.php` — E6

The GET ?id= branch moves to info.php; the endpoint becomes POST-only.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```php
requireLogin(); requirePermission('media','edit');
// GET ?id=… : info about one library file
if($_SERVER['REQUEST_METHOD']==='GET' && isset($_GET['id'])){
  $pdo=getPDO();
  $stmt=$pdo->prepare("SELECT id, file_name, file_path, file_type, mime_type, file_size_kb, width, height, created_at FROM media_library WHERE id=? AND purpose='library'"); $stmt->execute([(int)$_GET['id']]);
  $row=$stmt->fetch(); if(!$row) jsonResponse(['success'=>false,'message'=>'یافت نشد'],404);
  jsonResponse(['success'=>true,'data'=>$row]);
}
if($_SERVER['REQUEST_METHOD']!=='POST') jsonResponse(['success'=>false,'message'=>'متد مجاز نیست'],405);
if(!csrf_verify($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')){ jsonResponse(['success'=>false,'message'=>'توکن نامعتبر'],403); }
```

**REPLACE IT WITH:**

```php
requireLogin(); requirePermission('media','edit');
// Part 11A: POST only. Read-only file info lives in /api/media/info.php.
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){ header('Allow: POST'); jsonResponse(['success'=>false,'message'=>'متد مجاز نیست'],405); }
if(!csrf_verify($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')){ jsonResponse(['success'=>false,'message'=>'توکن نامعتبر'],403); }
```

### EDIT `.gitignore` — E7

Ignore Python bytecode (two tracked __pycache__ folders are deleted in this part).

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```
../panel-backups/
```

**REPLACE IT WITH:**

```
../panel-backups/
__pycache__/
*.pyc
```

Then run `php -l` on: `public/logout.php`, `includes/layout/sidebar.php`, `includes/layout/topbar.php`, `public/api/notifications/mark_read.php`, `public/api/staff/invite.php`, `public/api/media/upload.php`. Each must print `No syntax errors detected`.

### STEP 5 — create the media info endpoint

### CREATE `public/api/media/info.php`

Content (copy exactly; it ends with a newline):

```php
<?php
// Part 11A: read-only info about one library file. Split out of upload.php so that a GET endpoint never shares a file with a POST endpoint.
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';
require_once __DIR__ . '/../../../includes/functions.php';
if(($_SERVER['REQUEST_METHOD']??'')!=='GET'){ header('Allow: GET'); jsonResponse(['success'=>false,'message'=>'متد مجاز نیست'],405); }
requireLogin(); requirePermission('media','view');
$id=(int)($_GET['id']??0);
if($id<1) jsonResponse(['success'=>false,'message'=>'شناسه نامعتبر'],400);
$pdo=getPDO();
$stmt=$pdo->prepare("SELECT id, file_name, file_path, file_type, mime_type, file_size_kb, width, height, created_at FROM media_library WHERE id=? AND purpose='library'"); $stmt->execute([$id]);
$row=$stmt->fetch(); if(!$row) jsonResponse(['success'=>false,'message'=>'یافت نشد'],404);
jsonResponse(['success'=>true,'data'=>$row]);
```

### STEP 6 — delete bytecode folders

Delete these two folders with all their content: `tests/browser/__pycache__/` and `tools/ui/__pycache__/`. A folder that does not exist is skipped (write it in the changelog). Delete nothing else there.

### STEP 7 — create the four tests

### CREATE `tests/api_method_guards_test.php`

Content (copy exactly; it ends with a newline):

```php
<?php
// Part 11A — static checks: every API endpoint declares its HTTP method; logout is POST+CSRF; no GET logout links remain;
// no CMS endpoint folder is back.
$root = dirname(__DIR__);
$fail = 0;
function check(string $label, $actual, $expected): void {
    global $fail;
    if ($actual !== $expected) { $fail++; fwrite(STDERR, "FAIL: $label\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n"); }
}
function read(string $root, string $rel): string { return (string) @file_get_contents($root . '/' . $rel); }

// 1) every file under public/api/ mentions REQUEST_METHOD (a method guard)
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/public/api', FilesystemIterator::SKIP_DOTS));
$n = 0;
foreach ($it as $f) {
    if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') { continue; }
    $n++;
    $rel = substr($f->getPathname(), strlen($root) + 1);
    check("method guard in $rel", strpos((string) file_get_contents($f->getPathname()), 'REQUEST_METHOD') !== false, true);
}
check('api file count is plausible (>= 9 after the CMS removal)', $n >= 9, true);
foreach (['builder', 'templates', 'licenses'] as $gone) {
    check("public/api/$gone stays removed", is_dir($root . '/public/api/' . $gone), false);
}

// 2) media: upload is POST-only, info is GET-only
$upload = read($root, 'public/api/media/upload.php');
check('upload.php has no $_GET', strpos($upload, '$_GET') === false, true);
check('upload.php requires POST', strpos($upload, "!=='POST'") !== false, true);
$info = read($root, 'public/api/media/info.php');
check('info.php exists', $info !== '', true);
check('info.php requires GET', strpos($info, "!=='GET'") !== false, true);
check('info.php checks media.view', strpos($info, "requirePermission('media','view')") !== false, true);

// 3) logout
$logout = read($root, 'public/logout.php');
check('logout.php requires POST', strpos($logout, "!== 'POST'") !== false, true);
check('logout.php verifies CSRF', strpos($logout, 'csrf_verify(') !== false, true);
foreach (['includes/layout/sidebar.php', 'includes/layout/topbar.php'] as $rel) {
    $src = read($root, $rel);
    check("$rel has no GET logout link", preg_match('/href\s*=\s*"\/logout\.php"/', $src), 0);
    check("$rel has a POST logout form", (bool) preg_match('/<form method="post" action="\/logout\.php"/', $src), true);
    check("$rel form carries csrf_field()", strpos($src, 'csrf_field()') !== false, true);
}
if ($fail > 0) { fwrite(STDERR, "$fail failure(s)\n"); exit(1); }
echo "api_method_guards_test: OK ($n api files)\n";
```

### CREATE `tests/php80_gate_test.php`

Content (copy exactly; it ends with a newline):

```php
<?php
// Part 11A — unit test of tools/gates/php80_gate.php, plus the gate run over the real project.
require __DIR__ . '/../tools/gates/php80_gate.php';
$fail = 0;
function check(string $label, $actual, $expected): void {
    global $fail;
    if ($actual !== $expected) { $fail++; fwrite(STDERR, "FAIL: $label\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n"); }
}
$bad = [
    'enum' => 'enum A { case X; }',
    'readonly' => 'class A { public readonly int $x; }',
    'never' => 'function f(): never { exit; }',
    'first-class callable' => '$f = strlen(...);',
    'octal' => '$x = 0o17;',
    'final const' => 'class A { final const X = 1; }',
    'typed const' => 'class A { const string X = "a"; }',
    'fiber' => '$f = new Fiber(fn() => 1);',
    'array_is_list' => '$x = array_is_list([1]);',
    'json_validate' => '$x = json_validate("{}");',
];
foreach ($bad as $label => $code) {
    check("flags $label", count(php80GateScan("<?php\n$code\n")), 1);
}
$good = 'class A { const X = 1; const Y = [1]; public function array_is_list() {} }'
    . ' $o->array_is_list(); A::array_is_list(); $a = [...$b]; f(...$args); const Z = 2; $s = "enum readonly never";'
    . ' // enum readonly' . "\n" . 'function g(): int|null { return match(true) { default => 1 }; }';
check('clean 8.0 code passes', php80GateScan("<?php\n$good\n"), []);
check('line numbers are reported', php80GateScan("<?php\n\n\n\$x = array_is_list([]);\n"), ['line 4: function array_is_list() does not exist in PHP 8.0']);

// The real project must be clean.
$root = dirname(__DIR__);
$files = php80GateCollect($root, ['app', 'bin', 'config', 'database', 'includes', 'modules', 'public', 'routes', 'tests', 'tools']);
$total = 0;
foreach ($files as $file) {
    foreach (php80GateScan((string) file_get_contents($file)) as $msg) {
        $total++;
        fwrite(STDERR, substr($file, strlen($root) + 1) . ': ' . $msg . "\n");
    }
}
check('project has no PHP 8.1+ syntax', $total, 0);
if ($fail > 0) { fwrite(STDERR, "$fail failure(s)\n"); exit(1); }
echo 'php80_gate_test: OK (' . count($files) . " project files scanned)\n";
```

### CREATE `tests/ai_tools_test.php`

Content (copy exactly; it ends with a newline):

```php
<?php
// Part 11A — tests for tools/gates/verify_manifest.php and tools/ai/mark_part.php (pure functions, temp files only).
require __DIR__ . '/../tools/gates/verify_manifest.php';
require __DIR__ . '/../tools/ai/mark_part.php';
$fail = 0;
function check(string $label, $actual, $expected): void {
    global $fail;
    if ($actual !== $expected) { $fail++; fwrite(STDERR, "FAIL: $label\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n"); }
}
check('hash ignores CRLF', manifestHash("a\r\nb\r\n"), manifestHash("a\nb\n"));
check('hash sees content change', manifestHash("a\n") === manifestHash("b\n"), false);

$dir = sys_get_temp_dir() . '/m11a_' . bin2hex(random_bytes(4));
mkdir($dir . '/sub', 0777, true);
file_put_contents($dir . '/one.txt', "one\r\n");      // CRLF on purpose
file_put_contents($dir . '/sub/two.txt', "two\n");
$manifest = ['part' => 'T', 'files' => [
    'one.txt' => manifestHash("one\n"),
    'sub/two.txt' => manifestHash("two\n"),
]];
$r = manifestVerify($dir, $manifest);
check('all ok', [$r['ok'], $r['bad']], [2, 0]);
file_put_contents($dir . '/sub/two.txt', "TWO\n");
$manifest['files']['gone.txt'] = manifestHash('x');
$r = manifestVerify($dir, $manifest);
check('mismatch + missing counted', [$r['ok'], $r['bad']], [1, 2]);
check('missing line text', in_array('MISSING     gone.txt', $r['lines'], true), true);
unlink($dir . '/one.txt'); unlink($dir . '/sub/two.txt'); rmdir($dir . '/sub'); rmdir($dir);

// mark_part: sorting, validation, vocabulary, key preservation
$s = markPartApply(['parts' => []], '11B', 'executed', 'n', '2026-01-01T00:00:00Z', true);
$s = markPartApply($s, '11A', 'partial', 'm', '2026-01-02T00:00:00Z', true);
$s = markPartApply($s, '11A0', 'executed', 'z', '2026-01-03T00:00:00Z', true);
check('parts sorted', array_keys($s['parts']), ['11A', '11A0', '11B']);
check('status stored', $s['parts']['11A']['status'], 'partial');
$s['parts']['11A0']['database'] = 'pending-owner-approval';
$s = markPartApply($s, '11A0', 'executed', 'again', '2026-01-04T00:00:00Z', true);
check('existing keys are kept', $s['parts']['11A0']['database'], 'pending-owner-approval');
check('note replaced', $s['parts']['11A0']['note'], 'again');
foreach ([['1A', 'executed'], ['11A', 'finished'], ['11a', 'executed'], ['11AB', 'executed'], ['11A', 'verified'], ['11A', 'released'], ['11A', 'ready']] as [$p, $st]) {
    $threw = false;
    try { markPartApply([], $p, $st, '', 'x', true); } catch (InvalidArgumentException $e) { $threw = true; }
    check("executor rejects $p/$st", $threw, true);
}
$ok = markPartApply([], '11H', 'verified', '', 'x', false);
check('owner/author vocabulary allows verified', $ok['parts']['11H']['status'], 'verified');
check('vocabulary has 8 words', count(markPartVocabulary()), 8);
if ($fail > 0) { fwrite(STDERR, "$fail failure(s)\n"); exit(1); }
echo "ai_tools_test: OK\n";
```

### CREATE `tests/persian_letters_test.php`

Content (copy exactly; it ends with a newline):

```php
<?php
// Part 11A — the project rule "Persian text uses ی and ک only": fails when a source file contains the Arabic look-alikes
// U+064A (Arabic yeh), U+0643 (Arabic kaf), U+0649 (alef maksura), U+06C0 or U+0629 (teh marbuta).
$root = dirname(__DIR__);
$bad = ["\u{064A}" => 'U+064A', "\u{0643}" => 'U+0643', "\u{0649}" => 'U+0649', "\u{06C0}" => 'U+06C0', "\u{0629}" => 'U+0629'];
$skip = ['changelogs', 'vendor', 'storage', 'uploads', 'node_modules', '.git', '__pycache__'];
$exts = ['php', 'js', 'css', 'html', 'md', 'json', 'sql', 'py'];
$it = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    function ($cur) use ($skip) { return !($cur->isDir() && in_array($cur->getFilename(), $skip, true)); }
));
$files = 0;
$problems = 0;
foreach ($it as $f) {
    if (!$f->isFile() || !in_array(strtolower($f->getExtension()), $exts, true)) { continue; }
    if (substr($f->getPathname(), -strlen('tests/persian_letters_test.php')) === 'tests/persian_letters_test.php') { continue; }
    $files++;
    $text = (string) file_get_contents($f->getPathname());
    foreach (explode("\n", $text) as $n => $line) {
        foreach ($bad as $ch => $name) {
            if (strpos($line, $ch) !== false) {
                $problems++;
                fwrite(STDERR, substr($f->getPathname(), strlen($root) + 1) . ':' . ($n + 1) . ": $name\n");
            }
        }
    }
}
if ($problems > 0) { fwrite(STDERR, "$problems Arabic-letter problem(s)\n"); exit(1); }
echo "persian_letters_test: OK ($files files)\n";
```

### STEP 8 — hand-off set, manifest and changelog

Four edits to existing hand-off files, then two new files.

### EDIT `docs/ai/HANDOFF.md` — E8

Current-state section: add the 11A line.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```markdown
- 11A0 — product reset and CMS decommission: see `state.json` and the 11A0 changelog. The CMS code, pages, APIs, tests and assets are removed; the database part (migrations 0009 and 0010) needs the owner's approval — `state.json` says whether it was applied.

```

**REPLACE IT WITH:**

```markdown
- 11A0 — product reset and CMS decommission: see `state.json` and the 11A0 changelog. The CMS code, pages, APIs, tests and assets are removed; the database part (migrations 0009 and 0010) needs the owner's approval — `state.json` says whether it was applied.
- 11A — baseline safety: logout is POST + CSRF; every file in `public/api/` has a method guard (`tests/api_method_guards_test.php`); `public/api/media/upload.php` is POST-only and read-only file info lives in `public/api/media/info.php` (GET, needs `media` view); tools: `tools/gates/php80_gate.php` (static PHP 8.0 scan), `tools/ai/mark_part.php` (writes `docs/ai/state.json`); test `tests/persian_letters_test.php` forbids Arabic look-alike letters.

```

### EDIT `docs/ai/HANDOFF.md` — E9

Rules: add the PHP 8.0 gate and the state tool.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```markdown
7. Status words: `authored` (prompt written) → `reviewed` → `ready` (ready to execute) → `executed` → `verified` (checks run on the real stack) → `released`; `partial` and `blocked` for stopped work.

```

**REPLACE IT WITH:**

```markdown
7. Status words: `authored` (prompt written) → `reviewed` → `ready` (ready to execute) → `executed` → `verified` (checks run on the real stack) → `released`; `partial` and `blocked` for stopped work.
8. PHP 8.0 only: no enums, `readonly`, `never`, first-class callables, `new` in initializers. Run `php tools/gates/php80_gate.php` after every change to PHP code. It is a static scan, not a PHP 8.0 runtime.
9. When your part is finished run `php tools/ai/mark_part.php <id> <executed|partial|blocked> "<note>"`. You never write `verified` or `released`; only the owner does, after the checks ran on the real stack.

```

### EDIT `docs/ai/DECISIONS.md` — E10

Append decisions D-012 … D-016.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```markdown
| D-011 | CMS files on disk (`storage/private/licenses`, `storage/private/templates`, `public/uploads/templates`) are never deleted by a migration or by the executor; the owner decides. | Files may hold the owner's only copy of a certificate or template. |
```

**REPLACE IT WITH:**

```markdown
| D-011 | CMS files on disk (`storage/private/licenses`, `storage/private/templates`, `public/uploads/templates`) are never deleted by a migration or by the executor; the owner decides. | Files may hold the owner's only copy of a certificate or template. |
| D-012 | Logout is POST + CSRF; GET `/logout.php` only redirects (303) and never logs out. | A GET logout can be triggered from another site by an image tag. |
| D-013 | Every file in `public/api/` must contain a method guard; `tests/api_method_guards_test.php` enforces it. | Method guards on all APIs (security baseline). |
| D-014 | `media/upload.php` is POST-only; the old `GET ?id=` branch moved to `media/info.php` (GET, `media` view). Nothing in the repository called the old branch. | Split GET and POST. View permission is enough for a read-only lookup; edit implies view, so nobody loses access. |
| D-015 | The PHP 8.0 gate is a static token scan (`tools/gates/php80_gate.php`) and is run by a unit test over the whole project. A real PHP 8.0 run is release blocker RB-2 (closed in 11H). | The owner targets PHP 8.0; the author's sandbox has a newer PHP, so only a static check is possible there. Saying so openly avoids false confidence. |
| D-016 | `tools/ai/mark_part.php` lets the executor write only `executed`, `partial`, `blocked`; it keeps other keys of the part entry. `verified` and `released` are written by the owner. | The executor cannot know whether the real stack passed; the status must not claim it. |
```

### EDIT `docs/ai/FILE-MAP.md` — E11

Append the 11A section.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```markdown
Removed in 11A0 (do not look for them): page builder, pages, articles, templates, licenses, `public/file.php`. See `docs/decommission-cms.md`.
```

**REPLACE IT WITH:**

```markdown
Removed in 11A0 (do not look for them): page builder, pages, articles, templates, licenses, `public/file.php`. See `docs/decommission-cms.md`.

## Added or changed by Part 11A (baseline safety)
| Path | Kind | What it is |
|---|---|---|
| `public/logout.php` | changed | POST + CSRF logout; GET redirects only |
| `includes/layout/sidebar.php`, `includes/layout/topbar.php` | changed | logout link → POST form with `csrf_field()` |
| `public/api/notifications/mark_read.php`, `public/api/staff/invite.php` | changed | POST-only guard |
| `public/api/media/upload.php` | changed | GET branch removed; POST-only |
| `public/api/media/info.php` | new | GET file info (moved out of upload.php) |
| `tools/gates/php80_gate.php` | new | static PHP 8.0 syntax/function gate |
| `tools/ai/mark_part.php` | new | writes part status into `docs/ai/state.json` |
| `tests/api_method_guards_test.php`, `php80_gate_test.php`, `ai_tools_test.php`, `persian_letters_test.php` | new | tests of the above |
| `.gitignore` | changed | ignores `__pycache__/` and `*.pyc` |
| `tests/browser/__pycache__/`, `tools/ui/__pycache__/` | deleted | bytecode noise |
| `docs/ai/manifests/11A.json` | new | sha256 of the frozen code files of 11A |
```

### CREATE `docs/ai/manifests/11A.json`

Content (copy exactly; it ends with a newline):

```json
{
  "absent": [
    "tests/browser/__pycache__",
    "tools/ui/__pycache__"
  ],
  "files": {
    "includes/layout/sidebar.php": "af58228e39296eb8fc56923616f8a3e175a8a488c8e10af9fd71745150ec847c",
    "includes/layout/topbar.php": "f1fb1d289d4981f2e959ed6106ee1e92eac60532586ca8a59525b1e48de3a5ca",
    "public/api/media/info.php": "c5cc1ea51a794ba74a54190384217bfbb7a83a32f6a7c38ea23dc274358bbb1d",
    "public/api/media/upload.php": "6b3b0c068da9673e0541fad0924529371e2cb779d74d59adbdd3dad43983172d",
    "public/api/notifications/mark_read.php": "769d3f9a2c99462ac46817a3ac6775204e1bbbd291ec44b0c26e1d9ded041176",
    "public/api/staff/invite.php": "b9d07ad2afa8304e0fd651d815837958739935f89e959de1cc5b2ef7f58cd6d1",
    "public/logout.php": "296b15d4fccae634d7b38e82bec32ff3432110cd896afbc33af787849c684b2f",
    "tests/ai_tools_test.php": "0991c8ed6f2b7745854769593261468e72dc49945a910e8c925d56c6b1086f76",
    "tests/api_method_guards_test.php": "646369d71003faec557f5efa1b73846a9e73aa43a03a8b25035b74b8a25a52f4",
    "tests/persian_letters_test.php": "5d157be8378288429dd4da960c2e35afc58b36e2917c10c870eedff5688f08a7",
    "tests/php80_gate_test.php": "2a58a6cf8eea50b22d4df424674616c2ccb11a6e5d9053f598e8061ca671d60c",
    "tools/ai/mark_part.php": "a9cca981abea42afbc1300f634d47959e0c890823ba0ab6e99637d1a6691e5bb",
    "tools/gates/php80_gate.php": "8469197997843000b6002b6c9915a2bb4d0c0496c330821cfd2af19961205712"
  },
  "part": "11A"
}
```

### CREATE `changelogs/changelog-part-11a-baseline-safety.md`

Content (copy exactly; it ends with a newline):

```markdown
# changelog — Part 11A (baseline safety)

**Written from:** `master-prompt-11A-baseline-safety.md` (revision 2: runs after Part 11A0). Author of the code: the prompt author (tested in a Linux sandbox, PHP 8.3, SQLite, no MySQL, no Apache). The executing agent copied files and ran the checks; it did no engineering.

## 1. Security fixes
- Logout is POST + CSRF: `public/logout.php` rewritten; the sidebar link and the user-menu item became `<form method="post" action="/logout.php">` with `csrf_field()`. A GET to `/logout.php` only redirects (303) and never logs out.
- Method guards added (POST only, 405 + `Allow` header): `public/api/notifications/mark_read.php`, `public/api/staff/invite.php`.
- `public/api/media/upload.php` is POST-only (its `GET ?id=` branch was removed); the same lookup lives in the new `public/api/media/info.php` (GET only, needs `media` view).

## 2. Tooling
- `tools/gates/php80_gate.php` — static scan for PHP 8.1+ syntax/functions. A token scan, not a PHP 8.0 runtime (release blocker RB-2).
- `tools/ai/mark_part.php` — writes the part status into `docs/ai/state.json`; the executor may write only `executed`, `partial`, `blocked`.
- New tests: `tests/api_method_guards_test.php`, `tests/php80_gate_test.php`, `tests/ai_tools_test.php`, `tests/persian_letters_test.php`.

## 3. Hygiene and hand-off
- `.gitignore` ignores `__pycache__/` and `*.pyc`; the two tracked `__pycache__` folders were deleted. No git command was run.
- `docs/ai/` HANDOFF, DECISIONS (D-012…D-016) and FILE-MAP were extended; `docs/ai/manifests/11A.json` was added.

## 4. Not verified by the author (be honest)
MySQL behaviour of `info.php`/`upload.php`; the logged-in happy path of logout (it writes to the database through `logActivity`); the look of the two logout buttons in a real browser; the PHP 8.0 runtime; Apache; Windows paths.

## Backup and baseline
(The executing agent writes the backup path, the PHP version, the baseline test output and the Step 1 output here.)

## Deviations from the prompt
(The executing agent replaces this line with `none`, or with one bullet per deviation: what, why, which file.)

## Gate outputs (verbatim)
(The executing agent pastes below this line the real output of every command the prompt's Acceptance Checklist asks for, unedited.)
```

### STEP 9 — verification (run all, record real output)

```text
php tools/gates/verify_manifest.php docs/ai/manifests/11A.json
php tests/run_all.php
php tools/gates/php80_gate.php
```

Expected:
- manifest: last line `manifest 11A: 15 ok, 0 bad, 0 info`. A `MISMATCH` means a copy error: re-copy that file from this prompt (never edit the manifest).
- tests: **17 lines**, all `OK` (baseline 13 + `ai_tools_test`, `api_method_guards_test`, `php80_gate_test`, `persian_letters_test`).
- gate: last line `php80 gate: scanned <N> file(s), violations: 0` (N differs per copy; the author's copy: 96). A violation in an **existing owner file** is recorded under Deviations (file, line, message), not fixed.

### STEP 10 — finish the changelog and the state file

1. In `changelogs/changelog-part-11a-baseline-safety.md` fill the three placeholder sections: "Backup and baseline" (backup path, PHP version, Step 1 outputs, the 11A0 `database` value), "Deviations from the prompt" (`none` or one bullet each), "Gate outputs (verbatim)" (the real output of Steps 1 and 9).
2. Run `php tools/ai/mark_part.php 11A executed "baseline safety applied"` (use `partial` or `blocked` instead of `executed` if a step was stopped). It must print `state.json: 11A = executed`.

## 8. Acceptance Checklist

- [ ] 11A0 was executed; pre-flight outputs and the backup path are in the changelog.
- [ ] `php tools/gates/verify_manifest.php docs/ai/manifests/11A.json`: `15 ok, 0 bad, 0 info`.
- [ ] `public/logout.php` is replaced; `sidebar.php` and `topbar.php` contain `<form method="post" action="/logout.php">` with `csrf_field()` and **no** `href="/logout.php"`.
- [ ] `mark_read.php` and `invite.php` have the POST guard; `upload.php` has no `$_GET`; `info.php` exists.
- [ ] Both `__pycache__` folders are gone; `.gitignore` ignores `__pycache__/` and `*.pyc`.
- [ ] `php tests/run_all.php`: 17 lines, all `OK`.
- [ ] `php tools/gates/php80_gate.php`: `violations: 0` (or each violation recorded as information).
- [ ] `docs/ai/HANDOFF.md` has the 11A line and rules 8–9; `DECISIONS.md` has D-012…D-016; `FILE-MAP.md` has the 11A section; `state.json` shows `11A` = `executed` and still shows the `database` key of `11A0`.
- [ ] Changelog complete (backup, baseline, Deviations, verbatim outputs).
- [ ] No file outside the tree in section 2 was modified; nothing in `database/` was opened.

## 9. Final message to the owner (Persian)

Write a short Persian report: what was done (by step), the real outputs (manifest line, number of test lines, gate line), every deviation, and what you could **not** run. Then give these **manual checks for the owner**:

1. وارد پنل شو و در منوی کناری روی «خروج از حساب» بزن؛ باید به صفحهٔ ورود بروی.
2. دوباره وارد شو، منوی کاربر بالای صفحه را باز کن و «خروج» را بزن؛ باید به صفحهٔ ورود بروی.
3. وارد شو و در نوار آدرس `/logout.php` را تایپ کن؛ باید **وارد بمانی** و به پیشخوان هدایت شوی.
