# MASTER PROMPT — Part 11A: Baseline safety fixes + AI hand-off set

> **Series:** Part 11 — Admin Framework / Starter Kit (source: owner's blueprint `Admin-Framework-Blueprint-fa.md`). **Execution order: 11A → 11B → 11C (strictly in this order).** This file is **11A**.
> **Prepared by:** Claude (prompt author) for the owner, Pouya. **Executor:** the OpenCode agent (you).
> **Target:** PHP 8.0 / MySQL / XAMPP on Windows, Apache DocumentRoot = `public/`.

> **Verification banner (honest).** Author-tested in a Linux sandbox (PHP 8.3, SQLite, no MySQL, no Apache): all unit tests pass (16 test files in the author's copy), the PHP-8.0 static gate reports 0 violations, the POST-only / GET-only behaviour of the changed endpoints and of `logout.php` was exercised over HTTP with PHP's built-in server.
> **NOT verified by the author:** MySQL behaviour (`info.php`, `upload.php`, `logActivity` inside logout), the real look of the two logout buttons in a browser, PHP 8.0 runtime (the gate is a static token scan), Windows paths.

## 0. READ THIS FIRST — you are an OPERATOR, not an engineer

The prompt author (Claude) has already made every engineering decision, written all code and tested it (see the banner for what was and was not tested).
Your job is to **copy, replace, delete, run commands and report**. Nothing else.

1. **Do only what is written.** Where this prompt says "create", "replace", "delete" or "run", do exactly that. Do **not** design, refactor, rename, reformat, add comments, add error handling, "improve", "modernise", reorder, merge or split anything. Do not fix anything you think is a bug in this prompt — report it instead (rule 3).
2. **Copy byte for byte.** File content in code fences is the exact file content: UTF-8 without BOM, LF line endings, one final newline, no trailing spaces added or removed. The code fence markers themselves are not part of the file. Persian text is exact (Persian letters ی and ک only).
3. **If anything cannot be done exactly as written → STOP that step.** Examples: a FIND text occurs 0 or 2+ times, a file that must exist is missing, a file that must not exist already exists, a command fails, a check does not print the expected result. Do not improvise, do not "make it work", do not continue with steps that depend on it. Write what happened in the changelog under "Deviations", and tell the owner in your final message. (Steps that clearly do not depend on the failed one may continue; when in doubt, stop.)
4. **Touch only the files this prompt names.** Do not open the owner's other files for editing. Do not run `git` commands that change anything (the local copy may not even be a git repository), `composer require/update/install`, `npm`, or any database command. This part has no database step.
5. **Documentation duty.** The owner works with several AI agents one after another. The next one must be able to see exactly what you did. So the steps in section "Changelog and hand-off" are **mandatory**, not optional: changelog file, `docs/ai/state.json` update, and honest "Deviations" and "Gate outputs".
6. **Be honest in the report.** Paste real command output. If a check was not run or could not run, say so. Never write "passed" for something you did not run.
7. **Language.** Your final message to the owner is in **Persian** (the owner is Persian-speaking). File contents stay exactly as given here.
8. **Use your task list.** One task per numbered step below; mark each done only after its check passed.

## 1. Goal and scope

Part 11A does three things and nothing else:

1. **Closes three P0 security items of the blueprint (§17):** logout becomes POST + CSRF; three API endpoints get a method guard; the media upload endpoint stops mixing GET and POST.
2. **Adds the tooling** the rest of the series depends on: a PHP-8.0 static gate, a sha256 manifest verifier, a state-file updater, and a test that forbids Arabic look-alike letters.
3. **Creates the AI hand-off set** `docs/ai/` (`HANDOFF.md`, `DECISIONS.md`, `FILE-MAP.md`, `state.json`, `manifests/11A.json`) so any later AI agent can see what was done and why.

**Changes / completions the prompt author made to the owner's spec (stated openly):**
- The blueprint's P0 list also names *no default credentials*, *CSP + inline-JS removal* and *fail-closed production config*. They are **not** in 11A: default credentials belong with the setup wizard (11D), CSP/inline-JS with the UI foundation (11E); fail-closed production config is already implemented in `config/database.php` and `config/config.php` (the author read them), so nothing to do.
- The blueprint says "split media upload GET/POST". The author checked the repository: nothing calls the old `GET /api/media/upload.php?id=` branch (the builder media picker uses `/api/builder/media.php`). It is still moved to a new `info.php` instead of being deleted, so no capability is lost. Permission needed: `media` view (edit implies view, so nobody who could use the old branch loses access).
- Added by the author, not in the blueprint: the hand-off docs, the PHP-8.0 gate, the manifest verifier, and the Arabic-letters test (the owner asked for precise documentation so a later AI does not confuse the work).
- The author's earlier outline said "`git rm --cached` the `__pycache__` files". Dropped: the owner's copy may not be a git repository and you must not run git. The two `__pycache__` folders are simply deleted and `.gitignore` is extended.

**Out of scope (later parts):** everything else in the blueprint — kernel (11B), modules (11C), database core v2, setup wizard, UI foundation, service modules, CMS move, HRM pilot, release/CI.

## 2. File tree (only what this part touches)

```text
.
├── changelogs/
│   └── changelog-part-11a-baseline-safety.md   [NEW]
├── docs/
│   └── ai/
│       ├── manifests/
│       │   └── 11A.json   [NEW]
│       ├── DECISIONS.md   [NEW]
│       ├── FILE-MAP.md   [NEW]
│       ├── HANDOFF.md   [NEW]
│       └── state.json   [NEW]
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
│   │   ├── staff/
│   │   │   └── invite.php   [CHANGED]
│   │   └── templates/
│   │       └── activate.php   [CHANGED]
│   └── logout.php   [CHANGED]
├── tests/
│   ├── browser/
│   │   └── __pycache__   [DELETED]
│   ├── ai_tools_test.php   [NEW]
│   ├── api_method_guards_test.php   [NEW]
│   ├── persian_letters_test.php   [NEW]
│   └── php80_gate_test.php   [NEW]
├── tools/
│   ├── ai/
│   │   └── mark_part.php   [NEW]
│   ├── gates/
│   │   ├── php80_gate.php   [NEW]
│   │   └── verify_manifest.php   [NEW]
│   └── ui/
│       └── __pycache__   [DELETED]
└── .gitignore   [CHANGED]
```

`[NEW]` = created, `[CHANGED]` = existing file edited, `[DELETED]` = removed. Nothing else changes.

## 3. Database design (PK/FK) — **no schema change**

- **No table, column, index, PK or FK is created, altered or dropped in this part, and there is no migration.** Do not open `database/` at all.
- Reason: every change here is request handling (logout, method guards), tooling or documentation. The existing keys stay exactly as they are (for example `media_library.id` PK; `panel_users.id` is referenced by `activity_logs`, which `logActivity()` writes to on logout — untouched).
- State of this series that must survive between AI sessions is kept in **files** (`docs/ai/state.json`, manifests), not in the database, so it works before the database part exists and cannot be lost by a database restore.
- Table design with PK/FK reasons returns in the database part (11D); it is not decided here.

## 4. Library decision

**No library is added. No Composer change.** `composer.json` and `composer.lock` are not touched. All tools are plain PHP 8.0 scripts using only core functions (`token_get_all`, `hash`, `json_*`). Reason: the tools must run on the owner's XAMPP without any installation step, and a static gate is a few dozen lines; a framework or a package would add risk with no benefit.

## 5. "Must NOT do" table

| Do NOT | Why |
|---|---|
| Edit, "fix" or reformat any file not named in this prompt | Other files belong to earlier verified parts; the next AI relies on the file map. |
| Change any CSS | `.nav-link-item` already resets button styling (border 0, transparent background, inherited font, full width) — the sidebar logout button needs no CSS. Bootstrap's `.dropdown-item` already supports `<button>`. |
| Keep a GET path that logs out ("for convenience") | That is exactly the vulnerability. GET `/logout.php` must only redirect. |
| Add CSRF checks to `info.php` | It is a read-only GET; CSRF applies to state changes. |
| Add method guards to other APIs or change the guard style | Every other API already has a guard (the new test proves it). Only the three listed ones were missing. |
| Delete or move the POST upload code of `upload.php` | Only the `GET ?id=` branch is removed. |
| Run `git`, `composer`, `npm`, or any DB command | No such step exists in this part. |
| Modify `tests/run_all.php` | It auto-discovers `tests/*_test.php`; new tests are picked up by themselves. |
| Add "TBD"/placeholder text to the docs | Docs are complete as given; only the changelog's last two sections are filled by you. |
| Rewrite the Persian strings | They are exact (ی ک only). |

## 6. Prerequisites (check first — STOP if any fails)

Work from the **project root** (the folder that contains `composer.json`, `public/`, `includes/`, `tests/`).

1. These must **exist**: `composer.json`, `includes/auth.php`, `includes/csrf.php`, `includes/layout/sidebar.php`, `includes/layout/topbar.php`, `public/login.php`, `public/logout.php`, `public/api/media/upload.php`, `public/api/notifications/mark_read.php`, `public/api/templates/activate.php`, `public/api/staff/invite.php`, `tests/run_all.php`.
2. These must **not exist** (otherwise this part was already applied or partly applied → STOP and ask the owner): `docs/ai/`, `tools/gates/`, `tools/ai/`, `public/api/media/info.php`.
3. Run `php -v` → PHP **8.0 or newer**. Record the version.
4. Run `php tests/run_all.php` → every line must be `OK`, no `FAIL`. Record the number of lines (this is the baseline; the author's copy had 12). If the baseline fails → STOP: do not build on a broken base.

### Backup (the only step where you choose the method)
Make a full copy of the project folder to `../panel-backups/pre-11A/` (create the parent folder if needed; `.gitignore` already lists `../panel-backups/`). Exclude `vendor/`, `node_modules/` and `.git/`. Example (Windows): `robocopy . ..\panel-backups\pre-11A /E /XD vendor node_modules .git` (robocopy exit codes below 8 are success). If the target already exists, use `pre-11A-2` etc. Write the backup path in the changelog.

## 7. Steps

Do them in this order. After each step, run its check.

### STEP 1 — create the three tools

Create these files exactly. Check after the step: `php -l` on each prints `No syntax errors detected`.

### CREATE `tools/gates/verify_manifest.php`

Content (copy exactly; it ends with a newline):

```php
<?php
// tools/gates/verify_manifest.php — Part 11A
// Verifies that files on disk are byte-identical (line endings normalised to LF) to the files a master prompt asked for.
//
//   php tools/gates/verify_manifest.php docs/ai/manifests/11A.json
//
// Manifest format: {"part":"11A","files":{"relative/path.php":"<sha256 of the LF-normalised content>", ...}}
// Exit code 0 = every file present and identical; 1 = at least one MISSING or MISMATCH.
// Plain PHP 8.0, no dependencies. Paths in the manifest are relative to the project root (the parent of tools/).

/** sha256 of the content with CRLF / CR converted to LF (so Windows checkouts compare equal). */
function manifestHash(string $content): string {
    return hash('sha256', str_replace(["\r\n", "\r"], "\n", $content));
}

/**
 * @return array{ok: int, bad: int, lines: string[]}
 */
function manifestVerify(string $root, array $manifest): array {
    $ok = 0;
    $bad = 0;
    $lines = [];
    $files = $manifest['files'] ?? [];
    ksort($files, SORT_STRING);
    foreach ($files as $rel => $expected) {
        $path = rtrim($root, '/\\') . '/' . $rel;
        if (!is_file($path)) {
            $bad++;
            $lines[] = 'MISSING   ' . $rel;
            continue;
        }
        $actual = manifestHash((string) file_get_contents($path));
        if (hash_equals((string) $expected, $actual)) {
            $ok++;
            $lines[] = 'OK        ' . $rel;
        } else {
            $bad++;
            $lines[] = 'MISMATCH  ' . $rel . '  (expected ' . substr((string) $expected, 0, 12) . ', got ' . substr($actual, 0, 12) . ')';
        }
    }
    return ['ok' => $ok, 'bad' => $bad, 'lines' => $lines];
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
    echo 'manifest ', (string) ($manifest['part'] ?? '?'), ': ', $result['ok'], ' ok, ', $result['bad'], " bad\n";
    exit($result['bad'] > 0 ? 1 : 0);
}
```

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
// tools/ai/mark_part.php — Part 11A
// Records the status of one master-prompt part in docs/ai/state.json (the machine-readable hand-off for the next AI).
//
//   php tools/ai/mark_part.php 11A done "short factual note"
//
// Status must be one of: done, partial, blocked. The timestamp is UTC. Keys are kept sorted so diffs stay small.

/** Returns the updated state array. Throws InvalidArgumentException on bad input. */
function markPartApply(array $state, string $part, string $status, string $note, string $nowUtc): array {
    if (!preg_match('/^11[A-Z]$/D', $part)) {
        throw new InvalidArgumentException('part must look like 11A');
    }
    if (!in_array($status, ['done', 'partial', 'blocked'], true)) {
        throw new InvalidArgumentException('status must be done, partial or blocked');
    }
    if (!isset($state['parts']) || !is_array($state['parts'])) {
        $state['parts'] = [];
    }
    $state['parts'][$part] = ['status' => $status, 'updated_utc' => $nowUtc, 'note' => $note];
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
        $state = markPartApply($state, (string) ($argv[1] ?? ''), (string) ($argv[2] ?? ''), (string) ($argv[3] ?? ''), gmdate('Y-m-d\TH:i:s\Z'));
    } catch (InvalidArgumentException $e) {
        fwrite(STDERR, $e->getMessage() . "\nusage: php tools/ai/mark_part.php <11A> <done|partial|blocked> \"note\"\n");
        exit(2);
    }
    file_put_contents($file, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
    echo 'state.json: ', $argv[1], ' = ', $argv[2], "\n";
}
```

### STEP 2 — security edits to existing files

Apply each edit with your editor tool. Every FIND text must occur exactly once (rule 3).

### EDIT `public/logout.php` — E0

REPLACE THE WHOLE FILE (logout becomes POST + CSRF).

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → STOP, see rule 3):

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

logout link becomes a POST form with CSRF.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → STOP, see rule 3):

```php
    <a class="nav-link-item nav-link-item--danger" href="/logout.php" data-tip="خروج از حساب"><i class="ti ti-logout" aria-hidden="true"></i><span class="nav-label">خروج از حساب</span></a>
```

**REPLACE IT WITH:**

```php
    <form method="post" action="/logout.php" class="app-sidebar__logout">
      <?= csrf_field() ?>
      <button type="submit" class="nav-link-item nav-link-item--danger" data-tip="خروج از حساب"><i class="ti ti-logout" aria-hidden="true"></i><span class="nav-label">خروج از حساب</span></button>
    </form>
```

### EDIT `includes/layout/topbar.php` — E3

user-menu logout becomes a POST form with CSRF.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → STOP, see rule 3):

```php
        <li><a class="dropdown-item" href="/logout.php"><i class="ti ti-logout" aria-hidden="true"></i>خروج</a></li>
```

**REPLACE IT WITH:**

```php
        <li><form method="post" action="/logout.php"><?= csrf_field() ?><button type="submit" class="dropdown-item"><i class="ti ti-logout" aria-hidden="true"></i>خروج</button></form></li>
```

### EDIT `public/api/notifications/mark_read.php` — E4

POST-only guard (inserted before requireLogin).

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → STOP, see rule 3):

```php
requireLogin();
```

**REPLACE IT WITH:**

```php
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){ header('Allow: POST'); jsonResponse(['success'=>false,'message'=>'متد مجاز نیست'],405); }
requireLogin();
```

### EDIT `public/api/templates/activate.php` — E5

POST-only guard.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → STOP, see rule 3):

```php
requireLogin(); requirePermission('templates','edit');
```

**REPLACE IT WITH:**

```php
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){ header('Allow: POST'); jsonResponse(['success'=>false,'message'=>'متد مجاز نیست'],405); }
requireLogin(); requirePermission('templates','edit');
```

### EDIT `public/api/staff/invite.php` — E6

POST-only guard.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → STOP, see rule 3):

```php
requireLogin(); if(!isOwner()) jsonResponse(['success'=>false,'message'=>'فقط مالک'],403);
```

**REPLACE IT WITH:**

```php
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){ header('Allow: POST'); jsonResponse(['success'=>false,'message'=>'متد مجاز نیست'],405); }
requireLogin(); if(!isOwner()) jsonResponse(['success'=>false,'message'=>'فقط مالک'],403);
```

### EDIT `public/api/media/upload.php` — E7

remove the GET ?id= branch (moved to info.php); the endpoint becomes POST-only.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → STOP, see rule 3):

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
```

**REPLACE IT WITH:**

```php
requireLogin(); requirePermission('media','edit');
// Part 11A: POST only. Read-only file info lives in /api/media/info.php.
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){ header('Allow: POST'); jsonResponse(['success'=>false,'message'=>'متد مجاز نیست'],405); }
```

### EDIT `.gitignore` — E1

ignore Python bytecode (two stray __pycache__ folders exist in tests/ and tools/).

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → STOP, see rule 3):

```
../panel-backups/
```

**REPLACE IT WITH:**

```
../panel-backups/
__pycache__/
*.pyc
```

After the edits run `php -l` on: `public/logout.php`, `includes/layout/sidebar.php`, `includes/layout/topbar.php`, `public/api/notifications/mark_read.php`, `public/api/templates/activate.php`, `public/api/staff/invite.php`, `public/api/media/upload.php`. Each must print `No syntax errors detected`.

### STEP 3 — create the media info endpoint

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

### STEP 4 — delete bytecode folders

Delete these two folders with all their content (any method; if a folder does not exist, skip it and write that in the changelog): `tests/browser/__pycache__/` and `tools/ui/__pycache__/`. Do not delete anything else in those folders.

### STEP 5 — create the four tests

### CREATE `tests/api_method_guards_test.php`

Content (copy exactly; it ends with a newline):

```php
<?php
// Part 11A — static checks: every API endpoint declares its HTTP method; logout is POST+CSRF; no GET logout links remain.
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
check('api file count is plausible (>= 18)', $n >= 18, true);

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
check('missing line text', in_array('MISSING   gone.txt', $r['lines'], true), true);
unlink($dir . '/one.txt'); unlink($dir . '/sub/two.txt'); rmdir($dir . '/sub'); rmdir($dir);

$s = markPartApply(['parts' => []], '11B', 'done', 'n', '2026-01-01T00:00:00Z');
$s = markPartApply($s, '11A', 'partial', 'm', '2026-01-02T00:00:00Z');
check('parts sorted', array_keys($s['parts']), ['11A', '11B']);
check('status stored', $s['parts']['11A']['status'], 'partial');
foreach ([['1A', 'done'], ['11A', 'finished'], ['11a', 'done']] as [$p, $st]) {
    $threw = false;
    try { markPartApply([], $p, $st, '', 'x'); } catch (InvalidArgumentException $e) { $threw = true; }
    check("rejects $p/$st", $threw, true);
}
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

### STEP 6 — create the hand-off set

Create the folder `docs/ai/` and `docs/ai/manifests/`, then these files.

### CREATE `docs/ai/HANDOFF.md`

Content (copy exactly; it ends with a newline):

```markdown
# AI HAND-OFF — read this first

This folder (`docs/ai/`) is the memory of the project for any AI that continues the work. Humans wrote the plan; an AI agent
executed it part by part. If you are an AI that was just asked to continue: **read this file, then `state.json`, then
`DECISIONS.md`, then `FILE-MAP.md`, then the newest file in `changelogs/`. Do not start editing before that.**

## 1. What this project is
A PHP 8.0 / MySQL admin panel (XAMPP on Windows, Apache DocumentRoot = `public/`). Persian (RTL) UI, session login, owner/staff
permissions, a page builder (CMS). It is being turned, in small verified steps, into a **modular-monolith admin framework**
(Core kernel + modules + UI foundation). The plan source is the Persian blueprint `Admin-Framework-Blueprint-fa.md`
(kept outside the repo by the owner). The step-by-step execution is **Part 11** (prompts 11A, 11B, 11C, ...).

## 2. How the work is organised
- Each part is one English "master prompt". The prompt contains every file's exact content. The executing agent only
  copies, replaces and deletes what the prompt says, then runs the listed checks. It does no engineering of its own.
- Every part leaves: (a) files in the repo, (b) a changelog `changelogs/changelog-part-<id>-*.md`, (c) an entry in
  `state.json`, (d) rows in `FILE-MAP.md`, (e) decisions in `DECISIONS.md`, (f) for code files a manifest
  `docs/ai/manifests/<id>.json` that `php tools/gates/verify_manifest.php docs/ai/manifests/<id>.json` can verify.
- Order matters: 11A -> 11B -> 11C. A part's prompt starts with a prerequisite check; if it fails, the agent stops.

## 3. Rules for any AI continuing the work
1. Do not "improve" a file listed in `FILE-MAP.md` unless the owner's next prompt tells you to. If something looks wrong, write it into
   `docs/ai/OPEN-ISSUES.md` (create it if absent: one bullet per issue, with file + line + what you saw) and tell the owner.
2. Never guess. If a prompt's "before" text does not match the file on disk, stop and report; do not edit by feel.
3. PHP 8.0 only (no enums, readonly, `never`, first-class callables, `new` in initializers). Run `php tools/gates/php80_gate.php`.
4. Persian UI strings must use the Persian letters ی and ک only, never the Arabic look-alikes (code points U+064A and U+0643). `tests/persian_letters_test.php` enforces it for source files (the changelogs folder is skipped).
5. After you finish: write the changelog, run `php tools/ai/mark_part.php <id> done "<note>"`, append to `FILE-MAP.md` and `DECISIONS.md`.
6. Do not run git commands that change history. The owner's local copy may not be a git repository at all.

## 4. Current state (updated by each part)
- After 11A: logout is POST + CSRF; every file in `public/api/` has a method guard; `public/api/media/upload.php` is POST-only and
  read-only file info moved to `public/api/media/info.php` (GET, needs `media` view); tooling exists:
  `tools/gates/php80_gate.php`, `tools/gates/verify_manifest.php`, `tools/ai/mark_part.php`.
- Not done yet (outline only, no prompt written): 11D database core v2 + setup wizard + no default credentials; 11E UI foundation v2
  (CSP, inline-JS removal); 11F service modules; 11G CMS as a module; 11H HRM pilot; 11I release/CI/docs.

## 5. Known limits of verification (be honest about them)
The prompts were tested by their author in a Linux sandbox with PHP 8.3, SQLite and no MySQL. Therefore: MySQL behaviour,
XAMPP/Windows paths, Apache mod_rewrite and real PHP 8.0 execution were **not** verified there. The PHP 8.0 gate is a static token
scan, not a PHP 8.0 runtime.
```

### CREATE `docs/ai/DECISIONS.md`

Content (copy exactly; it ends with a newline):

```markdown
# DECISIONS — why things are the way they are

One row per decision. Never delete a row; if a decision changes, add a new row that says "supersedes D-xxx".

| ID | Part | Decision | Reason |
|---|---|---|---|
| D-001 | 11 | Architecture path A: keep the project's own light kernel (PSR-style), do not adopt Laravel/Symfony. | The blueprint's path A; the app is a modular monolith, a full framework would be a rewrite. |
| D-002 | 11 | Target PHP 8.0 (composer.json `php >=8.0`). No syntax or functions newer than 8.0. | The owner's XAMPP runs PHP 8.0. A static gate enforces it. |
| D-003 | 11A | Logout is POST + CSRF; GET `/logout.php` only redirects (303) and never logs out. | Blueprint P0: a GET logout can be triggered by an image tag on another site. |
| D-004 | 11A | Every file in `public/api/` must contain a method guard; enforced by `tests/api_method_guards_test.php`. | Blueprint P0: method guards on all APIs. |
| D-005 | 11A | `media/upload.php` is POST-only; the old `GET ?id=` branch moved to `media/info.php` (GET, `media` view). No code in the repo called the old GET branch (the builder picker uses `/api/builder/media.php`). | Blueprint P0: split GET/POST. View permission is enough for a read-only lookup; edit implies view so nobody loses access. |
| D-006 | 11A | Hand-off docs live in `docs/ai/`; frozen code files are protected by a sha256 manifest (LF-normalised). | Another AI must be able to tell what was done and detect accidental edits. |
| D-007 | 11A | Two stray `__pycache__` folders are deleted and `.gitignore` ignores them. The agent runs no git commands. | They are build noise; the local copy may not be a git repo. |
```

### CREATE `docs/ai/FILE-MAP.md`

Content (copy exactly; it ends with a newline):

```markdown
# FILE-MAP — which part created or changed which file

`new` = file created by that part. `changed` = existing file edited by that part. `deleted` = removed.
A later part must append rows here; never rewrite earlier rows.

## Part 11A — baseline safety + AI hand-off
| File | Kind | Purpose |
|---|---|---|
| `.gitignore` | changed | ignores `__pycache__/` and `*.pyc` |
| `public/logout.php` | changed | POST + CSRF logout; GET redirects only |
| `includes/layout/sidebar.php` | changed | logout link -> POST form |
| `includes/layout/topbar.php` | changed | user-menu logout link -> POST form |
| `public/api/notifications/mark_read.php` | changed | POST-only guard |
| `public/api/templates/activate.php` | changed | POST-only guard |
| `public/api/staff/invite.php` | changed | POST-only guard |
| `public/api/media/upload.php` | changed | GET branch removed; POST-only |
| `public/api/media/info.php` | new | GET file info (moved out of upload.php) |
| `tools/gates/php80_gate.php` | new | static PHP 8.0 syntax gate |
| `tools/gates/verify_manifest.php` | new | sha256 manifest verifier |
| `tools/ai/mark_part.php` | new | writes part status into `docs/ai/state.json` |
| `tests/api_method_guards_test.php` | new | method guards + logout checks |
| `tests/php80_gate_test.php` | new | gate unit test + project scan |
| `tests/ai_tools_test.php` | new | tests of the two tools above |
| `tests/persian_letters_test.php` | new | fails when Arabic look-alike letters (U+064A, U+0643, ...) appear in source files |
| `docs/ai/HANDOFF.md`, `DECISIONS.md`, `FILE-MAP.md`, `state.json` | new | AI hand-off set (mutable: later parts append) |
| `docs/ai/manifests/11A.json` | new | sha256 of the frozen code files of 11A |
| `changelogs/changelog-part-11a-baseline-safety.md` | new | what the executing agent did and saw |
| `tests/browser/__pycache__/`, `tools/ui/__pycache__/` | deleted | bytecode noise |
```

### CREATE `docs/ai/state.json`

Content (copy exactly; it ends with a newline):

```json
{
  "schema": 1,
  "series": "Part 11 - Admin Framework / Starter Kit (from Admin-Framework-Blueprint-fa.md)",
  "execution_order": ["11A", "11B", "11C"],
  "outline_only_not_written": ["11D", "11E", "11F", "11G", "11H", "11I"],
  "parts": {}
}
```

### CREATE `docs/ai/manifests/11A.json`

Content (copy exactly; it ends with a newline):

```json
{
 "part": "11A",
 "files": {
  "tools/gates/php80_gate.php": "8469197997843000b6002b6c9915a2bb4d0c0496c330821cfd2af19961205712",
  "tools/gates/verify_manifest.php": "367ace73e9f54641b35a85fe4aa70f2606e7c43ef3e67118a3ed94ed3361bb59",
  "tools/ai/mark_part.php": "6f1056a7a2b55e99d815c59182b9dabfb7851aee731dde0e1d482fe41c4bbe53",
  "tests/api_method_guards_test.php": "9bd7e3c84824f73e29d2fed18efc235aef49e0c29063011f7b21d2f771cd5980",
  "tests/php80_gate_test.php": "2a58a6cf8eea50b22d4df424674616c2ccb11a6e5d9053f598e8061ca671d60c",
  "tests/ai_tools_test.php": "7a02363f58d6f3e8f84a69a9dc24ce4131747154e8aecb444b008e5fa68fc957",
  "tests/persian_letters_test.php": "5d157be8378288429dd4da960c2e35afc58b36e2917c10c870eedff5688f08a7",
  "public/logout.php": "296b15d4fccae634d7b38e82bec32ff3432110cd896afbc33af787849c684b2f",
  "public/api/media/info.php": "c5cc1ea51a794ba74a54190384217bfbb7a83a32f6a7c38ea23dc274358bbb1d"
 }
}
```

### STEP 7 — create the changelog

Create this file. Its last two sections are written by you in Step 9.

### CREATE `changelogs/changelog-part-11a-baseline-safety.md`

Content (copy exactly; it ends with a newline):

```markdown
# changelog — Part 11A (baseline safety + AI hand-off)

**Written from:** `master-prompt-11A-baseline-safety.md`. Author of the code: the prompt author (tested in a Linux sandbox, PHP 8.3, no MySQL). The executing agent copied files and ran the checks; it did no engineering.

## 1. Security fixes (blueprint P0)
- Logout is now POST + CSRF: `public/logout.php` rewritten; the logout link in `includes/layout/sidebar.php` and the user-menu item in `includes/layout/topbar.php` became `<form method="post" action="/logout.php">` with `csrf_field()`. A GET to `/logout.php` only redirects (303) and never logs out.
- Method guards added (POST only, 405 + `Allow` header): `public/api/notifications/mark_read.php`, `public/api/templates/activate.php`, `public/api/staff/invite.php`.
- `public/api/media/upload.php` is POST-only (its `GET ?id=` branch was removed). The same lookup now lives in the new `public/api/media/info.php` (GET only, needs `media` view). No code in the repository called the old GET branch (the builder media picker uses `/api/builder/media.php`).

## 2. Tooling
- `tools/gates/php80_gate.php` — static scan for PHP 8.1+ syntax/functions (project target is PHP 8.0). It is a token scan, not a PHP 8.0 runtime.
- `tools/gates/verify_manifest.php` — checks files against the sha256 manifest `docs/ai/manifests/11A.json` (line endings ignored).
- `tools/ai/mark_part.php` — writes the part status into `docs/ai/state.json`.
- New tests: `tests/api_method_guards_test.php`, `tests/php80_gate_test.php`, `tests/ai_tools_test.php`, `tests/persian_letters_test.php`.

## 3. Hygiene + hand-off
- `.gitignore` now ignores `__pycache__/` and `*.pyc`; the two existing `__pycache__` folders (`tests/browser/`, `tools/ui/`) were deleted. The agent ran no git command.
- `docs/ai/` created: `HANDOFF.md`, `DECISIONS.md`, `FILE-MAP.md`, `state.json`, `manifests/11A.json`.

## 4. Not verified by the author (be honest)
MySQL behaviour of `info.php`/`upload.php`, the logged-in happy path of logout (it calls the database through `logActivity`), a real browser look of the two logout buttons, PHP 8.0 runtime.

## Deviations from the prompt
(The executing agent replaces this line with `none`, or with one bullet per deviation: what, why, which file.)

## Gate outputs (verbatim)
(The executing agent pastes below this line the real output of every command the prompt's Acceptance Checklist asks for, unedited.)
```

### STEP 8 — verification (run all, record real output)

```text
php tools/gates/verify_manifest.php docs/ai/manifests/11A.json
php tests/run_all.php
php tools/gates/php80_gate.php
```

Expected:
- manifest: last line `manifest 11A: 9 ok, 0 bad` and no `MISSING`/`MISMATCH` lines. If a file shows `MISMATCH`, you mis-copied it: re-copy that file from this prompt (do not edit the manifest) and run again.
- tests: every line `OK`, no `FAIL`; line count = baseline + 4 (author's copy: 16). The new lines are `ai_tools_test`, `api_method_guards_test`, `php80_gate_test`, `persian_letters_test`.
- gate: last line `php80 gate: scanned <N> file(s), violations: 0` (N differs per copy; `violations: 0` is what matters). If it reports a violation in an **existing owner file**, do not fix it: record it in the changelog under Deviations (file, line, message) and continue — it is information for the owner.

### STEP 9 — finish the changelog and the state file

1. In `changelogs/changelog-part-11a-baseline-safety.md`: replace the line under "Deviations from the prompt" with `none` or one bullet per deviation; paste the verbatim outputs of Step 8 (and the Prerequisites baseline output + backup path) under "Gate outputs (verbatim)".
2. Run: `php tools/ai/mark_part.php 11A done "baseline safety + hand-off set applied"` (use `partial` or `blocked` instead of `done` if a step was stopped). It must print `state.json: 11A = done`.

## 8. Acceptance Checklist

- [ ] Prerequisites passed; baseline tests were green; backup path recorded.
- [ ] 9 files listed in `docs/ai/manifests/11A.json` verified `0 bad`.
- [ ] `public/logout.php` replaced; `includes/layout/sidebar.php` and `topbar.php` contain a `<form method="post" action="/logout.php">` with `csrf_field()` and **no** `href="/logout.php"`.
- [ ] `mark_read.php`, `activate.php`, `invite.php` have the POST guard; `upload.php` has no `$_GET`; `info.php` exists.
- [ ] Two `__pycache__` folders deleted; `.gitignore` extended.
- [ ] `php tests/run_all.php`: all `OK`, baseline + 4 lines.
- [ ] `php tools/gates/php80_gate.php`: `violations: 0` (or each violation recorded as information).
- [ ] `docs/ai/` complete (5 files), `state.json` shows `11A` with status `done`.
- [ ] Changelog complete: Deviations filled, outputs pasted, backup path written.
- [ ] No file outside the tree in section 2 was modified.

## 9. Final message to the owner (Persian)

Write a short Persian report: what was done (by step), the real outputs (manifest line, test line count, gate line), any deviation, and this list of **manual checks for the owner** (you cannot do them): (1) open the panel, log in, press «خروج از حساب» in the sidebar → you must land on the login page; (2) open the user menu in the top bar and press «خروج» → same; (3) type `/logout.php` in the address bar while logged in → you must **stay logged in** and be sent to the dashboard.
