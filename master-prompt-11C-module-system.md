# MASTER PROMPT 11C — Module system (declarative manifest, trust levels, fail-soft loading)

> **Series:** Part 11 (modular admin application platform) · **Revision 2** (rewritten: PHP manifest replaced by `module.json`; trust, failure policy, lifecycle and contracts defined) · **File:** `master-prompt-11C-module-system.md` · **Language of this file:** English (final message to the owner: Persian).

| Field | Value |
|---|---|
| **Status** | `ready` (authored and tested by the author; not yet executed) |
| **Outcome** | Business modules can be added as folders without editing the core. A module is described by `modules/<id>/module.json`; the core reads it without executing anything; only owner-trusted modules may run PHP; a broken module is skipped and logged instead of taking the panel down. |
| **Contract (true after this part)** | 1. Discovery never executes module code (tested with a booby-trapped `module.php`/`src` file). 2. A manifest cannot grant itself trust (needs the owner's id in `config/modules.php` `trusted`). 3. ParseError, exception in a routes file or provider, missing provider class, wrong provider type, route/name/permission collision, invalid or oversized JSON, dependency cycle, missing or failed dependency: each ends in a recorded state (`invalid`, `blocked`, `failed`), a log line in `storage/logs/`, and a healthy rest of the application. 4. `php bin/console modules:check` exits 1 on any such problem and also checks module boundaries. 5. `php tests/run_all.php` prints 28 lines (24 + 4), all `OK`. 6. With nothing enabled (the default) the panel behaves exactly as before. |
| **In scope** | Module classes, `ModuleBootstrapper`, `bin/console`, `config/modules.php`, sidebar bridge for module menu entries, sample module, four tests, `docs/modules.md` (trust, lifecycle owners, failure policy, contracts, page contract v0), hand-off docs, manifest. |
| **Out of scope** | Writing module permissions into the database (11F1), enable/disable/install/upgrade/uninstall commands and persisted module state (11G with 11D), module migrations being run (11G), view engine and shell integration of module pages (11E1/11E3), any real business module. |
| **Requires** | 11B executed. |
| **Unblocks** | 11D (module state table design), 11E3 (nav/widget registry builds on `NavRegistry`), 11F1 (permission sync reads `PermissionRegistry`), 11G (lifecycle CLI). |
| **Runtime proof** | `php tests/run_all.php`, `php bin/console modules:check`, `php bin/console modules:list`, `php tools/gates/verify_manifest.php docs/ai/manifests/11C.json`, and (if the site runs) `php tools/verify/http_smoke.php <url>`. |
| **Tested by the author** | Linux sandbox, PHP 8.3, SQLite, no MySQL, no Apache. Replayed literally on a clean copy of the post-11B tree: 28/28 tests green (166 checks in the four module tests, covering the failure scenarios listed in the contract), gate 0 violations, manifest 29 ok, `modules:check` OK; the sample module was loaded end to end (JSON route, HTML page, permission enforcement 401/403/200). |
| **NOT tested by the author** | MySQL; Apache; a staff user (not the owner) seeing a module menu item (impossible by design until 11F1 stores module permissions); the sidebar with a module entry in a browser; Windows paths; PHP 8.0 runtime. |

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

Revision 1 loaded `modules/<id>/module.php` with `require` during discovery, so one syntax error or malicious file could stop the whole panel, and it left the lifecycle, contracts, trust and failure behaviour undefined. This revision fixes that:

- **Declarative manifest.** `module.json` (schema 1) is data. Discovery only `json_decode`s it (size and depth limited); it never includes anything from a module folder.
- **Trust levels.** `declarative` modules contribute permissions and menu entries only. `trusted` modules may have `routes.php`, providers and an autoloaded `src/`; this needs the manifest word **and** the owner's id in `config/modules.php` `trusted`.
- **Fail-soft loading.** A module can be `disabled`, `invalid`, `blocked` or `failed`; the reason is kept, logged through the Logger from 11B, and the rest of the panel keeps working. Routes, permissions and menu entries are committed only when the whole module loaded.
- **Lifecycle and contracts written down** (`docs/modules.md`): who owns discover/enable/install/upgrade/uninstall (11C, 11G), dependency-disable behaviour (dependents are blocked), cross-module rules (declared dependency + `Contracts` namespace only, checked by `modules:check`).
- **Permissions are not stored here** and the doc says so (stored by 11F1).
- **Module HTML pages**: contract v0 (a route returning `Response::html`), replaced in one place by the view engine in 11E1.

**Changes the author made to the spec and to revision 1 (stated openly)**
- `module.php` is **no longer supported** (a folder that has only it is reported invalid with a hint). There are no real modules yet, so nothing is migrated.
- Reserved module ids no longer contain the removed CMS names (a future business module may be called `pages`); they now contain the core platform names (`auth`, `files`, `audit`, `modules`, …).
- Boundary checks are a **static token scan run by `modules:check` and the tests**, not on every request (speed). Runtime enforcement is not claimed.
- The enabled/trusted lists stay in a hand-edited file until 11G; this is recorded as release blocker RB-7.
- A module with `trust: trusted` that is not granted is **blocked** (valid but not loadable), not invalid, so the shipped sample module does not make `modules:check` fail on a default install.

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
│       │   ├── ModuleBoundaryScanner.php   [NEW]
│       │   ├── ModuleException.php   [NEW]
│       │   ├── ModuleManifest.php   [NEW]
│       │   ├── ModuleNav.php   [NEW]
│       │   ├── ModuleProviderInterface.php   [NEW]
│       │   ├── ModuleRegistry.php   [NEW]
│       │   ├── ModuleState.php   [NEW]
│       │   ├── ModuleStatuses.php   [NEW]
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
│   │   ├── HANDOFF.md   [CHANGED]
│   │   ├── RELEASE-BLOCKERS.md   [CHANGED]
│   │   └── state.json   [CHANGED (by the tool)]
│   └── modules.md   [NEW]
├── includes/
│   ├── header.php   [CHANGED]
│   └── module_nav.php   [NEW]
├── modules/
│   └── example/
│       ├── src/
│       │   └── Controllers/
│       │       ├── PageController.php   [NEW]
│       │       └── PingController.php   [NEW]
│       ├── README.md   [NEW]
│       ├── module.json   [NEW]
│       └── routes.php   [NEW]
└── tests/
    ├── modules_boot_test.php   [NEW]
    ├── modules_console_nav_test.php   [NEW]
    ├── modules_registry_test.php   [NEW]
    └── modules_version_manifest_test.php   [NEW]
```

## 3. Database design (PK/FK) — **no schema change**

No table, column, index, PK or FK is created, altered or dropped; there is no migration; do not open `database/`. Reason: module state in this part is derived from files (`module.json`, `config/modules.php`) at boot. A module table (id, version, enabled, installed_at, …), the sync of module permissions into `permissions`, and running a module's `migrations/` folder are database work owned by 11D (design) and 11G (CLI); the manifest field `migrations` is only shape-checked here so manifests written today stay valid. Doing the table now would freeze a design before the DB Core v2 decisions of 11D.

## 4. Library decision

**No Composer package is added; `composer.json` and `composer.lock` are not touched.** JSON uses PHP's built-in `json_decode`; version constraints use the 80-line `App\Core\Support\Version` (no `^`/`~` on purpose); boundary checks use PHP's built-in tokenizer. Rejected: `composer/semver` (adds a dependency for a feature this small) and a plugin framework (a modular monolith needs folders and a manifest, not a runtime plugin loader).

## 5. "Must NOT do" table

| Do not | Why |
|---|---|
| Enable the sample module (add ids to `config/modules.php`) | it ships disabled; the owner decides |
| Add an id to `trusted` yourself | only the owner grants PHP execution |
| Create a `module.php` or put PHP logic in `module.json` | the manifest is data |
| Edit any file other than `config/kernel.php` and `includes/header.php` (one edit each, see Step 4) | old code moves later, one file per part |
| Change the order or the text of the existing menu in `includes/nav.php` | the bridge only appends module groups |
| Make a failing module throw on a web request | fail-soft is the contract (D-024) |
| Touch `database/`, run any database command | no database step exists |
| Touch `composer.json`, `composer.lock`, `vendor/` | no library change |
| Run `git`, `composer`, `npm` | not needed |
| Rewrite Persian strings | they are exact (ی ک only) |

## 6. Prerequisites (check first)

1. 11B must be executed: `docs/ai/state.json` has `11B` with status `executed` (or `partial` only because the real-site smoke test was not possible; write which). `app/Bootstrap/Application.php`, `config/kernel.php`, `tests/_kernel.php`, `docs/ai/manifests/11B.json` must exist. Otherwise **stop**.
2. These must **not** exist (else this part was applied before → stop and ask): `app/Core/Modules/`, `app/Console/`, `bin/`, `modules/`, `config/modules.php`, `includes/module_nav.php`, `docs/modules.md`, `docs/ai/manifests/11C.json`.
3. `config/kernel.php` must contain the lines `'api_prefix' => '/api/v1',` and `'bootstrappers' => [],`; `includes/header.php` must contain the line that begins `$navGroups = uiNavVisibleGroups(`. (The FIND texts in Step 4 are the safety net.)
4. `php -v` → PHP 8.0 or newer (record it).

## 7. Steps

### STEP 1 — pre-flight (read-only)

```text
php tools/gates/verify_manifest.php docs/ai/manifests/11B.json
php tests/run_all.php
php tools/gates/php80_gate.php
```

Expected: `manifest 11B: 39 ok, 0 bad, 0 info`; 24 lines all `OK`; `violations: 0`. Record the real outputs as the baseline.

### STEP 2 — backup

Copy the whole project folder to `../panel-backups/pre-11C/` (exclude `vendor/`, `node_modules/`, `.git/`; include `storage/` and `public/uploads/`). Example (Windows): `robocopy . ..\panel-backups\pre-11C /E /XD vendor node_modules .git`. If the target exists use `pre-11C-2`. Write the path in the changelog. If you cannot copy → stop.

### STEP 3 — create the module system

All files below are new. Create the folders as needed. `bin/console` has no `.php` extension; copy it byte for byte (the first line is a shebang).

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

### CREATE `app/Core/Modules/ModuleState.php`

Content (copy exactly; it ends with a newline):

```php
<?php
declare(strict_types=1);

namespace App\Core\Modules;

/**
 * Lifecycle states of a module as the core sees them after boot.
 *   disabled : valid, but its id is not in config/modules.php "enabled"
 *   invalid  : module.json is missing, unreadable or breaks a rule (or the id is enabled but no folder exists)
 *   blocked  : valid and enabled, but the core version, a dependency or the dependency graph prevents loading
 *   failed   : valid, enabled and unblocked, but loading it threw (routes file, provider, route collision)
 *   loaded   : routes, permissions, menu entries and providers are registered
 * A module that is not "loaded" never affects the rest of the panel. The owner sees the reason with `php bin/console modules:list`.
 */
final class ModuleState
{
    public const DISABLED = 'disabled';
    public const INVALID = 'invalid';
    public const BLOCKED = 'blocked';
    public const FAILED = 'failed';
    public const LOADED = 'loaded';
}
```

### CREATE `app/Core/Modules/ModuleStatuses.php`

Content (copy exactly; it ends with a newline):

```php
<?php
declare(strict_types=1);

namespace App\Core\Modules;

/** The outcome of ModuleBootstrapper for every module the core knows about: id => state + reasons. Lives in the container. */
final class ModuleStatuses
{
    /** @var array<string,array{state:string,reasons:string[]}> */
    private array $items = [];

    /** @param string[] $reasons */
    public function set(string $id, string $state, array $reasons = []): void
    {
        $this->items[$id] = ['state' => $state, 'reasons' => array_values($reasons)];
        ksort($this->items, SORT_STRING);
    }

    public function state(string $id): ?string
    {
        return $this->items[$id]['state'] ?? null;
    }

    public function isLoaded(string $id): bool
    {
        return $this->state($id) === ModuleState::LOADED;
    }

    /** @return string[] */
    public function reasons(string $id): array
    {
        return $this->items[$id]['reasons'] ?? [];
    }

    /** @return array<string,array{state:string,reasons:string[]}> */
    public function all(): array
    {
        return $this->items;
    }

    /** @return string[] ids in this state */
    public function idsIn(string $state): array
    {
        $out = [];
        foreach ($this->items as $id => $item) {
            if ($item['state'] === $state) {
                $out[] = $id;
            }
        }
        return $out;
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

    /** Adds an item that is already in menu shape (used to commit a staged registry). @param array<string,string> $item */
    public function addRaw(string $groupTitle, array $item): void
    {
        $this->groups[$groupTitle][] = $item;
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

    /** "declarative" (data only) or "trusted" (may run its own PHP: routes, providers). */
    public function trust(): string
    {
        return $this->manifest['trust'] ?? 'declarative';
    }

    public function isTrusted(): bool
    {
        return $this->trust() === 'trusted';
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
 * Validates and normalises a module manifest (the array decoded from modules/<id>/module.json).
 * Fields: schema, id, name, version, core, description, trust, dependencies, providers, routes, migrations, permissions,
 * navigation, settings, assets. Unknown fields are errors (they are almost always typos). Fields that a later part will use
 * (migrations, settings, assets) are validated for shape only.
 *
 * Trust: "declarative" (default) modules contribute data only (permissions, menu entries): no PHP of theirs ever runs.
 * Only a module with trust "trusted" may have routes and providers, and the owner must also list its id in config/modules.php
 * ("trusted"); the manifest alone never grants trust.
 */
final class ModuleManifest
{
    public const SCHEMA = 1;
    public const ACTIONS = ['view', 'edit', 'delete'];
    public const TRUST = ['declarative', 'trusted'];
    /** Ids and permission keys that belong to the core platform; a module may not take them. */
    public const RESERVED = ['core', 'app', 'auth', 'dashboard', 'media', 'settings', 'staff', 'profile', 'users', 'roles', 'notifications', 'files', 'audit', 'modules'];
    private const KEYS = ['schema', 'id', 'name', 'version', 'core', 'description', 'trust', 'dependencies', 'providers', 'routes', 'migrations', 'permissions', 'navigation', 'settings', 'assets'];

    /** @return string[] problems; empty array = valid */
    public static function validate(array $m, string $dirName): array
    {
        $e = [];
        foreach (array_keys($m) as $k) {
            if (!in_array($k, self::KEYS, true)) {
                $e[] = "unknown manifest field '" . (is_string($k) ? $k : (string) $k) . "'";
            }
        }
        if (($m['schema'] ?? null) !== self::SCHEMA) {
            $e[] = 'schema must be the number ' . self::SCHEMA;
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
        if (isset($m['description']) && (!is_string($m['description']) || mb_strlen($m['description'], 'UTF-8') > 300)) {
            $e[] = 'description must be a string of at most 300 characters';
        }
        if (!isset($m['version']) || !is_string($m['version']) || !preg_match('/^\d+\.\d+\.\d+$/D', $m['version'])) {
            $e[] = 'version must look like 1.2.3';
        }
        if (!isset($m['core']) || !is_string($m['core']) || !Version::isValidConstraint($m['core'])) {
            $e[] = "core must be a version constraint such as '>=1.0.0'";
        }
        $trust = $m['trust'] ?? 'declarative';
        if (!is_string($trust) || !in_array($trust, self::TRUST, true)) {
            $e[] = 'trust must be "declarative" or "trusted"';
            $trust = 'declarative';
        }
        $deps = $m['dependencies'] ?? [];
        if (!is_array($deps)) {
            $e[] = 'dependencies must be an object id => constraint';
        } else {
            foreach ($deps as $depId => $constraint) {
                $depId = (string) $depId;
                if (!preg_match('/^[a-z][a-z0-9_]{1,31}$/D', $depId)) {
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
            if ($providers !== [] && $trust !== 'trusted') {
                $e[] = 'providers need "trust": "trusted"';
            }
        }
        foreach (['routes', 'migrations'] as $field) {
            $v = $m[$field] ?? null;
            if ($v !== null && (!is_string($v) || !self::safeRelative($v))) {
                $e[] = "$field must be null or a relative path inside the module (no '..', no leading slash)";
            }
        }
        if (($m['routes'] ?? null) !== null && $trust !== 'trusted') {
            $e[] = 'routes need "trust": "trusted"';
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
                    $e[] = "navigation[$i]: must be an object";
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
            $e[] = 'settings must be an object';
        }
        $assets = $m['assets'] ?? [];
        if (!is_array($assets)) {
            $e[] = 'assets must be an object with optional css/js lists';
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
            'description' => '', 'trust' => 'declarative', 'dependencies' => [], 'providers' => [], 'routes' => null, 'migrations' => null,
            'permissions' => [], 'navigation' => [], 'settings' => [], 'assets' => ['css' => [], 'js' => []],
        ];
    }

    private static function safeRelative(string $p): bool
    {
        return $p !== '' && $p[0] !== '/' && $p[0] !== '\\' && strpos($p, '..') === false && strpos($p, ':') === false && strpos($p, "\0") === false;
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
use Throwable;

/**
 * Finds modules on disk (modules/<id>/module.json), validates their manifests and plans which enabled modules may load.
 *
 * scan() only READS files. It never includes or executes anything from a module folder, so a module with a broken or
 * malicious PHP file cannot do anything during discovery. A folder with a problem is recorded in invalid() and logged by
 * the caller; scan() never throws.
 * Trust is checked in plan(): a module whose manifest says "trusted" but whose id is not in the owner's list is valid (it can be listed)
 * but cannot be loaded (state blocked).
 * plan() never throws either: it returns the modules that are ready in load order (dependencies first) and, for every enabled
 * module that is not ready, its state (invalid or blocked) with the reasons.
 */
final class ModuleRegistry
{
    private const MAX_MANIFEST_BYTES = 65536;

    private string $path;
    private string $coreVersion;
    /** @var string[] */
    private array $trusted;
    /** @var array<string,Module> */
    private array $modules = [];
    /** @var array<string,string[]> folder => problems */
    private array $invalid = [];

    /** @param string[] $trustedIds ids the owner allows to run PHP (config/modules.php "trusted") */
    public function __construct(string $modulesPath, string $coreVersion, array $trustedIds = [])
    {
        $this->path = rtrim($modulesPath, '/\\');
        $this->coreVersion = $coreVersion;
        $this->trusted = array_values(array_map('strval', $trustedIds));
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
            $file = $this->path . '/' . $dir . '/module.json';
            if (!is_file($file)) {
                $this->invalid[$dir] = [is_file($this->path . '/' . $dir . '/module.php')
                    ? 'module.php is no longer supported: create module.json (see docs/modules.md)'
                    : 'folder has no module.json'];
                continue;
            }
            $size = @filesize($file);
            if ($size === false || $size > self::MAX_MANIFEST_BYTES) {
                $this->invalid[$dir] = ['module.json is unreadable or larger than ' . self::MAX_MANIFEST_BYTES . ' bytes'];
                continue;
            }
            try {
                $manifest = json_decode((string) file_get_contents($file), true, 16, JSON_THROW_ON_ERROR);
            } catch (Throwable $e) {
                $this->invalid[$dir] = ['module.json is not valid JSON: ' . $e->getMessage()];
                continue;
            }
            if (!is_array($manifest) || ($manifest !== [] && array_keys($manifest) === range(0, count($manifest) - 1))) {
                $this->invalid[$dir] = ['module.json must be a JSON object'];
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

    public function coreVersion(): string
    {
        return $this->coreVersion;
    }

    /**
     * @param string[] $enabledIds
     * @return array{ready:Module[],problems:array<string,array{state:string,reasons:string[]}>}
     *         ready = modules that may load, dependencies first; problems = enabled modules that may not (state invalid|blocked)
     */
    public function plan(array $enabledIds): array
    {
        $enabled = array_values(array_unique(array_map('strval', $enabledIds)));
        sort($enabled, SORT_STRING);
        $problems = [];
        $candidates = [];
        foreach ($enabled as $id) {
            if (isset($this->invalid[$id])) {
                $problems[$id] = ['state' => ModuleState::INVALID, 'reasons' => $this->invalid[$id]];
            } elseif (!isset($this->modules[$id])) {
                $problems[$id] = ['state' => ModuleState::INVALID, 'reasons' => ["enabled in config/modules.php but modules/$id/module.json was not found"]];
            } elseif ($this->modules[$id]->isTrusted() && !in_array($id, $this->trusted, true)) {
                $problems[$id] = ['state' => ModuleState::BLOCKED, 'reasons' => ["trust 'trusted' is not granted: the owner must add '$id' to config/modules.php \"trusted\""]];
            } elseif (!Version::satisfies($this->coreVersion, $this->modules[$id]->coreConstraint())) {
                $problems[$id] = ['state' => ModuleState::BLOCKED, 'reasons' => ["needs core {$this->modules[$id]->coreConstraint()} but core is {$this->coreVersion}"]];
            } else {
                $candidates[] = $id;
            }
        }

        // Order the candidates (dependencies first) and find dependency cycles.
        $order = [];
        $mark = [];
        $cycle = [];
        $visit = function (string $id, array $stack) use (&$visit, &$order, &$mark, &$cycle, $candidates): void {
            if (($mark[$id] ?? '') === 'done') {
                return;
            }
            if (($mark[$id] ?? '') === 'visiting') {
                $path = array_merge(array_slice($stack, (int) array_search($id, $stack, true)), [$id]);
                foreach ($path as $member) {
                    $cycle[$member] = 'dependency cycle: ' . implode(' -> ', $path);
                }
                return;
            }
            $mark[$id] = 'visiting';
            $deps = array_keys($this->modules[$id]->dependencies());
            sort($deps, SORT_STRING);
            foreach ($deps as $d) {
                if (in_array($d, $candidates, true)) {
                    $visit($d, array_merge($stack, [$id]));
                }
            }
            $mark[$id] = 'done';
            $order[] = $id;
        };
        foreach ($candidates as $id) {
            $visit($id, []);
        }

        $ready = [];
        $readyIds = [];
        foreach ($order as $id) {
            $m = $this->modules[$id];
            $reasons = [];
            if (isset($cycle[$id])) {
                $reasons[] = $cycle[$id];
            }
            foreach ($m->dependencies() as $depId => $constraint) {
                $depId = (string) $depId;
                if (!in_array($depId, $enabled, true)) {
                    $reasons[] = "needs module '$depId' which is not enabled";
                } elseif (isset($problems[$depId])) {
                    $reasons[] = "needs module '$depId' which is {$problems[$depId]['state']}";
                } elseif (!in_array($depId, $readyIds, true)) {
                    $reasons[] = "needs module '$depId' which cannot load";
                } elseif (!Version::satisfies($this->modules[$depId]->version(), $constraint)) {
                    $reasons[] = "needs '$depId' $constraint but found {$this->modules[$depId]->version()}";
                }
            }
            if ($reasons !== []) {
                $problems[$id] = ['state' => ModuleState::BLOCKED, 'reasons' => $reasons];
                continue;
            }
            $ready[] = $m;
            $readyIds[] = $id;
        }
        ksort($problems, SORT_STRING);
        return ['ready' => $ready, 'problems' => $problems];
    }
}
```

### CREATE `app/Core/Modules/ModuleBoundaryScanner.php`

Content (copy exactly; it ends with a newline):

```php
<?php
declare(strict_types=1);

namespace App\Core\Modules;

/**
 * Static check of the module boundary rules (run by `php bin/console modules:check` and by the tests, not on every request):
 *  1. A module may use classes of another module only when that module is a declared dependency.
 *  2. Even then, only the public contract namespace Modules\<Other>\Contracts\ is allowed; everything else of another module is internal.
 * It reads the PHP files of the module (src/ and the routes file) with the tokenizer; nothing is executed.
 */
final class ModuleBoundaryScanner
{
    /**
     * @param array<string,Module> $known every valid module by id (used to map a namespace back to a module id)
     * @return string[] problems
     */
    public static function scan(Module $module, array $known): array
    {
        $byStudly = [];
        foreach ($known as $id => $m) {
            $byStudly[$m->studly()] = $id;
        }
        $problems = [];
        foreach (self::phpFiles($module) as $file) {
            $relative = substr($file, strlen($module->path()) + 1);
            $tokens = token_get_all((string) file_get_contents($file));
            foreach ($tokens as $t) {
                if (!is_array($t) || !in_array($t[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_STRING], true)) {
                    continue;
                }
                $name = ltrim($t[1], '\\');
                if (strncmp($name, 'Modules\\', 8) !== 0) {
                    continue;
                }
                $parts = explode('\\', $name);
                $studly = $parts[1] ?? '';
                if ($studly === $module->studly()) {
                    continue;
                }
                $otherId = $byStudly[$studly] ?? null;
                if ($otherId === null) {
                    $problems[] = "$relative: line {$t[2]}: uses $name but no module with that namespace exists";
                } elseif (!array_key_exists($otherId, $module->dependencies())) {
                    $problems[] = "$relative: line {$t[2]}: uses $name but '$otherId' is not a declared dependency of '{$module->id()}'";
                } elseif (($parts[2] ?? '') !== 'Contracts') {
                    $problems[] = "$relative: line {$t[2]}: uses internal class $name of '$otherId'; only Modules\\$studly\\Contracts\\ is public";
                }
            }
        }
        sort($problems, SORT_STRING);
        return array_values(array_unique($problems));
    }

    /** @return string[] */
    private static function phpFiles(Module $module): array
    {
        $files = [];
        $src = $module->path() . '/src';
        if (is_dir($src)) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->isFile() && strtolower($f->getExtension()) === 'php') {
                    $files[] = $f->getPathname();
                }
            }
        }
        $routes = $module->manifest()['routes'] ?? null;
        if (is_string($routes) && is_file($module->path() . '/' . $routes)) {
            $files[] = $module->path() . '/' . $routes;
        }
        sort($files, SORT_STRING);
        return $files;
    }
}
```

### CREATE `app/Core/Modules/ModuleNav.php`

Content (copy exactly; it ends with a newline):

```php
<?php
declare(strict_types=1);

namespace App\Core\Modules;

use App\Core\Log\Logger;
use Throwable;

/**
 * Menu entries of the enabled modules, without booting the whole application. Used by the existing PHP pages
 * (includes/module_nav.php). Only modules the plan says are ready contribute. A broken setup must never take the panel down:
 * any problem is logged (storage/logs) and no entries are returned.
 */
final class ModuleNav
{
    /** @return array<int,array{title:string,items:array<int,array<string,string>>}> */
    public static function groupsFor(string $basePath): array
    {
        $base = rtrim($basePath, '/\\');
        $file = $base . '/config/modules.php';
        if (!is_file($file)) {
            return [];
        }
        try {
            $cfg = require $file;
            $enabled = is_array($cfg) && isset($cfg['enabled']) && is_array($cfg['enabled']) ? $cfg['enabled'] : [];
            if ($enabled === []) {
                return [];
            }
            $trusted = isset($cfg['trusted']) && is_array($cfg['trusted']) ? $cfg['trusted'] : [];
            $kernelFile = $base . '/config/kernel.php';
            $kernel = is_file($kernelFile) ? require $kernelFile : [];
            $coreVersion = is_array($kernel) && isset($kernel['core_version']) ? (string) $kernel['core_version'] : '1.0.0';
            $plan = (new ModuleRegistry($base . '/modules', $coreVersion, $trusted))->scan()->plan($enabled);
            $nav = new NavRegistry();
            foreach ($plan['ready'] as $module) {
                foreach ($module->manifest()['navigation'] as $i => $item) {
                    $nav->add($module->id(), $item, (int) $i);
                }
            }
            return $nav->groups();
        } catch (Throwable $e) {
            (new Logger($base . '/storage/logs'))->error('ModuleNav failed: ' . $e->getMessage());
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

use App\Core\Http\Router;
use App\Core\Log\Logger;
use App\Core\Modules\Module;
use App\Core\Modules\ModuleProviderInterface;
use App\Core\Modules\ModuleRegistry;
use App\Core\Modules\ModuleState;
use App\Core\Modules\ModuleStatuses;
use App\Core\Modules\NavRegistry;
use App\Core\Modules\PermissionRegistry;
use App\Core\Modules\PrefixAutoloader;
use LogicException;
use Throwable;

/**
 * Loads the enabled modules (config/modules.php "enabled") and fails SOFT: a module that is invalid, blocked or throws while
 * loading is recorded in ModuleStatuses and logged, and the rest of the panel (and every other module) keeps working.
 * Routes, permissions and menu entries are staged first and committed only when the whole module loaded (routes file, collision
 * check and providers), so a failing module leaves none of them behind. `php bin/console modules:check` is the strict gate.
 *
 * Only a module with trust "trusted" (and listed in config/modules.php "trusted") may run PHP: autoload prefix, routes file, providers.
 * Config overrides (used by tests): modules.enabled, modules.trusted (arrays) and modules.path (folder relative to the project root).
 */
final class ModuleBootstrapper implements Bootstrapper
{
    public function bootstrap(Application $app): void
    {
        $config = $app->config();
        $path = $app->basePath() . '/' . trim((string) $config->get('modules.path', 'modules'), '/\\');
        $file = $app->basePath() . '/config/modules.php';
        $loaded = is_file($file) ? require $file : [];
        $loaded = is_array($loaded) ? $loaded : [];
        $enabled = $config->get('modules.enabled');
        $enabled = is_array($enabled) ? $enabled : (isset($loaded['enabled']) && is_array($loaded['enabled']) ? $loaded['enabled'] : []);
        $trusted = $config->get('modules.trusted');
        $trusted = is_array($trusted) ? $trusted : (isset($loaded['trusted']) && is_array($loaded['trusted']) ? $loaded['trusted'] : []);

        $logger = $app->logger();
        $registry = (new ModuleRegistry($path, (string) $config->get('core_version', '1.0.0'), $trusted))->scan();
        $nav = new NavRegistry();
        $permissions = new PermissionRegistry();
        $statuses = new ModuleStatuses();
        $app->container()->instance(ModuleRegistry::class, $registry);
        $app->container()->instance(NavRegistry::class, $nav);
        $app->container()->instance(PermissionRegistry::class, $permissions);
        $app->container()->instance(ModuleStatuses::class, $statuses);

        $enabled = array_values(array_unique(array_map('strval', $enabled)));
        foreach ($registry->all() as $id => $module) {
            if (!in_array($id, $enabled, true)) {
                $statuses->set($id, ModuleState::DISABLED);
            }
        }
        foreach ($registry->invalid() as $folder => $problems) {
            if (!in_array($folder, $enabled, true)) {
                $statuses->set($folder, ModuleState::INVALID, $problems);
                $logger->warning("module folder '$folder' is invalid and ignored", ['module' => $folder, 'problems' => $problems]);
            }
        }
        if ($enabled === []) {
            return;
        }

        $plan = $registry->plan($enabled);
        foreach ($plan['problems'] as $id => $problem) {
            $statuses->set($id, $problem['state'], $problem['reasons']);
            $logger->log($problem['state'] === ModuleState::INVALID ? 'error' : 'warning', "module '$id' is {$problem['state']} and was not loaded", ['module' => $id, 'reasons' => $problem['reasons']]);
        }
        foreach ($plan['ready'] as $module) {
            $notLoaded = [];
            foreach (array_keys($module->dependencies()) as $dep) {
                if (!$statuses->isLoaded((string) $dep)) {
                    $notLoaded[] = "needs module '$dep' which is " . ($statuses->state((string) $dep) ?? 'unknown');
                }
            }
            if ($notLoaded !== []) {
                $statuses->set($module->id(), ModuleState::BLOCKED, $notLoaded);
                $logger->warning("module '{$module->id()}' is blocked and was not loaded", ['module' => $module->id(), 'reasons' => $notLoaded]);
                continue;
            }
            try {
                $this->load($app, $module, $nav, $permissions);
                $statuses->set($module->id(), ModuleState::LOADED);
            } catch (Throwable $e) {
                $statuses->set($module->id(), ModuleState::FAILED, [get_class($e) . ': ' . $e->getMessage()]);
                $logger->error("module '{$module->id()}' failed to load", ['module' => $module->id(), 'error' => get_class($e) . ': ' . $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
            }
        }
    }

    /** Stages everything, commits only at the end. Throws on any problem (also ParseError and other Errors). */
    private function load(Application $app, Module $module, NavRegistry $nav, PermissionRegistry $permissions): void
    {
        $manifest = $module->manifest();
        $stagedPermissions = new PermissionRegistry();
        foreach ($manifest['permissions'] as $permission) {
            if ($permissions->has((string) $permission['key'])) {
                throw new LogicException("permission key '{$permission['key']}' is already declared by another module");
            }
            $stagedPermissions->add($module->id(), $permission);
        }
        $stagedNav = new NavRegistry();
        foreach ($manifest['navigation'] as $i => $item) {
            $stagedNav->add($module->id(), $item, (int) $i);
        }

        $stagedRoutes = new Router();
        if ($module->isTrusted()) {
            PrefixAutoloader::register('Modules\\' . $module->studly() . '\\', $module->path() . '/src');
            if ($manifest['routes'] !== null) {
                $define = require $module->path() . '/' . $manifest['routes'];
                if (!is_callable($define)) {
                    throw new LogicException("routes file must return a callable: {$manifest['routes']}");
                }
                $define($stagedRoutes, $app);
            }
            $this->assertNoCollision($app->router(), $stagedRoutes);
            $providers = [];
            foreach ($manifest['providers'] as $class) {
                $provider = $app->container()->get($class);
                if (!$provider instanceof ModuleProviderInterface) {
                    throw new LogicException("provider $class must implement ModuleProviderInterface");
                }
                $providers[] = $provider;
            }
        } else {
            $providers = [];
        }

        // Providers run before the commit: if one throws, nothing of this module's routes, permissions or menu entries is registered.
        foreach ($providers as $provider) {
            $provider->register($app);
        }
        // Commit.
        foreach ($stagedPermissions->all() as $permission) {
            $permissions->add($module->id(), $permission);
        }
        foreach ($stagedNav->groups() as $group) {
            foreach ($group['items'] as $item) {
                $nav->addRaw($group['title'], $item);
            }
        }
        foreach ($stagedRoutes->all() as $route) {
            $added = $app->router()->add($route->methods(), $route->path(), $route->handler());
            if ($route->getMiddleware() !== []) {
                $added->middleware(...$route->getMiddleware());
            }
            if ($route->getName() !== null) {
                $added->name((string) $route->getName());
            }
        }
    }

    private function assertNoCollision(Router $main, Router $staged): void
    {
        $shape = static function (string $path): string {
            return (string) preg_replace('#\{[A-Za-z_][A-Za-z0-9_]*(?::[a-z]+)?\}#', '{}', $path);
        };
        $existing = [];
        $names = [];
        foreach ($main->all() as $route) {
            foreach ($route->methods() as $method) {
                $existing[$method . ' ' . $shape($route->path())] = $route->path();
            }
            if ($route->getName() !== null) {
                $names[(string) $route->getName()] = true;
            }
        }
        foreach ($staged->all() as $route) {
            foreach ($route->methods() as $method) {
                $key = $method . ' ' . $shape($route->path());
                if (isset($existing[$key])) {
                    throw new LogicException("route collision: $key is already defined (" . $existing[$key] . ')');
                }
                $existing[$key] = $route->path();
            }
            if ($route->getName() !== null) {
                if (isset($names[(string) $route->getName()])) {
                    throw new LogicException("route name '{$route->getName()}' is already used");
                }
                $names[(string) $route->getName()] = true;
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
use App\Core\Modules\ModuleBoundaryScanner;
use App\Core\Modules\ModuleRegistry;
use App\Core\Modules\ModuleState;
use App\Core\Modules\ModuleStatuses;
use Throwable;

/**
 * Command line tool (bin/console). Commands: help, modules:list, modules:check, modules:why, routes:list.
 * Exit codes: 0 ok, 1 a check failed, 2 usage error. Output goes through the $out callable so tests can capture it.
 * The web request path is fail-soft (a broken module does not stop the panel); modules:check is the strict gate.
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
                $this->line('  modules:list      every module folder with its state (loaded, disabled, invalid, blocked, failed)');
                $this->line('  modules:why <id>  the reasons a module is not loaded');
                $this->line('  modules:check     strict gate: exit 1 when an enabled module is not loaded, a folder is invalid, a module crosses its boundary or routes collide');
                $this->line('  routes:list       print every route after boot');
                return 0;
            case 'modules:list':
                return $this->modulesList();
            case 'modules:why':
                return $this->modulesWhy((string) ($args[1] ?? ''));
            case 'modules:check':
                return $this->modulesCheck();
            case 'routes:list':
                return $this->routesList();
        }
        $this->line("Unknown command: $cmd (try: help)");
        return 2;
    }

    /** @return array{0:ModuleRegistry,1:ModuleStatuses}|null */
    private function boot(): ?array
    {
        try {
            $this->app->boot();
            return [$this->app->container()->get(ModuleRegistry::class), $this->app->container()->get(ModuleStatuses::class)];
        } catch (Throwable $e) {
            $this->line('boot failed: ' . get_class($e) . ': ' . $e->getMessage());
            return null;
        }
    }

    private function modulesList(): int
    {
        $booted = $this->boot();
        if ($booted === null) {
            return 1;
        }
        [$registry, $statuses] = $booted;
        $this->line(sprintf('%-20s %-10s %-9s %-10s %s', 'id', 'version', 'trust', 'state', 'name / reason'));
        foreach ($statuses->all() as $id => $item) {
            $m = $registry->get($id);
            $detail = $item['reasons'] === [] ? ($m ? $m->name() : '') : implode('; ', $item['reasons']);
            $this->line(sprintf('%-20s %-10s %-9s %-10s %s', $id, $m ? $m->version() : '-', $m ? $m->trust() : '-', $item['state'], $detail));
        }
        $counts = [];
        foreach ($statuses->all() as $item) {
            $counts[$item['state']] = ($counts[$item['state']] ?? 0) + 1;
        }
        ksort($counts);
        $parts = [];
        foreach ($counts as $state => $n) {
            $parts[] = "$n $state";
        }
        $this->line($parts === [] ? 'no modules' : implode(', ', $parts));
        return 0;
    }

    private function modulesWhy(string $id): int
    {
        if ($id === '') {
            $this->line('usage: modules:why <id>');
            return 2;
        }
        $booted = $this->boot();
        if ($booted === null) {
            return 1;
        }
        $state = $booted[1]->state($id);
        if ($state === null) {
            $this->line("module '$id' is unknown (no folder modules/$id and not enabled)");
            return 1;
        }
        $this->line("$id: $state");
        foreach ($booted[1]->reasons($id) as $reason) {
            $this->line('  - ' . $reason);
        }
        return 0;
    }

    private function modulesCheck(): int
    {
        $booted = $this->boot();
        if ($booted === null) {
            return 1;
        }
        [$registry, $statuses] = $booted;
        $problems = [];
        foreach ($registry->invalid() as $dir => $list) {
            foreach ($list as $p) {
                $problems[] = "modules/$dir: $p";
            }
        }
        foreach ($statuses->all() as $id => $item) {
            if (in_array($item['state'], [ModuleState::INVALID, ModuleState::BLOCKED, ModuleState::FAILED], true) && !isset($registry->invalid()[$id])) {
                foreach ($item['reasons'] === [] ? ['not loaded'] : $item['reasons'] as $r) {
                    $problems[] = "module '$id' is {$item['state']}: $r";
                }
            }
        }
        $enabledAndLoaded = 0;
        foreach ($statuses->idsIn(ModuleState::LOADED) as $id) {
            $enabledAndLoaded++;
            $m = $registry->get($id);
            if ($m !== null) {
                foreach (ModuleBoundaryScanner::scan($m, $registry->all()) as $p) {
                    $problems[] = "module '$id' boundary: $p";
                }
            }
        }
        try {
            $this->app->validateRoutes();
        } catch (Throwable $e) {
            $problems[] = get_class($e) . ': ' . $e->getMessage();
        }
        $problems = array_values(array_unique($problems));
        if ($problems !== []) {
            foreach ($problems as $p) {
                $this->line('PROBLEM ' . $p);
            }
            $this->line('modules:check FAILED (' . count($problems) . ' problem(s))');
            return 1;
        }
        $this->line('modules:check OK (' . $enabledAndLoaded . ' loaded, ' . count($registry->all()) . ' valid)');
        return 0;
    }

    private function routesList(): int
    {
        if ($this->boot() === null) {
            return 1;
        }
        foreach ($this->app->router()->all() as $r) {
            $names = [];
            foreach ($r->getMiddleware() as $m) {
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
// config/modules.php — Part 11C. Owner-controlled switches for the module system.
//  enabled : ids of modules that are switched on. An id needs a folder modules/<id>/ with a valid module.json.
//  trusted : ids that may run their own PHP (routes file, providers). A module whose manifest says "trust": "trusted"
//            is invalid until its id is listed here; a manifest can never grant itself trust. Add an id only after
//            you (or someone you trust) read the module's code.
// To try the sample: add 'example' to BOTH lists, then run `php bin/console modules:check`.
return [
    'enabled' => [],
    'trusted' => [],
];
```

### STEP 4 — wire it in (two edits to config, one to the page header)

Each FIND text must occur **exactly once** (0 or 2+ → stop that edit, rule 3). With nothing enabled the panel looks and behaves exactly as before.

### EDIT `config/kernel.php` — E1

Declare the core version (modules state which core versions they accept).

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```php
    'api_prefix' => '/api/v1',
    // Folder of the legacy allowlist (see App\Core\Http\LegacyGateway) and of the application log (App\Core\Log\Logger).
```

**REPLACE IT WITH:**

```php
    'api_prefix' => '/api/v1',
    // Version of this core. Modules declare which core versions they accept (manifest field "core").
    'core_version' => '1.0.0',
    // Folder of the legacy allowlist (see App\Core\Http\LegacyGateway) and of the application log (App\Core\Log\Logger).
```

### EDIT `config/kernel.php` — E2

Switch the module bootstrapper on.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```php
    'bootstrappers' => [],
];
```

**REPLACE IT WITH:**

```php
    'bootstrappers' => [\App\Bootstrap\ModuleBootstrapper::class],
];
```

### EDIT `includes/header.php` — E3

Sidebar menu = base menu + menu entries of ready modules (nothing changes while no module is enabled).

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```php
$navGroups = uiNavVisibleGroups(require __DIR__ . '/nav.php', 'hasPermission', isOwner());

```

**REPLACE IT WITH:**

```php
require_once __DIR__ . '/module_nav.php';
$navGroups = uiNavVisibleGroups(moduleNavMerge(require __DIR__ . '/nav.php', moduleNavGroups(dirname(__DIR__))), 'hasPermission', isOwner());

```

### STEP 5 — sidebar bridge and the sample module

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

### CREATE `modules/example/module.json`

Content (copy exactly; it ends with a newline):

```json
{
  "schema": 1,
  "id": "example",
  "name": "ماژول نمونه",
  "description": "نمونهٔ کوچک برای نشان دادن قرارداد ماژول: مسیر JSON، صفحهٔ HTML، مجوز و آیتم منو.",
  "version": "1.0.0",
  "core": ">=1.0.0",
  "trust": "trusted",
  "dependencies": {},
  "providers": [],
  "routes": "routes.php",
  "migrations": null,
  "permissions": [
    { "key": "example", "label": "ماژول نمونه", "actions": ["view"] }
  ],
  "navigation": [
    { "group": "نمونه", "label": "آزمایش ماژول", "icon": "ti-bolt", "url": "/example/page", "permission": "example" }
  ],
  "settings": {},
  "assets": { "css": [], "js": [] }
}
```

### CREATE `modules/example/routes.php`

Content (copy exactly; it ends with a newline):

```php
<?php
// Routes of the sample module. A routes file returns function (Router $router, Application $app): void.
// It runs only because the module is "trusted" (manifest) AND listed in config/modules.php "trusted".
use App\Bootstrap\Application;
use App\Core\Http\Router;
use Modules\Example\Controllers\PageController;
use Modules\Example\Controllers\PingController;

return function (Router $router, Application $app): void {
    $router->get('/example/ping', [PingController::class, 'ping'])
        ->middleware('auth', 'permission:example.view')
        ->name('example.ping');
    $router->get('/example/page', [PageController::class, 'index'])
        ->middleware('auth', 'permission:example.view')
        ->name('example.page');
};
```

### CREATE `modules/example/README.md`

Content (copy exactly; it ends with a newline):

```markdown
# example (sample module)

Not enabled by default. To try it add `'example'` to both `enabled` and `trusted` in `config/modules.php`, then run
`php bin/console modules:check` and open `/example/page` as the owner. Copy its **shape** (module.json, routes.php, src/), not its content.
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

### CREATE `modules/example/src/Controllers/PageController.php`

Content (copy exactly; it ends with a newline):

```php
<?php
declare(strict_types=1);

namespace Modules\Example\Controllers;

use App\Core\Http\Request;
use App\Core\Http\Response;

/**
 * Module HTML page contract (version 0, before the view engine of part 11E1): a page is a route that returns Response::html().
 * It must escape everything it prints and it must not include the old header/footer files. From 11E1 on, pages return a view
 * rendered inside the shell instead; this sample then changes in one place.
 */
final class PageController
{
    public function index(Request $request, array $params): Response
    {
        $title = htmlspecialchars('ماژول نمونه', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $title . '</title></head><body style="font-family:Tahoma,sans-serif;max-width:40rem;margin:3rem auto;padding:0 1rem">'
            . '<h1>' . $title . '</h1><p>این صفحه از یک ماژول بارگذاری شده است.</p></body></html>';
        return Response::html($html);
    }
}
```

### STEP 6 — create the tests

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
        'schema' => 1, 'id' => 'hrm', 'name' => 'منابع انسانی', 'description' => 'نمونه', 'version' => '1.0.0', 'core' => '>=1.0.0', 'trust' => 'trusted',
        'dependencies' => ['users' . '_x' => '>=1.0'], 'providers' => ['Modules\\Hrm\\HrmProvider'], 'routes' => 'routes.php', 'migrations' => 'migrations',
        'permissions' => [['key' => 'hrm', 'label' => 'منابع انسانی', 'actions' => ['view', 'edit']], ['key' => 'hrm_leave', 'label' => 'مرخصی', 'actions' => ['view']]],
        'navigation' => [['group' => 'منابع انسانی', 'label' => 'کارمندان', 'icon' => 'ti-users', 'url' => '/hrm/employees', 'permission' => 'hrm']],
        'settings' => [], 'assets' => ['css' => ['resources/css/a.css'], 'js' => []],
    ];
}
function problems(array $m, string $dir = 'hrm'): array { return ModuleManifest::validate($m, $dir); }
function with(array $patch, array $remove = []): array { $m = array_replace(good(), $patch); foreach ($remove as $k) { unset($m[$k]); } return $m; }

check('a good manifest has no problems', problems(good()), []);
check('minimal manifest (only required fields) is fine', problems(['schema' => 1, 'id' => 'abc', 'name' => 'x', 'version' => '0.1.0', 'core' => '*'], 'abc'), []);
check('normalize fills defaults', array_keys(ModuleManifest::normalize(['schema' => 1, 'id' => 'abc', 'name' => 'x', 'version' => '0.1.0', 'core' => '*'])), ['schema', 'id', 'name', 'version', 'core', 'description', 'trust', 'dependencies', 'providers', 'routes', 'migrations', 'permissions', 'navigation', 'settings', 'assets']);
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
check('schema is required', problems(with([], ['schema'])), ['schema must be the number 1']);
check('schema must be 1', problems(with(['schema' => 2])), ['schema must be the number 1']);
check('schema as string is rejected', count(problems(with(['schema' => '1']))) > 0, true);
$d = ['schema' => 1, 'id' => 'abc', 'name' => 'x', 'version' => '0.1.0', 'core' => '*'];
check('trust must be a known word', problems(array_replace($d, ['trust' => 'yes']), 'abc'), ['trust must be "declarative" or "trusted"']);
check('declarative module without code is valid', problems($d, 'abc'), []);
check('routes need trust', problems($d + ['routes' => 'routes.php'], 'abc'), ['routes need "trust": "trusted"']);
check('providers need trust', problems($d + ['providers' => ['Modules\\Abc\\Boot']], 'abc'), ['providers need "trust": "trusted"']);
check('trusted module may have routes and providers', problems($d + ['trust' => 'trusted', 'routes' => 'routes.php', 'providers' => ['Modules\\Abc\\Boot']], 'abc'), []);
check('description too long', count(problems(with(['description' => str_repeat('x', 301)]))) > 0, true);
check('core platform ids are reserved', array_map(function ($id) use ($d) { return in_array("id '$id' is reserved", problems(array_replace($d, ['id' => $id]), $id), true); }, ['auth', 'files', 'audit', 'modules']), [true, true, true, true]);
check('former CMS names are NOT reserved any more (a business module may use them)', problems(['schema' => 1, 'id' => 'pages', 'name' => 'x', 'version' => '1.0.0', 'core' => '*'], 'pages'), []);
check('settings must be array', count(problems(with(['settings' => 'x']))) > 0, true);
finish('modules_version_manifest_test');
```

### CREATE `tests/modules_registry_test.php`

Content (copy exactly; it ends with a newline):

```php
<?php
require __DIR__ . '/_kernel.php';

use App\Core\Modules\Module;
use App\Core\Modules\ModuleRegistry;
use App\Core\Modules\ModuleState;
use App\Core\Modules\ModuleStatuses;

// ---------- temp project helpers ----------
function rrmdir(string $d): void {
    if (!is_dir($d)) { return; }
    foreach (scandir($d) as $f) { if ($f === '.' || $f === '..') { continue; } $p = "$d/$f"; is_dir($p) ? rrmdir($p) : unlink($p); }
    rmdir($d);
}
function tmpModules(): string {
    $d = sys_get_temp_dir() . '/m11cr_' . bin2hex(random_bytes(4));
    mkdir($d . '/modules', 0777, true);
    return $d;
}
function mf(string $id, array $extra = []): array {
    return array_replace(['schema' => 1, 'id' => $id, 'name' => $id, 'version' => '1.0.0', 'core' => '>=1.0.0'], $extra);
}
function putModule(string $proj, string $id, array $manifest): void {
    $dir = "$proj/modules/$id"; mkdir("$dir/src", 0777, true);
    file_put_contents("$dir/module.json", json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
function ids(array $modules): array { return array_map(function ($m) { return $m->id(); }, $modules); }
function reg(string $proj, string $core = '1.2.0', array $trusted = []): ModuleRegistry { return (new ModuleRegistry($proj . '/modules', $core, $trusted))->scan(); }

$p = tmpModules();
putModule($p, 'alpha', mf('alpha'));
putModule($p, 'beta', mf('beta', ['dependencies' => ['alpha' => '>=1.0.0']]));
putModule($p, 'gamma', mf('gamma', ['dependencies' => ['beta' => '*', 'alpha' => '*']]));
mkdir("$p/modules/no_manifest");
mkdir("$p/modules/_ignored"); mkdir("$p/modules/.hidden");
file_put_contents("$p/modules/stray.txt", 'x');
putModule($p, 'broken', ['schema' => 1, 'id' => 'broken', 'name' => 'x', 'version' => 'nope', 'core' => '*']);
mkdir("$p/modules/badjson"); file_put_contents("$p/modules/badjson/module.json", '{"schema": 1, "id": ');
mkdir("$p/modules/aslist"); file_put_contents("$p/modules/aslist/module.json", '[1,2,3]');
mkdir("$p/modules/asnumber"); file_put_contents("$p/modules/asnumber/module.json", '5');
mkdir("$p/modules/empty"); file_put_contents("$p/modules/empty/module.json", '');
mkdir("$p/modules/huge"); file_put_contents("$p/modules/huge/module.json", '{"schema":1,"x":"' . str_repeat('a', 70000) . '"}');
mkdir("$p/modules/legacy"); file_put_contents("$p/modules/legacy/module.php", "<?php return ['id' => 'legacy'];");
mkdir("$p/modules/deep"); file_put_contents("$p/modules/deep/module.json", str_repeat('[', 40) . str_repeat(']', 40));

// A module.php must NEVER be executed by discovery.
$marker = $p . '/EXECUTED';
putModule($p, 'evil', mf('evil'));
file_put_contents("$p/modules/evil/module.php", "<?php file_put_contents(" . var_export($marker, true) . ", 'x'); exit(9);");
file_put_contents("$p/modules/evil/src/Boom.php", "<?php file_put_contents(" . var_export($marker, true) . ", 'x'); throw new Exception('x');");

$r = reg($p);
check('scan finds valid modules', array_keys($r->all()), ['alpha', 'beta', 'evil', 'gamma']);
check('discovery never executes module code', is_file($marker), false);
check('scan records invalid folders', array_keys($r->invalid()), ['aslist', 'asnumber', 'badjson', 'broken', 'deep', 'empty', 'huge', 'legacy', 'no_manifest']);
check('invalid: no module.json', $r->invalid()['no_manifest'], ['folder has no module.json']);
check('invalid: legacy module.php', $r->invalid()['legacy'], ['module.php is no longer supported: create module.json (see docs/modules.md)']);
check('invalid: broken JSON is reported, not thrown', strpos($r->invalid()['badjson'][0], 'module.json is not valid JSON') === 0, true);
check('invalid: empty file', strpos($r->invalid()['empty'][0], 'not valid JSON') !== false, true);
check('invalid: JSON list', $r->invalid()['aslist'], ['module.json must be a JSON object']);
check('invalid: JSON scalar', $r->invalid()['asnumber'], ['module.json must be a JSON object']);
check('invalid: too large', strpos($r->invalid()['huge'][0], 'larger than 65536 bytes') !== false, true);
check('invalid: nesting too deep is an error, not a crash', strpos($r->invalid()['deep'][0], 'not valid JSON') !== false, true);
check('invalid: manifest rule problems are listed', count($r->invalid()['broken']) >= 1, true);
check('hidden and underscore folders and stray files are ignored', isset($r->invalid()['_ignored']) || isset($r->invalid()['.hidden']) || isset($r->invalid()['stray.txt']), false);
check('get()', [$r->get('alpha') !== null, $r->get('zzz')], [true, null]);
check('Module data', [$r->get('beta')->studly(), $r->get('beta')->dependencies(), $r->get('beta')->trust(), $r->get('beta')->isTrusted()], ['Beta', ['alpha' => '>=1.0.0'], 'declarative', false]);
check('studly of snake id', (new Module('/x', ['id' => 'leave_requests']))->studly(), 'LeaveRequests');
check('scan on a missing folder is empty, not an error', (new ModuleRegistry('/no/such/dir', '1.0.0'))->scan()->all(), []);

// ---- trust: a manifest cannot grant itself trust ----
putModule($p, 'tr1', mf('tr1', ['trust' => 'trusted', 'routes' => 'routes.php']));
file_put_contents("$p/modules/tr1/routes.php", '<?php return function () {};');
check('trusted manifest is valid and listable without the owner grant', reg($p)->get('tr1') !== null, true);
check('but it cannot load without the owner grant (blocked, with the exact instruction)', reg($p)->plan(['tr1'])['problems']['tr1'], ['state' => ModuleState::BLOCKED, 'reasons' => ["trust 'trusted' is not granted: the owner must add 'tr1' to config/modules.php \"trusted\""]]);
check('with the owner grant it is ready', ids(reg($p, '1.2.0', ['tr1'])->plan(['tr1'])['ready']), ['tr1']);
check('the grant is per id', array_keys(reg($p, '1.2.0', ['someone_else'])->plan(['tr1'])['problems']), ['tr1']);
check('a declarative module needs no grant', ids(reg($p)->plan(['alpha'])['ready']), ['alpha']);
putModule($p, 'tr2', mf('tr2', ['trust' => 'trusted', 'routes' => 'missing.php']));
check('routes file must exist', in_array("routes file 'missing.php' does not exist", reg($p, '1.2.0', ['tr2'])->invalid()['tr2'], true), true);

// ---- plan() ----
$r = reg($p);
$plan = $r->plan(['gamma', 'beta', 'alpha']);
check('plan: dependencies first', ids($plan['ready']), ['alpha', 'beta', 'gamma']);
check('plan: no problems', $plan['problems'], []);
check('plan: order is deterministic whatever the input order', ids($r->plan(['alpha', 'gamma', 'beta'])['ready']), ['alpha', 'beta', 'gamma']);
check('plan: nothing enabled', $r->plan([]), ['ready' => [], 'problems' => []]);
check('plan: duplicates ignored', ids($r->plan(['alpha', 'alpha'])['ready']), ['alpha']);
$plan = $r->plan(['ghost']);
check('plan: unknown module is invalid', $plan['problems']['ghost'], ['state' => ModuleState::INVALID, 'reasons' => ["enabled in config/modules.php but modules/ghost/module.json was not found"]]);
$plan = $r->plan(['broken', 'alpha']);
check('plan: an invalid module does not stop the others', [ids($plan['ready']), $plan['problems']['broken']['state']], [['alpha'], ModuleState::INVALID]);
$plan = $r->plan(['beta']);
check('plan: dependency not enabled blocks the dependent', [$plan['ready'], $plan['problems']['beta']], [[], ['state' => ModuleState::BLOCKED, 'reasons' => ["needs module 'alpha' which is not enabled"]]]);
$plan = $r->plan(['gamma', 'beta', 'ghost']);
check('plan: all problems are reported, the transitive dependent is blocked', [array_keys($plan['problems']), $plan['problems']['gamma']['state'], $plan['problems']['beta']['state']], [['beta', 'gamma', 'ghost'], ModuleState::BLOCKED, ModuleState::BLOCKED]);
$plan = reg($p, '0.9.0')->plan(['alpha', 'beta']);
check('plan: core too old blocks the module', [$plan['ready'], $plan['problems']['alpha']['reasons'], $plan['problems']['beta']['reasons']], [[], ['needs core >=1.0.0 but core is 0.9.0'], ['needs core >=1.0.0 but core is 0.9.0']]);
$plan = $r->plan(['broken', 'alpha', 'beta']);
check('plan: dependency that is invalid blocks the dependent', ids($plan['ready']), ['alpha', 'beta']);
putModule($p, 'oldlib', mf('oldlib', ['version' => '1.0.0']));
putModule($p, 'needsnew', mf('needsnew', ['dependencies' => ['oldlib' => '>=2.0.0']]));
check('plan: dependency version too low', reg($p)->plan(['oldlib', 'needsnew'])['problems']['needsnew']['reasons'], ["needs 'oldlib' >=2.0.0 but found 1.0.0"]);
putModule($p, 'dep_invalid', mf('dep_invalid', ['dependencies' => ['broken' => '*']]));
check('plan: dependent of an invalid module is blocked with the reason', reg($p)->plan(['broken', 'dep_invalid'])['problems']['dep_invalid']['reasons'], ["needs module 'broken' which is invalid"]);

$p2 = tmpModules();
putModule($p2, 'aa', mf('aa', ['dependencies' => ['bb' => '*']]));
putModule($p2, 'bb', mf('bb', ['dependencies' => ['cc' => '*']]));
putModule($p2, 'cc', mf('cc', ['dependencies' => ['aa' => '*']]));
putModule($p2, 'dd', mf('dd', ['dependencies' => ['aa' => '*']]));
putModule($p2, 'ok', mf('ok'));
$plan = reg($p2)->plan(['aa', 'bb', 'cc', 'dd', 'ok']);
check('plan: a cycle blocks its members, not the panel', [ids($plan['ready']), array_keys($plan['problems'])], [['ok'], ['aa', 'bb', 'cc', 'dd']]);
check('plan: cycle reason shows the path', strpos($plan['problems']['aa']['reasons'][0], 'dependency cycle: ') === 0, true);
check('plan: a dependent of a cycle is blocked too', $plan['problems']['dd']['reasons'], ["needs module 'aa' which is blocked"]);

// ---- statuses holder ----
$s = new ModuleStatuses();
$s->set('b', ModuleState::LOADED); $s->set('a', ModuleState::FAILED, ['x']);
check('statuses are sorted and queryable', [array_keys($s->all()), $s->isLoaded('b'), $s->isLoaded('a'), $s->reasons('a'), $s->state('zz'), $s->idsIn(ModuleState::FAILED)], [['a', 'b'], true, false, ['x'], null, ['a']]);

rrmdir($p); rrmdir($p2);
finish('modules_registry_test');
```

### CREATE `tests/modules_boot_test.php`

Content (copy exactly; it ends with a newline):

```php
<?php
require __DIR__ . '/_kernel.php';

use App\Bootstrap\Application;
use App\Bootstrap\ModuleBootstrapper;
use App\Core\Http\Request;
use App\Core\Modules\ModuleState;
use App\Core\Modules\ModuleStatuses;
use App\Core\Modules\NavRegistry;
use App\Core\Modules\PermissionRegistry;

$root = dirname(__DIR__);

function rrmdir(string $d): void {
    if (!is_dir($d)) { return; }
    foreach (scandir($d) as $f) { if ($f === '.' || $f === '..') { continue; } $p = "$d/$f"; is_dir($p) ? rrmdir($p) : unlink($p); }
    rmdir($d);
}
function tmpProject(): string {
    $d = sys_get_temp_dir() . '/m11cb_' . bin2hex(random_bytes(4));
    mkdir($d . '/modules', 0777, true); mkdir($d . '/routes', 0777, true); mkdir($d . '/config', 0777, true);
    file_put_contents($d . '/routes/core.php', "<?php return function (\$r, \$a) { \$r->get('/health', function () { return App\\Core\\Http\\Response::ok(['status' => 'up']); })->name('core.health'); };");
    file_put_contents($d . '/config/kernel.php', "<?php return ['core_version' => '1.2.0', 'login_url' => '/login.php'];");
    return $d;
}
function mf(string $id, array $extra = []): array {
    return array_replace(['schema' => 1, 'id' => $id, 'name' => $id, 'version' => '1.0.0', 'core' => '>=1.0.0'], $extra);
}
/** $routes = PHP source of routes.php (null = no routes file); a module with routes or providers is "trusted". */
function putModule(string $proj, string $id, array $extra = [], ?string $routes = null): void {
    $dir = "$proj/modules/$id"; mkdir("$dir/src", 0777, true);
    if ($routes !== null) { $extra += ['trust' => 'trusted', 'routes' => 'routes.php']; file_put_contents("$dir/routes.php", $routes); }
    if (isset($extra['providers'])) { $extra += ['trust' => 'trusted']; }
    file_put_contents("$dir/module.json", json_encode(mf($id, $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
function appFor(string $proj, array $enabled, array $trusted): Application {
    $a = Application::create($proj, ['config' => ['bootstrappers' => [ModuleBootstrapper::class], 'modules' => ['enabled' => $enabled, 'trusted' => $trusted], 'log_dir' => 'storage/logs']]);
    $a->boot();
    return $a;
}
function st(Application $a): ModuleStatuses { return $a->container()->get(ModuleStatuses::class); }
function status(Application $a, string $id): ?string { return st($a)->state($id); }
function routePaths(Application $a): array { return array_map(function ($r) { return implode('|', $r->methods()) . ' ' . $r->path(); }, $a->router()->all()); }
function logText(string $proj): string { $t = ''; foreach (glob($proj . '/storage/logs/*.log') ?: [] as $f) { $t .= file_get_contents($f); } return $t; }
$getRoutes = function (string $path, string $name): string {
    return "<?php return function (\$r, \$a) { \$r->get('$path', function () { return 'x'; })->name('$name'); };";
};

// ===================== the real example module, end to end =====================
$app = Application::create($root, ['debug' => false, 'config' => ['bootstrappers' => [ModuleBootstrapper::class], 'modules' => ['enabled' => ['example'], 'trusted' => ['example']]]]);
$state = ['login' => true, 'can' => true];
$app->registerMiddleware('auth', function () use (&$state) { return new App\Core\Http\Middleware\AuthMiddleware(function () use (&$state) { return $state['login']; }); });
$app->registerMiddleware('permission', function (array $a) use (&$state) { return new App\Core\Http\Middleware\PermissionMiddleware(function () use (&$state) { return $state['can']; }, 'example', 'view'); });
$app->boot();
$app->validateRoutes();
check('example module is loaded', status($app, 'example'), ModuleState::LOADED);
$res = $app->handle(new Request('GET', '/example/ping', [], [], ['Accept' => 'application/json']));
check('example JSON route works', [$res->status(), $res->payload()['data']], [200, ['pong' => true, 'module' => 'example']]);
$res = $app->handle(new Request('GET', '/example/page', [], [], ['Accept' => 'text/html']));
check('example HTML page route works', [$res->status(), strpos($res->body(), 'dir="rtl"') !== false, strpos($res->body(), 'ماژول نمونه') !== false], [200, true, true]);
$state['can'] = false;
check('example route enforces permission', $app->handle(new Request('GET', '/example/ping', [], [], ['Accept' => 'application/json']))->status(), 403);
$state['can'] = true; $state['login'] = false;
check('example route enforces login (401 for JSON)', $app->handle(new Request('GET', '/example/ping', [], [], ['Accept' => 'application/json']))->status(), 401);
check('permission registered in memory only', $app->container()->get(PermissionRegistry::class)->has('example'), true);
check('nav registered in legacy shape', $app->container()->get(NavRegistry::class)->groups()[0]['items'][0]['visible'], 'perm:example');
check('module classes autoload under Modules\\Example\\', class_exists('Modules\\Example\\Controllers\\PingController'), true);

// enabled but NOT trusted: the module is blocked (it asks for PHP it was not granted) and nothing of it runs
$untrusted = Application::create($root, ['config' => ['bootstrappers' => [ModuleBootstrapper::class], 'modules' => ['enabled' => ['example'], 'trusted' => []], 'log_dir' => 'storage/test-logs-x']]);
$untrusted->boot();
check('enabled but not trusted: blocked, no route', [status($untrusted, 'example'), $untrusted->handle(new Request('GET', '/example/ping', [], [], ['Accept' => 'application/json']))->status()], [ModuleState::BLOCKED, 404]);
check('the reason names the missing grant', strpos(implode(' ', st($untrusted)->reasons('example')), 'config/modules.php "trusted"') !== false, true);
rrmdir($root . '/storage/test-logs-x');

// not enabled => disabled, nothing registered
$off = Application::create($root, ['config' => ['bootstrappers' => [ModuleBootstrapper::class], 'modules' => ['enabled' => [], 'trusted' => []]]]);
$off->boot();
check('disabled module adds no route', $off->handle(new Request('GET', '/example/ping', [], [], ['Accept' => 'application/json']))->status(), 404);
check('disabled module is reported as disabled', status($off, 'example'), ModuleState::DISABLED);
check('registries exist even when nothing is enabled', [count($off->container()->get(NavRegistry::class)->groups()), count($off->container()->get(PermissionRegistry::class)->all())], [0, 0]);

// ===================== fail-soft: one bad module never takes the panel down =====================
$p = tmpProject();
putModule($p, 'good', [], $getRoutes('/good', 'good.index'));
putModule($p, 'parse_error', [], "<?php return function (\$r, \$a) { \$r->get('/pe', function () { return 'x'; });  this is not php ;");
putModule($p, 'throws_in_routes', [], "<?php return function (\$r, \$a) { \$r->get('/tr', function () { return 'x'; }); throw new RuntimeException('routes exploded'); };");
putModule($p, 'not_callable', [], "<?php return 5;");
putModule($p, 'missing_class', ['providers' => ['Modules\\MissingClass\\NoSuchProvider']], $getRoutes('/mc', 'mc.index'));
putModule($p, 'bad_provider', ['providers' => ['Modules\\BadProvider\\Boot']], $getRoutes('/bp', 'bp.index'));
file_put_contents("$p/modules/bad_provider/src/Boot.php", "<?php namespace Modules\\BadProvider; class Boot {}");
putModule($p, 'prov_throws', ['providers' => ['Modules\\ProvThrows\\Boot']], $getRoutes('/pt', 'pt.index'));
file_put_contents("$p/modules/prov_throws/src/Boot.php", "<?php namespace Modules\\ProvThrows; class Boot implements \\App\\Core\\Modules\\ModuleProviderInterface { public function register(\\App\\Bootstrap\\Application \$app): void { throw new \\RuntimeException('provider exploded'); } }");
putModule($p, 'prov_parse', ['providers' => ['Modules\\ProvParse\\Boot']], $getRoutes('/pp', 'pp.index'));
file_put_contents("$p/modules/prov_parse/src/Boot.php", "<?php namespace Modules\\ProvParse; class Boot implements { broken");
putModule($p, 'prov_ok', ['providers' => ['Modules\\ProvOk\\Boot']], $getRoutes('/po', 'po.index'));
file_put_contents("$p/modules/prov_ok/src/Boot.php", "<?php namespace Modules\\ProvOk; class Boot implements \\App\\Core\\Modules\\ModuleProviderInterface { public static \$ran = false; public function register(\\App\\Bootstrap\\Application \$app): void { self::\$ran = true; } }");
putModule($p, 'zz_collide', [], $getRoutes('/good', 'zz_collide.index'));
putModule($p, 'zz_collide_name', [], $getRoutes('/other', 'good.index'));
putModule($p, 'needs_bad', ['dependencies' => ['parse_error' => '*']]);
putModule($p, 'needs_good', ['dependencies' => ['good' => '*'], 'permissions' => [['key' => 'needs_good', 'label' => 'x', 'actions' => ['view']]], 'navigation' => [['group' => 'G', 'label' => 'L', 'icon' => 'ti-x', 'url' => '/ng', 'permission' => 'needs_good']]]);
putModule($p, 'dup_perm_a', ['permissions' => [['key' => 'dup_perm_a', 'label' => 'x', 'actions' => ['view']]]]);
mkdir("$p/modules/broken_json"); file_put_contents("$p/modules/broken_json/module.json", '{ nope');
$enabled = ['good', 'parse_error', 'throws_in_routes', 'not_callable', 'missing_class', 'bad_provider', 'prov_throws', 'prov_parse', 'prov_ok', 'zz_collide', 'zz_collide_name', 'needs_bad', 'needs_good', 'broken_json', 'ghost'];
$a = appFor($p, $enabled, $enabled);
check('the application booted although many modules are broken', $a instanceof Application, true);
check('good module loaded', status($a, 'good'), ModuleState::LOADED);
check('ParseError in a routes file => failed', status($a, 'parse_error'), ModuleState::FAILED);
check('ParseError reason is recorded', strpos(implode(' ', st($a)->reasons('parse_error')), 'ParseError') !== false, true);
check('exception in routes file => failed', [status($a, 'throws_in_routes'), implode(' ', st($a)->reasons('throws_in_routes'))], [ModuleState::FAILED, 'RuntimeException: routes exploded']);
check('routes file that does not return a callable => failed', status($a, 'not_callable'), ModuleState::FAILED);
check('provider class that does not exist => failed', status($a, 'missing_class'), ModuleState::FAILED);
check('provider without the interface => failed', [status($a, 'bad_provider'), strpos(implode(' ', st($a)->reasons('bad_provider')), 'must implement ModuleProviderInterface') !== false], [ModuleState::FAILED, true]);
check('provider that throws => failed', [status($a, 'prov_throws'), implode(' ', st($a)->reasons('prov_throws'))], [ModuleState::FAILED, 'RuntimeException: provider exploded']);
check('provider with a ParseError => failed', status($a, 'prov_parse'), ModuleState::FAILED);
check('a healthy provider ran', [status($a, 'prov_ok'), Modules\ProvOk\Boot::$ran], [ModuleState::LOADED, true]);
check('route collision => failed (the first module keeps its route)', [status($a, 'zz_collide'), strpos(implode(' ', st($a)->reasons('zz_collide')), 'route collision') !== false], [ModuleState::FAILED, true]);
check('route name collision => failed', [status($a, 'zz_collide_name'), strpos(implode(' ', st($a)->reasons('zz_collide_name')), "route name 'good.index'") !== false], [ModuleState::FAILED, true]);
check('dependency that failed blocks the dependent', [status($a, 'needs_bad'), st($a)->reasons('needs_bad')], [ModuleState::BLOCKED, ["needs module 'parse_error' which is failed"]]);
check('dependent of a loaded module loads', status($a, 'needs_good'), ModuleState::LOADED);
check('invalid JSON module (enabled) => invalid', status($a, 'broken_json'), ModuleState::INVALID);
check('enabled id without a folder => invalid', status($a, 'ghost'), ModuleState::INVALID);
$paths = routePaths($a);
check('only loaded modules have routes (no half-registered routes of failed modules)', array_values(array_filter($paths, function ($x) { return strpos($x, '/pe') !== false || strpos($x, '/tr') !== false || strpos($x, '/mc') !== false || strpos($x, '/bp') !== false || strpos($x, '/pt') !== false || strpos($x, '/pp') !== false || strpos($x, '/other') !== false; })), []);
check('loaded modules have their routes', [in_array('GET /good', $paths, true), in_array('GET /po', $paths, true)], [true, true]);
check('permissions of failed modules are not registered', array_keys($a->container()->get(PermissionRegistry::class)->all()), ['needs_good']);
check('menu entries only from loaded modules', count($a->container()->get(NavRegistry::class)->groups()), 1);
$log = logText($p);
check('failures are written to the application log', [strpos($log, "module 'parse_error' failed to load") !== false, strpos($log, 'ParseError') !== false, strpos($log, 'routes exploded') !== false, strpos($log, 'provider exploded') !== false], [true, true, true, true]);
check('blocked and invalid modules are logged', [strpos($log, "module 'needs_bad' is blocked") !== false, strpos($log, "module 'ghost' is invalid") !== false, strpos($log, "module 'broken_json' is invalid") !== false], [true, true, true]);
check('every log line is valid JSON', array_filter(array_map(function ($l) { return $l === '' ? true : is_array(json_decode($l, true)); }, explode("\n", $log)), function ($v) { return !$v; }), []);
$res = $a->handle(new Request('GET', '/good', [], [], ['Accept' => 'application/json']));
check('a healthy module still answers while others are broken', [$res->status(), $res->body()], [200, 'x']);

// dependency-disable behaviour: switching a dependency off blocks the dependents, nothing else changes
$b = appFor($p, ['good', 'needs_good'], ['good']);
check('both enabled: dependent loads', [status($b, 'good'), status($b, 'needs_good')], [ModuleState::LOADED, ModuleState::LOADED]);
$c = appFor($p, ['needs_good'], ['good']);
check('dependency disabled: dependent is blocked with the reason', [status($c, 'needs_good'), st($c)->reasons('needs_good'), status($c, 'good')], [ModuleState::BLOCKED, ["needs module 'good' which is not enabled"], ModuleState::DISABLED]);
check('dependency disabled: dependent registers nothing', [count($c->container()->get(NavRegistry::class)->groups()), count($c->container()->get(PermissionRegistry::class)->all())], [0, 0]);

// route collision with a CORE route
putModule($p, 'zz_steals_health', [], $getRoutes('/health', 'steal.health'));
$d = appFor($p, ['zz_steals_health'], ['zz_steals_health']);
check('a module cannot take a core route', [status($d, 'zz_steals_health'), strpos(implode(' ', st($d)->reasons('zz_steals_health')), 'route collision') !== false], [ModuleState::FAILED, true]);
check('core /health still works', $d->handle(new Request('GET', '/health', [], [], ['Accept' => 'application/json']))->status(), 200);

// a declarative module (no PHP) contributes permission + menu without any code running
putModule($p, 'declared', ['permissions' => [['key' => 'declared', 'label' => 'x', 'actions' => ['view']]], 'navigation' => [['group' => 'G2', 'label' => 'L2', 'icon' => 'ti-y', 'url' => '/declared', 'permission' => 'declared']]]);
$e = appFor($p, ['declared'], []);
check('declarative module loads without trust', [status($e, 'declared'), $e->container()->get(PermissionRegistry::class)->has('declared'), $e->container()->get(NavRegistry::class)->groups()[0]['title']], [ModuleState::LOADED, true, 'G2']);

// two modules declaring the same permission key: the second one fails, the first keeps it
putModule($p, 'dup_perm_b', ['permissions' => [['key' => 'dup_perm_a', 'label' => 'x', 'actions' => ['view']]]]);
file_put_contents("$p/modules/dup_perm_b/module.json", str_replace('"id":"dup_perm_a"', '"id":"dup_perm_b"', file_get_contents("$p/modules/dup_perm_b/module.json")));
$f = appFor($p, ['dup_perm_a', 'dup_perm_b'], []);
check('the manifest rule rejects a key that does not belong to the module', status($f, 'dup_perm_b'), ModuleState::INVALID);

rrmdir($p);
finish('modules_boot_test');
```

### CREATE `tests/modules_console_nav_test.php`

Content (copy exactly; it ends with a newline):

```php
<?php
require __DIR__ . '/_kernel.php';
require_once __DIR__ . '/../includes/module_nav.php';

use App\Bootstrap\Application;
use App\Bootstrap\ModuleBootstrapper;
use App\Console\Console;
use App\Core\Modules\ModuleBoundaryScanner;
use App\Core\Modules\ModuleNav;
use App\Core\Modules\ModuleRegistry;

$root = dirname(__DIR__);

function rrmdir(string $d): void {
    if (!is_dir($d)) { return; }
    foreach (scandir($d) as $f) { if ($f === '.' || $f === '..') { continue; } $p = "$d/$f"; is_dir($p) ? rrmdir($p) : unlink($p); }
    rmdir($d);
}
function tmpProject(): string {
    $d = sys_get_temp_dir() . '/m11cc_' . bin2hex(random_bytes(4));
    mkdir($d . '/modules', 0777, true); mkdir($d . '/routes', 0777, true); mkdir($d . '/config', 0777, true);
    file_put_contents($d . '/routes/core.php', "<?php return function (\$r, \$a) {};");
    file_put_contents($d . '/config/kernel.php', "<?php return ['core_version' => '1.2.0'];");
    file_put_contents($d . '/config/modules.php', "<?php return ['enabled' => [], 'trusted' => []];");
    return $d;
}
function putModule(string $proj, string $id, array $extra = [], ?string $routes = null): void {
    $dir = "$proj/modules/$id"; mkdir("$dir/src", 0777, true);
    if ($routes !== null) { $extra += ['trust' => 'trusted', 'routes' => 'routes.php']; file_put_contents("$dir/routes.php", $routes); }
    file_put_contents("$dir/module.json", json_encode(array_replace(['schema' => 1, 'id' => $id, 'name' => $id, 'version' => '1.0.0', 'core' => '>=1.0.0'], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
function con(Application $app, array $args, &$code): string { $o = ''; $c = new Console($app, function ($s) use (&$o) { $o .= $s; }); $code = $c->run($args); return $o; }
function appFor(string $proj, array $enabled, array $trusted): Application {
    return Application::create($proj, ['config' => ['bootstrappers' => [ModuleBootstrapper::class], 'modules' => ['enabled' => $enabled, 'trusted' => $trusted]]]);
}

// ---------- ModuleNav (legacy sidebar bridge) ----------
$p = tmpProject();
check('ModuleNav: no config file => []', ModuleNav::groupsFor($p . '/none'), []);
check('ModuleNav: nothing enabled => []', ModuleNav::groupsFor($p), []);
putModule($p, 'navmod', ['permissions' => [['key' => 'navmod', 'label' => 'N', 'actions' => ['view']]], 'navigation' => [['group' => 'مدیریت', 'label' => 'ماژول', 'icon' => 'ti-star', 'url' => '/navmod', 'permission' => 'navmod']]]);
file_put_contents("$p/config/modules.php", "<?php return ['enabled' => ['navmod'], 'trusted' => []];");
$groups = ModuleNav::groupsFor($p);
check('ModuleNav: an enabled declarative module contributes its menu', $groups[0]['items'][0]['label'], 'ماژول');
putModule($p, 'blocked_one', ['dependencies' => ['navmod_x' => '*'], 'permissions' => [['key' => 'blocked_one', 'label' => 'N', 'actions' => ['view']]], 'navigation' => [['group' => 'مدیریت', 'label' => 'نباید', 'icon' => 'ti-star', 'url' => '/b', 'permission' => 'blocked_one']]]);
file_put_contents("$p/config/modules.php", "<?php return ['enabled' => ['navmod', 'blocked_one', 'ghost'], 'trusted' => []];");
$groups = ModuleNav::groupsFor($p);
check('ModuleNav: blocked and unknown modules add nothing, the rest still shows', [count($groups), count($groups[0]['items'])], [1, 1]);
file_put_contents("$p/config/modules.php", "<?php this is broken");
$groups = [];
try { $groups = ModuleNav::groupsFor($p); } catch (Throwable $e) { $groups = ['threw']; }
check('ModuleNav: an unreadable config never throws into a page', $groups, []);
check('ModuleNav: the failure is logged', strpos((string) @file_get_contents($p . '/storage/logs/app-' . gmdate('Y-m-d') . '.log'), 'ModuleNav failed') !== false, true);
$base = [['title' => 'مدیریت', 'items' => [['key' => 'a']]], ['title' => 'تنظیمات', 'items' => [['key' => 'b']]]];
check('moduleNavMerge: same title appends items', moduleNavMerge($base, [['title' => 'مدیریت', 'items' => [['key' => 'm']]]])[0]['items'], [['key' => 'a'], ['key' => 'm']]);
check('moduleNavMerge: new title is added last', array_column(moduleNavMerge($base, [['title' => 'نمونه', 'items' => [['key' => 'm']]]]), 'title'), ['مدیریت', 'تنظیمات', 'نمونه']);
check('moduleNavMerge: nothing to merge', moduleNavMerge($base, []), $base);
check('moduleNavGroups: the default install (nothing enabled) is empty', moduleNavGroups($root), []);
rrmdir($p);

// ---------- boundary scanner ----------
$p = tmpProject();
putModule($p, 'lib', [], "<?php return function (\$r, \$a) {};");
putModule($p, 'app_a', ['dependencies' => ['lib' => '*']], "<?php use Modules\\Lib\\Contracts\\Thing; return function (\$r, \$a) {};");
file_put_contents("$p/modules/lib/src/Thing.php", "<?php namespace Modules\\Lib; class Thing {}");
@mkdir("$p/modules/lib/src/Contracts"); file_put_contents("$p/modules/lib/src/Contracts/Thing.php", "<?php namespace Modules\\Lib\\Contracts; interface Thing {}");
file_put_contents("$p/modules/app_a/src/Ok.php", "<?php namespace Modules\\AppA; use Modules\\Lib\\Contracts\\Thing; use App\\Core\\Http\\Response; class Ok { public function f(Thing \$t) {} }");
putModule($p, 'app_b', ['dependencies' => ['lib' => '*']], "<?php return function (\$r, \$a) {};");
file_put_contents("$p/modules/app_b/src/Internal.php", "<?php namespace Modules\\AppB;\nuse Modules\\Lib\\Thing;\nclass Internal { }");
putModule($p, 'app_c', [], "<?php return function (\$r, \$a) {};");
file_put_contents("$p/modules/app_c/src/Undeclared.php", "<?php namespace Modules\\AppC;\nclass Undeclared { public function f() { return new \\Modules\\Lib\\Contracts\\Thing(); } }");
file_put_contents("$p/modules/app_c/src/Ghost.php", "<?php namespace Modules\\AppC;\nclass Ghost { public function f() { return Modules\\Nobody\\X::class; } }");
file_put_contents("$p/modules/app_c/src/Comment.php", "<?php namespace Modules\\AppC;\n// Modules\\Lib\\Thing is only mentioned in a comment\nclass Comment {}");
$reg = (new ModuleRegistry($p . '/modules', '1.2.0', ['lib', 'app_a', 'app_b', 'app_c']))->scan();
check('boundary: using only the public Contracts of a declared dependency is fine', ModuleBoundaryScanner::scan($reg->get('app_a'), $reg->all()), []);
check('boundary: internal class of a dependency is reported', ModuleBoundaryScanner::scan($reg->get('app_b'), $reg->all()), ["src/Internal.php: line 2: uses internal class Modules\\Lib\\Thing of 'lib'; only Modules\\Lib\\Contracts\\ is public"]);
$c = ModuleBoundaryScanner::scan($reg->get('app_c'), $reg->all());
check('boundary: undeclared dependency and unknown module are reported', [count($c), strpos(implode("\n", $c), "'lib' is not a declared dependency of 'app_c'") !== false, strpos(implode("\n", $c), 'Modules\\Nobody\\X') !== false], [2, true, true]);
check('boundary: a module may use its own classes and the kernel', ModuleBoundaryScanner::scan($reg->get('lib'), $reg->all()), []);
rrmdir($p);

// ---------- Console ----------
$p = tmpProject();
putModule($p, 'fine', [], "<?php return function (\$r, \$a) { \$r->get('/fine', function () { return 'x'; })->name('fine.index'); };");
putModule($p, 'wrecked', [], "<?php return function (\$r, \$a) { throw new RuntimeException('nope'); };");
putModule($p, 'waits', ['dependencies' => ['wrecked' => '*']]);
mkdir("$p/modules/junk"); file_put_contents("$p/modules/junk/module.json", '{');
$all = ['fine', 'wrecked', 'waits'];
$out = con(appFor($p, $all, ['fine', 'wrecked']), ['modules:list'], $code);
check('modules:list shows every module with its state and reason', [$code, (bool) preg_match('/fine\s+1\.0\.0\s+trusted\s+loaded/', $out), (bool) preg_match('/wrecked\s+1\.0\.0\s+trusted\s+failed\s+RuntimeException: nope/', $out), (bool) preg_match('/waits\s+1\.0\.0\s+declarative\s+blocked\s+needs module \'wrecked\' which is failed/', $out), (bool) preg_match('/junk\s+-\s+-\s+invalid/', $out)], [0, true, true, true, true]);
check('modules:list prints a summary line', strpos($out, '1 blocked, 1 failed, 1 invalid, 1 loaded') !== false, true);
$out = con(appFor($p, $all, ['fine', 'wrecked']), ['modules:why', 'waits'], $code);
check('modules:why prints the reasons', [$code, strpos($out, "waits: blocked") !== false, strpos($out, "needs module 'wrecked' which is failed") !== false], [0, true, true]);
con(appFor($p, $all, ['fine', 'wrecked']), ['modules:why', 'nothing'], $code);
check('modules:why unknown id exits 1', $code, 1);
con(appFor($p, $all, ['fine', 'wrecked']), ['modules:why'], $code);
check('modules:why without an id exits 2', $code, 2);
$out = con(appFor($p, $all, ['fine', 'wrecked']), ['modules:check'], $code);
check('modules:check FAILED lists the problems and exits 1 (strict gate over a fail-soft boot)', [$code, strpos($out, 'PROBLEM module \'wrecked\' is failed') !== false, strpos($out, "PROBLEM module 'waits' is blocked") !== false, strpos($out, 'PROBLEM modules/junk') !== false, strpos($out, 'modules:check FAILED') !== false], [1, true, true, true, true]);
rrmdir("$p/modules/junk"); rrmdir("$p/modules/wrecked"); rrmdir("$p/modules/waits");
$out = con(appFor($p, ['fine'], ['fine']), ['modules:check'], $code);
check('modules:check OK when everything enabled is loaded', [$code, strpos($out, 'modules:check OK (1 loaded, 1 valid)') !== false], [0, true]);
putModule($p, 'crosser', [], "<?php return function (\$r, \$a) {};");
file_put_contents("$p/modules/crosser/src/X.php", "<?php namespace Modules\\Crosser; class X { public function f() { return \\Modules\\Fine\\Secret::class; } }");
$out = con(appFor($p, ['fine', 'crosser'], ['fine', 'crosser']), ['modules:check'], $code);
check('modules:check fails on a boundary violation', [$code, strpos($out, "boundary") !== false], [1, true]);
$out = con(appFor($p, ['fine'], ['fine']), ['routes:list'], $code);
check('routes:list shows module routes', [$code, strpos($out, '/fine') !== false, strpos($out, 'fine.index') !== false], [0, true, true]);
check('help exits 0 and mentions modules:why', [con(appFor($p, [], []), ['help'], $code) !== '', $code], [true, 0]);
con(appFor($p, [], []), ['nonsense'], $code);
check('unknown command exits 2', $code, 2);
con(appFor($p, [], []), [], $code);
check('no command prints help', $code, 0);
rrmdir($p);

// ---------- the real example module passes the strict gate ----------
$out = con(appFor($root, ['example'], ['example']), ['modules:check'], $code);
check('the sample module passes modules:check', [$code, strpos($out, 'modules:check OK (1 loaded') !== false], [0, true]);
$out = con(appFor($root, [], []), ['modules:check'], $code);
check('the default install (nothing enabled) passes modules:check', [$code, strpos($out, 'modules:check OK (0 loaded') !== false], [0, true]);
finish('modules_console_nav_test');
```

### STEP 7 — documentation, hand-off set, manifest, changelog

Four edits to existing hand-off files, then three new files.

### EDIT `docs/ai/HANDOFF.md` — E4

Current-state section: add the 11C line.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```markdown
- 11B — HTTP kernel: `public/index.php` is the **only** entry point (see `docs/kernel.md`); old pages/APIs run only through the allowlist `config/legacy.php`; `config/config.php` answers 404 to any legacy file reached directly; kernel in `app/` (router, middleware, container, validator, logger); `php tools/verify/http_smoke.php <url>` checks a running site. New endpoints are never written in the old response shape.

```

**REPLACE IT WITH:**

```markdown
- 11B — HTTP kernel: `public/index.php` is the **only** entry point (see `docs/kernel.md`); old pages/APIs run only through the allowlist `config/legacy.php`; `config/config.php` answers 404 to any legacy file reached directly; kernel in `app/` (router, middleware, container, validator, logger); `php tools/verify/http_smoke.php <url>` checks a running site. New endpoints are never written in the old response shape.
- 11C — module system (see `docs/modules.md`): a module is `modules/<id>/module.json` (declarative; never executed during discovery); only modules that are `trusted` in the manifest **and** listed in `config/modules.php` `trusted` may run PHP; loading is fail-soft (a broken module is skipped and logged); `php bin/console modules:check` is the strict gate; module permissions are collected in memory only until 11F1.

```

### EDIT `docs/ai/DECISIONS.md` — E5

Append decisions D-022 … D-027.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```markdown
| D-021 | One owner per cross-cutting capability (logging 11B; rate limit, events, queue, cache 11F2; CSP 11E1; readiness 11H). Table in `docs/kernel.md`. | Prevents two parts building the same thing. |
```

**REPLACE IT WITH:**

```markdown
| D-021 | One owner per cross-cutting capability (logging 11B; rate limit, events, queue, cache 11F2; CSP 11E1; readiness 11H). Table in `docs/kernel.md`. | Prevents two parts building the same thing. |
| D-022 | Module manifest is `module.json` (schema 1). Discovery reads and validates it and never executes module code. The old `module.php` is not read. | A PHP manifest was `require`d during scan: one syntax error or one malicious file took the whole panel down. |
| D-023 | Module trust: `declarative` (data only, never runs PHP) or `trusted` (routes, providers, autoload). Trust needs the manifest word **and** the owner's id in `config/modules.php` `trusted`. No marketplace, no downloaded code. | Running another party's PHP is a full compromise of the panel; the owner must decide per module. |
| D-024 | Loading is fail-soft: invalid, blocked or failed modules are skipped and logged; the rest of the panel runs. `modules:check` is the strict gate (exit 1). | One broken optional module must not be an outage; the release gate still refuses it. |
| D-025 | Routes, permissions and menu entries of a module are staged and committed only when the whole module loaded. | No half-registered module after a failure. |
| D-026 | Cross-module use only through declared dependencies and `Modules\<Other>\Contracts\`; checked by a token scan in `modules:check`. | Keeps business modules independent (Lego rule). |
| D-027 | Enabled and trusted lists live in `config/modules.php` (owner-edited) until the lifecycle CLI and a module table exist (11G / 11D). Module permissions are in memory until `PermissionSync` (11F1). | Persistence needs the database parts; stating the gap avoids pretending a module can be granted to staff today. |
```

### EDIT `docs/ai/FILE-MAP.md` — E6

Append the 11C section.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```markdown
| `docs/ai/manifests/11B.json` | new | sha256 of the frozen files of 11B |
```

**REPLACE IT WITH:**

```markdown
| `docs/ai/manifests/11B.json` | new | sha256 of the frozen files of 11B |

## Added or changed by Part 11C (module system)
| Path | Kind | What it is |
|---|---|---|
| `app/Core/Modules/*` | new | `ModuleManifest`, `Module`, `ModuleRegistry`, `ModuleState`, `ModuleStatuses`, `ModuleBoundaryScanner`, `ModuleNav`, `NavRegistry`, `PermissionRegistry`, `PrefixAutoloader`, `ModuleProviderInterface`, `ModuleException` |
| `app/Core/Support/Version.php` | new | version and constraint helper |
| `app/Bootstrap/ModuleBootstrapper.php` | new | fail-soft loader (staged commit, logging) |
| `app/Console/Console.php`, `bin/console` | new | `modules:list`, `modules:why`, `modules:check`, `routes:list` |
| `config/modules.php` | new | `enabled` and `trusted` lists (owner-edited) |
| `config/kernel.php` | changed | `core_version`, module bootstrapper |
| `includes/module_nav.php`, `includes/header.php` | new / changed | module menu entries in the legacy sidebar |
| `modules/example/**` | new | sample trusted module (JSON route, HTML page, permission, menu) |
| `docs/modules.md` | new | contract, trust, lifecycle, failure policy |
| `tests/modules_*_test.php` | new | version/manifest, registry, boot (fail-soft), console/nav/boundaries |
| `docs/ai/manifests/11C.json` | new | sha256 of the frozen files of 11C |
```

### EDIT `docs/ai/RELEASE-BLOCKERS.md` — E7

RB-7: module state is a hand-edited file until 11G.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```markdown
| RB-6 | Inline scripts/styles make a strict Content-Security-Policy impossible today. | 11E1 | open |
```

**REPLACE IT WITH:**

```markdown
| RB-6 | Inline scripts/styles make a strict Content-Security-Policy impossible today. | 11E1 | open |
| RB-7 | The enabled/trusted module lists are a hand-edited PHP file (`config/modules.php`); module permissions are not persisted; there is no install/upgrade/uninstall. | 11G (lifecycle CLI) with 11D (module state table) and 11F1 (permission sync) | open |
```

### CREATE `docs/modules.md`

Content (copy exactly; it ends with a newline):

````markdown
# Module system (Part 11C, revision 2)

A module is a folder `modules/<id>/` with a **declarative manifest `module.json`**. The core never imports a module; modules plug in through the manifest and through the kernel contracts (`docs/kernel.md`). Discovery only *reads* files: it never includes or runs anything from a module folder.

```
modules/<id>/
  module.json       manifest (JSON object, UTF-8)                       required
  routes.php        returns function (Router $router, Application $app): void   trusted modules only
  src/              classes, namespace Modules\<Studly>\ (PSR-4; "leave_requests" -> LeaveRequests)   trusted modules only
```

## Trust: who may run PHP
| Level | Manifest | May contribute | Runs its PHP? |
|---|---|---|---|
| declarative (default) | `"trust": "declarative"` or no field | permissions, menu entries (and later: settings, assets) | **never** |
| trusted | `"trust": "trusted"` **and** the owner lists the id in `config/modules.php` `"trusted"` | everything above + routes file + providers + `src/` autoload | yes |

A manifest can never grant itself trust. A trusted manifest whose id is not in the owner's list is *blocked* with the exact instruction. Only first-party modules in this repository, or code the owner has read, belong in the `trusted` list. There is no module store and no code download: a module is a folder the owner put there. (Decision D-023.)

## Manifest fields
| Field | Required | Meaning |
|---|---|---|
| `schema` | yes | the number `1` |
| `id` | yes | `[a-z][a-z0-9_]{1,31}`, equals the folder name, not a reserved core name (`ModuleManifest::RESERVED`) |
| `name` | yes | Persian display name, at most 80 characters |
| `description` | no | at most 300 characters |
| `version` | yes | `1.2.3` |
| `core` | yes | constraint on `core_version` of `config/kernel.php`, e.g. `>=1.0.0` or `*` |
| `trust` | no | `declarative` (default) or `trusted` |
| `dependencies` | no | `{"other": ">=1.0.0"}`; the other module must be enabled and loaded |
| `providers` | no | class names implementing `ModuleProviderInterface` (trusted only); `register()` runs once, before the module's routes, permissions and menu entries are committed |
| `routes` | no | relative file, must exist (trusted only) |
| `migrations` | no | relative folder. **Declared in 11C, applied by the module lifecycle CLI (11G)** |
| `permissions` | no | `[{"key":"hrm","label":"...","actions":["view","edit"]}]`; key = module id or `<id>_...` |
| `navigation` | no | `[{"group","label","icon":"ti-...","url":"/path","permission":"<one of this module's keys>"}]` |
| `settings`, `assets` | no | shape-checked only; used by later parts |

Unknown fields are errors. Version constraints: `*`, or space-separated `>=`, `<=`, `>`, `<`, `=` comparisons (no `^` / `~`). `module.json` larger than 64 KB, nested deeper than 16 levels, or not a JSON object is invalid. The old `module.php` manifest is no longer read (a folder that only has it is reported as invalid with the migration hint).

## Lifecycle and who owns each step
States seen after boot (`php bin/console modules:list`): **disabled** (valid, not in `enabled`), **invalid** (manifest problem, or enabled id without a folder), **blocked** (core version, dependency, cycle or missing trust grant), **failed** (loading threw: ParseError, exception in the routes file or a provider, route/permission collision), **loaded**.

| Step | Owner part | State today |
|---|---|---|
| discover, validate, resolve order, load, report | 11C | done |
| enable / disable | owner edits `config/modules.php` today; persisted state + CLI (`modules:enable`, `modules:disable`) in 11G | manual |
| install (run the module's migrations, sync its permissions into the database) | 11G CLI + 11F1 permission sync | not yet |
| upgrade, uninstall | 11G | not yet |

**Failure policy (fail-soft).** On a web request a module that is invalid, blocked or fails never stops the panel or other modules: its routes, permissions and menu entries are not registered, the reason is kept (`modules:list`, `modules:why <id>`) and written to `storage/logs/app-YYYY-MM-DD.log`. `php bin/console modules:check` is the strict gate (exit 1) and belongs in every release (11H).

**Dependencies.** A module is loaded only after every dependency is loaded. Disabling or breaking a dependency blocks its dependents (transitively) with the reason `needs module 'x' which is <state>`. Dependency cycles block all members. Nothing is deleted or migrated by disabling.

## Contracts between modules
1. A module may use classes of another module only if that module is in its `dependencies`.
2. Even then, only `Modules\<Other>\Contracts\...` (interfaces and value objects) is public; everything else is internal.
3. A module never edits core files. If it needs something that does not exist in the core, write it in `docs/ai/OPEN-ISSUES.md`.
4. Two modules may not define the same method + path or the same route name, nor declare the same permission key.
`modules:check` enforces 1 and 2 with a token scan (`ModuleBoundaryScanner`) and 4 at load.

## Permissions are not stored (yet)
`PermissionRegistry` only **collects** what loaded modules declare, in memory. Writing them into the `permissions` table (so staff can be granted them in the UI) is `PermissionSync` in part 11F1. Until then only the owner passes `permission:<key>.<action>` on a module route (fail-closed).

## Module HTML pages (contract v0)
A page is a route that returns `Response::html(...)`; it must escape everything it prints and must not include the old `header.php` / `footer.php`. The sample `modules/example/src/Controllers/PageController.php` shows the shape. From part 11E1 on, pages return a view rendered inside the shell instead; the contract changes in one place.

## Commands
`php bin/console modules:list` · `modules:why <id>` · `modules:check` (exit 1 on any problem) · `routes:list`

## Try the sample
Add `'example'` to both `enabled` and `trusted` in `config/modules.php`, run `php bin/console modules:check` (expect `OK (1 loaded ...)`), open `/example/page` as the owner. Remove it again afterwards.

## Troubleshooting
| Symptom | Look at |
|---|---|
| a module's menu item is missing | `php bin/console modules:why <id>`; `storage/logs/` |
| `trust 'trusted' is not granted` | add the id to `trusted` in `config/modules.php` (only after reading the module's code) |
| `module.php is no longer supported` | convert the array to JSON in `module.json` and add `"schema": 1` |
````

### CREATE `docs/ai/manifests/11C.json`

Content (copy exactly; it ends with a newline):

```json
{
  "files": {
    "app/Bootstrap/ModuleBootstrapper.php": "f3d74fc649ff32b7e42b444890afc6c48398b854fabf64af74416f98155d418d",
    "app/Console/Console.php": "3677135830866e7fb741b02b2ff845b39721be3a037614e0c85fdfe9561b3d0e",
    "app/Core/Modules/Module.php": "4493d52ce925cb4328c6af2ea920b9c7019f4d8bbd49d75659970acb5391480b",
    "app/Core/Modules/ModuleBoundaryScanner.php": "edbb88e06a45a63c7b974fb89129911973db5ce3e437a2ac1f1e433f2787c475",
    "app/Core/Modules/ModuleException.php": "085b28fc12588921c770f39f3bd8872f3cffab1e4c720dda01828dbc9a7a686a",
    "app/Core/Modules/ModuleManifest.php": "3625ff69ffdec3845a6e4aa0f23821315ace8a3cf04a0f1232a9f75bd284e895",
    "app/Core/Modules/ModuleNav.php": "0718bbbc1a23c9354cadc5bfd253e7fbba132159ead9404da0899be68789beab",
    "app/Core/Modules/ModuleProviderInterface.php": "a4c289e1103fee624e5ffeaf31c48f8cd53dbc64068969b27acd067e7a0a0f1d",
    "app/Core/Modules/ModuleRegistry.php": "2564593173927c07bdadf3038dbc7e50b66bdd93ee15e6307c541c71cb80255e",
    "app/Core/Modules/ModuleState.php": "d8ae461f56080a9343e657df9e2a9b61808eb3ee59d192043849f00e485aa74c",
    "app/Core/Modules/ModuleStatuses.php": "379aaed7d31f2fb957301ff11e0db280dc33e9bd0fa5ae2cf7f5d27ab3c3ad2e",
    "app/Core/Modules/NavRegistry.php": "6ab24754bff95e2d365783eb97aabb55b539e9f27337962d21ca866026ab54ac",
    "app/Core/Modules/PermissionRegistry.php": "e7f9d9f4c95aa03e0a841ac88125e557ef8d24acc34e6471632608e202f62822",
    "app/Core/Modules/PrefixAutoloader.php": "f5892139fddf9336ba447e1d896260819d5bb467fdf5730a2cce1aa74c6ff98d",
    "app/Core/Support/Version.php": "696c7d54c6c3417ddca20b65a48c01471c7a7525ebdae96b473a6c4498b768e2",
    "bin/console": "6eb8469ef228a0abd5780f43682af4d7a56903973361cf7a03625ccc7dedb67a",
    "config/kernel.php": "357957ef149f940afa07e59c0ab2509ffc9107baaef0b2eeccb49ac5c534ba86",
    "config/modules.php": "c66068123edb8b7bbf46733cf3d8a1030890b209136d9c6347bb3d0e33d6f5fd",
    "includes/header.php": "36338b398ec66aba3e92829fac8d47c6ac804d1f076113d7202bc8b6709b17b8",
    "includes/module_nav.php": "f61b9d78f82edc8b6102dadb3d26cab68dc6fe719e65b5cae34d4696dbcd244e",
    "modules/example/README.md": "667711de77bbb4c9a88f4802fc11a2ab0b690263c8fb6643887feddc919a91cc",
    "modules/example/module.json": "1755a8cd23b263c5cfa1d6b88b465ef724b07e4852f0fbe3a87d191cf48803da",
    "modules/example/routes.php": "5b2695f17ce5b29d2c45629757e3805a037a340e97f35b2ed0f2ace936746f95",
    "modules/example/src/Controllers/PageController.php": "838e5c5b27e728ce2f35511539daa47b8e0757dc5a08edd2e134f14271d313a2",
    "modules/example/src/Controllers/PingController.php": "ed807ffb942f5bfe35e094451f03f10e4fae074a02451262061760c7b10156d4",
    "tests/modules_boot_test.php": "3c8d760c56959e3d57307af290a5f7aef70001cb6316459e8f9f42d8309a9402",
    "tests/modules_console_nav_test.php": "e490eeccfd2f36042996ee862f3b8a346342ce218387a4b91a00fdc24ba38d1b",
    "tests/modules_registry_test.php": "0491d7b4920ab1de08ed9790b4ce73059c4b9f1bda49dd0bcd35bcb96ed78822",
    "tests/modules_version_manifest_test.php": "216b8a91524d3704f7a6c134bc6006cd432d2a2de4c078c209495ae846ab6481"
  },
  "part": "11C"
}
```

### CREATE `changelogs/changelog-part-11c-module-system.md`

Content (copy exactly; it ends with a newline):

```markdown
# changelog — Part 11C (module system)

**Written from:** `master-prompt-11C-module-system.md` (revision 2). Author of the code: the prompt author (tested in a Linux sandbox, PHP 8.3, SQLite, no MySQL, no Apache). The executing agent copied files and ran the checks; it did no engineering.

## 1. What changed
- Declarative manifest `modules/<id>/module.json` (schema 1). Discovery only reads JSON; nothing from a module folder is executed while scanning. A legacy `module.php` is reported as invalid with a hint.
- Trust levels: `declarative` (data only) and `trusted` (routes, providers, autoload). Trust needs the manifest and the owner's id in `config/modules.php` `trusted`.
- Fail-soft loading (`ModuleBootstrapper`): invalid, blocked and failed modules are skipped and logged (`storage/logs/`); routes, permissions and menu entries are staged and committed only after the whole module loaded; ParseError, provider errors and route collisions are covered.
- Lifecycle states (`disabled, invalid, blocked, failed, loaded`), dependency-disable behaviour (dependents are blocked), cycle handling, module boundary scan (`ModuleBoundaryScanner`).
- `bin/console`: `modules:list`, `modules:why`, `modules:check` (strict gate), `routes:list`. Sample module `modules/example` (JSON route, HTML page, permission, menu item), not enabled by default.
- Legacy sidebar bridge: `includes/module_nav.php` + one edit of `includes/header.php`; nothing changes while no module is enabled.
- Hand-off docs: HANDOFF, DECISIONS (D-022…D-027), FILE-MAP, RELEASE-BLOCKERS (RB-7), `docs/modules.md`, manifest `11C.json`.

## 2. Not verified by the author (be honest)
MySQL; Apache; a real logged-in staff session seeing a module menu item (module permissions are not stored in the database until 11F1, so only the owner sees module menu items today); Windows paths; PHP 8.0 runtime; the sidebar rendering of module entries in a browser.

## Backup and baseline
(The executing agent writes the backup path, the PHP version, the baseline test output and the Step 1 output here.)

## Deviations from the prompt
(The executing agent replaces this line with `none`, or with one bullet per deviation: what, why, which file.)

## Gate outputs (verbatim)
(The executing agent pastes below this line the real output of every command the prompt's Acceptance Checklist asks for, unedited.)
```

### STEP 8 — verification (run all, record real output)

```text
php -l on every PHP file created or edited in Steps 3–6 (list below)
php tools/gates/verify_manifest.php docs/ai/manifests/11C.json
php tests/run_all.php
php tools/gates/php80_gate.php
php database/tools/check_cms_removed.php
php bin/console modules:check
php bin/console modules:list
```

PHP files to lint:

```text
app/Core/Support/Version.php
app/Core/Modules/ModuleException.php
app/Core/Modules/ModuleProviderInterface.php
app/Core/Modules/ModuleState.php
app/Core/Modules/ModuleStatuses.php
app/Core/Modules/PermissionRegistry.php
app/Core/Modules/NavRegistry.php
app/Core/Modules/PrefixAutoloader.php
app/Core/Modules/Module.php
app/Core/Modules/ModuleManifest.php
app/Core/Modules/ModuleRegistry.php
app/Core/Modules/ModuleBoundaryScanner.php
app/Core/Modules/ModuleNav.php
app/Bootstrap/ModuleBootstrapper.php
app/Console/Console.php
config/modules.php
includes/module_nav.php
modules/example/routes.php
modules/example/src/Controllers/PingController.php
modules/example/src/Controllers/PageController.php
tests/modules_version_manifest_test.php
tests/modules_registry_test.php
tests/modules_boot_test.php
tests/modules_console_nav_test.php
config/kernel.php
includes/header.php
bin/console
```

Expected:
- manifest: last line `manifest 11C: 29 ok, 0 bad, 0 info`. A `MISMATCH` is a copy error: re-copy that file from this prompt (never edit the manifest).
- tests: **28 lines**, all `OK` (24 + `modules_boot_test`, `modules_console_nav_test`, `modules_registry_test`, `modules_version_manifest_test`).
- gate: `violations: 0`. `check_cms_removed.php`: `cms leftovers: 0`.
- `modules:check`: `modules:check OK (0 loaded, 1 valid)` and exit code 0. `modules:list`: one row, `example  1.0.0  trusted  disabled  ماژول نمونه`, then the line `1 disabled`.

### STEP 9 — smoke test of the running site (if it is running)

Because `includes/header.php` changed, open pages must still render. If the owner's site is running, run `php tools/verify/http_smoke.php <the address the owner uses, for example http://localhost>` (add `--skip-db` only if the database is stopped). Expected: `http smoke: 18 passed, 0 failed, 0 skipped`. Paste the output into the changelog. If nothing answers write "not run: no server answered". A failure: record it under Deviations, set the status to `partial` in Step 10, do not fix anything.

### STEP 10 — finish the changelog and the state file

1. In `changelogs/changelog-part-11c-module-system.md` fill the three placeholder sections: "Backup and baseline", "Deviations from the prompt" (`none` or one bullet each), "Gate outputs (verbatim)" (the real output of Steps 1, 8 and 9).
2. Run `php tools/ai/mark_part.php 11C executed "module system applied"`. Use `partial` instead of `executed` when Step 9 failed or any step was stopped; use `blocked` if Step 1 failed. It must print `state.json: 11C = <status>`.

## 8. Acceptance Checklist

- [ ] 11B was executed; pre-flight outputs and the backup path are in the changelog.
- [ ] `php tools/gates/verify_manifest.php docs/ai/manifests/11C.json`: `29 ok, 0 bad, 0 info`.
- [ ] `config/modules.php` has `'enabled' => []` and `'trusted' => []` (nothing is enabled or trusted).
- [ ] `config/kernel.php` has `core_version` and the `ModuleBootstrapper` entry; `includes/header.php` has the `moduleNavMerge(` line.
- [ ] `php tests/run_all.php`: 28 lines, all `OK`.
- [ ] `php tools/gates/php80_gate.php`: `violations: 0`; `check_cms_removed.php`: `cms leftovers: 0`.
- [ ] `php bin/console modules:check`: `OK (0 loaded, 1 valid)`; `modules:list` shows the sample as `disabled`.
- [ ] Step 9: smoke output pasted (18 passed) **or** "not run" with the reason, **or** failures recorded with status `partial`.
- [ ] `docs/modules.md` exists; DECISIONS has D-022…D-027; RELEASE-BLOCKERS has RB-7; FILE-MAP has the 11C section; `state.json` shows `11C`.
- [ ] No existing page or API edited; `composer.json`, `composer.lock`, `database/` untouched.
- [ ] Changelog complete.

## 9. Final message to the owner (Persian)

Write a short Persian report: what was done (by step), the real outputs (manifest line, number of test lines, `modules:check` line, smoke line or «اجرا نشد»), every deviation, and what you could **not** run. Then give these **manual checks for the owner**:

1. پنل را باز کن و وارد شو؛ همهٔ صفحه‌ها و منوی کناری باید دقیقاً مثل قبل باشند (هیچ ماژولی فعال نیست).
2. در پوشهٔ پروژه دستور `php bin/console modules:list` را اجرا کن؛ باید ماژول «ماژول نمونه» با وضعیت `disabled` دیده شود.
3. اگر خواستی ماژول نمونه را امتحان کنی: در `config/modules.php` شناسهٔ `example` را هم به `enabled` و هم به `trusted` اضافه کن، `php bin/console modules:check` را اجرا کن، سپس با حساب مالک آدرس `/example/page` را باز کن و بعد از امتحان آن را دوباره بردار.
