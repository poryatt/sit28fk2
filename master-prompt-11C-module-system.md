# MASTER PROMPT — Part 11C: Module system (manifest, registry, console, sample module)

> **Series:** Part 11 — Admin Framework / Starter Kit. **Execution order: 11A → 11B → 11C (strictly in this order).** This file is **11C**. It needs 11A and 11B applied.
> **Prepared by:** Claude (prompt author) for the owner, Pouya. **Executor:** the OpenCode agent (you).
> **Target:** PHP 8.0 / MySQL / XAMPP on Windows, Apache DocumentRoot = `public/`.

> **Verification banner (honest).** Author-tested in a Linux sandbox (PHP 8.3, no MySQL, no Apache) on a copy of the real repository with 11A and 11B applied first: 93 module-system unit checks pass (manifest validation, version constraints, registry scan/resolve/cycle detection, registries, bootstrapper, provider contract, route collisions, legacy menu bridge, console commands), plus an HTTP smoke test of the sample module with PHP's built-in server. Static PHP-8.0 gate: 0 violations.
> **NOT verified by the author:** how the sidebar looks in a real browser with a module enabled, `php bin/console` on Windows, a real MySQL-backed owner session reaching `/example/ping`, PHP 8.0 runtime.

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

Add the **module system** on top of the 11B kernel:

- A module is a folder `modules/<id>/` with a manifest `module.php` (fields: id, name, version, core, dependencies, providers, routes, migrations, permissions, navigation, settings, assets).
- `ModuleRegistry` finds modules, validates manifests, checks core/dependency versions, orders the enabled ones (dependencies first) and detects cycles.
- `ModuleBootstrapper` (switched on in `config/kernel.php`) loads enabled modules during `Application::boot()`: autoload prefix `Modules\<Studly>\`, permissions, menu entries, routes, providers.
- Enabled modules are listed in `config/modules.php` (**default: none**). A sample module `modules/example/` ships **disabled**.
- `includes/module_nav.php` + a two-line change in `includes/header.php`: the existing sidebar menu gets the menu entries of enabled modules. **With no module enabled the panel is exactly as before.**
- `php bin/console modules:list | modules:check | routes:list`.
- `docs/modules.md` (the contract), tests.

**Changes / completions the prompt author made to the owner's spec (stated openly):**
- Blueprint §14 describes modules with `module.php`, `routes.php`, `migrations/`, `src/`, `resources/`. 11C implements manifest, routes, `src/` autoload, permissions, navigation, providers, dependencies and core version. `migrations`, `settings`, `assets` are **validated for shape only**; applying migrations belongs to the database part (11D), assets/views to the UI part (11E).
- The earlier outline of this series included a widget registry and file-reference contracts. **Moved to a later part (11F)**: the manifest has no widget field yet, so nothing would consume them.
- Module permissions are **collected, not stored**: nothing is written to the `permissions` table. Consequence (by design, fail-closed): on a module route protected by `permission:<key>.<action>`, only the **owner** passes until the database part stores module permissions.
- Module **HTML pages** are not part of 11C (they need the view layer). A module can serve JSON/API routes and add menu entries now.
- Module state lives in a file (`config/modules.php`), not in the database.

**Out of scope:** any edit to existing pages/APIs (except the two-line `includes/header.php` change), `composer.json`, the database.

## 2. File tree (only what this part touches)

```text
.
├── app/
│   ├── Bootstrap/
│   │   └── ModuleBootstrapper.php   [NEW]
│   ├── Console/
│   │   └── Console.php   [NEW]
│   └── Core/
│       ├── Modules/
│       │   ├── Module.php   [NEW]
│       │   ├── ModuleException.php   [NEW]
│       │   ├── ModuleManifest.php   [NEW]
│       │   ├── ModuleNav.php   [NEW]
│       │   ├── ModuleProviderInterface.php   [NEW]
│       │   ├── ModuleRegistry.php   [NEW]
│       │   ├── NavRegistry.php   [NEW]
│       │   ├── PermissionRegistry.php   [NEW]
│       │   └── PrefixAutoloader.php   [NEW]
│       └── Support/
│           └── Version.php   [NEW]
├── bin/
│   └── console   [NEW]
├── changelogs/
│   └── changelog-part-11c-module-system.md   [NEW]
├── config/
│   ├── kernel.php   [CHANGED]
│   └── modules.php   [NEW]
├── docs/
│   ├── ai/
│   │   ├── manifests/
│   │   │   └── 11C.json   [NEW]
│   │   ├── DECISIONS.md   [CHANGED]
│   │   ├── FILE-MAP.md   [CHANGED]
│   │   └── HANDOFF.md   [CHANGED]
│   └── modules.md   [NEW]
├── includes/
│   ├── header.php   [CHANGED]
│   └── module_nav.php   [NEW]
├── modules/
│   └── example/
│       ├── src/
│       │   └── Controllers/
│       │       └── PingController.php   [NEW]
│       ├── README.md   [NEW]
│       ├── module.php   [NEW]
│       └── routes.php   [NEW]
└── tests/
    ├── modules_registry_boot_test.php   [NEW]
    └── modules_version_manifest_test.php   [NEW]
```

## 3. Database design (PK/FK) — **no schema change**

- **No table, column, index, PK or FK is created, altered or dropped; no migration.** Do not open `database/`.
- Reason: which modules are enabled is configuration (`config/modules.php`), and module permissions are only collected in memory in this part. A `modules` table and permission rows would need design decisions (PK/FK, uniqueness of `(module, key)`, ownership by `panel_users`, migration per module) that belong to the database part (11D), where they will be written with their reasons. Doing it here would also make `php bin/console modules:check` depend on MySQL.
- The existing `permissions` table and its keys stay as they are.

## 4. Library decision

**No library is added; `composer.json` / `composer.lock` untouched.**

| Option considered | Decision | Reason |
|---|---|---|
| `composer/semver` (version constraints) | not used | Not in `composer.lock`; Packagist was unreachable for the author, so it could not be added and locked. `App\Core\Support\Version` implements the small subset needed (`>= <= > < =`, `*`, space-separated AND) with tests; `^` and `~` are intentionally unsupported. |
| Composer plugins / `wikimedia/composer-merge-plugin` for module autoload | not used | Module classes autoload through `PrefixAutoloader` (`Modules\<Studly>\` → `modules/<id>/src/`), no Composer step. |
| `symfony/console` | not used | Three commands do not justify a dependency; `App\Console\Console` is ~120 lines with tests. |

## 5. "Must NOT do" table

| Do NOT | Why |
|---|---|
| Enable `example` in `config/modules.php` | It ships disabled on purpose; the owner decides. `modules:check` must report `0 enabled`. |
| Edit anything in `includes/header.php` except the one replacement given | The page shell is verified work from earlier parts. |
| Add module permissions to the database or touch `database/` | Fail-closed by design; 11D does it. |
| Put business logic into `modules/example/` | It is only a sample of the contract. |
| Change `config/kernel.php` beyond the two given edits | Other keys are exact and used by 11B tests. |
| Change 11A/11B files (the manifests would report `MISMATCH`) | They are frozen; this part only adds files and the listed edits. |
| Make `modules:check` print extra text or change exit codes | The console is tested; CI will rely on the exit code. |
| Use `^` or `~` constraints in manifests, or invent manifest fields | Unknown fields are errors by design. |
| Rewrite Persian strings | They are exact (ی ک only). |

## 6. Prerequisites (check first — STOP if any fails)

Work from the **project root**.

1. 11A and 11B must be applied: `php tools/gates/verify_manifest.php docs/ai/manifests/11A.json` → `manifest 11A: 9 ok, 0 bad`; `php tools/gates/verify_manifest.php docs/ai/manifests/11B.json` → `manifest 11B: 27 ok, 0 bad`; and `docs/ai/state.json` has `11A` and `11B` with `"status": "done"`. Otherwise STOP and tell the owner which part is missing.
2. These must **exist**: `config/kernel.php` (it must contain the line `    'bootstrappers' => [],`), `includes/header.php`, `app/Bootstrap/Application.php`, `docs/ai/FILE-MAP.md`, `docs/ai/DECISIONS.md`, `docs/ai/HANDOFF.md`.
3. These must **not exist** (else 11C was already applied → STOP and ask): `modules/`, `bin/`, `config/modules.php`, `includes/module_nav.php`, `app/Core/Modules/`, `app/Console/`, `docs/modules.md`.
4. `php -v` ≥ 8.0 (record). `php tests/run_all.php` → all `OK`; record the line count (author's copy before 11C: 20).

### Backup
Copy the whole project folder to `../panel-backups/pre-11C/` (exclude `vendor/`, `node_modules/`, `.git/`; Windows example: `robocopy . ..\panel-backups\pre-11C /E /XD vendor node_modules .git`). If it exists use `pre-11C-2`. Write the path in the changelog.

## 7. Steps

### STEP 1 — create the module-system classes

Create folders as needed; after each file run `php -l <file>` → `No syntax errors detected`.

### CREATE `app/Core/Support/Version.php`

Content (copy exactly; it ends with a newline):

```php
<?php
declare(strict_types=1);

namespace App\Core\Support;

use InvalidArgumentException;

/**
 * Tiny version helper. Versions are 1 to 3 dot-separated numbers ("1", "1.2", "1.2.3"; missing parts are 0).
 * Constraints: "*" (anything) or one or more space-separated comparisons such as ">=1.0" or ">=1.2.0 <2.0.0".
 * Operators: >= <= > < = (no operator means =). All comparisons must hold. No "^" or "~" on purpose: keep it explicit.
 */
final class Version
{
    public static function isValid(string $version): bool
    {
        return (bool) preg_match('/^\d{1,6}(\.\d{1,6}){0,2}$/D', $version);
    }

    public static function isValidConstraint(string $constraint): bool
    {
        $constraint = trim($constraint);
        if ($constraint === '*') {
            return true;
        }
        if ($constraint === '') {
            return false;
        }
        foreach (preg_split('/\s+/', $constraint) ?: [] as $part) {
            if (!preg_match('/^(>=|<=|>|<|=)?\d{1,6}(\.\d{1,6}){0,2}$/D', $part)) {
                return false;
            }
        }
        return true;
    }

    /** -1, 0 or 1. */
    public static function compare(string $a, string $b): int
    {
        $pa = self::parts($a);
        $pb = self::parts($b);
        for ($i = 0; $i < 3; $i++) {
            if ($pa[$i] !== $pb[$i]) {
                return $pa[$i] < $pb[$i] ? -1 : 1;
            }
        }
        return 0;
    }

    public static function satisfies(string $version, string $constraint): bool
    {
        if (!self::isValidConstraint($constraint)) {
            throw new InvalidArgumentException("Invalid version constraint: $constraint");
        }
        $constraint = trim($constraint);
        if ($constraint === '*') {
            return true;
        }
        foreach (preg_split('/\s+/', $constraint) ?: [] as $part) {
            preg_match('/^(>=|<=|>|<|=)?(.+)$/D', $part, $m);
            $op = $m[1] === '' ? '=' : $m[1];
            $cmp = self::compare($version, $m[2]);
            $ok = $op === '>=' ? $cmp >= 0 : ($op === '<=' ? $cmp <= 0 : ($op === '>' ? $cmp > 0 : ($op === '<' ? $cmp < 0 : $cmp === 0)));
            if (!$ok) {
                return false;
            }
        }
        return true;
    }

    /** @return int[] three numbers */
    private static function parts(string $v): array
    {
        if (!self::isValid($v)) {
            throw new InvalidArgumentException("Invalid version: $v");
        }
        $p = array_map('intval', explode('.', $v));
        return [$p[0], $p[1] ?? 0, $p[2] ?? 0];
    }
}
```

### CREATE `app/Core/Modules/ModuleException.php`

Content (copy exactly; it ends with a newline):

```php
<?php
declare(strict_types=1);

namespace App\Core\Modules;

use RuntimeException;

/** Module problem. errors() lists every problem found, message() joins them. */
final class ModuleException extends RuntimeException
{
    /** @var string[] */
    private array $errors;

    /** @param string[] $errors */
    public function __construct(array $errors)
    {
        parent::__construct(implode("\n", $errors));
        $this->errors = $errors;
    }

    /** @return string[] */
    public function errors(): array
    {
        return $this->errors;
    }
}
```

### CREATE `app/Core/Modules/ModuleProviderInterface.php`

Content (copy exactly; it ends with a newline):

```php
<?php
declare(strict_types=1);

namespace App\Core\Modules;

use App\Bootstrap\Application;

/** A module may list provider classes in its manifest. register() runs once, after the module's routes are loaded. */
interface ModuleProviderInterface
{
    public function register(Application $app): void;
}
```

### CREATE `app/Core/Modules/Module.php`

Content (copy exactly; it ends with a newline):

```php
<?php
declare(strict_types=1);

namespace App\Core\Modules;

/** One validated module: its manifest plus where it lives. Immutable data holder. */
final class Module
{
    private string $path;
    private array $manifest;

    public function __construct(string $path, array $manifest)
    {
        $this->path = rtrim($path, '/\\');
        $this->manifest = $manifest;
    }

    public function id(): string
    {
        return $this->manifest['id'];
    }

    public function name(): string
    {
        return $this->manifest['name'];
    }

    public function version(): string
    {
        return $this->manifest['version'];
    }

    public function path(): string
    {
        return $this->path;
    }

    public function manifest(): array
    {
        return $this->manifest;
    }

    /** @return array<string,string> module id => version constraint */
    public function dependencies(): array
    {
        return $this->manifest['dependencies'];
    }

    public function coreConstraint(): string
    {
        return $this->manifest['core'];
    }

    /** "hrm" => "Hrm", "leave_requests" => "LeaveRequests". Namespace of the module is Modules\<Studly>\ and lives in <path>/src. */
    public function studly(): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $this->id())));
    }
}
```

### CREATE `app/Core/Modules/ModuleManifest.php`

Content (copy exactly; it ends with a newline):

```php
<?php
declare(strict_types=1);

namespace App\Core\Modules;

use App\Core\Support\Version;

/**
 * Validates and normalises a module manifest (the array returned by modules/<id>/module.php).
 * Fields: id, name, version, core, dependencies, providers, routes, migrations, permissions, navigation, settings, assets.
 * Unknown fields are errors (they are almost always typos). Fields that a later part will use (migrations, settings, assets)
 * are validated for shape only.
 */
final class ModuleManifest
{
    public const ACTIONS = ['view', 'edit', 'delete'];
    /** Ids and permission keys of the existing panel; a module may not take them. */
    public const RESERVED = ['core', 'app', 'auth', 'dashboard', 'pages', 'articles', 'templates', 'media', 'licenses', 'settings', 'staff', 'profile', 'users', 'roles', 'notifications'];
    private const KEYS = ['id', 'name', 'version', 'core', 'dependencies', 'providers', 'routes', 'migrations', 'permissions', 'navigation', 'settings', 'assets'];

    /** @return string[] problems; empty array = valid */
    public static function validate(array $m, string $dirName): array
    {
        $e = [];
        foreach (array_keys($m) as $k) {
            if (!in_array($k, self::KEYS, true)) {
                $e[] = "unknown manifest field '$k'";
            }
        }
        $id = $m['id'] ?? null;
        if (!is_string($id) || !preg_match('/^[a-z][a-z0-9_]{1,31}$/D', $id)) {
            $e[] = "id must match [a-z][a-z0-9_]{1,31}";
        } else {
            if ($id !== $dirName) {
                $e[] = "id '$id' must equal the folder name '$dirName'";
            }
            if (in_array($id, self::RESERVED, true)) {
                $e[] = "id '$id' is reserved";
            }
        }
        if (!isset($m['name']) || !is_string($m['name']) || trim($m['name']) === '' || mb_strlen($m['name'], 'UTF-8') > 80) {
            $e[] = 'name must be a non-empty string of at most 80 characters';
        }
        if (!isset($m['version']) || !is_string($m['version']) || !preg_match('/^\d+\.\d+\.\d+$/D', $m['version'])) {
            $e[] = 'version must look like 1.2.3';
        }
        if (!isset($m['core']) || !is_string($m['core']) || !Version::isValidConstraint($m['core'])) {
            $e[] = "core must be a version constraint such as '>=1.0.0'";
        }
        $deps = $m['dependencies'] ?? [];
        if (!is_array($deps)) {
            $e[] = 'dependencies must be an array id => constraint';
        } else {
            foreach ($deps as $depId => $constraint) {
                if (!is_string($depId) || !preg_match('/^[a-z][a-z0-9_]{1,31}$/D', $depId)) {
                    $e[] = 'dependencies: bad module id';
                } elseif ($depId === $id) {
                    $e[] = 'a module cannot depend on itself';
                } elseif (!is_string($constraint) || !Version::isValidConstraint($constraint)) {
                    $e[] = "dependencies: bad constraint for '$depId'";
                }
            }
        }
        $providers = $m['providers'] ?? [];
        if (!is_array($providers)) {
            $e[] = 'providers must be a list of class names';
        } else {
            foreach ($providers as $p) {
                if (!is_string($p) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/D', $p)) {
                    $e[] = 'providers: bad class name';
                }
            }
        }
        foreach (['routes' => false, 'migrations' => true] as $field => $isDir) {
            $v = $m[$field] ?? null;
            if ($v !== null && (!is_string($v) || !self::safeRelative($v))) {
                $e[] = "$field must be null or a relative path inside the module (no '..', no leading slash)";
            }
        }
        $permKeys = [];
        $perms = $m['permissions'] ?? [];
        if (!is_array($perms)) {
            $e[] = 'permissions must be a list';
        } else {
            foreach ($perms as $i => $p) {
                $key = is_array($p) ? ($p['key'] ?? null) : null;
                if (!is_array($p) || !is_string($key) || !preg_match('/^[a-z][a-z0-9_]{1,31}$/D', $key)) {
                    $e[] = "permissions[$i]: key must match [a-z][a-z0-9_]{1,31}";
                    continue;
                }
                if (is_string($id) && $key !== $id && strncmp($key, $id . '_', strlen($id) + 1) !== 0) {
                    $e[] = "permissions[$i]: key '$key' must be '$id' or start with '{$id}_'";
                }
                if (in_array($key, $permKeys, true)) {
                    $e[] = "permissions[$i]: duplicate key '$key'";
                }
                $permKeys[] = $key;
                if (!isset($p['label']) || !is_string($p['label']) || trim($p['label']) === '') {
                    $e[] = "permissions[$i]: label is required";
                }
                $actions = $p['actions'] ?? null;
                if (!is_array($actions) || $actions === [] || array_diff($actions, self::ACTIONS) !== []) {
                    $e[] = "permissions[$i]: actions must be a non-empty subset of view, edit, delete";
                }
            }
        }
        $nav = $m['navigation'] ?? [];
        if (!is_array($nav)) {
            $e[] = 'navigation must be a list';
        } else {
            foreach ($nav as $i => $n) {
                if (!is_array($n)) {
                    $e[] = "navigation[$i]: must be an array";
                    continue;
                }
                foreach (['group', 'label'] as $f) {
                    if (!isset($n[$f]) || !is_string($n[$f]) || trim($n[$f]) === '') {
                        $e[] = "navigation[$i]: $f is required";
                    }
                }
                if (!isset($n['icon']) || !is_string($n['icon']) || !preg_match('/^ti-[a-z0-9-]+$/D', $n['icon'])) {
                    $e[] = "navigation[$i]: icon must be a Tabler class such as ti-bolt";
                }
                if (!isset($n['url']) || !is_string($n['url']) || !preg_match('#^/[A-Za-z0-9_\-./]*$#D', $n['url']) || strpos($n['url'], '//') !== false) {
                    $e[] = "navigation[$i]: url must be a local path starting with /";
                }
                if (!isset($n['permission']) || !is_string($n['permission']) || !in_array($n['permission'], $permKeys, true)) {
                    $e[] = "navigation[$i]: permission is required and must be one of this module's permission keys";
                }
            }
        }
        if (!is_array($m['settings'] ?? [])) {
            $e[] = 'settings must be an array';
        }
        $assets = $m['assets'] ?? [];
        if (!is_array($assets)) {
            $e[] = 'assets must be an array with optional css/js lists';
        } else {
            foreach ($assets as $kind => $files) {
                if (!in_array($kind, ['css', 'js'], true) || !is_array($files)) {
                    $e[] = "assets: only 'css' and 'js' lists are allowed";
                    continue;
                }
                foreach ($files as $f) {
                    if (!is_string($f) || !self::safeRelative($f)) {
                        $e[] = "assets.$kind: bad path";
                    }
                }
            }
        }
        return $e;
    }

    /** Fills defaults so every optional field exists. Call only after validate() returned no problems. */
    public static function normalize(array $m): array
    {
        return $m + [
            'dependencies' => [], 'providers' => [], 'routes' => null, 'migrations' => null,
            'permissions' => [], 'navigation' => [], 'settings' => [], 'assets' => ['css' => [], 'js' => []],
        ];
    }

    private static function safeRelative(string $p): bool
    {
        return $p !== '' && $p[0] !== '/' && $p[0] !== '\\' && strpos($p, '..') === false && strpos($p, ':') === false && strpos($p, "\0") === false;
    }
}
```

### CREATE `app/Core/Modules/PermissionRegistry.php`

Content (copy exactly; it ends with a newline):

```php
<?php
declare(strict_types=1);

namespace App\Core\Modules;

/**
 * Permissions declared by enabled modules. This part only COLLECTS them. Writing them into the `permissions` table (so that
 * staff can be granted them) is a later part; until then only the owner passes a 'permission:<key>.<action>' check on a module route.
 */
final class PermissionRegistry
{
    /** @var array<string,array{module:string,key:string,label:string,actions:string[]}> */
    private array $items = [];

    public function add(string $moduleId, array $permission): void
    {
        $key = (string) $permission['key'];
        if (isset($this->items[$key])) {
            throw new ModuleException(["permission key '$key' is declared twice (modules '{$this->items[$key]['module']}' and '$moduleId')"]);
        }
        $this->items[$key] = ['module' => $moduleId, 'key' => $key, 'label' => (string) $permission['label'], 'actions' => array_values($permission['actions'])];
    }

    /** @return array<string,array{module:string,key:string,label:string,actions:string[]}> */
    public function all(): array
    {
        return $this->items;
    }

    public function has(string $key): bool
    {
        return isset($this->items[$key]);
    }
}
```

### CREATE `app/Core/Modules/NavRegistry.php`

Content (copy exactly; it ends with a newline):

```php
<?php
declare(strict_types=1);

namespace App\Core\Modules;

/** Sidebar entries contributed by modules, in the same array shape as includes/nav.php so the existing sidebar can print them. */
final class NavRegistry
{
    /** @var array<string,array<int,array<string,string>>> group title => items */
    private array $groups = [];

    public function add(string $moduleId, array $item, int $index): void
    {
        $this->groups[(string) $item['group']][] = [
            'key' => $moduleId . ':' . $index,
            'label' => (string) $item['label'],
            'icon' => (string) $item['icon'],
            'url' => (string) $item['url'],
            'visible' => 'perm:' . $item['permission'],
            'match' => 'path',
        ];
    }

    /** @return array<int,array{title:string,items:array<int,array<string,string>>}> */
    public function groups(): array
    {
        $out = [];
        foreach ($this->groups as $title => $items) {
            $out[] = ['title' => (string) $title, 'items' => $items];
        }
        return $out;
    }
}
```

### CREATE `app/Core/Modules/PrefixAutoloader.php`

Content (copy exactly; it ends with a newline):

```php
<?php
declare(strict_types=1);

namespace App\Core\Modules;

/** Autoloading for module classes: namespace Modules\<Studly>\ maps to modules/<id>/src/ (PSR-4). */
final class PrefixAutoloader
{
    public static function register(string $prefix, string $dir): void
    {
        $dir = rtrim($dir, '/\\') . '/';
        spl_autoload_register(static function (string $class) use ($prefix, $dir): void {
            if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
                return;
            }
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
        });
    }
}
```

### CREATE `app/Core/Modules/ModuleRegistry.php`

Content (copy exactly; it ends with a newline):

```php
<?php
declare(strict_types=1);

namespace App\Core\Modules;

use App\Core\Support\Version;

/**
 * Finds modules on disk (modules/<id>/module.php), validates their manifests and decides the load order of the enabled ones.
 * scan() never throws for a broken module: it records the problems (invalid()). resolve() throws ModuleException with every
 * problem that stops the enabled set from loading: missing/invalid module, core or dependency version mismatch, disabled
 * dependency, dependency cycle.
 */
final class ModuleRegistry
{
    private string $path;
    private string $coreVersion;
    /** @var array<string,Module> */
    private array $modules = [];
    /** @var array<string,string[]> folder => problems */
    private array $invalid = [];

    public function __construct(string $modulesPath, string $coreVersion)
    {
        $this->path = rtrim($modulesPath, '/\\');
        $this->coreVersion = $coreVersion;
    }

    public function scan(): self
    {
        $this->modules = [];
        $this->invalid = [];
        if (!is_dir($this->path)) {
            return $this;
        }
        $dirs = scandir($this->path) ?: [];
        sort($dirs, SORT_STRING);
        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..' || $dir[0] === '.' || $dir[0] === '_' || !is_dir($this->path . '/' . $dir)) {
                continue;
            }
            $file = $this->path . '/' . $dir . '/module.php';
            if (!is_file($file)) {
                $this->invalid[$dir] = ['folder has no module.php'];
                continue;
            }
            $manifest = (static function (string $f) {
                return require $f;
            })($file);
            if (!is_array($manifest)) {
                $this->invalid[$dir] = ['module.php must return an array'];
                continue;
            }
            $problems = ModuleManifest::validate($manifest, $dir);
            if ($problems === []) {
                $manifest = ModuleManifest::normalize($manifest);
                if ($manifest['routes'] !== null && !is_file($this->path . '/' . $dir . '/' . $manifest['routes'])) {
                    $problems[] = "routes file '{$manifest['routes']}' does not exist";
                }
            }
            if ($problems !== []) {
                $this->invalid[$dir] = $problems;
                continue;
            }
            $this->modules[$manifest['id']] = new Module($this->path . '/' . $dir, $manifest);
        }
        return $this;
    }

    /** @return array<string,Module> valid modules by id */
    public function all(): array
    {
        return $this->modules;
    }

    /** @return array<string,string[]> */
    public function invalid(): array
    {
        return $this->invalid;
    }

    public function get(string $id): ?Module
    {
        return $this->modules[$id] ?? null;
    }

    /**
     * @param string[] $enabledIds
     * @return Module[] in load order (dependencies first)
     */
    public function resolve(array $enabledIds): array
    {
        $enabled = array_values(array_unique(array_map('strval', $enabledIds)));
        sort($enabled, SORT_STRING);
        $errors = [];
        foreach ($enabled as $id) {
            if (isset($this->invalid[$id])) {
                $errors[] = "module '$id' is invalid: " . implode('; ', $this->invalid[$id]);
            } elseif (!isset($this->modules[$id])) {
                $errors[] = "module '$id' is enabled but modules/$id/module.php was not found";
            }
        }
        foreach ($enabled as $id) {
            $m = $this->modules[$id] ?? null;
            if ($m === null) {
                continue;
            }
            if (!Version::satisfies($this->coreVersion, $m->coreConstraint())) {
                $errors[] = "module '$id' needs core {$m->coreConstraint()} but core is {$this->coreVersion}";
            }
            foreach ($m->dependencies() as $depId => $constraint) {
                if (!in_array($depId, $enabled, true)) {
                    $errors[] = "module '$id' needs module '$depId' which is not enabled";
                } elseif (isset($this->modules[$depId]) && !Version::satisfies($this->modules[$depId]->version(), $constraint)) {
                    $errors[] = "module '$id' needs '$depId' $constraint but found {$this->modules[$depId]->version()}";
                }
            }
        }
        if ($errors !== []) {
            throw new ModuleException($errors);
        }

        $order = [];
        $state = [];
        $visit = function (string $id, array $stack) use (&$visit, &$order, &$state): void {
            if (($state[$id] ?? '') === 'done') {
                return;
            }
            if (($state[$id] ?? '') === 'visiting') {
                throw new ModuleException(['dependency cycle: ' . implode(' -> ', array_merge($stack, [$id]))]);
            }
            $state[$id] = 'visiting';
            $deps = array_keys($this->modules[$id]->dependencies());
            sort($deps, SORT_STRING);
            foreach ($deps as $d) {
                $visit($d, array_merge($stack, [$id]));
            }
            $state[$id] = 'done';
            $order[] = $this->modules[$id];
        };
        foreach ($enabled as $id) {
            $visit($id, []);
        }
        return $order;
    }
}
```

### CREATE `app/Core/Modules/ModuleNav.php`

Content (copy exactly; it ends with a newline):

```php
<?php
declare(strict_types=1);

namespace App\Core\Modules;

use Throwable;

/**
 * Menu entries of the enabled modules, without booting the whole application. Used by the existing PHP pages
 * (includes/module_nav.php). A broken module must never take the panel down: any problem is logged and no entries are returned.
 */
final class ModuleNav
{
    /** @return array<int,array{title:string,items:array<int,array<string,string>>}> */
    public static function groupsFor(string $basePath): array
    {
        $file = rtrim($basePath, '/\\') . '/config/modules.php';
        if (!is_file($file)) {
            return [];
        }
        try {
            $cfg = require $file;
            $enabled = is_array($cfg) && isset($cfg['enabled']) && is_array($cfg['enabled']) ? $cfg['enabled'] : [];
            if ($enabled === []) {
                return [];
            }
            $kernelFile = rtrim($basePath, '/\\') . '/config/kernel.php';
            $kernel = is_file($kernelFile) ? require $kernelFile : [];
            $coreVersion = is_array($kernel) && isset($kernel['core_version']) ? (string) $kernel['core_version'] : '1.0.0';
            $registry = (new ModuleRegistry(rtrim($basePath, '/\\') . '/modules', $coreVersion))->scan();
            $nav = new NavRegistry();
            foreach ($registry->resolve($enabled) as $module) {
                foreach ($module->manifest()['navigation'] as $i => $item) {
                    $nav->add($module->id(), $item, (int) $i);
                }
            }
            return $nav->groups();
        } catch (Throwable $e) {
            error_log('ModuleNav: ' . $e->getMessage());
            return [];
        }
    }
}
```

### CREATE `app/Bootstrap/ModuleBootstrapper.php`

Content (copy exactly; it ends with a newline):

```php
<?php
declare(strict_types=1);

namespace App\Bootstrap;

use App\Core\Modules\ModuleException;
use App\Core\Modules\ModuleProviderInterface;
use App\Core\Modules\ModuleRegistry;
use App\Core\Modules\NavRegistry;
use App\Core\Modules\PermissionRegistry;
use App\Core\Modules\PrefixAutoloader;

/**
 * Loads the enabled modules (config/modules.php "enabled"): autoload prefix, permissions, menu entries, routes, providers.
 * The three registries are always put in the container, even when no module is enabled.
 * Config overrides (used by tests): modules.enabled (array) and modules.path (folder relative to the project root).
 */
final class ModuleBootstrapper implements Bootstrapper
{
    public function bootstrap(Application $app): void
    {
        $config = $app->config();
        $path = $app->basePath() . '/' . trim((string) $config->get('modules.path', 'modules'), '/\\');
        $enabled = $config->get('modules.enabled');
        if (!is_array($enabled)) {
            $file = $app->basePath() . '/config/modules.php';
            $loaded = is_file($file) ? require $file : [];
            $enabled = is_array($loaded) && isset($loaded['enabled']) && is_array($loaded['enabled']) ? $loaded['enabled'] : [];
        }

        $registry = (new ModuleRegistry($path, (string) $config->get('core_version', '1.0.0')))->scan();
        $nav = new NavRegistry();
        $permissions = new PermissionRegistry();
        $app->container()->instance(ModuleRegistry::class, $registry);
        $app->container()->instance(NavRegistry::class, $nav);
        $app->container()->instance(PermissionRegistry::class, $permissions);
        if ($enabled === []) {
            return;
        }

        foreach ($registry->resolve($enabled) as $module) {
            PrefixAutoloader::register('Modules\\' . $module->studly() . '\\', $module->path() . '/src');
            foreach ($module->manifest()['permissions'] as $permission) {
                $permissions->add($module->id(), $permission);
            }
            foreach ($module->manifest()['navigation'] as $i => $item) {
                $nav->add($module->id(), $item, (int) $i);
            }
            $routes = $module->manifest()['routes'];
            if ($routes !== null) {
                $app->loadRoutes($module->path() . '/' . $routes);
            }
            foreach ($module->manifest()['providers'] as $class) {
                $provider = $app->container()->get($class);
                if (!$provider instanceof ModuleProviderInterface) {
                    throw new ModuleException(["module '{$module->id()}': provider $class must implement ModuleProviderInterface"]);
                }
                $provider->register($app);
            }
        }
    }
}
```

### CREATE `app/Console/Console.php`

Content (copy exactly; it ends with a newline):

```php
<?php
declare(strict_types=1);

namespace App\Console;

use App\Bootstrap\Application;
use App\Core\Modules\ModuleException;
use App\Core\Modules\ModuleRegistry;
use Throwable;

/**
 * Command line tool (bin/console). Commands: help, modules:list, modules:check, routes:list.
 * Exit codes: 0 ok, 1 a check failed, 2 usage error. Output goes through the $out callable so tests can capture it.
 */
final class Console
{
    private Application $app;
    /** @var callable */
    private $out;

    /** @param callable $out function (string $text): void */
    public function __construct(Application $app, callable $out)
    {
        $this->app = $app;
        $this->out = $out;
    }

    /** @param string[] $args arguments after the script name */
    public function run(array $args): int
    {
        $cmd = $args[0] ?? 'help';
        switch ($cmd) {
            case 'help':
            case '--help':
                $this->line('Commands:');
                $this->line('  modules:list    list modules found in modules/ and whether they are enabled');
                $this->line('  modules:check   boot the application and fail on any module or route problem');
                $this->line('  routes:list     print every route after boot');
                return 0;
            case 'modules:list':
                return $this->modulesList();
            case 'modules:check':
                return $this->modulesCheck();
            case 'routes:list':
                return $this->routesList();
        }
        $this->line("Unknown command: $cmd (try: help)");
        return 2;
    }

    private function enabledIds(): array
    {
        $enabled = $this->app->config()->get('modules.enabled');
        if (is_array($enabled)) {
            return array_map('strval', $enabled);
        }
        $file = $this->app->basePath() . '/config/modules.php';
        $loaded = is_file($file) ? require $file : [];
        return is_array($loaded) && isset($loaded['enabled']) && is_array($loaded['enabled']) ? array_map('strval', $loaded['enabled']) : [];
    }

    private function registry(): ModuleRegistry
    {
        $path = $this->app->basePath() . '/' . trim((string) $this->app->config()->get('modules.path', 'modules'), '/\\');
        return (new ModuleRegistry($path, (string) $this->app->config()->get('core_version', '1.0.0')))->scan();
    }

    private function modulesList(): int
    {
        $registry = $this->registry();
        $enabled = $this->enabledIds();
        $this->line(sprintf('%-20s %-10s %-10s %s', 'id', 'version', 'state', 'name'));
        foreach ($registry->all() as $id => $m) {
            $this->line(sprintf('%-20s %-10s %-10s %s', $id, $m->version(), in_array($id, $enabled, true) ? 'enabled' : 'disabled', $m->name()));
        }
        foreach ($registry->invalid() as $dir => $problems) {
            $this->line(sprintf('%-20s %-10s %-10s %s', $dir, '-', 'INVALID', implode('; ', $problems)));
        }
        $this->line(count($registry->all()) . ' valid, ' . count($registry->invalid()) . ' invalid, ' . count($enabled) . ' enabled');
        return 0;
    }

    private function modulesCheck(): int
    {
        $registry = $this->registry();
        $problems = [];
        foreach ($registry->invalid() as $dir => $list) {
            foreach ($list as $p) {
                $problems[] = "modules/$dir: $p";
            }
        }
        try {
            $this->app->boot();
            $this->app->validateRoutes();
        } catch (ModuleException $e) {
            foreach ($e->errors() as $p) {
                $problems[] = $p;
            }
        } catch (Throwable $e) {
            $problems[] = get_class($e) . ': ' . $e->getMessage();
        }
        if ($problems !== []) {
            foreach ($problems as $p) {
                $this->line('PROBLEM ' . $p);
            }
            $this->line('modules:check FAILED (' . count($problems) . ' problem(s))');
            return 1;
        }
        $this->line('modules:check OK (' . count($this->enabledIds()) . ' enabled, ' . count($registry->all()) . ' valid)');
        return 0;
    }

    private function routesList(): int
    {
        try {
            $this->app->boot();
        } catch (Throwable $e) {
            $this->line('boot failed: ' . $e->getMessage());
            return 1;
        }
        foreach ($this->app->router()->all() as $r) {
            $mw = $r->getMiddleware();
            $names = [];
            foreach ($mw as $m) {
                $names[] = is_string($m) ? $m : (is_object($m) ? get_class($m) : 'callable');
            }
            $this->line(sprintf('%-12s %-40s %-16s %s', implode('|', $r->methods()), $r->path(), (string) $r->getName(), implode(',', $names)));
        }
        return 0;
    }

    private function line(string $text): void
    {
        ($this->out)($text . "\n");
    }
}
```

### STEP 2 — console, configuration, menu bridge

### CREATE `bin/console`

Content (copy exactly; it ends with a newline):

```php
#!/usr/bin/env php
<?php
// bin/console — Part 11C. Run from the project root:  php bin/console help
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../app/autoload.php';

$app = App\Bootstrap\Application::create(dirname(__DIR__), ['debug' => true]);
$console = new App\Console\Console($app, function (string $text): void {
    echo $text;
});
exit($console->run(array_slice($argv, 1)));
```

### CREATE `config/modules.php`

Content (copy exactly; it ends with a newline):

```php
<?php
// config/modules.php — Part 11C. Which modules are switched on. An id here must have a folder modules/<id>/ with a valid module.php.
// To try the sample: add 'example' to the list, then run `php bin/console modules:check`.
return [
    'enabled' => [],
];
```

### CREATE `includes/module_nav.php`

Content (copy exactly; it ends with a newline):

```php
<?php
// includes/module_nav.php — Part 11C. Bridge between the existing sidebar (includes/nav.php data) and the module system.
// Pure merge function + a loader that returns [] when no module is enabled (the default), so the panel is unchanged until a module is switched on.

if (!function_exists('moduleNavMerge')) {
    /** Appends module groups to the base menu. A group with an existing title gets the new items at its end; new titles are added after the base groups. */
    function moduleNavMerge(array $base, array $extra): array
    {
        foreach ($extra as $group) {
            $placed = false;
            foreach ($base as $i => $existing) {
                if (($existing['title'] ?? null) === $group['title']) {
                    $base[$i]['items'] = array_merge($existing['items'], $group['items']);
                    $placed = true;
                    break;
                }
            }
            if (!$placed) {
                $base[] = $group;
            }
        }
        return $base;
    }

    /** Menu groups contributed by the enabled modules (config/modules.php). */
    function moduleNavGroups(string $basePath): array
    {
        $file = rtrim($basePath, '/\\') . '/config/modules.php';
        if (!is_file($file)) {
            return [];
        }
        $cfg = require $file;
        if (!is_array($cfg) || empty($cfg['enabled'])) {
            return [];
        }
        require_once rtrim($basePath, '/\\') . '/app/autoload.php';
        return \App\Core\Modules\ModuleNav::groupsFor($basePath);
    }
}
```

### STEP 3 — edits to existing files

Three small edits. After them run `php -l config/kernel.php` and `php -l includes/header.php`.

### EDIT `config/kernel.php` — E1

declare the core version and switch the module bootstrapper on.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → STOP, see rule 3):

```php
    'api_prefix' => '/api/v1',
```

**REPLACE IT WITH:**

```php
    'api_prefix' => '/api/v1',
    // Version of this core. Modules declare which core versions they accept (manifest field "core").
    'core_version' => '1.0.0',
```

### EDIT `config/kernel.php` — E2

register the module bootstrapper.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → STOP, see rule 3):

```php
    'bootstrappers' => [],
```

**REPLACE IT WITH:**

```php
    'bootstrappers' => [\App\Bootstrap\ModuleBootstrapper::class],
```

### EDIT `includes/header.php` — E3

sidebar menu = base menu + menu entries of enabled modules (nothing changes while no module is enabled).

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → STOP, see rule 3):

```php
$navGroups = uiNavVisibleGroups(require __DIR__ . '/nav.php', 'hasPermission', isOwner());
```

**REPLACE IT WITH:**

```php
require_once __DIR__ . '/module_nav.php';
$navGroups = uiNavVisibleGroups(moduleNavMerge(require __DIR__ . '/nav.php', moduleNavGroups(dirname(__DIR__))), 'hasPermission', isOwner());
```

### STEP 4 — the sample module (stays disabled)

### CREATE `modules/example/module.php`

Content (copy exactly; it ends with a newline):

```php
<?php
// Sample module (Part 11C). It is NOT enabled by default: add 'example' to config/modules.php to try it.
// It proves the module contract end to end: manifest, routes, permission declaration, menu entry, autoloaded controller.
return [
    'id' => 'example',
    'name' => 'ماژول نمونه',
    'version' => '1.0.0',
    'core' => '>=1.0.0',
    'dependencies' => [],
    'providers' => [],
    'routes' => 'routes.php',
    'migrations' => null,
    'permissions' => [
        ['key' => 'example', 'label' => 'ماژول نمونه', 'actions' => ['view']],
    ],
    'navigation' => [
        ['group' => 'نمونه', 'label' => 'آزمایش ماژول', 'icon' => 'ti-bolt', 'url' => '/example/ping', 'permission' => 'example'],
    ],
    'settings' => [],
    'assets' => ['css' => [], 'js' => []],
];
```

### CREATE `modules/example/routes.php`

Content (copy exactly; it ends with a newline):

```php
<?php
// Routes of the sample module. A routes file returns function (Router $router, Application $app): void.
use App\Bootstrap\Application;
use App\Core\Http\Router;
use Modules\Example\Controllers\PingController;

return function (Router $router, Application $app): void {
    $router->get('/example/ping', [PingController::class, 'ping'])
        ->middleware('auth', 'permission:example.view')
        ->name('example.ping');
};
```

### CREATE `modules/example/src/Controllers/PingController.php`

Content (copy exactly; it ends with a newline):

```php
<?php
declare(strict_types=1);

namespace Modules\Example\Controllers;

use App\Core\Http\Request;
use App\Core\Http\Response;

final class PingController
{
    public function ping(Request $request, array $params): Response
    {
        return Response::ok(['pong' => true, 'module' => 'example']);
    }
}
```

### CREATE `modules/example/README.md`

Content (copy exactly; it ends with a newline):

```markdown
# example module (sample)

A minimal module that shows the contract of the module system (see `docs/modules.md`).
Disabled by default. To try it: add `'example'` to `config/modules.php`, then `php bin/console modules:check`.
Do not copy business logic from here: copy the *shape* (manifest, routes file, `src/` namespace `Modules\Example\`).
```

### STEP 5 — tests

### CREATE `tests/modules_version_manifest_test.php`

Content (copy exactly; it ends with a newline):

```php
<?php
require __DIR__ . '/_kernel.php';

use App\Core\Modules\ModuleManifest;
use App\Core\Support\Version;

// ---- Version ----
check('valid versions', [Version::isValid('1'), Version::isValid('1.2'), Version::isValid('1.2.3')], [true, true, true]);
check('invalid versions', [Version::isValid(''), Version::isValid('1.2.3.4'), Version::isValid('v1'), Version::isValid('1.x')], [false, false, false, false]);
check('compare', [Version::compare('1.2.3', '1.2.3'), Version::compare('1.2', '1.2.0'), Version::compare('1.10.0', '1.9.9'), Version::compare('1.0.0', '2')], [0, 0, 1, -1]);
check('>=', [Version::satisfies('1.0.0', '>=1.0'), Version::satisfies('0.9.9', '>=1.0'), Version::satisfies('2.0.0', '>=1.0')], [true, false, true]);
check('range', [Version::satisfies('1.5.0', '>=1.2.0 <2.0.0'), Version::satisfies('2.0.0', '>=1.2.0 <2.0.0'), Version::satisfies('1.1.9', '>=1.2.0 <2.0.0')], [true, false, false]);
check('= and bare', [Version::satisfies('1.2.0', '1.2'), Version::satisfies('1.2.1', '=1.2'), Version::satisfies('1.2.1', '1.2')], [true, false, false]);
check('> < <=', [Version::satisfies('1.0.1', '>1.0.0'), Version::satisfies('1.0.0', '>1.0.0'), Version::satisfies('1.0.0', '<1.0.1'), Version::satisfies('1.0.0', '<=1.0.0')], [true, false, true, true]);
check('star', Version::satisfies('9.9.9', '*'), true);
check('constraint validity', [Version::isValidConstraint('>=1.0'), Version::isValidConstraint('^1.0'), Version::isValidConstraint(''), Version::isValidConstraint('>= 1.0'), Version::isValidConstraint('*')], [true, false, false, false, true]);
check('bad constraint throws', thrown(function () { Version::satisfies('1.0.0', '^1.0'); }), InvalidArgumentException::class);
check('bad version throws', thrown(function () { Version::compare('x', '1.0.0'); }), InvalidArgumentException::class);

// ---- ModuleManifest ----
function good(): array {
    return [
        'id' => 'hrm', 'name' => 'منابع انسانی', 'version' => '1.0.0', 'core' => '>=1.0.0',
        'dependencies' => ['users' . '_x' => '>=1.0'], 'providers' => ['Modules\\Hrm\\HrmProvider'], 'routes' => 'routes.php', 'migrations' => 'migrations',
        'permissions' => [['key' => 'hrm', 'label' => 'منابع انسانی', 'actions' => ['view', 'edit']], ['key' => 'hrm_leave', 'label' => 'مرخصی', 'actions' => ['view']]],
        'navigation' => [['group' => 'منابع انسانی', 'label' => 'کارمندان', 'icon' => 'ti-users', 'url' => '/hrm/employees', 'permission' => 'hrm']],
        'settings' => [], 'assets' => ['css' => ['resources/css/a.css'], 'js' => []],
    ];
}
function problems(array $m, string $dir = 'hrm'): array { return ModuleManifest::validate($m, $dir); }
function with(array $patch, array $remove = []): array { $m = array_replace(good(), $patch); foreach ($remove as $k) { unset($m[$k]); } return $m; }

check('a good manifest has no problems', problems(good()), []);
check('minimal manifest (only required fields) is fine', problems(['id' => 'abc', 'name' => 'x', 'version' => '0.1.0', 'core' => '*'], 'abc'), []);
check('normalize fills defaults', array_keys(ModuleManifest::normalize(['id' => 'abc', 'name' => 'x', 'version' => '0.1.0', 'core' => '*'])), ['id', 'name', 'version', 'core', 'dependencies', 'providers', 'routes', 'migrations', 'permissions', 'navigation', 'settings', 'assets']);
check('unknown field', problems(with(['colour' => 'red'])), ["unknown manifest field 'colour'"]);
check('id must equal folder', problems(good(), 'other'), ["id 'hrm' must equal the folder name 'other'"]);
check('id pattern', count(problems(with(['id' => 'Hrm']), 'Hrm')) > 0, true);
check('id with dash rejected', count(problems(with(['id' => 'a-b']), 'a-b')) > 0, true);
check('reserved id', in_array("id 'media' is reserved", problems(with(['id' => 'media', 'permissions' => [], 'navigation' => []]), 'media'), true), true);
check('name required', count(problems(with(['name' => ' '])) ) > 0, true);
check('version format', count(problems(with(['version' => '1.0'])) ) > 0, true);
check('core constraint', count(problems(with(['core' => '^1.0'])) ) > 0, true);
check('dependency on itself', count(problems(with(['dependencies' => ['hrm' => '*']]))) > 0, true);
check('dependency bad constraint', count(problems(with(['dependencies' => ['abc' => 'latest']]))) > 0, true);
check('provider class name', count(problems(with(['providers' => ['not a class']]))) > 0, true);
check('routes path traversal', count(problems(with(['routes' => '../x.php']))) > 0, true);
check('routes absolute path', count(problems(with(['routes' => '/etc/passwd']))) > 0, true);
check('routes drive path', count(problems(with(['routes' => 'C:\\x.php']))) > 0, true);
check('migrations traversal', count(problems(with(['migrations' => 'a/../../b']))) > 0, true);
$p = good(); $p['permissions'][0]['key'] = 'other';
check('permission key must start with module id', count(problems($p)) > 0, true);
$p = good(); $p['permissions'][1]['key'] = 'hrm';
check('duplicate permission key', count(problems($p)) > 0, true);
$p = good(); $p['permissions'][0]['actions'] = ['view', 'destroy'];
check('bad permission action', count(problems($p)) > 0, true);
$p = good(); $p['permissions'][0]['actions'] = [];
check('empty actions', count(problems($p)) > 0, true);
$p = good(); unset($p['permissions'][0]['label']);
check('permission label required', count(problems($p)) > 0, true);
$p = good(); $p['navigation'][0]['permission'] = 'nope';
check('nav permission must be declared', count(problems($p)) > 0, true);
$p = good(); unset($p['navigation'][0]['permission']);
check('nav permission required (default-deny)', count(problems($p)) > 0, true);
$p = good(); $p['navigation'][0]['icon'] = 'bi-house';
check('nav icon must be Tabler', count(problems($p)) > 0, true);
$p = good(); $p['navigation'][0]['url'] = 'http://evil.example/';
check('nav url must be local', count(problems($p)) > 0, true);
$p = good(); $p['navigation'][0]['url'] = '//evil.example/';
check('nav url protocol-relative rejected', count(problems($p)) > 0, true);
$p = good(); $p['assets'] = ['img' => []];
check('assets only css/js', count(problems($p)) > 0, true);
$p = good(); $p['assets'] = ['css' => ['../x.css']];
check('asset path traversal', count(problems($p)) > 0, true);
check('settings must be array', count(problems(with(['settings' => 'x']))) > 0, true);
finish('modules_version_manifest_test');
```

### CREATE `tests/modules_registry_boot_test.php`

Content (copy exactly; it ends with a newline):

```php
<?php
require __DIR__ . '/_kernel.php';
require_once __DIR__ . '/../includes/module_nav.php';

use App\Bootstrap\Application;
use App\Console\Console;
use App\Core\Http\Request;
use App\Core\Modules\ModuleException;
use App\Core\Modules\ModuleNav;
use App\Core\Modules\ModuleRegistry;
use App\Core\Modules\NavRegistry;
use App\Core\Modules\PermissionRegistry;

$root = dirname(__DIR__);

// ---------- temp project helper ----------
function rrmdir(string $d): void {
    if (!is_dir($d)) { return; }
    foreach (scandir($d) as $f) { if ($f === '.' || $f === '..') { continue; } $p = "$d/$f"; is_dir($p) ? rrmdir($p) : unlink($p); }
    rmdir($d);
}
function tmpProject(): string {
    $d = sys_get_temp_dir() . '/m11c_' . bin2hex(random_bytes(4));
    mkdir($d . '/modules', 0777, true); mkdir($d . '/routes', 0777, true); mkdir($d . '/config', 0777, true);
    file_put_contents($d . '/routes/core.php', "<?php return function (\$r, \$a) {};");
    file_put_contents($d . '/config/kernel.php', "<?php return ['core_version' => '1.2.0', 'login_url' => '/login.php'];");
    return $d;
}
function putModule(string $proj, string $id, array $manifest, bool $routes = true): void {
    $dir = "$proj/modules/$id"; mkdir("$dir/src", 0777, true);
    file_put_contents("$dir/module.php", '<?php return ' . var_export($manifest, true) . ';');
    if ($routes) { file_put_contents("$dir/routes.php", "<?php return function (\$r, \$a) {};"); }
}
function mf(string $id, array $extra = []): array {
    return array_replace(['id' => $id, 'name' => $id, 'version' => '1.0.0', 'core' => '>=1.0.0', 'routes' => 'routes.php'], $extra);
}
function ids(array $modules): array { return array_map(function ($m) { return $m->id(); }, $modules); }
function reg(string $proj, string $core = '1.2.0'): ModuleRegistry { return (new ModuleRegistry($proj . '/modules', $core))->scan(); }
function problemsOf(callable $fn): array { try { $fn(); } catch (ModuleException $e) { return $e->errors(); } return []; }

$p = tmpProject();
putModule($p, 'alpha', mf('alpha'));
putModule($p, 'beta', mf('beta', ['dependencies' => ['alpha' => '>=1.0.0']]));
putModule($p, 'gamma', mf('gamma', ['dependencies' => ['beta' => '*', 'alpha' => '*']]));
mkdir("$p/modules/no_manifest");
mkdir("$p/modules/_ignored"); mkdir("$p/modules/.hidden");
file_put_contents("$p/modules/stray.txt", 'x');
putModule($p, 'broken', ['id' => 'broken', 'name' => 'x', 'version' => 'nope', 'core' => '*']);
putModule($p, 'noroutes', mf('noroutes', ['routes' => 'missing.php']), false);
file_put_contents("$p/modules/notarray.php", '');
mkdir("$p/modules/notarray"); file_put_contents("$p/modules/notarray/module.php", '<?php return 5;');

$r = reg($p);
check('scan finds valid modules', array_keys($r->all()), ['alpha', 'beta', 'gamma']);
check('scan records invalid folders', array_keys($r->invalid()), ['broken', 'no_manifest', 'noroutes', 'notarray']);
check('invalid: no module.php', $r->invalid()['no_manifest'], ['folder has no module.php']);
check('invalid: not an array', $r->invalid()['notarray'], ['module.php must return an array']);
check('invalid: missing routes file', $r->invalid()['noroutes'], ["routes file 'missing.php' does not exist"]);
check('hidden and underscore folders are ignored', isset($r->invalid()['_ignored']) || isset($r->invalid()['.hidden']), false);
check('get()', [$r->get('alpha') !== null, $r->get('zzz')], [true, null]);
check('Module data', [$r->get('beta')->studly(), $r->get('beta')->dependencies()], ['Beta', ['alpha' => '>=1.0.0']]);
check('studly of snake id', (new App\Core\Modules\Module('/x', ['id' => 'leave_requests']))->studly(), 'LeaveRequests');

check('resolve: dependencies first', ids($r->resolve(['gamma', 'beta', 'alpha'])), ['alpha', 'beta', 'gamma']);
check('resolve: order is deterministic whatever the input order', ids($r->resolve(['alpha', 'gamma', 'beta'])), ['alpha', 'beta', 'gamma']);
check('resolve: nothing enabled', $r->resolve([]), []);
check('resolve: duplicates ignored', ids($r->resolve(['alpha', 'alpha'])), ['alpha']);
check('resolve: unknown module', problemsOf(function () use ($r) { $r->resolve(['ghost']); }), ["module 'ghost' is enabled but modules/ghost/module.php was not found"]);
check('resolve: invalid module', count(problemsOf(function () use ($r) { $r->resolve(['broken']); })), 1);
check('resolve: dependency not enabled', problemsOf(function () use ($r) { $r->resolve(['beta']); }), ["module 'beta' needs module 'alpha' which is not enabled"]);
check('all problems are reported together', count(problemsOf(function () use ($r) { $r->resolve(['gamma', 'ghost']); })), 3);
check('resolve: core too old', problemsOf(function () use ($p) { reg($p, '0.9.0')->resolve(['alpha']); }), ["module 'alpha' needs core >=1.0.0 but core is 0.9.0"]);

putModule($p, 'oldlib', mf('oldlib', ['version' => '1.0.0']));
putModule($p, 'needsnew', mf('needsnew', ['dependencies' => ['oldlib' => '>=2.0.0']]));
check('resolve: dependency version too low', problemsOf(function () use ($p) { reg($p)->resolve(['oldlib', 'needsnew']); }), ["module 'needsnew' needs 'oldlib' >=2.0.0 but found 1.0.0"]);

$p2 = tmpProject();
putModule($p2, 'aa', mf('aa', ['dependencies' => ['bb' => '*']]));
putModule($p2, 'bb', mf('bb', ['dependencies' => ['cc' => '*']]));
putModule($p2, 'cc', mf('cc', ['dependencies' => ['aa' => '*']]));
check('resolve: cycle detected', problemsOf(function () use ($p2) { reg($p2)->resolve(['aa', 'bb', 'cc']); }), ['dependency cycle: aa -> bb -> cc -> aa']);
check('scan on a missing folder is empty, not an error', (new ModuleRegistry('/no/such/dir', '1.0.0'))->scan()->all(), []);

// ---------- registries ----------
$perm = new PermissionRegistry();
$perm->add('alpha', ['key' => 'alpha', 'label' => 'A', 'actions' => ['view']]);
check('permission registry stores', [$perm->has('alpha'), $perm->all()['alpha']['actions']], [true, ['view']]);
check('permission registry rejects duplicates', thrown(function () use ($perm) { $perm->add('beta', ['key' => 'alpha', 'label' => 'A', 'actions' => ['view']]); }), ModuleException::class);
$nav = new NavRegistry();
$nav->add('alpha', ['group' => 'G', 'label' => 'L', 'icon' => 'ti-x', 'url' => '/a', 'permission' => 'alpha'], 0);
$nav->add('beta', ['group' => 'G', 'label' => 'L2', 'icon' => 'ti-y', 'url' => '/b', 'permission' => 'beta'], 0);
check('nav registry uses the legacy menu shape', $nav->groups(), [['title' => 'G', 'items' => [
    ['key' => 'alpha:0', 'label' => 'L', 'icon' => 'ti-x', 'url' => '/a', 'visible' => 'perm:alpha', 'match' => 'path'],
    ['key' => 'beta:0', 'label' => 'L2', 'icon' => 'ti-y', 'url' => '/b', 'visible' => 'perm:beta', 'match' => 'path'],
]]]);

// ---------- the real example module, end to end ----------
$logged = [];
$app = Application::create($root, ['debug' => false, 'config' => ['bootstrappers' => [App\Bootstrap\ModuleBootstrapper::class], 'modules' => ['enabled' => ['example']]]]);
$state = ['login' => true, 'can' => true];
$app->registerMiddleware('auth', function () use (&$state) { return new App\Core\Http\Middleware\AuthMiddleware(function () use (&$state) { return $state['login']; }); });
$app->registerMiddleware('permission', function (array $a) use (&$state) { return new App\Core\Http\Middleware\PermissionMiddleware(function () use (&$state) { return $state['can']; }, 'example', 'view'); });
$app->boot();
$app->validateRoutes();
$res = $app->handle(new Request('GET', '/example/ping', [], [], ['Accept' => 'application/json']));
check('example module route works', [$res->status(), $res->payload()['data']], [200, ['pong' => true, 'module' => 'example']]);
$state['can'] = false;
check('example module route enforces permission', $app->handle(new Request('GET', '/example/ping', [], [], ['Accept' => 'application/json']))->status(), 403);
$state['can'] = true; $state['login'] = false;
check('example module route enforces login (401 for JSON)', $app->handle(new Request('GET', '/example/ping', [], [], ['Accept' => 'application/json']))->status(), 401);
check('permission registered', $app->container()->get(PermissionRegistry::class)->has('example'), true);
check('nav registered in legacy shape', $app->container()->get(NavRegistry::class)->groups()[0]['items'][0]['visible'], 'perm:example');
check('module classes autoload under Modules\\Example\\', class_exists('Modules\\Example\\Controllers\\PingController'), true);

// not enabled => nothing registered, no route
$off = Application::create($root, ['config' => ['bootstrappers' => [App\Bootstrap\ModuleBootstrapper::class], 'modules' => ['enabled' => []]]]);
$off->boot();
check('disabled module adds no route', $off->handle(new Request('GET', '/example/ping', [], [], ['Accept' => 'application/json']))->status(), 404);
check('registries exist even when nothing is enabled', [count($off->container()->get(NavRegistry::class)->groups()), count($off->container()->get(PermissionRegistry::class)->all())], [0, 0]);

// provider contract + provider that is not a provider
putModule($p, 'prov', mf('prov', ['providers' => ['Modules\\Prov\\Boot']]));
file_put_contents("$p/modules/prov/src/Boot.php", "<?php namespace Modules\\Prov; use App\\Bootstrap\\Application; class Boot implements \\App\\Core\\Modules\\ModuleProviderInterface { public static \$ran = false; public function register(Application \$app): void { self::\$ran = true; } }");
$pa = Application::create($p, ['config' => ['bootstrappers' => [App\Bootstrap\ModuleBootstrapper::class], 'modules' => ['enabled' => ['prov']]]]);
$pa->boot();
check('provider register() ran', Modules\Prov\Boot::$ran, true);
putModule($p, 'badprov', mf('badprov', ['providers' => ['Modules\\Badprov\\Nope']]));
file_put_contents("$p/modules/badprov/src/Nope.php", "<?php namespace Modules\\Badprov; class Nope {}");
check('a provider without the interface is rejected', thrown(function () use ($p) { Application::create($p, ['config' => ['bootstrappers' => [App\Bootstrap\ModuleBootstrapper::class], 'modules' => ['enabled' => ['badprov']]]])->boot(); }), ModuleException::class);

// route collision between two modules is caught
putModule($p, 'c1', mf('c1', ['routes' => 'routes.php']));
putModule($p, 'c2', mf('c2'));
foreach (['c1', 'c2'] as $c) { file_put_contents("$p/modules/$c/routes.php", "<?php return function (\$r, \$a) { \$r->get('/same', function () { return 'x'; }); };"); }
$col = Application::create($p, ['config' => ['bootstrappers' => [App\Bootstrap\ModuleBootstrapper::class], 'modules' => ['enabled' => ['c1', 'c2']]]]);
$col->boot();
check('route collision between modules is detected', thrown(function () use ($col) { $col->validateRoutes(); }), LogicException::class);

// ---------- ModuleNav (legacy bridge) ----------
check('ModuleNav: no config file => []', ModuleNav::groupsFor($p2 . '/none'), []);
file_put_contents("$p/config/modules.php", "<?php return ['enabled' => []];");
check('ModuleNav: nothing enabled => []', ModuleNav::groupsFor($p), []);
putModule($p, 'navmod', mf('navmod', ['permissions' => [['key' => 'navmod', 'label' => 'N', 'actions' => ['view']]], 'navigation' => [['group' => 'مدیریت', 'label' => 'ماژول', 'icon' => 'ti-star', 'url' => '/navmod', 'permission' => 'navmod']]]));
file_put_contents("$p/config/modules.php", "<?php return ['enabled' => ['navmod']];");
$groups = ModuleNav::groupsFor($p);
check('ModuleNav: enabled module contributes its menu', $groups[0]['items'][0]['label'], 'ماژول');
file_put_contents("$p/config/modules.php", "<?php return ['enabled' => ['ghost']];");
$prev = ini_set('error_log', sys_get_temp_dir() . '/m11c_err.log');
check('ModuleNav: a broken setup returns [] instead of throwing', ModuleNav::groupsFor($p), []);
ini_set('error_log', (string) $prev);
@unlink(sys_get_temp_dir() . '/m11c_err.log');
$base = [['title' => 'مدیریت', 'items' => [['key' => 'a']]], ['title' => 'تنظیمات', 'items' => [['key' => 'b']]]];
check('moduleNavMerge: same title appends items', moduleNavMerge($base, [['title' => 'مدیریت', 'items' => [['key' => 'm']]]])[0]['items'], [['key' => 'a'], ['key' => 'm']]);
check('moduleNavMerge: new title is added last', array_column(moduleNavMerge($base, [['title' => 'نمونه', 'items' => [['key' => 'm']]]]), 'title'), ['مدیریت', 'تنظیمات', 'نمونه']);
check('moduleNavMerge: nothing to merge', moduleNavMerge($base, []), $base);
check('moduleNavGroups: default install (nothing enabled) is empty', moduleNavGroups($root), []);

// ---------- Console ----------
function con(Application $app, array $args, &$code): string { $o = ''; $c = new Console($app, function ($s) use (&$o) { $o .= $s; }); $code = $c->run($args); return $o; }
$c1 = Application::create($root, ['config' => ['bootstrappers' => [App\Bootstrap\ModuleBootstrapper::class], 'modules' => ['enabled' => ['example']]]]);
$out = con($c1, ['modules:list'], $code);
check('modules:list shows the example as enabled', [$code, (bool) preg_match('/example\s+1\.0\.0\s+enabled/', $out)], [0, true]);
$out = con($c1, ['modules:check'], $code);
check('modules:check OK', [$code, strpos($out, 'modules:check OK') !== false], [0, true]);
$out = con($c1, ['routes:list'], $code);
check('routes:list shows core and module routes', [$code, strpos($out, '/health') !== false, strpos($out, '/example/ping') !== false, strpos($out, 'permission:example.view') !== false], [0, true, true, true]);
$c2 = Application::create($p, ['config' => ['bootstrappers' => [App\Bootstrap\ModuleBootstrapper::class], 'modules' => ['enabled' => ['ghost']]]]);
$out = con($c2, ['modules:check'], $code);
check('modules:check FAILED lists problems and exits 1', [$code, strpos($out, 'PROBLEM') !== false, strpos($out, 'modules:check FAILED') !== false], [1, true, true]);
$out = con($c2, ['modules:list'], $code);
check('modules:list marks invalid folders', strpos($out, 'INVALID') !== false, true);
check('help exits 0', [con($c1, ['help'], $code) !== '', $code], [true, 0]);
con($c1, ['nonsense'], $code);
check('unknown command exits 2', $code, 2);
con($c1, [], $code);
check('no command prints help', $code, 0);

rrmdir($p); rrmdir($p2);
finish('modules_registry_boot_test');
```

### STEP 6 — documentation, manifest, changelog

### CREATE `docs/modules.md`

Content (copy exactly; it ends with a newline):

````markdown
# Module system (Part 11C)

A module is a folder `modules/<id>/` with a manifest `module.php`. The core never imports a module; modules plug in through
the manifest and through the contracts of the kernel (`docs/kernel.md`). Switch a module on by adding its id to `config/modules.php`.

```
modules/<id>/
  module.php        manifest (array)
  routes.php        returns function (Router $router, Application $app): void   (optional)
  src/              classes, namespace Modules\<Studly>\  (PSR-4; "leave_requests" -> LeaveRequests)
```

## Manifest fields
| Field | Required | Meaning |
|---|---|---|
| `id` | yes | `[a-z][a-z0-9_]{1,31}`, equals the folder name, not reserved (`ModuleManifest::RESERVED`) |
| `name` | yes | Persian display name, at most 80 characters |
| `version` | yes | `1.2.3` |
| `core` | yes | constraint on `core_version` of `config/kernel.php`, e.g. `>=1.0.0` or `*` |
| `dependencies` | no | `['other' => '>=1.0.0']`; the other module must also be enabled |
| `providers` | no | class names implementing `ModuleProviderInterface`; `register()` runs after the routes are loaded |
| `routes` | no | relative file, must exist |
| `migrations` | no | relative folder. **Only declared in 11C**; applying them is the database part |
| `permissions` | no | `[['key'=>'hrm','label'=>'...','actions'=>['view','edit']]]`; key = module id or `<id>_...` |
| `navigation` | no | `[['group','label','icon'=>'ti-...','url'=>'/path','permission'=>'<one of this module's keys>']]` |
| `settings`, `assets` | no | shape-checked only; used by later parts |

Unknown fields are errors. Version constraints: `*`, or space-separated `>=`, `<=`, `>`, `<`, `=` comparisons (no `^`/`~`).

## What a module can do today
- Add routes (JSON/API, or any `Response`). Module **HTML pages** need the UI foundation part; do not build them with `include 'header.php'` inside a handler.
- Declare permissions. They are collected into `PermissionRegistry`; they are **not yet written to the database**, so until the database part only the owner passes `permission:<key>.<action>` on a module route (fail-closed).
- Add sidebar entries: `includes/header.php` merges them into the menu (`includes/module_nav.php`).

## Commands
`php bin/console modules:list` · `php bin/console modules:check` (exit 1 on any problem) · `php bin/console routes:list`

## Rules
1. A module never edits core files. If it needs something from the core that does not exist, write it down in `docs/ai/OPEN-ISSUES.md`.
2. Two modules may not define the same method + path (`validateRoutes()` and `modules:check` fail).
3. PHP 8.0 only. Persian text with ی ک only.
4. `modules/example/` is a sample: copy its shape, not its content.
````

### CREATE `docs/ai/manifests/11C.json`

Content (copy exactly; it ends with a newline):

```json
{
 "part": "11C",
 "files": {
  "app/Bootstrap/ModuleBootstrapper.php": "79f088b59a5f843ab77cd0c00cb574e8ce51c52954bf92196a7776a1105e846e",
  "app/Console/Console.php": "f87fc7e92fdf529e9b39f35d32f0a8e87de55169bb79ca4aae91e364e35f6954",
  "app/Core/Modules/Module.php": "2cbb20994d3c09756aabc1a06293a760a4b8ab62bd00b48a309bc7af6e7c1ebc",
  "app/Core/Modules/ModuleException.php": "085b28fc12588921c770f39f3bd8872f3cffab1e4c720dda01828dbc9a7a686a",
  "app/Core/Modules/ModuleManifest.php": "10b0f84ff892ed34031e5d3126f8a0167866f2885312a24da955337d7249f258",
  "app/Core/Modules/ModuleNav.php": "7f9332bca88d4e3394e17c983c9cfbf22a69c9581334fb4a9a0a63574f3b1c66",
  "app/Core/Modules/ModuleProviderInterface.php": "a4c289e1103fee624e5ffeaf31c48f8cd53dbc64068969b27acd067e7a0a0f1d",
  "app/Core/Modules/ModuleRegistry.php": "3c645b2a4634502b70b38658175ababcc13c7293cc03c4b2b29451e35d1dc2bc",
  "app/Core/Modules/NavRegistry.php": "bc738a4e14e3986004d8beacc560d0858d3487ec8e3fb87b3b564f339a81c2fa",
  "app/Core/Modules/PermissionRegistry.php": "e7f9d9f4c95aa03e0a841ac88125e557ef8d24acc34e6471632608e202f62822",
  "app/Core/Modules/PrefixAutoloader.php": "f5892139fddf9336ba447e1d896260819d5bb467fdf5730a2cce1aa74c6ff98d",
  "app/Core/Support/Version.php": "696c7d54c6c3417ddca20b65a48c01471c7a7525ebdae96b473a6c4498b768e2",
  "bin/console": "6eb8469ef228a0abd5780f43682af4d7a56903973361cf7a03625ccc7dedb67a",
  "includes/module_nav.php": "f61b9d78f82edc8b6102dadb3d26cab68dc6fe719e65b5cae34d4696dbcd244e",
  "modules/example/README.md": "cdaf5f2e9185f73e7f902d279263523260384459f180a08830455aa67ca21f01",
  "modules/example/module.php": "542bc9c1f5a6c4317cd1b05a8b18d7df4c4ae93e4bafdd60d36b999baeb4f0b6",
  "modules/example/routes.php": "e49897e195cacf91155c333cba2d57bd35e1d08edbc2c3f29b646727720460d0",
  "modules/example/src/Controllers/PingController.php": "ed807ffb942f5bfe35e094451f03f10e4fae074a02451262061760c7b10156d4",
  "tests/modules_registry_boot_test.php": "e5321ca0ff1185c676feb7a8d74993c49f5b459d2ae688291f0d6b07f898eace",
  "tests/modules_version_manifest_test.php": "557aa92bfe1154a2850b51c37a38bf5167b308f8eb7a40246d2c1132b8505744"
 }
}
```

### CREATE `changelogs/changelog-part-11c-module-system.md`

Content (copy exactly; it ends with a newline):

```markdown
# changelog — Part 11C (module system)

**Written from:** `master-prompt-11C-module-system.md`. Code by the prompt author, tested in a Linux sandbox (PHP 8.3, no MySQL): 93 unit checks, the console commands, and an HTTP smoke test of the sample module. The executing agent only copied files and ran checks.

## 1. What was added
- Module contract: manifest validation (`ModuleManifest`), `Version`, `ModuleRegistry` (scan, dependency/core checks, load order, cycle detection), `PermissionRegistry`, `NavRegistry`, `PrefixAutoloader`, `ModuleNav`, `ModuleBootstrapper` (switched on in `config/kernel.php`).
- `config/modules.php` — enabled module ids (default: none). `core_version` = `1.0.0` in `config/kernel.php`.
- `includes/module_nav.php` + a two-line change in `includes/header.php`: the sidebar menu is the old menu plus entries of enabled modules (nothing changes while no module is enabled).
- `bin/console`: `modules:list`, `modules:check`, `routes:list`.
- `modules/example/`: a sample module, **disabled**. `docs/modules.md` explains the contract.
- No database change. Module permissions are collected but not stored in the database yet (owner-only on module routes).

## 2. Not verified by the author (be honest)
The sidebar rendering of module entries in a real browser, `bin/console` on Windows (`php bin/console ...` is the supported form), PHP 8.0 runtime, MySQL.

## Deviations from the prompt
(The executing agent replaces this line with `none`, or with one bullet per deviation: what, why, which file.)

## Gate outputs (verbatim)
(The executing agent pastes below this line the real output of every command the prompt's Acceptance Checklist asks for, unedited.)
```

### STEP 7 — hand-off docs (append rows)

### EDIT `docs/ai/FILE-MAP.md` — D1

append this part's rows.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → STOP, see rule 3):

```markdown
| `changelogs/changelog-part-11b-http-kernel.md` | new | what the agent did and saw |
```

**REPLACE IT WITH:**

```markdown
| `changelogs/changelog-part-11b-http-kernel.md` | new | what the agent did and saw |

## Part 11C — module system
| File | Kind | Purpose |
|---|---|---|
| `app/Core/Support/Version.php` | new | version compare + constraints |
| `app/Core/Modules/ModuleManifest.php` | new | manifest validation + defaults |
| `app/Core/Modules/Module.php`, `ModuleException.php`, `ModuleProviderInterface.php` | new | value object, error, provider contract |
| `app/Core/Modules/ModuleRegistry.php` | new | scan `modules/`, resolve enabled set + load order |
| `app/Core/Modules/PermissionRegistry.php`, `NavRegistry.php` | new | collected permissions / menu entries |
| `app/Core/Modules/PrefixAutoloader.php` | new | `Modules\<Studly>\` -> `modules/<id>/src/` |
| `app/Core/Modules/ModuleNav.php` | new | menu entries for the legacy sidebar, never throws |
| `app/Bootstrap/ModuleBootstrapper.php` | new | loads enabled modules during `boot()` |
| `app/Console/Console.php`, `bin/console` | new | `help`, `modules:list`, `modules:check`, `routes:list` |
| `config/modules.php` | new | list of enabled module ids (mutable; default empty) |
| `config/kernel.php` | changed | `core_version`, bootstrapper switched on |
| `includes/module_nav.php` | new | `moduleNavMerge()` / `moduleNavGroups()` |
| `includes/header.php` | changed | sidebar menu = base menu + module entries |
| `modules/example/` (module.php, routes.php, src/Controllers/PingController.php, README.md) | new | sample module (disabled by default) |
| `tests/modules_version_manifest_test.php`, `tests/modules_registry_boot_test.php` | new | 93 checks |
| `docs/modules.md` | new | module contract |
| `docs/ai/manifests/11C.json` | new | sha256 of the frozen files of 11C |
| `changelogs/changelog-part-11c-module-system.md` | new | what the agent did and saw |
```

### EDIT `docs/ai/DECISIONS.md` — D2

append this part's decisions.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → STOP, see rule 3):

```markdown
| D-014 | 11B | Validator: Persian messages, stops at first failing rule per field, no `unique`/`exists`/wildcards. | Keeps the first version small; DB rules arrive with the database part. |
```

**REPLACE IT WITH:**

```markdown
| D-014 | 11B | Validator: Persian messages, stops at first failing rule per field, no `unique`/`exists`/wildcards. | Keeps the first version small; DB rules arrive with the database part. |
| D-015 | 11C | Enabled modules are listed in `config/modules.php` (a file), not in the database. No schema change in 11A, 11B, 11C. | Needed before the database part exists; also lets `modules:check` run without MySQL. |
| D-016 | 11C | Manifest is strict: unknown fields are errors; a navigation entry must name one of the module's own permission keys; permission keys are the module id or `<id>_...`; ids of existing panel modules are reserved. | Typos and collisions are caught by `modules:check`, not in production. |
| D-017 | 11C | Module permissions are collected (`PermissionRegistry`) but NOT written to the database. Until the database part, only the owner passes `permission:<key>.<action>` on a module route. | Fail-closed; writing to the `permissions` table is a schema/data change that belongs to the next database part. |
| D-018 | 11C | A module can add routes, permissions and menu entries. Module HTML pages wait for the UI foundation part. | Rendering needs the view layer; do not hack `header.php` into handlers. |
| D-019 | 11C | The legacy sidebar gets module entries through `includes/module_nav.php`; any module error is logged and ignored there (panel keeps working); `php bin/console modules:check` is where errors are reported. | A broken module must never lock the owner out of the panel. |
| D-020 | 11C | Widget registry and file-reference contracts from the earlier outline are moved to a later part (11F). | The manifest has no widget field yet, so nothing would consume them. |
```

### EDIT `docs/ai/HANDOFF.md` — D3

current-state bullet.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → STOP, see rule 3):

```markdown
- Not done yet (outline only, no prompt written): 11D database core v2 + setup wizard + no default credentials; 11E UI foundation v2
  (CSP, inline-JS removal); 11F service modules; 11G CMS as a module; 11H HRM pilot; 11I release/CI/docs.
```

**REPLACE IT WITH:**

```markdown
- After 11C: module system (see `docs/modules.md`): `modules/<id>/module.php`, `config/modules.php` (enabled list, default empty), `php bin/console modules:check`. `modules/example/` is a disabled sample. Module permissions are not in the database yet (owner-only).
- Not done yet (outline only, no prompt written): 11D database core v2 + setup wizard + no default credentials; 11E UI foundation v2
  (CSP, inline-JS removal); 11F service modules; 11G CMS as a module; 11H HRM pilot; 11I release/CI/docs.
```

### STEP 8 — verification (run all, record real output)

```text
php tools/gates/verify_manifest.php docs/ai/manifests/11C.json
php tools/gates/verify_manifest.php docs/ai/manifests/11B.json
php tools/gates/verify_manifest.php docs/ai/manifests/11A.json
php tests/run_all.php
php tools/gates/php80_gate.php
php bin/console modules:check
php bin/console modules:list
php bin/console routes:list
```

Expected:
- 11C manifest: `manifest 11C: 20 ok, 0 bad`; 11B and 11A manifests unchanged (`0 bad`). A `MISMATCH` means a mis-copied file: re-copy from this prompt (never edit a manifest).
- tests: all `OK`, no `FAIL`; line count = previous + 2 (new: `modules_registry_boot_test`, `modules_version_manifest_test`).
- gate: `violations: 0` (a violation in an existing owner file → do not fix, record under Deviations).
- `modules:check` → exactly `modules:check OK (0 enabled, 1 valid)` and exit code 0.
- `modules:list` → one row `example  1.0.0  disabled  ماژول نمونه` and the last line `1 valid, 0 invalid, 0 enabled`.
- `routes:list` → includes `/health` and `/api/v1/health`; does **not** include `/example/ping` (module disabled).

### STEP 9 — finish the changelog and the state file

1. In `changelogs/changelog-part-11c-module-system.md`: replace the line under "Deviations from the prompt" with `none` or one bullet per deviation; paste the verbatim outputs of Step 8 (plus the prerequisite baseline and backup path) under "Gate outputs (verbatim)".
2. Run `php tools/ai/mark_part.php 11C done "module system applied"` (`partial`/`blocked` if a step was stopped). It must print `state.json: 11C = done`.

## 8. Acceptance Checklist

- [ ] 11A and 11B verified at the start; baseline tests green; backup path recorded.
- [ ] 12 classes in `app/`, `bin/console`, `config/modules.php`, `includes/module_nav.php`, sample module (4 files), 2 tests, `docs/modules.md` created.
- [ ] `config/kernel.php` has `'core_version' => '1.0.0'` and the `ModuleBootstrapper` line; `includes/header.php` has the two-line menu change.
- [ ] `docs/ai/manifests/11C.json` verifies `20 ok, 0 bad`; 11A/11B manifests still `0 bad`.
- [ ] `php tests/run_all.php` all `OK`, previous + 2 lines.
- [ ] `php tools/gates/php80_gate.php` → `violations: 0` (or recorded).
- [ ] `php bin/console modules:check` → `modules:check OK (0 enabled, 1 valid)`.
- [ ] `config/modules.php` still has `'enabled' => []`.
- [ ] `FILE-MAP.md`, `DECISIONS.md` (D-015 … D-020), `HANDOFF.md` updated; `state.json` shows `11C` = `done`.
- [ ] Changelog complete (Deviations, outputs, backup path).
- [ ] No file outside the tree in section 2 was modified.

## 9. Final message to the owner (Persian)

Write a short Persian report: steps done, real outputs, deviations, and these **manual checks for the owner**: (1) open the panel — the sidebar must look exactly as before (no module is enabled); (2) run `php bin/console modules:check` yourself; (3) *optional trial*: add `'example'` to the list in `config/modules.php`, reload the dashboard — a new menu group «نمونه» with «آزمایش ماژول» appears for the owner; opening it shows a JSON `{"success":true,"data":{"pong":true,...}}` (needs `mod_rewrite`, see 11B); then **remove** `'example'` again; (4) tell the author that module permissions are not stored in the database yet (owner-only) — that is planned for the database part.
