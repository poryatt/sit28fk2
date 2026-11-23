# MASTER PROMPT 11B — HTTP kernel, single entry point and legacy gateway

> **Series:** Part 11 (modular admin application platform) · **Revision 2** (rewritten: the single-entry-point problem of revision 1 is fixed) · **File:** `master-prompt-11B-http-kernel.md` · **Language of this file:** English (final message to the owner: Persian).

| Field | Value |
|---|---|
| **Status** | `ready` (authored and tested by the author; not yet executed) |
| **Outcome** | Every web request enters through `public/index.php`. The kernel (router, middleware, container, validator, logger) answers its own routes; the old pages and APIs run only through an explicit allowlist; any other way of reaching an old file answers 404. |
| **Contract (true after this part)** | 1. `.htaccess` sends all requests except `/assets/`, `/uploads/`, `/ui-kit/` to `public/index.php` (no "file exists" escape). 2. `config/config.php` answers 404 when a legacy file runs without the front controller. 3. `config/legacy.php` lists exactly the PHP files of `public/` except `index.php` (a test enforces both directions). 4. `/health` and `/api/v1/health` answer a v2 envelope with `X-Request-Id`. 5. `php tests/run_all.php` prints 24 lines (17 + 7), all `OK`. 6. `php tools/verify/http_smoke.php <url>` passes on the running site. |
| **In scope** | Kernel in `app/`; `LegacyGateway`; `Logger`; fail-closed guard; rewrite block; `tools/verify/*`; tests; `docs/kernel.md` (PSR decision, envelope migration timeline, homes of cross-cutting capabilities); hand-off docs; manifest. |
| **Out of scope** | Modules (11C), views and CSP (11E1), database (11D), moving any legacy file to a route (11E3, 11F1, 11F2), rate limit / events / queue / cache (11F2), real Apache + MySQL release run (11H). |
| **Requires** | 11A executed. |
| **Unblocks** | 11C (module loader plugs into `config/kernel.php` `bootstrappers` and uses the Logger). |
| **Runtime proof** | `php tests/run_all.php` (includes a real HTTP test with the built-in server), `php tools/gates/verify_manifest.php docs/ai/manifests/11B.json`, and on the owner's running site `php tools/verify/http_smoke.php http://localhost`. |
| **Tested by the author** | Linux sandbox, PHP 8.3, no MySQL, no Apache. Replayed literally on a clean copy of the post-11A tree: 24/24 tests green (about 300 kernel checks, plus real HTTP requests to PHP's built-in server: all 16 legacy files answer 404 when requested directly, the router script behaves like `.htaccess`); mutation checks (removing the guard, removing a file from the allowlist, adding an unlisted file) make the tests fail; the smoke tool passed 18/18 against the built-in server; gate 0 violations; manifest 39 ok. |
| **NOT tested by the author** | **Apache itself**: the rewrite block of `.htaccess` was never executed by Apache (its effect was imitated); `AllowOverride`/`mod_rewrite` on the owner's XAMPP; the legacy bridges (`auth`, `csrf`, `permission`) against a MySQL-backed session (tests use fakes); Windows paths; PHP 8.0 runtime. The smoke run on the owner's server (Step 9) is the first real test of the rewrite. |

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

Revision 1 of this part added a kernel but left Apache serving real files directly, so every old page could still bypass it. This revision closes that:

- **Single entry point.** `public/index.php` is the only door. The router answers first; if it has no route, `LegacyGateway` lets an old file run only if `config/legacy.php` lists it and the URL equals `/` + that path exactly.
- **Fail closed.** If `.htaccess` is ignored (mod_rewrite off, `AllowOverride None`), old files answer 404 by themselves, because `config/config.php` refuses to run without the front controller.
- **Kernel** (namespace `App\`, folder `app/`): Config, Container (constructor autowiring), Request, Response (standard envelopes), Router, Pipeline, middleware, ExceptionHandler, Validator (Persian messages), Application (`boot()` with a `bootstrappers` extension point), **Logger** (new).
- **Decisions the brief asked for**, written in `docs/kernel.md` and `docs/ai/DECISIONS.md`: PSR decision (D-017), API-envelope migration timeline with an owner part for each old file (D-020), one owner part for each cross-cutting capability — logging, rate limiting, events, jobs, cache, observability, CSP (D-021).

**Changes the author made to the spec and to revision 1 (stated openly)**
- Added `Logger` to this part (revision 1 had none): the exception handler and the module loader (11C) need a log, and it cannot be postponed. Rate limiting, events, queue and cache are *not* built here; their owner is 11F2.
- CSP is not added here. It is designed together with the view engine in 11E1 (a nonce per request needs the views). `SecurityHeaders` still sends the three basic headers.
- The old rewrite rule (`!-f`, `!-d`) is **removed**, not extended. This is a deliberate breaking change: if Apache does not honour `.htaccess`, the panel shows 404 until mod_rewrite/`AllowOverride All` is fixed (see `docs/kernel.md`). A hidden insecure fallback was the bug.
- `/ui-kit/` stays outside the rewrite (it is a developer page protected by its own `.htaccess`, `Require local`).
- Tests stay flat in `tests/` (the project runner discovers `tests/*_test.php`).
- A real Apache + MySQL release run is **not** claimed here; it is a required gate of 11H.

## 2. File tree (only what this part touches)

```text
.
├── app/
│   ├── Bootstrap/
│   │   ├── Application.php   [NEW]
│   │   └── Bootstrapper.php   [NEW]
│   ├── Core/
│   │   ├── Config/
│   │   │   └── Config.php   [NEW]
│   │   ├── Container/
│   │   │   ├── Container.php   [NEW]
│   │   │   └── ContainerException.php   [NEW]
│   │   ├── Http/
│   │   │   ├── Middleware/
│   │   │   │   ├── AuthMiddleware.php   [NEW]
│   │   │   │   ├── CsrfMiddleware.php   [NEW]
│   │   │   │   ├── MiddlewareInterface.php   [NEW]
│   │   │   │   ├── PermissionMiddleware.php   [NEW]
│   │   │   │   ├── RequestId.php   [NEW]
│   │   │   │   └── SecurityHeaders.php   [NEW]
│   │   │   ├── ExceptionHandler.php   [NEW]
│   │   │   ├── HttpException.php   [NEW]
│   │   │   ├── LegacyGateway.php   [NEW]
│   │   │   ├── Pipeline.php   [NEW]
│   │   │   ├── Request.php   [NEW]
│   │   │   ├── Response.php   [NEW]
│   │   │   ├── Route.php   [NEW]
│   │   │   └── Router.php   [NEW]
│   │   ├── Log/
│   │   │   └── Logger.php   [NEW]
│   │   └── Validation/
│   │       ├── ValidationResult.php   [NEW]
│   │       └── Validator.php   [NEW]
│   └── autoload.php   [NEW]
├── changelogs/
│   └── changelog-part-11b-http-kernel.md   [NEW]
├── config/
│   ├── config.php   [CHANGED]
│   ├── kernel.php   [NEW]
│   └── legacy.php   [NEW]
├── docs/
│   ├── ai/
│   │   ├── manifests/
│   │   │   └── 11B.json   [NEW]
│   │   ├── DECISIONS.md   [CHANGED]
│   │   ├── FILE-MAP.md   [CHANGED]
│   │   ├── HANDOFF.md   [CHANGED]
│   │   ├── RELEASE-BLOCKERS.md   [CHANGED]
│   │   └── state.json   [CHANGED (by the tool)]
│   └── kernel.md   [NEW]
├── public/
│   ├── .htaccess   [CHANGED]
│   └── index.php   [CHANGED]
├── routes/
│   └── core.php   [NEW]
├── tests/
│   ├── _kernel.php   [NEW]
│   ├── gateway_http_test.php   [NEW]
│   ├── kernel_app_test.php   [NEW]
│   ├── kernel_container_config_test.php   [NEW]
│   ├── kernel_http_test.php   [NEW]
│   ├── kernel_validator_test.php   [NEW]
│   ├── legacy_gateway_test.php   [NEW]
│   └── logger_test.php   [NEW]
├── tools/
│   └── verify/
│       ├── dev_router.php   [NEW]
│       └── http_smoke.php   [NEW]
└── .gitignore   [CHANGED]
```

## 3. Database design (PK/FK) — **no schema change**

No table, column, index, PK or FK is created, altered or dropped; there is no migration; do not open `database/`. Reason: the kernel handles HTTP only. It reads the session through the existing functions (`isLoggedIn`, `csrf_verify`, `hasPermission`), which use the existing tables (`panel_users`, `permissions`, …) unchanged. `/health` deliberately does not touch the database (liveness probe; a readiness probe with a DB check is owned by 11H). Request ids and logs live in memory and in `storage/logs/`, not in the database. Table design for audit, settings and module state belongs to 11D.

## 4. Library decision

**No Composer package is added; `composer.json` and `composer.lock` are not touched.**

| Option | Decision | Reason |
|---|---|---|
| Laravel / Symfony / Slim | rejected | a framework would be a rewrite of a working panel |
| `psr/http-message`, `psr/container`, `psr/log` | **not now** (D-017) | no package budget; the kernel is small and tested; a PSR-7/15 adapter can be added at one seam later (decision point 11G) |
| FastRoute / a router package | rejected | the route table is about 150 lines and covered by tests |
| Own kernel (this part) | **chosen** | fits the existing procedural code through three narrow bridges |

## 5. "Must NOT do" table

| Do not | Why |
|---|---|
| Edit any existing page, API or include other than the four files in Step 4 | the old code is moved later, one file per part |
| Add a file to `config/legacy.php` or remove one by hand | the list must equal the PHP files of `public/`; the test checks it |
| Re-add `RewriteCond %{REQUEST_FILENAME} !-f` / `!-d` | that is the bypass this part removes |
| Put a PHP file into `public/` | it would be unreachable (404) and the drift test fails |
| "Fix" a 404 on the owner's site by weakening the guard in `config/config.php` | the fix is Apache's `mod_rewrite` / `AllowOverride All` (see `docs/kernel.md`) |
| Install `psr/*` or any package; edit `composer.json` / `composer.lock` | decision D-017 |
| Write a new endpoint in the old `{success,message}` shape | decision D-020 |
| Touch `database/`, run any database command | no database step exists |
| Run `git`, `composer`, `npm` | not needed |
| Rewrite Persian strings | they are exact (ی ک only) |

## 6. Prerequisites (check first)

1. 11A must be executed: `docs/ai/state.json` has `11A` with status `executed`; `tools/gates/php80_gate.php`, `tools/ai/mark_part.php`, `docs/ai/manifests/11A.json` exist. Otherwise **stop**.
2. These must **not** exist (else this part was applied before → stop and ask): `app/`, `routes/`, `config/kernel.php`, `config/legacy.php`, `tools/verify/`, `docs/kernel.md`, `docs/ai/manifests/11B.json`.
3. `public/` must contain exactly these PHP files (record any difference; the allowlist in this prompt lists 16 and the drift test will fail if your tree has more or fewer): `index.php`, `login.php`, `logout.php`, `modules/dashboard/index.php`, `modules/media/index.php`, `modules/settings/{general,profile,staff}.php`, `api/media/{delete,info,upload}.php`, `api/notifications/mark_read.php`, `api/staff/{invite,remove,save_permissions,set_status,toggle_theme}.php`.
4. `php -v` → PHP 8.0 or newer (record it).

## 7. Steps

### STEP 1 — pre-flight (read-only)

```text
php tools/gates/verify_manifest.php docs/ai/manifests/11A.json
php tests/run_all.php
php tools/gates/php80_gate.php
```

Expected: `manifest 11A: 15 ok, 0 bad, 0 info`; 17 lines all `OK`; `violations: 0`. Record the real outputs as the baseline.

### STEP 2 — backup

Copy the whole project folder to `../panel-backups/pre-11B/` (exclude `vendor/`, `node_modules/`, `.git/`; include `storage/` and `public/uploads/`). Example (Windows): `robocopy . ..\panel-backups\pre-11B /E /XD vendor node_modules .git`. If the target exists use `pre-11B-2`. Write the path in the changelog. If you cannot copy → stop.

### STEP 3 — create the kernel, routes and settings

All files below are new. Create the folders as needed. Check after the step: `php -l` on each file prints `No syntax errors detected`.

### CREATE `app/autoload.php`

Content (copy exactly; it ends with a newline):

```php
<?php
// app/autoload.php — Part 11B
// Tiny PSR-4 autoloader for the "App\" namespace (folder app/). It is independent from Composer on purpose:
// the kernel must work even if nobody ran `composer dump-autoload`. Safe to require more than once.
spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'App\\', 4) !== 0) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
```

### CREATE `app/Core/Config/Config.php`

Content (copy exactly; it ends with a newline):

```php
<?php
declare(strict_types=1);

namespace App\Core\Config;

/** Read-mostly configuration bag with dot access: $config->get('kernel.login_url'). */
final class Config
{
    /** @var array<string,mixed> */
    private array $items;

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public function has(string $key): bool
    {
        $found = false;
        $this->lookup($key, $found);
        return $found;
    }

    /** @param mixed $default */
    public function get(string $key, $default = null)
    {
        $found = false;
        $value = $this->lookup($key, $found);
        return $found ? $value : $default;
    }

    /** @param mixed $value */
    public function set(string $key, $value): void
    {
        $ref = &$this->items;
        foreach (explode('.', $key) as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }
        $ref = $value;
    }

    public function all(): array
    {
        return $this->items;
    }

    /** @return mixed */
    private function lookup(string $key, bool &$found)
    {
        $found = false;
        if (array_key_exists($key, $this->items)) {
            $found = true;
            return $this->items[$key];
        }
        $cur = $this->items;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($cur) || !array_key_exists($segment, $cur)) {
                return null;
            }
            $cur = $cur[$segment];
        }
        $found = true;
        return $cur;
    }
}
```

### CREATE `app/Core/Container/ContainerException.php`

Content (copy exactly; it ends with a newline):

```php
<?php
declare(strict_types=1);

namespace App\Core\Container;

use RuntimeException;

/** Thrown when a service is unknown or cannot be built. */
class ContainerException extends RuntimeException
{
}
```

### CREATE `app/Core/Container/Container.php`

Content (copy exactly; it ends with a newline):

```php
<?php
declare(strict_types=1);

namespace App\Core\Container;

use Closure;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Small service container. Explicit bindings first; a class that is not bound is built by looking at its
 * constructor: every parameter typed with a class is resolved from the container, a parameter with a default value
 * uses the default, anything else is an error (no guessing).
 */
final class Container
{
    /** @var array<string,array{0:Closure,1:bool}> id => [factory, shared] */
    private array $bindings = [];
    /** @var array<string,object> */
    private array $instances = [];
    /** @var string[] */
    private array $resolving = [];

    /** A new object on every get(). */
    public function bind(string $id, callable $factory): void
    {
        $this->bindings[$id] = [Closure::fromCallable($factory), false];
        unset($this->instances[$id]);
    }

    /** One object, built on first get(). */
    public function singleton(string $id, callable $factory): void
    {
        $this->bindings[$id] = [Closure::fromCallable($factory), true];
        unset($this->instances[$id]);
    }

    public function instance(string $id, object $object): void
    {
        $this->instances[$id] = $object;
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->bindings[$id]) || (class_exists($id) && (new ReflectionClass($id))->isInstantiable());
    }

    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }
        if (in_array($id, $this->resolving, true)) {
            throw new ContainerException('Circular dependency: ' . implode(' -> ', $this->resolving) . ' -> ' . $id);
        }
        $this->resolving[] = $id;
        try {
            if (isset($this->bindings[$id])) {
                [$factory, $shared] = $this->bindings[$id];
                $object = $factory($this);
                if (!is_object($object)) {
                    throw new ContainerException("Factory for $id did not return an object");
                }
                if ($shared) {
                    $this->instances[$id] = $object;
                }
                return $object;
            }
            return $this->autowire($id);
        } finally {
            array_pop($this->resolving);
        }
    }

    /** Build $class now, with optional explicit constructor values by parameter name. */
    public function make(string $class, array $params = []): object
    {
        return $this->autowire($class, $params);
    }

    private function autowire(string $class, array $params = []): object
    {
        if (!class_exists($class)) {
            throw new ContainerException("Service not found: $class");
        }
        $ref = new ReflectionClass($class);
        if (!$ref->isInstantiable()) {
            throw new ContainerException("Class is not instantiable: $class");
        }
        $ctor = $ref->getConstructor();
        if ($ctor === null) {
            return new $class();
        }
        $args = [];
        foreach ($ctor->getParameters() as $p) {
            $name = $p->getName();
            if (array_key_exists($name, $params)) {
                $args[] = $params[$name];
                continue;
            }
            $type = $p->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $args[] = $this->get($type->getName());
                continue;
            }
            if ($p->isDefaultValueAvailable()) {
                $args[] = $p->getDefaultValue();
                continue;
            }
            throw new ContainerException("Cannot resolve parameter \$$name of $class");
        }
        return $ref->newInstanceArgs($args);
    }
}
```

### CREATE `app/Core/Http/HttpException.php`

Content (copy exactly; it ends with a newline):

```php
<?php
declare(strict_types=1);

namespace App\Core\Http;

use RuntimeException;
use Throwable;

/** An error that is meant to reach the client with a given HTTP status and a stable machine code. */
class HttpException extends RuntimeException
{
    private int $status;
    private string $errorCode;
    /** @var array<string,string[]> */
    private array $fields;
    /** @var array<string,string> */
    private array $headers;

    public function __construct(int $status, string $errorCode, string $message, array $fields = [], array $headers = [], ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->status = $status;
        $this->errorCode = $errorCode;
        $this->fields = $fields;
        $this->headers = $headers;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function fields(): array
    {
        return $this->fields;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public static function badRequest(string $message = 'درخواست نامعتبر است.'): self
    {
        return new self(400, 'BAD_REQUEST', $message);
    }

    public static function unauthorized(string $message = 'نشست شما منقضی شده است. دوباره وارد شوید.'): self
    {
        return new self(401, 'UNAUTHENTICATED', $message);
    }

    public static function forbidden(string $message = 'دسترسی غیر مجاز'): self
    {
        return new self(403, 'FORBIDDEN', $message);
    }

    public static function notFound(string $message = 'مسیر یا منبع درخواستی یافت نشد.'): self
    {
        return new self(404, 'NOT_FOUND', $message);
    }

    /** @param string[] $allowed */
    public static function methodNotAllowed(array $allowed): self
    {
        return new self(405, 'METHOD_NOT_ALLOWED', 'متد مجاز نیست', [], ['Allow' => implode(', ', $allowed)]);
    }

    public static function validation(array $fields): self
    {
        return new self(422, 'VALIDATION_FAILED', 'اطلاعات واردشده معتبر نیست.', $fields);
    }
}
```

### CREATE `app/Core/Http/Request.php`

Content (copy exactly; it ends with a newline):

```php
<?php
declare(strict_types=1);

namespace App\Core\Http;

/** One HTTP request. Built from PHP globals in production, built by hand in tests. */
final class Request
{
    private string $method;
    private string $uri;
    private array $query;
    private array $post;
    /** @var array<string,string> lower-case name => value */
    private array $headers = [];
    private array $server;
    private string $body;
    private array $cookies;
    private ?array $jsonCache = null;
    private bool $jsonParsed = false;
    private array $attributes = [];

    public function __construct(string $method, string $uri, array $query = [], array $post = [], array $headers = [], array $server = [], string $body = '', array $cookies = [])
    {
        $this->method = strtoupper($method);
        $this->uri = $uri;
        $this->query = $query;
        $this->post = $post;
        foreach ($headers as $name => $value) {
            $this->headers[strtolower((string) $name)] = (string) $value;
        }
        $this->server = $server;
        $this->body = $body;
        $this->cookies = $cookies;
    }

    public static function fromGlobals(): self
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strncmp($key, 'HTTP_', 5) === 0) {
                $headers[str_replace('_', '-', substr($key, 5))] = (string) $value;
            }
        }
        foreach (['CONTENT_TYPE' => 'Content-Type', 'CONTENT_LENGTH' => 'Content-Length'] as $key => $name) {
            if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
                $headers[$name] = (string) $_SERVER[$key];
            }
        }
        $body = '';
        $in = @file_get_contents('php://input');
        if (is_string($in)) {
            $body = $in;
        }
        return new self(
            (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            (string) ($_SERVER['REQUEST_URI'] ?? '/'),
            $_GET,
            $_POST,
            $headers,
            $_SERVER,
            $body,
            $_COOKIE
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    /** Raw path (still percent-encoded): no query string, one leading slash, no repeated or trailing slash (except "/"). */
    public function path(): string
    {
        $path = parse_url($this->uri, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';
        $path = '/' . trim((string) preg_replace('#/+#', '/', $path), '/');
        return $path;
    }

    /** @param mixed $default */
    public function query(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->query;
        }
        return $this->query[$key] ?? $default;
    }

    /** @param mixed $default */
    public function post(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->post;
        }
        return $this->post[$key] ?? $default;
    }

    /** Body fields: form POST first, then JSON body, then query string. @param mixed $default */
    public function input(string $key, $default = null)
    {
        if (array_key_exists($key, $this->post)) {
            return $this->post[$key];
        }
        $json = $this->json();
        if ($json !== null && array_key_exists($key, $json)) {
            return $json[$key];
        }
        return $this->query[$key] ?? $default;
    }

    public function header(string $name, string $default = ''): string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function cookie(string $name, string $default = ''): string
    {
        $v = $this->cookies[$name] ?? $default;
        return is_string($v) ? $v : $default;
    }

    public function server(string $key, string $default = ''): string
    {
        $v = $this->server[$key] ?? $default;
        return is_scalar($v) ? (string) $v : $default;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function isJson(): bool
    {
        return stripos($this->header('Content-Type'), 'application/json') !== false;
    }

    /** Decoded JSON object/array body, or null when the body is empty, not JSON, or not an object/array. */
    public function json(): ?array
    {
        if (!$this->jsonParsed) {
            $this->jsonParsed = true;
            if ($this->isJson() && $this->body !== '') {
                $decoded = json_decode($this->body, true);
                $this->jsonCache = is_array($decoded) ? $decoded : null;
            }
        }
        return $this->jsonCache;
    }

    /** True when the client expects JSON: Accept/Content-Type says so, XHR header, or the path is under /api/. */
    public function wantsJson(): bool
    {
        return stripos($this->header('Accept'), 'application/json') !== false
            || $this->isJson()
            || strcasecmp($this->header('X-Requested-With'), 'XMLHttpRequest') === 0
            || strncmp($this->path(), '/api/', 5) === 0;
    }

    /** @param mixed $value */
    public function setAttribute(string $name, $value): void
    {
        $this->attributes[$name] = $value;
    }

    /** @param mixed $default */
    public function attribute(string $name, $default = null)
    {
        return $this->attributes[$name] ?? $default;
    }
}
```

### CREATE `app/Core/Http/Response.php`

Content (copy exactly; it ends with a newline):

```php
<?php
declare(strict_types=1);

namespace App\Core\Http;

use stdClass;

/**
 * One HTTP response. JSON responses keep their payload as an array until send(), so middleware can still add meta
 * (the request id) without decoding text.
 *
 * API envelope (blueprint §11):
 *   success: {"success":true,"data":...,"meta":{"request_id":"..."}}
 *   error:   {"success":false,"error":{"code":"...","message":"...","fields":{...}},"meta":{...}}
 */
final class Response
{
    private int $status;
    private string $body;
    /** @var array<string,array{0:string,1:string}> lower-case name => [Name, value] */
    private array $headers = [];
    private ?array $payload = null;

    public function __construct(string $body = '', int $status = 200, array $headers = [])
    {
        $this->body = $body;
        $this->status = $status;
        foreach ($headers as $name => $value) {
            $this->header((string) $name, (string) $value);
        }
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($html, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function text(string $text, int $status = 200): self
    {
        return new self($text, $status, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['Location' => $url]);
    }

    public static function noContent(): self
    {
        return new self('', 204);
    }

    /** Plain JSON (no envelope). */
    public static function json(array $payload, int $status = 200, array $headers = []): self
    {
        $r = new self('', $status, $headers);
        $r->payload = $payload;
        $r->header('Content-Type', 'application/json; charset=utf-8');
        return $r;
    }

    /** Success envelope. @param mixed $data */
    public static function ok($data = null, array $meta = [], int $status = 200): self
    {
        return self::json(['success' => true, 'data' => $data, 'meta' => $meta], $status);
    }

    /** Error envelope. $fields: field => list of messages (only printed when not empty). */
    public static function error(string $code, string $message, int $status, array $fields = [], array $meta = []): self
    {
        $error = ['code' => $code, 'message' => $message];
        if ($fields !== []) {
            $error['fields'] = $fields;
        }
        return self::json(['success' => false, 'error' => $error, 'meta' => $meta], $status);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function withStatus(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[strtolower($name)] = [$name, $value];
        return $this;
    }

    public function headerValue(string $name): ?string
    {
        $h = $this->headers[strtolower($name)] ?? null;
        return $h === null ? null : $h[1];
    }

    /** Set a header only when it is not set yet. */
    public function defaultHeader(string $name, string $value): self
    {
        if (!isset($this->headers[strtolower($name)])) {
            $this->header($name, $value);
        }
        return $this;
    }

    /** @return array<string,string> */
    public function headers(): array
    {
        $out = [];
        foreach ($this->headers as [$name, $value]) {
            $out[$name] = $value;
        }
        return $out;
    }

    /** True when the payload is a success/error envelope (has a boolean "success"). */
    public function isEnvelope(): bool
    {
        return $this->payload !== null && isset($this->payload['success']) && is_bool($this->payload['success']);
    }

    public function payload(): ?array
    {
        return $this->payload;
    }

    /** Merge keys into "meta" of an envelope response; ignored for other responses. */
    public function mergeMeta(array $meta): self
    {
        if ($this->isEnvelope()) {
            $current = isset($this->payload['meta']) && is_array($this->payload['meta']) ? $this->payload['meta'] : [];
            $this->payload['meta'] = array_merge($current, $meta);
        }
        return $this;
    }

    public function body(): string
    {
        if ($this->payload === null) {
            return $this->body;
        }
        $payload = $this->payload;
        if (array_key_exists('meta', $payload) && $payload['meta'] === []) {
            $payload['meta'] = new stdClass();
        }
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        return $json === false ? '{"success":false,"error":{"code":"ENCODE_FAILED","message":"Response could not be encoded."}}' : $json;
    }

    /** Sends status, headers and body. $omitBody is true for HEAD requests. */
    public function send(bool $omitBody = false): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as [$name, $value]) {
                header($name . ': ' . $value, true);
            }
        }
        if (!$omitBody && $this->status !== 204 && $this->status !== 304) {
            echo $this->body();
        }
    }
}
```

### CREATE `app/Core/Http/Route.php`

Content (copy exactly; it ends with a newline):

```php
<?php
declare(strict_types=1);

namespace App\Core\Http;

use InvalidArgumentException;

/** One registered route. Created by Router; the fluent methods name it and attach middleware. */
final class Route
{
    private const TYPES = [
        'num'  => '[0-9]+',
        'slug' => '[a-z0-9]+(?:-[a-z0-9]+)*',
        'any'  => '.+',
    ];

    /** @var string[] */
    private array $methods;
    private string $path;
    /** @var mixed */
    private $handler;
    private ?string $name = null;
    /** @var array<int,mixed> */
    private array $middleware;
    private string $regex;
    /** @var string[] */
    private array $paramNames = [];

    /** @param string[] $methods @param mixed $handler @param array<int,mixed> $middleware */
    public function __construct(array $methods, string $path, $handler, array $middleware = [])
    {
        $this->methods = $methods;
        $this->path = $path;
        $this->handler = $handler;
        $this->middleware = $middleware;
        $this->compile();
    }

    /** @param string ...$middleware aliases like 'auth', 'permission:media.view' (or objects) */
    public function middleware(...$middleware): self
    {
        foreach ($middleware as $m) {
            $this->middleware[] = $m;
        }
        return $this;
    }

    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    /** @return string[] */
    public function methods(): array
    {
        return $this->methods;
    }

    public function path(): string
    {
        return $this->path;
    }

    /** @return mixed */
    public function handler()
    {
        return $this->handler;
    }

    /** @return array<int,mixed> */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /** @return string[] */
    public function paramNames(): array
    {
        return $this->paramNames;
    }

    /** @return array<string,string>|null decoded params, or null when the path does not match */
    public function matchPath(string $requestPath): ?array
    {
        if (!preg_match($this->regex, $requestPath, $m)) {
            return null;
        }
        $params = [];
        foreach ($this->paramNames as $n) {
            $params[$n] = rawurldecode($m[$n]);
        }
        return $params;
    }

    /** Builds the URL for this route. @param array<string,scalar> $params */
    public function urlFor(array $params = []): string
    {
        $url = preg_replace_callback('#\{([A-Za-z_][A-Za-z0-9_]*)(?::[a-z]+)?\}#', function (array $m) use ($params): string {
            if (!array_key_exists($m[1], $params)) {
                throw new InvalidArgumentException('Missing route parameter: ' . $m[1]);
            }
            return rawurlencode((string) $params[$m[1]]);
        }, $this->path);
        return (string) $url;
    }

    private function compile(): void
    {
        $segments = $this->path === '/' ? [] : explode('/', trim($this->path, '/'));
        $regex = '';
        $seen = [];
        foreach ($segments as $seg) {
            if (preg_match('#^\{([A-Za-z_][A-Za-z0-9_]*)(?::([a-z]+))?\}$#', $seg, $m)) {
                $type = $m[2] ?? '';
                if ($type !== '' && !isset(self::TYPES[$type])) {
                    throw new InvalidArgumentException("Unknown route parameter type '$type' in {$this->path}");
                }
                if (isset($seen[$m[1]])) {
                    throw new InvalidArgumentException("Duplicate route parameter '{$m[1]}' in {$this->path}");
                }
                $seen[$m[1]] = true;
                $this->paramNames[] = $m[1];
                $regex .= '/(?P<' . $m[1] . '>' . ($type === '' ? '[^/]+' : self::TYPES[$type]) . ')';
            } elseif (strpbrk($seg, '{}') !== false) {
                throw new InvalidArgumentException("Bad route segment '$seg' in {$this->path}");
            } else {
                $regex .= '/' . preg_quote($seg, '#');
            }
        }
        $this->regex = '#^' . ($regex === '' ? '/' : $regex) . '$#D';
    }
}
```

### CREATE `app/Core/Http/Router.php`

Content (copy exactly; it ends with a newline):

```php
<?php
declare(strict_types=1);

namespace App\Core\Http;

use InvalidArgumentException;

/**
 * Route table + matching. It never calls handlers: match() returns the route and its parameters, or throws
 * HttpException 404 / 405 (the 405 carries the Allow header). HEAD matches GET routes. OPTIONS is a 405 unless
 * a route registers it.
 */
final class Router
{
    private const METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

    /** @var Route[] */
    private array $routes = [];
    /** @var array<string,Route> */
    private array $named = [];
    /** @var array<int,array{prefix:string,middleware:array<int,mixed>}> */
    private array $groups = [];

    /** @param string|string[] $methods @param mixed $handler */
    public function add($methods, string $path, $handler): Route
    {
        $methods = array_map('strtoupper', (array) $methods);
        foreach ($methods as $m) {
            if (!in_array($m, self::METHODS, true)) {
                throw new InvalidArgumentException("Unsupported HTTP method: $m");
            }
        }
        $prefix = '';
        $middleware = [];
        foreach ($this->groups as $g) {
            $prefix .= $g['prefix'];
            $middleware = array_merge($middleware, $g['middleware']);
        }
        $full = self::normalize($prefix . '/' . ltrim($path, '/'));
        $route = new Route($methods, $full, $handler, $middleware);
        $this->routes[] = $route;
        return $route;
    }

    /** @param mixed $handler */
    public function get(string $path, $handler): Route
    {
        return $this->add('GET', $path, $handler);
    }

    /** @param mixed $handler */
    public function post(string $path, $handler): Route
    {
        return $this->add('POST', $path, $handler);
    }

    /** @param mixed $handler */
    public function put(string $path, $handler): Route
    {
        return $this->add('PUT', $path, $handler);
    }

    /** @param mixed $handler */
    public function patch(string $path, $handler): Route
    {
        return $this->add('PATCH', $path, $handler);
    }

    /** @param mixed $handler */
    public function delete(string $path, $handler): Route
    {
        return $this->add('DELETE', $path, $handler);
    }

    /** Routes registered inside $define get the prefix and the middleware. @param array<int,mixed> $middleware */
    public function group(string $prefix, array $middleware, callable $define): void
    {
        $clean = self::normalize('/' . trim($prefix, '/'));
        $this->groups[] = ['prefix' => $clean === '/' ? '' : $clean, 'middleware' => $middleware];
        try {
            $define($this);
        } finally {
            array_pop($this->groups);
        }
    }

    /** Index named routes. Called by match()/url() so ->name() can be chained after add(). */
    private function indexNames(): void
    {
        $this->named = [];
        foreach ($this->routes as $r) {
            $n = $r->getName();
            if ($n !== null) {
                if (isset($this->named[$n])) {
                    throw new InvalidArgumentException("Duplicate route name: $n");
                }
                $this->named[$n] = $r;
            }
        }
    }

    /** @return array{route:Route,params:array<string,string>} */
    public function match(Request $request): array
    {
        $method = $request->method();
        $lookup = $method === 'HEAD' ? 'GET' : $method;
        $path = $request->path();
        $allowed = [];
        foreach ($this->routes as $route) {
            $params = $route->matchPath($path);
            if ($params === null) {
                continue;
            }
            if (in_array($lookup, $route->methods(), true)) {
                return ['route' => $route, 'params' => $params];
            }
            foreach ($route->methods() as $m) {
                $allowed[$m] = true;
                if ($m === 'GET') {
                    $allowed['HEAD'] = true;
                }
            }
        }
        if ($allowed !== []) {
            $list = array_keys($allowed);
            sort($list, SORT_STRING);
            throw HttpException::methodNotAllowed($list);
        }
        throw HttpException::notFound();
    }

    /** True when any route (of any method) matches this path. The front controller uses it to let the router win over the legacy gateway. */
    public function knowsPath(string $path): bool
    {
        foreach ($this->routes as $route) {
            if ($route->matchPath($path) !== null) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string,scalar> $params @param array<string,scalar> $query */
    public function url(string $name, array $params = [], array $query = []): string
    {
        $this->indexNames();
        if (!isset($this->named[$name])) {
            throw new InvalidArgumentException("Unknown route name: $name");
        }
        $url = $this->named[$name]->urlFor($params);
        return $query === [] ? $url : $url . '?' . http_build_query($query);
    }

    /** @return Route[] */
    public function all(): array
    {
        $this->indexNames();
        return $this->routes;
    }

    private static function normalize(string $path): string
    {
        return '/' . trim((string) preg_replace('#/+#', '/', $path), '/');
    }
}
```

### CREATE `app/Core/Http/Middleware/MiddlewareInterface.php`

Content (copy exactly; it ends with a newline):

```php
<?php
declare(strict_types=1);

namespace App\Core\Http\Middleware;

use App\Core\Http\Request;
use App\Core\Http\Response;

interface MiddlewareInterface
{
    /** @param callable $next function (Request): Response */
    public function handle(Request $request, callable $next): Response;
}
```

### CREATE `app/Core/Http/Pipeline.php`

Content (copy exactly; it ends with a newline):

```php
<?php
declare(strict_types=1);

namespace App\Core\Http;

use App\Core\Http\Middleware\MiddlewareInterface;
use InvalidArgumentException;

/** Runs middleware as an onion: the first item is the outermost layer. */
final class Pipeline
{
    /**
     * @param array<int,MiddlewareInterface|callable> $middleware objects with handle(Request, callable) or callables (Request, callable): Response
     * @param callable $core function (Request): Response — the innermost step
     */
    public function process(array $middleware, Request $request, callable $core): Response
    {
        $next = $core;
        foreach (array_reverse($middleware) as $m) {
            if ($m instanceof MiddlewareInterface) {
                $layer = [$m, 'handle'];
            } elseif (is_callable($m)) {
                $layer = $m;
            } else {
                throw new InvalidArgumentException('Middleware must be a MiddlewareInterface or a callable');
            }
            $inner = $next;
            $next = static function (Request $req) use ($layer, $inner): Response {
                $res = $layer($req, $inner);
                if (!$res instanceof Response) {
                    throw new InvalidArgumentException('Middleware must return a Response');
                }
                return $res;
            };
        }
        return $next($request);
    }
}
```

### CREATE `app/Core/Http/Middleware/RequestId.php`

Content (copy exactly; it ends with a newline):

```php
<?php
declare(strict_types=1);

namespace App\Core\Http\Middleware;

use App\Core\Http\Request;
use App\Core\Http\Response;

/** Gives every request an id (attribute "request_id", header X-Request-Id, meta.request_id of JSON envelopes). */
final class RequestId implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $incoming = $request->header('X-Request-Id');
        $id = preg_match('/^[A-Za-z0-9._-]{8,64}$/D', $incoming) ? $incoming : 'req_' . bin2hex(random_bytes(8));
        $request->setAttribute('request_id', $id);
        $response = $next($request);
        $response->header('X-Request-Id', $id);
        return $response->mergeMeta(['request_id' => $id]);
    }
}
```

### CREATE `app/Core/Http/Middleware/SecurityHeaders.php`

Content (copy exactly; it ends with a newline):

```php
<?php
declare(strict_types=1);

namespace App\Core\Http\Middleware;

use App\Core\Http\Request;
use App\Core\Http\Response;

/** Same three headers as includes/functions.php send_security_headers(), plus no-store for JSON. CSP comes in a later part. */
final class SecurityHeaders implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);
        $response->defaultHeader('X-Content-Type-Options', 'nosniff');
        $response->defaultHeader('X-Frame-Options', 'DENY');
        $response->defaultHeader('Referrer-Policy', 'same-origin');
        if ($response->payload() !== null) {
            $response->defaultHeader('Cache-Control', 'no-store');
        }
        return $response;
    }
}
```

### CREATE `app/Core/Http/Middleware/AuthMiddleware.php`

Content (copy exactly; it ends with a newline):

```php
<?php
declare(strict_types=1);

namespace App\Core\Http\Middleware;

use App\Core\Http\Request;
use App\Core\Http\Response;

/** Not logged in: JSON clients get 401 (never a redirect to an HTML page), browsers get a redirect to the login page. */
final class AuthMiddleware implements MiddlewareInterface
{
    /** @var callable */
    private $isLoggedIn;
    private string $loginUrl;

    /** @param callable $isLoggedIn function (): bool */
    public function __construct(callable $isLoggedIn, string $loginUrl = '/login.php')
    {
        $this->isLoggedIn = $isLoggedIn;
        $this->loginUrl = $loginUrl;
    }

    public function handle(Request $request, callable $next): Response
    {
        if (!($this->isLoggedIn)()) {
            if ($request->wantsJson()) {
                return Response::error('UNAUTHENTICATED', 'نشست شما منقضی شده است. دوباره وارد شوید.', 401);
            }
            return Response::redirect($this->loginUrl);
        }
        return $next($request);
    }
}
```

### CREATE `app/Core/Http/Middleware/CsrfMiddleware.php`

Content (copy exactly; it ends with a newline):

```php
<?php
declare(strict_types=1);

namespace App\Core\Http\Middleware;

use App\Core\Http\Request;
use App\Core\Http\Response;

/** POST/PUT/PATCH/DELETE need the session CSRF token: header X-CSRF-TOKEN / X-CSRFTOKEN, form field csrf_token, or JSON field csrf_token. */
final class CsrfMiddleware implements MiddlewareInterface
{
    /** @var callable */
    private $verify;

    /** @param callable $verify function (?string $token): bool */
    public function __construct(callable $verify)
    {
        $this->verify = $verify;
    }

    public function handle(Request $request, callable $next): Response
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }
        $token = $request->header('X-CSRF-TOKEN');
        if ($token === '') {
            $token = $request->header('X-CSRFTOKEN');
        }
        if ($token === '') {
            $field = $request->input('csrf_token', '');
            $token = is_string($field) ? $field : '';
        }
        if ($token === '' || !($this->verify)($token)) {
            if ($request->wantsJson()) {
                return Response::error('CSRF_INVALID', 'توکن امنیتی نامعتبر است.', 403);
            }
            return Response::text('توکن امنیتی نامعتبر است.', 403);
        }
        return $next($request);
    }
}
```

### CREATE `app/Core/Http/Middleware/PermissionMiddleware.php`

Content (copy exactly; it ends with a newline):

```php
<?php
declare(strict_types=1);

namespace App\Core\Http\Middleware;

use App\Core\Http\Request;
use App\Core\Http\Response;

/** Alias form: 'permission:media.view' = module "media", action "view" (view|edit|delete). Put it after 'auth'. */
final class PermissionMiddleware implements MiddlewareInterface
{
    /** @var callable */
    private $can;
    private string $module;
    private string $action;

    /** @param callable $can function (string $module, string $action): bool */
    public function __construct(callable $can, string $module, string $action = 'view')
    {
        $this->can = $can;
        $this->module = $module;
        $this->action = $action;
    }

    public function handle(Request $request, callable $next): Response
    {
        if (!($this->can)($this->module, $this->action)) {
            if ($request->wantsJson()) {
                return Response::error('FORBIDDEN', 'دسترسی غیر مجاز', 403);
            }
            return Response::html('<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><title>دسترسی غیر مجاز</title><p>دسترسی غیر مجاز</p></html>', 403);
        }
        return $next($request);
    }
}
```

### CREATE `app/Core/Http/ExceptionHandler.php`

Content (copy exactly; it ends with a newline):

```php
<?php
declare(strict_types=1);

namespace App\Core\Http;

use Throwable;

/**
 * Turns any Throwable into a Response. HttpException keeps its status/code/message. Everything else is a 500 with a generic
 * message and the request id; the details go to the log only (and to the response only when $debug is true).
 */
final class ExceptionHandler
{
    private bool $debug;
    /** @var callable */
    private $logger;

    /** @param callable|null $logger function (string $line): void — default error_log() */
    public function __construct(bool $debug = false, ?callable $logger = null)
    {
        $this->debug = $debug;
        $this->logger = $logger ?? static function (string $line): void {
            error_log($line);
        };
    }

    public function render(Throwable $e, Request $request): Response
    {
        $requestId = (string) $request->attribute('request_id', '');
        $meta = $requestId === '' ? [] : ['request_id' => $requestId];

        if ($e instanceof HttpException) {
            $status = $e->status();
            $code = $e->errorCode();
            $message = $e->getMessage();
            $fields = $e->fields();
            $headers = $e->headers();
        } else {
            $status = 500;
            $code = 'INTERNAL_ERROR';
            $message = 'خطای داخلی سرور. لطفاً دوباره تلاش کنید.';
            $fields = [];
            $headers = [];
            ($this->logger)(sprintf('[%s] %s: %s in %s:%d', $requestId === '' ? 'no-request-id' : $requestId, get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));
        }

        if ($request->wantsJson()) {
            $response = Response::error($code, $message, $status, $fields, $meta);
            if ($this->debug && !$e instanceof HttpException) {
                $payload = $response->payload() ?? [];
                $payload['error']['debug'] = ['class' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
                $response = Response::json($payload, $status);
            }
        } else {
            $response = Response::html($this->page($status, $message, $requestId, $this->debug && !$e instanceof HttpException ? $e : null), $status);
        }
        foreach ($headers as $name => $value) {
            $response->header((string) $name, (string) $value);
        }
        return $response;
    }

    private function page(int $status, string $message, string $requestId, ?Throwable $debug): string
    {
        $esc = static function (string $s): string {
            return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };
        $extra = $requestId === '' ? '' : '<p><small>کد پیگیری: ' . $esc($requestId) . '</small></p>';
        $detail = $debug === null ? '' : '<pre dir="ltr">' . $esc(get_class($debug) . ': ' . $debug->getMessage() . "\n" . $debug->getFile() . ':' . $debug->getLine()) . '</pre>';
        return '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $status . '</title></head><body style="font-family:Tahoma,sans-serif;max-width:32rem;margin:4rem auto;padding:0 1rem">'
            . '<h1>' . $status . '</h1><p>' . $esc($message) . '</p>' . $extra . $detail . '</body></html>';
    }
}
```

### CREATE `app/Core/Http/LegacyGateway.php`

Content (copy exactly; it ends with a newline):

```php
<?php
declare(strict_types=1);

namespace App\Core\Http;

/**
 * The only door to the old procedural pages and APIs under public/ (everything except public/index.php).
 * Apache sends every request to public/index.php; when the router does not know the path, this class decides whether
 * a legacy file may run. A file runs only when its public-relative path is listed in config/legacy.php and the URL is
 * exactly "/" + that path. Anything else (encoded characters, "..", extra path info, other case) is "not allowed".
 *
 * Bridge status: TEMPORARY. Each legacy file leaves the list when a later part moves it to a route; the list is empty
 * when the last one is gone, and then this class and config/legacy.php are deleted (removal criterion, see docs/kernel.md).
 */
final class LegacyGateway
{
    private string $publicDir;
    /** @var array<string,true> */
    private array $allowed = [];

    /** @param string[] $files public-relative paths such as "login.php" or "modules/media/index.php" */
    public function __construct(string $publicDir, array $files)
    {
        $this->publicDir = rtrim($publicDir, '/\\');
        foreach ($files as $f) {
            $this->allowed[ltrim((string) $f, '/')] = true;
        }
    }

    public static function fromBasePath(string $basePath, string $listFile = 'config/legacy.php'): self
    {
        $file = rtrim($basePath, '/\\') . '/' . $listFile;
        $files = is_file($file) ? require $file : [];
        return new self(rtrim($basePath, '/\\') . '/public', is_array($files) ? $files : []);
    }

    /** @return string[] sorted public-relative paths */
    public function files(): array
    {
        $list = array_keys($this->allowed);
        sort($list, SORT_STRING);
        return $list;
    }

    /** Absolute path of the legacy file to run for this URL path, or null. */
    public function resolve(string $urlPath): ?string
    {
        if ($urlPath === '' || $urlPath[0] !== '/') {
            return null;
        }
        if (strpbrk($urlPath, "%\\\0") !== false || strpos($urlPath, '..') !== false || strpos($urlPath, '//') !== false) {
            return null;
        }
        $relative = substr($urlPath, 1);
        if ($relative === '' || !isset($this->allowed[$relative])) {
            return null;
        }
        $file = $this->publicDir . '/' . $relative;
        return is_file($file) ? $file : null;
    }

    /**
     * Legacy code reads $_SERVER['SCRIPT_NAME'] to know which page it is (page guard, active menu item, forced password change).
     * Returns the $_SERVER entries that make the included file look like it was requested directly.
     * @return array<string,string>
     */
    public function serverVars(string $urlPath, string $file): array
    {
        return ['SCRIPT_NAME' => $urlPath, 'PHP_SELF' => $urlPath, 'SCRIPT_FILENAME' => $file, 'PATH_TRANSLATED' => $file];
    }
}
```

### CREATE `app/Core/Log/Logger.php`

Content (copy exactly; it ends with a newline):

```php
<?php
declare(strict_types=1);

namespace App\Core\Log;

use InvalidArgumentException;

/**
 * Minimal application log: one JSON object per line in <dir>/app-YYYY-MM-DD.log (UTC date). It never throws: when the
 * folder or file cannot be written it falls back to PHP's error_log(), so logging can never break a request.
 * Levels: debug info warning error. Context values must be scalars, arrays or null (objects are reduced to their class name).
 * This is the home of application logging; request ids come from the RequestId middleware (pass them in the context).
 */
final class Logger
{
    public const LEVELS = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];

    private string $dir;
    private int $min;

    public function __construct(string $dir, string $minLevel = 'info')
    {
        if (!isset(self::LEVELS[$minLevel])) {
            throw new InvalidArgumentException("Unknown log level: $minLevel");
        }
        $this->dir = rtrim($dir, '/\\');
        $this->min = self::LEVELS[$minLevel];
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function log(string $level, string $message, array $context = []): void
    {
        if (!isset(self::LEVELS[$level]) || self::LEVELS[$level] < $this->min) {
            return;
        }
        $line = json_encode([
            'time' => gmdate('Y-m-d\TH:i:s\Z'),
            'level' => $level,
            'message' => $message,
            'context' => self::clean($context),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if (!is_string($line)) {
            $line = '{"level":"' . $level . '","message":"log line could not be encoded"}';
        }
        if (!$this->write($line . "\n")) {
            error_log('[app-log] ' . $line);
        }
    }

    /** Path of today's file (UTC). */
    public function currentFile(): string
    {
        return $this->dir . '/app-' . gmdate('Y-m-d') . '.log';
    }

    private function write(string $text): bool
    {
        if (!is_dir($this->dir) && !@mkdir($this->dir, 0775, true) && !is_dir($this->dir)) {
            return false;
        }
        return @file_put_contents($this->currentFile(), $text, FILE_APPEND | LOCK_EX) !== false;
    }

    /** @param mixed $value @return mixed */
    private static function clean($value, int $depth = 0)
    {
        if (is_object($value)) {
            return '[object ' . get_class($value) . ']';
        }
        if (is_array($value)) {
            if ($depth >= 4) {
                return '[array]';
            }
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = self::clean($v, $depth + 1);
            }
            return $out;
        }
        if (is_resource($value)) {
            return '[resource]';
        }
        return $value;
    }
}
```

### CREATE `app/Core/Validation/ValidationResult.php`

Content (copy exactly; it ends with a newline):

```php
<?php
declare(strict_types=1);

namespace App\Core\Validation;

use App\Core\Http\HttpException;

/** Outcome of Validator::make(). errors() is field => list of messages (the API "fields" object). */
final class ValidationResult
{
    /** @var array<string,string[]> */
    private array $errors;
    private array $validated;

    public function __construct(array $errors, array $validated)
    {
        $this->errors = $errors;
        $this->validated = $validated;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    /** @return array<string,string[]> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** Only the fields that had rules, nested by dots. Values are returned as received (not trimmed or cast). */
    public function validated(): array
    {
        return $this->validated;
    }

    /** Returns validated() or throws HttpException 422 VALIDATION_FAILED. */
    public function validatedOrFail(): array
    {
        if ($this->errors !== []) {
            throw HttpException::validation($this->errors);
        }
        return $this->validated;
    }
}
```

### CREATE `app/Core/Validation/Validator.php`

Content (copy exactly; it ends with a newline):

```php
<?php
declare(strict_types=1);

namespace App\Core\Validation;

use LogicException;

/**
 * Rule-string validator. Rules: required, nullable, string, integer, numeric, boolean, array, email, url (http/https),
 * date (Y-m-d), min:n, max:n, between:a,b, in:a,b,c, regex:/pattern/.
 * Per field the rules run in the order written and stop at the first failure ("bail").
 * "nullable" makes null or "" skip the other rules. min/max/between measure characters for text, the number for
 * integer/numeric fields, the item count for arrays. Dot paths ("address.city") read nested arrays.
 * Not in this part: unique/exists (need the database), wildcards ("items.*.name"), file rules.
 */
final class Validator
{
    private const MESSAGES = [
        'required' => ':attribute الزامی است.',
        'string' => ':attribute باید متن باشد.',
        'integer' => ':attribute باید عدد صحیح باشد.',
        'numeric' => ':attribute باید عدد باشد.',
        'boolean' => ':attribute باید بله یا خیر باشد.',
        'array' => ':attribute باید آرایه باشد.',
        'email' => ':attribute باید یک ایمیل معتبر باشد.',
        'url' => ':attribute باید یک نشانی اینترنتی معتبر باشد.',
        'date' => ':attribute باید تاریخ معتبر (سال-ماه-روز) باشد.',
        'min.string' => ':attribute باید حداقل :min کاراکتر باشد.',
        'min.numeric' => ':attribute باید حداقل :min باشد.',
        'min.array' => ':attribute باید حداقل :min مورد داشته باشد.',
        'max.string' => ':attribute باید حداکثر :max کاراکتر باشد.',
        'max.numeric' => ':attribute باید حداکثر :max باشد.',
        'max.array' => ':attribute باید حداکثر :max مورد داشته باشد.',
        'between.string' => ':attribute باید بین :min و :max کاراکتر باشد.',
        'between.numeric' => ':attribute باید بین :min و :max باشد.',
        'between.array' => ':attribute باید بین :min و :max مورد داشته باشد.',
        'in' => ':attribute انتخاب‌شده معتبر نیست.',
        'regex' => ':attribute قالب معتبری ندارد.',
    ];

    /**
     * @param array<string,string|string[]> $rules field => 'required|string|max:50' or ['required','string']
     * @param array<string,string> $labels field => display name (Persian) used in messages
     * @param array<string,string> $messages override: 'required' or 'email.required' => text with :attribute
     */
    public static function make(array $data, array $rules, array $labels = [], array $messages = []): ValidationResult
    {
        $errors = [];
        $validated = [];
        foreach ($rules as $field => $spec) {
            $list = is_array($spec) ? $spec : self::splitRules((string) $spec);
            $found = false;
            $value = self::dataGet($data, (string) $field, $found);
            $label = $labels[$field] ?? (string) $field;
            $empty = $value === null || (is_string($value) && trim($value) === '');

            if (in_array('nullable', $list, true) && $empty) {
                if ($found) {
                    self::dataSet($validated, (string) $field, $value);
                }
                continue;
            }
            $failed = null;
            foreach ($list as $rule) {
                if ($rule === 'nullable') {
                    continue;
                }
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, '');
                if ($name !== 'required' && $empty) {
                    continue; // optional field left empty: only "required" may complain about it
                }
                $failed = self::check($name, $arg, $value, $empty, $list);
                if ($failed !== null) {
                    $errors[$field] = [self::message($field, $name, $failed, $label, $messages)];
                    break;
                }
            }
            if ($failed === null && $found) {
                self::dataSet($validated, (string) $field, $value);
            }
        }
        return new ValidationResult($errors, $validated);
    }

    /** @return string[] */
    private static function splitRules(string $spec): array
    {
        // A regex rule may contain "|", so it must be the last rule and is kept whole.
        $pos = strpos($spec, 'regex:');
        if ($pos !== false) {
            $head = $pos === 0 ? [] : self::splitRules(rtrim(substr($spec, 0, $pos), '|'));
            return array_merge($head, [substr($spec, $pos)]);
        }
        return array_values(array_filter(array_map('trim', explode('|', $spec)), static function (string $r): bool {
            return $r !== '';
        }));
    }

    /** @param mixed $value @param string[] $list @return array<string,string>|null replacement map when failed, null when passed */
    private static function check(string $name, string $arg, $value, bool $empty, array $list): ?array
    {
        switch ($name) {
            case 'required':
                return $empty || $value === [] ? [] : null;
            case 'string':
                return is_string($value) ? null : [];
            case 'integer':
                return filter_var($value, FILTER_VALIDATE_INT) !== false && !is_bool($value) ? null : [];
            case 'numeric':
                return is_numeric($value) && !is_bool($value) ? null : [];
            case 'boolean':
                return in_array($value, [true, false, 0, 1, '0', '1'], true) ? null : [];
            case 'array':
                return is_array($value) ? null : [];
            case 'email':
                return is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false ? null : [];
            case 'url':
                return is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false && preg_match('#^https?://#i', $value) ? null : [];
            case 'date':
                $d = is_string($value) ? \DateTime::createFromFormat('!Y-m-d', $value) : false;
                return $d !== false && $d->format('Y-m-d') === $value ? null : [];
            case 'in':
                return in_array((string) (is_scalar($value) ? $value : "\0"), explode(',', $arg), true) ? null : [];
            case 'regex':
                if (@preg_match($arg, '') === false) {
                    throw new LogicException('Invalid regex rule: ' . $arg);
                }
                return is_scalar($value) && preg_match($arg, (string) $value) === 1 ? null : [];
            case 'min':
            case 'max':
            case 'between':
                if (!is_numeric(trim(explode(',', $arg)[0]))) {
                    throw new LogicException("Rule $name needs a number");
                }
                $bounds = array_map('floatval', explode(',', $arg));
                if ($name === 'between' && count($bounds) !== 2) {
                    throw new LogicException('Rule between needs two numbers: between:1,10');
                }
                $kind = is_array($value) ? 'array' : (in_array('integer', $list, true) || in_array('numeric', $list, true) ? 'numeric' : 'string');
                $size = $kind === 'array' ? (float) count($value) : ($kind === 'numeric' ? (float) $value : (float) mb_strlen((string) $value, 'UTF-8'));
                $ok = $name === 'min' ? $size >= $bounds[0] : ($name === 'max' ? $size <= $bounds[0] : ($size >= $bounds[0] && $size <= $bounds[1]));
                if ($ok) {
                    return null;
                }
                return ['kind' => $kind, 'min' => self::num($bounds[0]), 'max' => self::num($bounds[count($bounds) - 1])];
        }
        throw new LogicException("Unknown validation rule: $name");
    }

    private static function num(float $n): string
    {
        return $n == (int) $n ? (string) (int) $n : (string) $n;
    }

    /** @param array<string,string> $replace @param array<string,string> $overrides */
    private static function message(string $field, string $rule, array $replace, string $label, array $overrides): string
    {
        $kind = $replace['kind'] ?? '';
        $key = $kind === '' ? $rule : $rule . '.' . $kind;
        $text = $overrides[$field . '.' . $rule] ?? $overrides[$rule] ?? self::MESSAGES[$key];
        return strtr($text, [':attribute' => $label, ':min' => $replace['min'] ?? '', ':max' => $replace['max'] ?? '']);
    }

    /** @return mixed */
    private static function dataGet(array $data, string $path, bool &$found)
    {
        $found = false;
        if (array_key_exists($path, $data)) {
            $found = true;
            return $data[$path];
        }
        $cur = $data;
        foreach (explode('.', $path) as $seg) {
            if (!is_array($cur) || !array_key_exists($seg, $cur)) {
                return null;
            }
            $cur = $cur[$seg];
        }
        $found = true;
        return $cur;
    }

    /** @param mixed $value */
    private static function dataSet(array &$data, string $path, $value): void
    {
        $ref = &$data;
        foreach (explode('.', $path) as $seg) {
            if (!isset($ref[$seg]) || !is_array($ref[$seg])) {
                $ref[$seg] = [];
            }
            $ref = &$ref[$seg];
        }
        $ref = $value;
    }
}
```

### CREATE `app/Bootstrap/Bootstrapper.php`

Content (copy exactly; it ends with a newline):

```php
<?php
declare(strict_types=1);

namespace App\Bootstrap;

/** A step that runs once while the application boots (after the core routes are loaded). Listed in config/kernel.php "bootstrappers". */
interface Bootstrapper
{
    public function bootstrap(Application $app): void;
}
```

### CREATE `app/Bootstrap/Application.php`

Content (copy exactly; it ends with a newline):

```php
<?php
declare(strict_types=1);

namespace App\Bootstrap;

use App\Core\Config\Config;
use App\Core\Container\Container;
use App\Core\Http\ExceptionHandler;
use App\Core\Http\HttpException;
use App\Core\Http\LegacyGateway;
use App\Core\Log\Logger;
use App\Core\Http\Middleware\AuthMiddleware;
use App\Core\Http\Middleware\CsrfMiddleware;
use App\Core\Http\Middleware\MiddlewareInterface;
use App\Core\Http\Middleware\PermissionMiddleware;
use App\Core\Http\Middleware\RequestId;
use App\Core\Http\Middleware\SecurityHeaders;
use App\Core\Http\Pipeline;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Http\Router;
use LogicException;
use Throwable;

/**
 * The front controller's engine. Flow: Request -> global middleware (RequestId, SecurityHeaders) -> route match
 * (404/405) -> route middleware (auth, csrf, permission:...) -> handler -> Response.
 * Any Throwable becomes a standard error response through ExceptionHandler and still passes the global middleware.
 *
 * Old procedural pages and APIs are reached only through LegacyGateway (see legacyTarget()): the router wins, then the allowlist.
 *
 * Middleware aliases used in routes: 'auth', 'csrf', 'permission:<module>.<action>'. They are wired to the existing
 * procedural code (includes/auth.php, csrf.php, permissions.php), which is loaded only when an alias is really used.
 */
final class Application
{
    private string $basePath;
    private Container $container;
    private Config $config;
    private Router $router;
    private ExceptionHandler $errors;
    private Logger $logger;
    private LegacyGateway $legacy;
    private bool $booted = false;
    /** @var array<string,callable> alias => function (string[] $args): MiddlewareInterface|callable */
    private array $aliases = [];
    /** @var array<int,mixed> */
    private array $globalMiddleware = [];

    public function __construct(string $basePath, Config $config, ExceptionHandler $errors)
    {
        $this->basePath = rtrim($basePath, '/\\');
        $this->config = $config;
        $this->errors = $errors;
        $this->container = new Container();
        $this->router = new Router();
        $this->logger = new Logger($this->basePath . '/' . trim((string) $config->get('log_dir', 'storage/logs'), '/\\'), (string) $config->get('log_level', 'info'));
        $this->legacy = LegacyGateway::fromBasePath($this->basePath, (string) $config->get('legacy_list', 'config/legacy.php'));
        $this->container->instance(self::class, $this);
        $this->container->instance(Container::class, $this->container);
        $this->container->instance(Config::class, $config);
        $this->container->instance(Router::class, $this->router);
        $this->container->instance(ExceptionHandler::class, $errors);
        $this->container->instance(Logger::class, $this->logger);
        $this->container->instance(LegacyGateway::class, $this->legacy);
    }

    /** Standard wiring for production and tests. $options: 'config' => array merged over config/kernel.php, 'debug' => bool. */
    public static function create(string $basePath, array $options = []): self
    {
        $items = [];
        $file = rtrim($basePath, '/\\') . '/config/kernel.php';
        if (is_file($file)) {
            $loaded = require $file;
            $items = is_array($loaded) ? $loaded : [];
        }
        $items = array_replace_recursive($items, $options['config'] ?? []);
        // Fail safe: debug output only when config.php says APP_ENV is exactly "local".
        $debug = array_key_exists('debug', $options) ? (bool) $options['debug'] : (defined('APP_ENV') && APP_ENV === 'local');
        $app = null;
        $sink = $options['logger'] ?? static function (string $line) use (&$app): void {
            $app->logger()->error($line);
        };
        $app = new self($basePath, new Config($items), new ExceptionHandler($debug, $sink));

        $app->globalMiddleware = [new RequestId(), new SecurityHeaders()];
        $login = (string) $app->config->get('login_url', '/login.php');
        $app->registerMiddleware('auth', function () use ($app, $login): MiddlewareInterface {
            return new AuthMiddleware(static function () use ($app): bool {
                return $app->legacyIsLoggedIn();
            }, $login);
        });
        $app->registerMiddleware('csrf', function () use ($app): MiddlewareInterface {
            return new CsrfMiddleware(static function (string $token) use ($app): bool {
                return $app->legacyCsrfVerify($token);
            });
        });
        $app->registerMiddleware('permission', function (array $args) use ($app): MiddlewareInterface {
            $parts = explode('.', (string) ($args[0] ?? ''), 2);
            if ($parts[0] === '') {
                throw new LogicException("Middleware 'permission' needs an argument: permission:<module>.<action>");
            }
            return new PermissionMiddleware(static function (string $module, string $action) use ($app): bool {
                return $app->legacyHasPermission($module, $action);
            }, $parts[0], $parts[1] ?? 'view');
        });
        return $app;
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function logger(): Logger
    {
        return $this->logger;
    }

    public function legacy(): LegacyGateway
    {
        return $this->legacy;
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    /** @param callable $factory function (string[] $args): MiddlewareInterface|callable */
    public function registerMiddleware(string $alias, callable $factory): void
    {
        $this->aliases[$alias] = $factory;
    }

    /** @param MiddlewareInterface|callable $middleware appended inside the existing global middleware */
    public function addGlobalMiddleware($middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

    /** Loads a routes file. The file returns function (Router $router, Application $app): void. */
    public function loadRoutes(string $file): void
    {
        $define = require $file;
        if (!is_callable($define)) {
            throw new LogicException("Routes file must return a callable: $file");
        }
        $define($this->router, $this);
    }

    /**
     * Loads routes/core.php, then runs every class listed in config "bootstrappers" (each has bootstrap(Application): void).
     * Later parts plug in here (for example the module system) by adding a class name to config/kernel.php.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return; // safe to call twice (the console does)
        }
        $this->booted = true;
        $this->loadRoutes($this->basePath . '/routes/core.php');
        foreach ((array) $this->config->get('bootstrappers', []) as $class) {
            $bootstrapper = $this->container->get((string) $class);
            if (!$bootstrapper instanceof Bootstrapper) {
                throw new LogicException("Bootstrapper must implement App\\Bootstrap\\Bootstrapper: $class");
            }
            $bootstrapper->bootstrap($this);
        }
    }

    /** Throws LogicException when two routes share a method and path shape, when a route uses an unknown middleware alias, or when a handler is unusable. */
    public function validateRoutes(): void
    {
        $seen = [];
        foreach ($this->router->all() as $route) {
            $shape = (string) preg_replace('#\{[A-Za-z_][A-Za-z0-9_]*(?::[a-z]+)?\}#', '{}', $route->path());
            foreach ($route->methods() as $method) {
                $key = $method . ' ' . $shape;
                if (isset($seen[$key])) {
                    throw new LogicException('Duplicate route: ' . $key . ' (' . $seen[$key] . ' and ' . $route->path() . ')');
                }
                $seen[$key] = $route->path();
            }
        }
        foreach ($this->router->all() as $route) {
            foreach ($route->getMiddleware() as $m) {
                $this->resolveMiddleware($m);
            }
            $this->resolveHandler($route->handler());
        }
    }

    public function handle(Request $request): Response
    {
        try {
            return (new Pipeline())->process($this->globalMiddleware, $request, function (Request $req): Response {
                return $this->dispatch($req);
            });
        } catch (Throwable $e) {
            return $this->errors->render($e, $request);
        }
    }

    /**
     * The path of the legacy file that must serve this request, or null. The router always wins: when any route matches
     * the path (even with another method, which becomes a 405), the legacy gateway is not asked.
     */
    public function legacyTarget(Request $request): ?string
    {
        if ($this->router->knowsPath($request->path())) {
            return null;
        }
        return $this->legacy->resolve($request->path());
    }

    /**
     * Call right before including the file returned by legacyTarget(): the legacy file must see the URL it was requested
     * under (SCRIPT_NAME is read by the page guard and the menu) and the response carries a request id.
     */
    public function enterLegacy(Request $request, string $file): void
    {
        foreach ($this->legacy->serverVars($request->path(), $file) as $name => $value) {
            $_SERVER[$name] = $value;
        }
        if (!headers_sent()) {
            header('X-Request-Id: req_' . bin2hex(random_bytes(8)));
        }
    }

    public function run(): void
    {
        $request = Request::fromGlobals();
        $response = $this->handle($request);
        $response->send($request->method() === 'HEAD');
    }

    private function dispatch(Request $request): Response
    {
        try {
            $found = $this->router->match($request);
            $route = $found['route'];
            $params = $found['params'];
            $middleware = [];
            foreach ($route->getMiddleware() as $m) {
                $middleware[] = $this->resolveMiddleware($m);
            }
            $handler = $this->resolveHandler($route->handler());
            $request->setAttribute('route', $route);
            $request->setAttribute('route_params', $params);
            return (new Pipeline())->process($middleware, $request, function (Request $req) use ($handler, $params): Response {
                return $this->toResponse($handler($req, $params));
            });
        } catch (Throwable $e) {
            return $this->errors->render($e, $request);
        }
    }

    /** @param mixed $m @return MiddlewareInterface|callable */
    private function resolveMiddleware($m)
    {
        if ($m instanceof MiddlewareInterface || $m instanceof \Closure) {
            return $m;
        }
        if (!is_string($m) || $m === '') {
            throw new LogicException('Route middleware must be an alias string, a MiddlewareInterface or a Closure');
        }
        [$alias, $argString] = array_pad(explode(':', $m, 2), 2, '');
        if (!isset($this->aliases[$alias])) {
            throw new LogicException("Unknown middleware alias: $alias");
        }
        $args = $argString === '' ? [] : array_map('trim', explode(',', $argString));
        return ($this->aliases[$alias])($args);
    }

    /** @param mixed $handler @return callable function (Request, array): mixed */
    private function resolveHandler($handler): callable
    {
        if ($handler instanceof \Closure) {
            return $handler;
        }
        if (is_array($handler) && count($handler) === 2 && is_string($handler[0]) && is_string($handler[1])) {
            [$class, $method] = $handler;
            if (!class_exists($class) || !method_exists($class, $method)) {
                throw new LogicException("Controller method not found: $class::$method");
            }
            return function (Request $request, array $params) use ($class, $method) {
                return $this->container->get($class)->$method($request, $params);
            };
        }
        if (is_string($handler) && class_exists($handler) && method_exists($handler, '__invoke')) {
            return function (Request $request, array $params) use ($handler) {
                return $this->container->get($handler)($request, $params);
            };
        }
        throw new LogicException('Unusable route handler');
    }

    /** Handler results: Response as is; array => success envelope; string => HTML; null => 204. @param mixed $result */
    private function toResponse($result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }
        if (is_array($result)) {
            return Response::ok($result);
        }
        if (is_string($result)) {
            return Response::html($result);
        }
        if ($result === null) {
            return Response::noContent();
        }
        throw new LogicException('Handler returned an unsupported value');
    }

    // ---- bridges to the existing procedural code (loaded lazily, only when a route really needs them) ----

    public function legacyIsLoggedIn(): bool
    {
        require_once $this->basePath . '/includes/auth.php';
        return \isLoggedIn();
    }

    public function legacyCsrfVerify(string $token): bool
    {
        require_once $this->basePath . '/includes/csrf.php';
        return \csrf_verify($token);
    }

    public function legacyHasPermission(string $module, string $action): bool
    {
        require_once $this->basePath . '/includes/permissions.php';
        return \hasPermission($module, $action);
    }
}
```

### CREATE `config/kernel.php`

Content (copy exactly; it ends with a newline):

```php
<?php
// config/kernel.php — Part 11B. Settings of the HTTP kernel. Returns a plain array (read through App\Core\Config\Config).
return [
    'login_url' => '/login.php',
    'home_url'  => '/modules/dashboard/index.php',
    'api_prefix' => '/api/v1',
    // Folder of the legacy allowlist (see App\Core\Http\LegacyGateway) and of the application log (App\Core\Log\Logger).
    'legacy_list' => 'config/legacy.php',
    'log_dir' => 'storage/logs',
    'log_level' => 'info',
    // Classes run by Application::boot() after routes/core.php. Each implements App\Bootstrap\Bootstrapper.
    'bootstrappers' => [],
];
```

### CREATE `config/legacy.php`

Content (copy exactly; it ends with a newline):

```php
<?php
// config/legacy.php — Part 11B. The allowlist of old procedural files that may still run (public-relative paths).
// Everything under public/ except index.php must be listed here (tests/legacy_gateway_test.php enforces it in both directions).
// TEMPORARY: a file is removed from this list in the part that replaces it with a route.
return [
    'api/media/delete.php',
    'api/media/info.php',
    'api/media/upload.php',
    'api/notifications/mark_read.php',
    'api/staff/invite.php',
    'api/staff/remove.php',
    'api/staff/save_permissions.php',
    'api/staff/set_status.php',
    'api/staff/toggle_theme.php',
    'login.php',
    'logout.php',
    'modules/dashboard/index.php',
    'modules/media/index.php',
    'modules/settings/general.php',
    'modules/settings/profile.php',
    'modules/settings/staff.php',
];
```

### CREATE `routes/core.php`

Content (copy exactly; it ends with a newline):

```php
<?php
// routes/core.php — Part 11B. Core routes of the front controller (public/index.php).
// The file returns a function; Application::loadRoutes() calls it with the router and the application.
use App\Bootstrap\Application;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Http\Router;

return function (Router $router, Application $app): void {
    $config = $app->config();

    // "/" keeps the behaviour of the old public/index.php: logged in -> dashboard, otherwise -> login.
    $home = function (Request $request) use ($app, $config): Response {
        return Response::redirect((string) ($app->legacyIsLoggedIn() ? $config->get('home_url') : $config->get('login_url')));
    };
    $router->get('/', $home)->name('home');
    $router->get('/index.php', $home);

    // Liveness probes: no database, no session, no version numbers.
    $health = function (): Response {
        return Response::ok(['status' => 'ok', 'time_utc' => gmdate('Y-m-d\TH:i:s\Z')]);
    };
    $router->get('/health', $health)->name('health');
    $router->group((string) $config->get('api_prefix', '/api/v1'), [], function (Router $router) use ($health): void {
        $router->get('/health', $health)->name('api.health');
    });
};
```

### STEP 4 — switch the entry point (four edits to existing files)

This is the step that changes behaviour. Each FIND text must occur **exactly once** (0 or 2+ → stop that edit, rule 3).

### EDIT `public/index.php` — E1

The front controller (the whole old file is replaced).

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```php
<?php
require_once __DIR__ . '/../includes/auth.php';
if (!isLoggedIn()) { header('Location: /login.php'); exit; }
header('Location: /modules/dashboard/index.php'); exit;
```

**REPLACE IT WITH:**

```php
<?php
// Front controller (Part 11B). EVERY web request reaches this file (public/.htaccess); nothing else under public/ is an entry point.
// 1) The kernel router answers its own routes (/, /health, /api/v1/..., and everything later parts add).
// 2) If the router has no route for the path, a legacy page or API runs only when config/legacy.php lists it.
// 3) Anything else is a standard 404.
// APP_GATEWAY tells config/config.php that this is the sanctioned entry point; a legacy file reached any other way answers 404.
define('APP_GATEWAY', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/autoload.php';

$app = App\Bootstrap\Application::create(dirname(__DIR__));
$app->boot();

$gatewayRequest = App\Core\Http\Request::fromGlobals();
$gatewayFile = $app->legacyTarget($gatewayRequest);
if ($gatewayFile === null) {
    $app->run();
    return;
}
// Legacy page or API: included at global scope, so its variables behave exactly as before.
$app->enterLegacy($gatewayRequest, $gatewayFile);
unset($app, $gatewayRequest);
require $gatewayFile;
```

### EDIT `public/.htaccess` — E2

Append the single-entry-point rewrite block (guarded by IfModule).

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```
<FilesMatch "\.(md|sql|log|lock|bak|ini|dist|sh|yml|yaml|env)$">
    Require all denied
</FilesMatch>
```

**REPLACE IT WITH:**

```
<FilesMatch "\.(md|sql|log|lock|bak|ini|dist|sh|yml|yaml|env)$">
    Require all denied
</FilesMatch>

# Part 11B: single entry point. EVERY request except the static folders goes to index.php: the kernel serves its routes and the allowlisted old files (config/legacy.php).
# If this block is not honoured (mod_rewrite off, AllowOverride None) the old files answer 404 by themselves (guard in config/config.php): the panel is unavailable, never exposed.
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_URI} !^/(assets|uploads|ui-kit)/
    RewriteCond %{REQUEST_URI} !^/index\.php$
    RewriteRule ^ index.php [L]
</IfModule>
```

### EDIT `config/config.php` — E3

Fail-closed guard: legacy files answer 404 unless the front controller started the request.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```php
require_once __DIR__ . '/env.php';
load_local_env();
```

**REPLACE IT WITH:**

```php
// Part 11B: fail closed. Under a web server this file (and so every legacy page) runs only when public/index.php is the entry
// point (it defines APP_GATEWAY). A legacy file reached directly (for example because .htaccess is not honoured) answers 404.
if (PHP_SAPI !== 'cli' && !defined('APP_GATEWAY')) {
    error_log('[gateway] a legacy file was reached directly (' . ($_SERVER['REQUEST_URI'] ?? '?') . '). Check that public/.htaccess is active: mod_rewrite on and AllowOverride All for the public folder.');
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Not found');
}
require_once __DIR__ . '/env.php';
load_local_env();
```

### EDIT `.gitignore` — E4

Ignore the application log folder.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```
*.pyc
```

**REPLACE IT WITH:**

```
*.pyc
storage/logs/
```

Then `php -l public/index.php` and `php -l config/config.php` must print `No syntax errors detected`.

### STEP 5 — create the verification tools

### CREATE `tools/verify/dev_router.php`

Content (copy exactly; it ends with a newline):

```php
<?php
// tools/verify/dev_router.php — Part 11B. Router script for PHP's built-in server that behaves like public/.htaccess:
// static folders are served as files, everything else goes to public/index.php.
//
//   php -S 127.0.0.1:8080 -t public tools/verify/dev_router.php
//
// This is for local checks and tests only. XAMPP/Apache uses public/.htaccess.
$path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if (preg_match('#^/(assets|uploads|ui-kit)/#', $path)) {
    return false; // let the built-in server serve the static file
}
$front = dirname(__DIR__, 2) . '/public/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $front;
$_SERVER['PHP_SELF'] = '/index.php';
chdir(dirname($front));
require $front;
```

### CREATE `tools/verify/http_smoke.php`

Content (copy exactly; it ends with a newline):

```php
<?php
// tools/verify/http_smoke.php — Part 11B. Black-box check of a RUNNING site (XAMPP/Apache, or the built-in server).
//
//   php tools/verify/http_smoke.php http://localhost
//   php tools/verify/http_smoke.php http://localhost:8080 --skip-db     # skip the checks that need the database
//   php tools/verify/http_smoke.php https://example.test --no-verify    # self-signed certificate
//
// It proves the single entry point from the outside: the front controller answers, old pages still work, and nothing
// outside public/ or the allowlist can be read. Exit code 0 = all checks passed.

/** @return array{status:int,headers:array<string,string>,body:string} */
function smokeFetch(string $base, string $path, bool $verify, string $method = 'GET'): array
{
    $ctx = stream_context_create([
        'http' => ['method' => $method, 'follow_location' => 0, 'ignore_errors' => true, 'timeout' => 10, 'header' => "Accept: */*\r\nConnection: close\r\n"],
        'ssl' => ['verify_peer' => $verify, 'verify_peer_name' => $verify],
    ]);
    $body = @file_get_contents(rtrim($base, '/') . $path, false, $ctx);
    $status = 0;
    $headers = [];
    foreach ((array) ($http_response_header ?? []) as $i => $line) {
        if ($i === 0 && preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
            $status = (int) $m[1];
        } elseif (strpos($line, ':') !== false) {
            [$k, $v] = explode(':', $line, 2);
            $headers[strtolower(trim($k))] = trim($v);
        }
    }
    return ['status' => $status, 'headers' => $headers, 'body' => $body === false ? '' : $body];
}

/** @return array<int,array{name:string,path:string,ok:int[],db:bool,test:?callable}> */
function smokeChecks(): array
{
    return [
        ['name' => 'health answers 200 with a request id', 'path' => '/health', 'ok' => [200], 'db' => false,
            'test' => static function (array $r): bool { $j = json_decode($r['body'], true); return isset($r['headers']['x-request-id']) && is_array($j) && ($j['success'] ?? false) === true; }],
        ['name' => 'api health answers 200', 'path' => '/api/v1/health', 'ok' => [200], 'db' => false, 'test' => null],
        ['name' => 'unknown path is 404', 'path' => '/definitely-not-here', 'ok' => [404], 'db' => false, 'test' => null],
        ['name' => 'PHP source of includes/ is never served', 'path' => '/includes/auth.php', 'ok' => [404], 'db' => false,
            'test' => static function (array $r): bool { return strpos($r['body'], '<?php') === false; }],
        ['name' => 'config/ is not reachable', 'path' => '/config/config.php', 'ok' => [404], 'db' => false, 'test' => null],
        ['name' => 'database/ is not reachable', 'path' => '/database/schema.sql', 'ok' => [404], 'db' => false, 'test' => null],
        ['name' => 'dotfiles are not served', 'path' => '/.htaccess', 'ok' => [403, 404], 'db' => false,
            'test' => static function (array $r): bool { return strpos($r['body'], 'RewriteEngine') === false; }],
        ['name' => 'extra path info on a legacy page is 404', 'path' => '/login.php/extra', 'ok' => [404], 'db' => false, 'test' => null],
        ['name' => 'a legacy file that is not in the allowlist is 404', 'path' => '/modules/reports/index.php', 'ok' => [404], 'db' => false, 'test' => null],
        ['name' => 'static css is served', 'path' => '/assets/css/app/00-tokens.css', 'ok' => [200], 'db' => false, 'test' => null],
        ['name' => 'no directory listing', 'path' => '/assets/', 'ok' => [403, 404], 'db' => false, 'test' => null],
        ['name' => 'uploads never execute PHP', 'path' => '/uploads/x.php', 'ok' => [403, 404], 'db' => false, 'test' => null],
        ['name' => 'home redirects', 'path' => '/', 'ok' => [301, 302, 303], 'db' => true, 'test' => null],
        ['name' => 'login page opens', 'path' => '/login.php', 'ok' => [200], 'db' => true, 'test' => null],
        ['name' => 'dashboard without a session redirects to login', 'path' => '/modules/dashboard/index.php', 'ok' => [301, 302, 303], 'db' => true,
            'test' => static function (array $r): bool { return strpos($r['headers']['location'] ?? '', 'login') !== false; }],
        ['name' => 'GET /logout.php never logs out (redirect)', 'path' => '/logout.php', 'ok' => [302, 303], 'db' => true, 'test' => null],
        ['name' => 'API without a session is refused', 'path' => '/api/media/info.php?id=1', 'ok' => [401, 403], 'db' => true, 'test' => null],
        ['name' => 'API method guard (GET on a POST-only endpoint)', 'path' => '/api/notifications/mark_read.php', 'ok' => [401, 403, 405], 'db' => true, 'test' => null],
    ];
}

if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    $args = array_slice($argv, 1);
    $base = '';
    $skipDb = false;
    $verify = true;
    foreach ($args as $a) {
        if ($a === '--skip-db') { $skipDb = true; }
        elseif ($a === '--no-verify') { $verify = false; }
        elseif ($base === '') { $base = $a; }
    }
    if ($base === '' || !preg_match('#^https?://#', $base)) {
        fwrite(STDERR, "usage: php tools/verify/http_smoke.php <http://host[:port]> [--skip-db] [--no-verify]\n");
        exit(2);
    }
    $pass = 0;
    $fail = 0;
    $skip = 0;
    foreach (smokeChecks() as $c) {
        if ($skipDb && $c['db']) {
            $skip++;
            echo 'SKIP  ', $c['name'], "\n";
            continue;
        }
        $r = smokeFetch($base, $c['path'], $verify);
        $ok = in_array($r['status'], $c['ok'], true) && ($c['test'] === null || ($c['test'])($r));
        $ok ? $pass++ : $fail++;
        echo $ok ? 'PASS  ' : 'FAIL  ', $c['name'], '  [', $c['path'], ' -> ', $r['status'] === 0 ? 'no answer' : $r['status'], ']', "\n";
    }
    echo "http smoke: $pass passed, $fail failed, $skip skipped\n";
    exit($fail > 0 ? 1 : 0);
}
```

### STEP 6 — create the tests

`tests/_kernel.php` is a helper (not a test; the runner only runs `*_test.php`).

### CREATE `tests/_kernel.php`

Content (copy exactly; it ends with a newline):

```php
<?php
// tests/_kernel.php — Part 11B. Shared helper for the kernel tests (not a test itself: run_all.php only runs *_test.php).
date_default_timezone_set('Asia/Tehran');
require_once __DIR__ . '/../app/autoload.php';

$GLOBALS['__fail'] = 0;
$GLOBALS['__count'] = 0;

function check(string $label, $actual, $expected): void {
    $GLOBALS['__count']++;
    if ($actual !== $expected) {
        $GLOBALS['__fail']++;
        fwrite(STDERR, "FAIL: $label\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n");
    }
}

/** Runs $fn and returns the class name of the exception it throws, or '' when nothing is thrown. */
function thrown(callable $fn): string {
    try { $fn(); } catch (Throwable $e) { return get_class($e); }
    return '';
}

function finish(string $name): void {
    if ($GLOBALS['__fail'] > 0) { fwrite(STDERR, $GLOBALS['__fail'] . " failure(s)\n"); exit(1); }
    echo $name . ': OK (' . $GLOBALS['__count'] . " checks)\n";
}
```

### CREATE `tests/kernel_container_config_test.php`

Content (copy exactly; it ends with a newline):

```php
<?php
require __DIR__ . '/_kernel.php';

use App\Core\Config\Config;
use App\Core\Container\Container;
use App\Core\Container\ContainerException;

// ---- Config ----
$c = new Config(['a' => ['b' => ['c' => 5]], 'flat.key' => 'x', 'n' => null]);
check('dot get', $c->get('a.b.c'), 5);
check('flat key containing a dot', $c->get('flat.key'), 'x');
check('missing uses default', $c->get('a.zzz', 'd'), 'd');
check('null value is found', $c->has('n'), true);
check('null value is returned as null', $c->get('n', 'd'), null);
check('has false', $c->has('a.b.q'), false);
$c->set('x.y.z', 1);
check('set creates path', $c->get('x.y.z'), 1);
check('all contains set', isset($c->all()['x']['y']['z']), true);

// ---- Container ----
class T_Dep { public int $n = 0; }
class T_Svc { public T_Dep $dep; public string $label; public function __construct(T_Dep $dep, string $label = 'dflt') { $this->dep = $dep; $this->label = $label; } }
class T_NeedsScalar { public function __construct(string $must) {} }
class T_A { public function __construct(T_B $b) {} }
class T_B { public function __construct(T_A $a) {} }
abstract class T_Abstract {}

$k = new Container();
check('autowire class dependency', $k->get(T_Svc::class)->label, 'dflt');
check('autowire builds a new object each time', $k->get(T_Dep::class) === $k->get(T_Dep::class), false);
$k->singleton(T_Dep::class, function () { return new T_Dep(); });
check('singleton returns the same object', $k->get(T_Dep::class) === $k->get(T_Dep::class), true);
check('singleton is injected into autowired classes', $k->get(T_Svc::class)->dep === $k->get(T_Dep::class), true);
$k->bind('clock', function () { return new DateTimeImmutable('2020-01-01'); });
check('bind: factory gets the container', $k->get('clock') instanceof DateTimeImmutable, true);
check('bind returns a fresh object', $k->get('clock') === $k->get('clock'), false);
$obj = new stdClass();
$k->instance('thing', $obj);
check('instance returns the given object', $k->get('thing'), $obj);
check('has: bound id', $k->has('clock'), true);
check('has: instantiable class', $k->has(T_Dep::class), true);
check('has: unknown id', $k->has('nope'), false);
check('has: abstract class', $k->has(T_Abstract::class), false);
check('make with explicit parameter', $k->make(T_Svc::class, ['label' => 'custom'])->label, 'custom');
check('unknown id throws ContainerException', thrown(function () use ($k) { $k->get('nope'); }), ContainerException::class);
check('scalar without default throws', thrown(function () use ($k) { $k->get(T_NeedsScalar::class); }), ContainerException::class);
check('circular dependency throws', thrown(function () use ($k) { $k->get(T_A::class); }), ContainerException::class);
check('abstract class throws', thrown(function () use ($k) { $k->get(T_Abstract::class); }), ContainerException::class);
check('factory returning a non-object throws', thrown(function () { $x = new Container(); $x->bind('bad', function () { return 5; }); $x->get('bad'); }), ContainerException::class);
$k->bind('bad2', function () { return 5; });
thrown(function () use ($k) { $k->get('bad2'); });
check('resolving stack is cleaned after a failure', $k->get(T_Dep::class) instanceof T_Dep, true);
finish('kernel_container_config_test');
```

### CREATE `tests/kernel_http_test.php`

Content (copy exactly; it ends with a newline):

```php
<?php
require __DIR__ . '/_kernel.php';

use App\Core\Http\HttpException;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Http\Router;

// ---- Request ----
$r = new Request('post', '/api//v1/users/?page=2&x=1', ['page' => '2'], ['name' => 'Ali'], ['Content-Type' => 'application/json', 'X-Test' => 'v'], ['REMOTE_ADDR' => '1.2.3.4'], '{"name":"Json","n":5}', ['sid' => 'abc']);
check('method is upper-cased', $r->method(), 'POST');
check('path normalised', $r->path(), '/api/v1/users');
check('root path', (new Request('GET', '/?a=1'))->path(), '/');
check('root path from empty', (new Request('GET', ''))->path(), '/');
check('query', $r->query('page'), '2');
check('query default', $r->query('zzz', 'd'), 'd');
check('post wins over json', $r->input('name'), 'Ali');
check('json fallback', $r->input('n'), 5);
check('header lookup is case-insensitive', $r->header('x-TEST'), 'v');
check('cookie', $r->cookie('sid'), 'abc');
check('server', $r->server('REMOTE_ADDR'), '1.2.3.4');
check('isJson', $r->isJson(), true);
check('json parsed', $r->json(), ['name' => 'Json', 'n' => 5]);
check('wantsJson via /api/', (new Request('GET', '/api/x'))->wantsJson(), true);
check('wantsJson via Accept', (new Request('GET', '/x', [], [], ['Accept' => 'text/html, application/json']))->wantsJson(), true);
check('wantsJson via XHR header', (new Request('GET', '/x', [], [], ['X-Requested-With' => 'XMLHttpRequest']))->wantsJson(), true);
check('plain page does not want json', (new Request('GET', '/modules/a.php'))->wantsJson(), false);
check('bad json body gives null', (new Request('POST', '/', [], [], ['Content-Type' => 'application/json'], [], '{oops'))->json(), null);
check('scalar json body gives null', (new Request('POST', '/', [], [], ['Content-Type' => 'application/json'], [], '5'))->json(), null);
$r->setAttribute('k', 1);
check('attributes', [$r->attribute('k'), $r->attribute('none', 'd')], [1, 'd']);

// ---- Response ----
$ok = Response::ok(['id' => 1]);
check('ok status', $ok->status(), 200);
check('ok is an envelope', $ok->isEnvelope(), true);
check('ok body (empty meta is {})', $ok->body(), '{"success":true,"data":{"id":1},"meta":{}}');
$ok->mergeMeta(['request_id' => 'req_1']);
check('mergeMeta', $ok->body(), '{"success":true,"data":{"id":1},"meta":{"request_id":"req_1"}}');
check('json content type', $ok->headerValue('content-type'), 'application/json; charset=utf-8');
$err = Response::error('VALIDATION_FAILED', 'اطلاعات واردشده معتبر نیست.', 422, ['email' => ['ایمیل معتبر نیست.']], ['request_id' => 'r']);
check('error body keeps Persian unescaped', $err->body(), '{"success":false,"error":{"code":"VALIDATION_FAILED","message":"اطلاعات واردشده معتبر نیست.","fields":{"email":["ایمیل معتبر نیست."]}},"meta":{"request_id":"r"}}');
check('error without fields omits "fields"', strpos(Response::error('X', 'm', 400)->body(), 'fields'), false);
$plain = Response::json(['a' => 1]);
check('plain json is not an envelope', $plain->isEnvelope(), false);
check('mergeMeta ignores non-envelopes', $plain->mergeMeta(['x' => 1])->body(), '{"a":1}');
$red = Response::redirect('/login.php', 303);
check('redirect', [$red->status(), $red->headerValue('Location'), $red->body()], [303, '/login.php', '']);
check('html content type', Response::html('<p>x</p>')->headerValue('Content-Type'), 'text/html; charset=utf-8');
check('noContent', Response::noContent()->status(), 204);
$h = new Response('b', 200, ['X-A' => '1']);
$h->defaultHeader('x-a', '2')->defaultHeader('X-B', '3');
check('defaultHeader does not override', $h->headers(), ['X-A' => '1', 'X-B' => '3']);
$h->header('x-a', '9');
check('header replaces case-insensitively', $h->headers()['x-a'] ?? $h->headers()['X-A'], '9');

// ---- Router ----
$router = new Router();
$router->get('/', 'root')->name('home');
$router->get('/users', 'list')->name('users.index');
$router->post('/users', 'create');
$router->get('/users/{id:num}', 'show')->name('users.show');
$router->get('/posts/{slug:slug}', 'post');
$router->get('/files/{path:any}', 'file');
$router->get('/u/{name}/x', 'ux');
$router->group('/api/v1', ['auth'], function (Router $r) {
    $r->get('/ping', 'ping')->name('api.ping');
    $r->group('/admin', ['csrf'], function (Router $r) {
        $r->delete('/things/{id:num}', 'del')->middleware('permission:things.delete');
    });
});

function m(Router $router, string $method, string $uri): array { return $router->match(new Request($method, $uri)); }
check('root', m($router, 'GET', '/')['route']->handler(), 'root');
check('static route', m($router, 'GET', '/users/')['route']->handler(), 'list');
check('same path, other method', m($router, 'POST', '/users')['route']->handler(), 'create');
check('num param', m($router, 'GET', '/users/42')['params'], ['id' => '42']);
check('num param rejects letters', thrown(function () use ($router) { m($router, 'GET', '/users/abc'); }), HttpException::class);
check('slug param', m($router, 'GET', '/posts/hello-world')['params'], ['slug' => 'hello-world']);
check('slug rejects uppercase', thrown(function () use ($router) { m($router, 'GET', '/posts/Hello'); }), HttpException::class);
check('any param spans slashes', m($router, 'GET', '/files/a/b/c.txt')['params'], ['path' => 'a/b/c.txt']);
check('default param is one segment and is url-decoded', m($router, 'GET', '/u/a%20b/x')['params'], ['name' => 'a b']);
check('HEAD matches GET routes', m($router, 'HEAD', '/users')['route']->handler(), 'list');
check('group prefix', m($router, 'GET', '/api/v1/ping')['route']->path(), '/api/v1/ping');
check('group middleware inherited + own', m($router, 'DELETE', '/api/v1/admin/things/7')['route']->getMiddleware(), ['auth', 'csrf', 'permission:things.delete']);
check('route outside group has no middleware', m($router, 'GET', '/users')['route']->getMiddleware(), []);
try { m($router, 'GET', '/nope'); } catch (HttpException $e) { check('404', [$e->status(), $e->errorCode()], [404, 'NOT_FOUND']); }
try { m($router, 'PUT', '/users'); } catch (HttpException $e) { check('405', [$e->status(), $e->errorCode(), $e->headers()], [405, 'METHOD_NOT_ALLOWED', ['Allow' => 'GET, HEAD, POST']]); }
try { m($router, 'OPTIONS', '/users'); } catch (HttpException $e) { check('OPTIONS is 405 unless registered', $e->status(), 405); }
check('url()', $router->url('users.show', ['id' => 5]), '/users/5');
check('url() with query', $router->url('users.index', [], ['page' => 2, 'q' => 'a b']), '/users?page=2&q=a+b');
check('url() in group', $router->url('api.ping'), '/api/v1/ping');
check('url() encodes params', $router->url('users.show', ['id' => '1/2']), '/users/1%2F2');
check('url() unknown name', thrown(function () use ($router) { $router->url('zzz'); }), InvalidArgumentException::class);
check('url() missing param', thrown(function () use ($router) { $router->url('users.show'); }), InvalidArgumentException::class);
check('bad method rejected', thrown(function () { (new Router())->add('TRACE', '/x', 'h'); }), InvalidArgumentException::class);
check('unknown param type rejected', thrown(function () { (new Router())->get('/x/{id:uuid}', 'h'); }), InvalidArgumentException::class);
check('duplicate param rejected', thrown(function () { (new Router())->get('/x/{id}/{id}', 'h'); }), InvalidArgumentException::class);
check('broken segment rejected', thrown(function () { (new Router())->get('/x/a{id}', 'h'); }), InvalidArgumentException::class);
check('duplicate route name rejected', thrown(function () { $r = new Router(); $r->get('/a', 'h')->name('n'); $r->get('/b', 'h')->name('n'); $r->all(); }), InvalidArgumentException::class);
check('all() lists every route', count($router->all()), 9);
finish('kernel_http_test');
```

### CREATE `tests/kernel_validator_test.php`

Content (copy exactly; it ends with a newline):

```php
<?php
require __DIR__ . '/_kernel.php';

use App\Core\Http\HttpException;
use App\Core\Validation\Validator;

function v(array $data, array $rules, array $labels = [], array $messages = []) { return Validator::make($data, $rules, $labels, $messages); }
function msg(array $data, array $rules, string $field, array $labels = []): string { return v($data, $rules, $labels)->errors()[$field][0] ?? ''; }

// required
check('required passes', v(['a' => 'x'], ['a' => 'required'])->passes(), true);
check('required: missing', msg([], ['a' => 'required'], 'a'), 'a الزامی است.');
check('required: blank string', msg(['a' => '   '], ['a' => 'required'], 'a', ['a' => 'نام']), 'نام الزامی است.');
check('required: empty array', v(['a' => []], ['a' => 'required'])->fails(), true);
check('required: zero is a value', v(['a' => 0], ['a' => 'required'])->passes(), true);
check('required: "0" is a value', v(['a' => '0'], ['a' => 'required'])->passes(), true);
// optional / nullable
check('optional empty field skips other rules', v([], ['a' => 'email'])->passes(), true);
check('nullable null', v(['a' => null], ['a' => 'nullable|email'])->passes(), true);
check('nullable "" is kept as given', v(['a' => ''], ['a' => 'nullable|integer'])->validated(), ['a' => '']);
check('nullable still validates a real value', v(['a' => 'x'], ['a' => 'nullable|integer'])->fails(), true);
// types
check('string ok', v(['a' => 'x'], ['a' => 'string'])->passes(), true);
check('string rejects int', msg(['a' => 5], ['a' => 'string'], 'a', ['a' => 'عنوان']), 'عنوان باید متن باشد.');
check('integer ok', v(['a' => '12', 'b' => 7, 'c' => '-3'], ['a' => 'integer', 'b' => 'integer', 'c' => 'integer'])->passes(), true);
check('integer rejects 1.5', v(['a' => 1.5], ['a' => 'integer'])->fails(), true);
check('integer rejects text', msg(['a' => 'x1'], ['a' => 'integer'], 'a', ['a' => 'سن']), 'سن باید عدد صحیح باشد.');
check('integer rejects bool', v(['a' => true], ['a' => 'integer'])->fails(), true);
check('numeric ok', v(['a' => '1.5', 'b' => 3], ['a' => 'numeric', 'b' => 'numeric'])->passes(), true);
check('numeric rejects text', msg(['a' => 'abc'], ['a' => 'numeric'], 'a', ['a' => 'مبلغ']), 'مبلغ باید عدد باشد.');
check('boolean ok', v(['a' => true, 'b' => '0', 'c' => 1], ['a' => 'boolean', 'b' => 'boolean', 'c' => 'boolean'])->passes(), true);
check('boolean rejects "yes"', msg(['a' => 'yes'], ['a' => 'boolean'], 'a', ['a' => 'فعال']), 'فعال باید بله یا خیر باشد.');
check('array ok', v(['a' => [1]], ['a' => 'array'])->passes(), true);
check('array rejects string', v(['a' => 'x'], ['a' => 'array'])->fails(), true);
check('email ok', v(['a' => 'a@b.co'], ['a' => 'email'])->passes(), true);
check('email bad', msg(['a' => 'a@'], ['a' => 'email'], 'a', ['a' => 'ایمیل']), 'ایمیل باید یک ایمیل معتبر باشد.');
check('url ok', v(['a' => 'https://example.com/x?y=1'], ['a' => 'url'])->passes(), true);
check('url rejects javascript:', v(['a' => 'javascript:alert(1)'], ['a' => 'url'])->fails(), true);
check('url rejects ftp', v(['a' => 'ftp://x.com'], ['a' => 'url'])->fails(), true);
check('date ok', v(['a' => '2026-02-28'], ['a' => 'date'])->passes(), true);
check('date rejects 2026-02-30', v(['a' => '2026-02-30'], ['a' => 'date'])->fails(), true);
check('date rejects other format', v(['a' => '28/02/2026'], ['a' => 'date'])->fails(), true);
// size rules
check('min string uses characters (Persian)', v(['a' => 'سلام'], ['a' => 'min:4'])->passes(), true);
check('min string message', msg(['a' => 'ab'], ['a' => 'min:3'], 'a', ['a' => 'نام']), 'نام باید حداقل 3 کاراکتر باشد.');
check('max string message', msg(['a' => 'abcd'], ['a' => 'max:3'], 'a', ['a' => 'نام']), 'نام باید حداکثر 3 کاراکتر باشد.');
check('max counts characters, not bytes', v(['a' => 'سلام'], ['a' => 'max:4'])->passes(), true);
check('numeric min', msg(['a' => '4'], ['a' => 'integer|min:5'], 'a', ['a' => 'تعداد']), 'تعداد باید حداقل 5 باشد.');
check('numeric max ok', v(['a' => '5'], ['a' => 'integer|max:5'])->passes(), true);
check('between numeric message', msg(['a' => 11], ['a' => 'integer|between:1,10'], 'a', ['a' => 'امتیاز']), 'امتیاز باید بین 1 و 10 باشد.');
check('between string ok', v(['a' => 'abc'], ['a' => 'between:2,4'])->passes(), true);
check('array min', msg(['a' => [1]], ['a' => 'array|min:2'], 'a', ['a' => 'گزینه‌ها']), 'گزینه‌ها باید حداقل 2 مورد داشته باشد.');
// in / regex
check('in ok', v(['a' => 'b'], ['a' => 'in:a,b,c'])->passes(), true);
check('in works with ints', v(['a' => 2], ['a' => 'in:1,2'])->passes(), true);
check('in rejects', msg(['a' => 'z'], ['a' => 'in:a,b'], 'a', ['a' => 'نقش']), 'نقش انتخاب‌شده معتبر نیست.');
check('regex ok', v(['a' => 'ab_12'], ['a' => 'required|regex:/^[a-z0-9_]{3,30}$/'])->passes(), true);
check('regex may contain a pipe', v(['a' => 'x'], ['a' => 'regex:/^(x|y)$/'])->passes(), true);
check('regex rejects', msg(['a' => 'A!'], ['a' => 'regex:/^[a-z]+$/'], 'a', ['a' => 'کد']), 'کد قالب معتبری ندارد.');
// bail + array rules + overrides + nesting
check('bail: one message per field', count(v(['a' => ''], ['a' => 'required|string|min:3'])->errors()['a']), 1);
check('rule array form', v(['a' => 'ab'], ['a' => ['required', 'string', 'min:3']])->fails(), true);
check('message override by rule', msg(['a' => ''], ['a' => 'required'], 'a') !== '', true);
check('message override text', v([], ['a' => 'required'], ['a' => 'نام'], ['a.required' => 'لطفاً :attribute را وارد کنید.'])->errors(), ['a' => ['لطفاً نام را وارد کنید.']]);
check('dot path read', v(['u' => ['mail' => 'x']], ['u.mail' => 'required|email'])->fails(), true);
check('validated nests by dots', v(['u' => ['mail' => 'a@b.co', 'extra' => 1], 'z' => 1], ['u.mail' => 'email'])->validated(), ['u' => ['mail' => 'a@b.co']]);
check('validated has only ruled fields', v(['a' => 'x', 'junk' => 1], ['a' => 'required'])->validated(), ['a' => 'x']);
check('several fields, several errors', array_keys(v([], ['a' => 'required', 'b' => 'required'])->errors()), ['a', 'b']);
// developer errors
check('unknown rule throws LogicException', thrown(function () { v(['a' => 'x'], ['a' => 'frobnicate']); }), LogicException::class);
check('bad regex throws', thrown(function () { v(['a' => 'x'], ['a' => 'regex:/(/']); }), LogicException::class);
check('min without number throws', thrown(function () { v(['a' => 'x'], ['a' => 'min:abc']); }), LogicException::class);
check('between with one number throws', thrown(function () { v(['a' => 'x'], ['a' => 'between:3']); }), LogicException::class);
// validatedOrFail
check('validatedOrFail returns data', v(['a' => 'x'], ['a' => 'required'])->validatedOrFail(), ['a' => 'x']);
try { v([], ['email' => 'required'])->validatedOrFail(); check('validatedOrFail must throw', true, false); }
catch (HttpException $e) { check('validatedOrFail -> 422', [$e->status(), $e->errorCode(), array_keys($e->fields())], [422, 'VALIDATION_FAILED', ['email']]); }
finish('kernel_validator_test');
```

### CREATE `tests/kernel_app_test.php`

Content (copy exactly; it ends with a newline):

```php
<?php
require __DIR__ . '/_kernel.php';

use App\Bootstrap\Application;
use App\Core\Http\ExceptionHandler;
use App\Core\Http\HttpException;
use App\Core\Http\Middleware\AuthMiddleware;
use App\Core\Http\Middleware\CsrfMiddleware;
use App\Core\Http\Middleware\MiddlewareInterface;
use App\Core\Http\Middleware\PermissionMiddleware;
use App\Core\Http\Pipeline;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Http\Router;
use App\Core\Validation\Validator;

$root = dirname(__DIR__);
$logged = [];
$state = ['login' => true, 'csrf_ok' => true, 'can' => true];

function newApp(string $root, array &$logged, array &$state, bool $debug = false): Application {
    $app = Application::create($root, ['debug' => $debug, 'logger' => function (string $l) use (&$logged) { $logged[] = $l; }]);
    // Fakes instead of the legacy procedural code (which needs MySQL).
    $app->registerMiddleware('auth', function () use (&$state) { return new AuthMiddleware(function () use (&$state) { return $state['login']; }, '/login.php'); });
    $app->registerMiddleware('csrf', function () use (&$state) { return new CsrfMiddleware(function (string $t) use (&$state) { return $state['csrf_ok'] && $t === 'good'; }); });
    $app->registerMiddleware('permission', function (array $a) use (&$state) {
        [$m, $act] = array_pad(explode('.', $a[0], 2), 2, 'view');
        return new PermissionMiddleware(function (string $mod, string $action) use (&$state) { return $state['can']; }, $m, $act);
    });
    return $app;
}
function req(string $m, string $u, array $post = [], array $headers = []): Request { return new Request($m, $u, [], $post, $headers); }

class T_Controller {
    public function show(Request $r, array $p): Response { return Response::ok(['id' => (int) $p['id']], [], 200); }
    public function __invoke(Request $r, array $p) { return ['invoked' => true]; }
}

// ---- Pipeline order ----
$trace = [];
$mk = function (string $n) use (&$trace) { return function (Request $r, callable $next) use ($n, &$trace) { $trace[] = "in:$n"; $res = $next($r); $trace[] = "out:$n"; return $res; }; };
$res = (new Pipeline())->process([$mk('a'), $mk('b')], req('GET', '/'), function () use (&$trace) { $trace[] = 'core'; return Response::text('x'); });
check('pipeline is an onion', $trace, ['in:a', 'in:b', 'core', 'out:b', 'out:a']);
check('pipeline rejects non-response', thrown(function () { (new Pipeline())->process([function ($r, $n) { return 5; }], req('GET', '/'), function () { return Response::text('x'); }); }), InvalidArgumentException::class);
check('pipeline rejects bad middleware', thrown(function () { (new Pipeline())->process(['nope'], req('GET', '/'), function () { return Response::text('x'); }); }), InvalidArgumentException::class);
$short = (new Pipeline())->process([function (Request $r, callable $n) { return Response::text('stop', 418); }], req('GET', '/'), function () { return Response::text('never'); });
check('middleware can short-circuit', $short->status(), 418);

// ---- Application: routing + envelope + request id ----
$app = newApp($root, $logged, $state);
$app->loadRoutes($root . '/routes/core.php');
$app->router()->get('/api/v1/items/{id:num}', [T_Controller::class, 'show'])->middleware('auth', 'permission:items.view')->name('items.show');
$app->router()->post('/api/v1/items', function (Request $r) { return Response::ok(['created' => true], [], 201); })->middleware('auth', 'csrf', 'permission:items.edit');
$app->router()->get('/boom', function () { throw new RuntimeException('secret db password'); });
$app->router()->get('/api/v1/boom', function () { throw new RuntimeException('secret db password'); });
$app->router()->get('/invoke', T_Controller::class);
$app->router()->get('/arr', function () { return ['x' => 1]; });
$app->router()->get('/str', function () { return '<b>hi</b>'; });
$app->router()->get('/nothing', function () { return null; });
$app->router()->get('/bad', function () { return 12; });
$app->router()->post('/validate', function (Request $r) {
    $data = Validator::make($r->json() ?? [], ['email' => 'required|email', 'name' => 'required|string|max:5'], ['email' => 'ایمیل', 'name' => 'نام'])->validatedOrFail();
    return Response::ok($data);
});
$app->validateRoutes();
check('validateRoutes passes for the core + test routes', true, true);

$h = $app->handle(req('GET', '/health'));
check('health 200', $h->status(), 200);
$p = $h->payload();
check('health envelope', [$p['success'], $p['data']['status']], [true, 'ok']);
check('health has request id in meta', (bool) preg_match('/^req_[0-9a-f]{16}$/', $p['meta']['request_id']), true);
check('X-Request-Id header equals meta', $h->headerValue('X-Request-Id'), $p['meta']['request_id']);
check('security headers', [$h->headerValue('X-Content-Type-Options'), $h->headerValue('X-Frame-Options'), $h->headerValue('Referrer-Policy'), $h->headerValue('Cache-Control')], ['nosniff', 'DENY', 'same-origin', 'no-store']);
check('api health', $app->handle(req('GET', '/api/v1/health'))->status(), 200);
check('health leaks no php version', strpos($h->body(), PHP_VERSION), false);
$given = $app->handle(req('GET', '/health', [], ['X-Request-Id' => 'client-supplied-123']));
check('a sane incoming request id is kept', $given->headerValue('X-Request-Id'), 'client-supplied-123');
$evil = $app->handle(req('GET', '/health', [], ['X-Request-Id' => "bad id\r\nSet-Cookie: x=1"]));
check('a hostile incoming request id is replaced', (bool) preg_match('/^req_[0-9a-f]{16}$/', (string) $evil->headerValue('X-Request-Id')), true);

// "/" redirect uses the legacy session; here we swap the bridge by routing through our fake auth state instead
$app->router()->get('/home-fake', function () use (&$state) { return Response::redirect($state['login'] ? '/modules/dashboard/index.php' : '/login.php'); });
check('handler redirect passes through', $app->handle(req('GET', '/home-fake'))->headerValue('Location'), '/modules/dashboard/index.php');

// 404 / 405
$nf = $app->handle(req('GET', '/api/v1/nothing-here'));
check('api 404 is JSON envelope', [$nf->status(), $nf->payload()['error']['code'], $nf->payload()['success']], [404, 'NOT_FOUND', false]);
$nfh = $app->handle(req('GET', '/nothing-here', [], ['Accept' => 'text/html']));
check('page 404 is HTML', [$nfh->status(), strpos((string) $nfh->headerValue('Content-Type'), 'text/html') === 0, strpos($nfh->body(), 'lang="fa"') !== false], [404, true, true]);
check('html 404 carries the request id', strpos($nfh->body(), (string) $nfh->headerValue('X-Request-Id')) !== false, true);
$na = $app->handle(req('DELETE', '/health'));
check('405 + Allow header', [$na->status(), $na->headerValue('Allow'), $na->payload()['error']['code'] ?? 'html'], [405, 'GET, HEAD', 'html']);
$na2 = $app->handle(req('DELETE', '/api/v1/health'));
check('api 405 JSON', [$na2->status(), $na2->payload()['error']['code']], [405, 'METHOD_NOT_ALLOWED']);

// auth
$state['login'] = false;
$u = $app->handle(req('GET', '/api/v1/items/5'));
check('api without session -> 401 JSON, not a redirect', [$u->status(), $u->payload()['error']['code'], $u->headerValue('Location')], [401, 'UNAUTHENTICATED', null]);
$page = $app->router()->get('/secret-page', function () { return 'x'; })->middleware('auth');
$u2 = $app->handle(req('GET', '/secret-page'));
check('page without session -> redirect to login', [$u2->status(), $u2->headerValue('Location')], [302, '/login.php']);
$state['login'] = true;

// controller + permission
$s = $app->handle(req('GET', '/api/v1/items/5'));
check('controller via container', [$s->status(), $s->payload()['data']], [200, ['id' => 5]]);
$state['can'] = false;
$f = $app->handle(req('GET', '/api/v1/items/5'));
check('missing permission -> 403 JSON', [$f->status(), $f->payload()['error']['code']], [403, 'FORBIDDEN']);
$state['can'] = true;

// csrf
$c1 = $app->handle(req('POST', '/api/v1/items'));
check('POST without token -> 403 CSRF_INVALID', [$c1->status(), $c1->payload()['error']['code']], [403, 'CSRF_INVALID']);
$c2 = $app->handle(req('POST', '/api/v1/items', [], ['X-CSRF-TOKEN' => 'bad']));
check('POST with wrong token -> 403', $c2->status(), 403);
$c3 = $app->handle(req('POST', '/api/v1/items', [], ['X-CSRF-TOKEN' => 'good']));
check('POST with header token -> 201', [$c3->status(), $c3->payload()['data']], [201, ['created' => true]]);
$c4 = $app->handle(req('POST', '/api/v1/items', ['csrf_token' => 'good']));
check('POST with form token -> 201', $c4->status(), 201);
$c5 = $app->handle(new Request('POST', '/api/v1/items', [], [], ['Content-Type' => 'application/json'], [], '{"csrf_token":"good"}'));
check('POST with JSON token -> 201', $c5->status(), 201);
$c6 = $app->handle(req('POST', '/api/v1/items', [], ['X-CSRFTOKEN' => 'good']));
check('legacy X-CSRFTOKEN header accepted', $c6->status(), 201);
$state['csrf_ok'] = false;
check('csrf verifier false -> 403', $app->handle(req('POST', '/api/v1/items', [], ['X-CSRF-TOKEN' => 'good']))->status(), 403);
$state['csrf_ok'] = true;

// handler return types
check('invokable controller + array result', $app->handle(req('GET', '/invoke'))->payload()['data'], ['invoked' => true]);
check('array -> envelope', $app->handle(req('GET', '/arr'))->payload()['data'], ['x' => 1]);
check('string -> html', [$app->handle(req('GET', '/str'))->body(), $app->handle(req('GET', '/str'))->headerValue('Content-Type')], ['<b>hi</b>', 'text/html; charset=utf-8']);
check('null -> 204', $app->handle(req('GET', '/nothing'))->status(), 204);
check('unsupported return -> 500', $app->handle(req('GET', '/bad'))->status(), 500);

// exceptions never leak
$b1 = $app->handle(req('GET', '/api/v1/boom'));
check('exception -> 500 JSON', [$b1->status(), $b1->payload()['error']['code']], [500, 'INTERNAL_ERROR']);
check('production hides the exception text', strpos($b1->body(), 'secret db password'), false);
check('request id present on 500', isset($b1->payload()['meta']['request_id']), true);
check('exception was logged with the request id', strpos(end($logged), (string) $b1->payload()['meta']['request_id']) !== false && strpos(end($logged), 'secret db password') !== false, true);
$b2 = $app->handle(req('GET', '/boom', [], ['Accept' => 'text/html']));
check('page 500 is HTML without details', [$b2->status(), strpos($b2->body(), 'secret db password')], [500, false]);
$dbg = newApp($root, $logged, $state, true);
$dbg->router()->get('/api/v1/boom', function () { throw new RuntimeException('visible in debug'); });
$d = $dbg->handle(req('GET', '/api/v1/boom'));
check('debug mode shows details', $d->payload()['error']['debug']['message'], 'visible in debug');

// validation through the pipeline
$v1 = $app->handle(new Request('POST', '/validate', [], [], ['Content-Type' => 'application/json'], [], '{"email":"x","name":"toolong"}'));
check('validation -> 422 with fields', [$v1->status(), $v1->payload()['error']['code'], array_keys($v1->payload()['error']['fields'])], [422, 'VALIDATION_FAILED', ['email', 'name']]);
check('validation message is Persian', $v1->payload()['error']['fields']['name'][0], 'نام باید حداکثر 5 کاراکتر باشد.');
$v2 = $app->handle(new Request('POST', '/validate', [], [], ['Content-Type' => 'application/json'], [], '{"email":"a@b.co","name":"ali"}'));
check('valid input passes', [$v2->status(), $v2->payload()['data']], [200, ['email' => 'a@b.co', 'name' => 'ali']]);

// configuration errors are loud
$bad = newApp($root, $logged, $state);
$bad->router()->get('/x', function () { return 'x'; })->middleware('no-such-alias');
check('unknown middleware alias is caught by validateRoutes', thrown(function () use ($bad) { $bad->validateRoutes(); }), LogicException::class);
check('...and becomes a 500 at request time', $bad->handle(req('GET', '/x'))->status(), 500);
$bad2 = newApp($root, $logged, $state);
$bad2->router()->get('/x', 'not-callable');
check('unusable handler is caught by validateRoutes', thrown(function () use ($bad2) { $bad2->validateRoutes(); }), LogicException::class);
check('routes file must return a callable', thrown(function () use ($bad, $root) { $f = sys_get_temp_dir() . '/r_' . bin2hex(random_bytes(3)) . '.php'; file_put_contents($f, '<?php return 5;'); try { $bad->loadRoutes($f); } finally { unlink($f); } }), LogicException::class);
// default (unfaked) aliases exist and permission alias needs an argument
$real = Application::create($root, ['debug' => false]);
$real->router()->get('/p', function () { return 'x'; })->middleware('permission');
check('permission alias without argument -> LogicException', thrown(function () use ($real) { $real->validateRoutes(); }), LogicException::class);
check('Application exposes container, config, router', [$real->container()->get(Application::class) === $real, $real->config()->get('login_url'), $real->router() === $real->container()->get(Router::class)], [true, '/login.php', true]);
check('ExceptionHandler is a container service', $real->container()->get(ExceptionHandler::class) instanceof ExceptionHandler, true);

// ---- boot() and bootstrappers ----
class T_Boot implements App\Bootstrap\Bootstrapper {
    public static int $calls = 0;
    public function bootstrap(Application $app): void { self::$calls++; $app->router()->get('/from-bootstrapper', function () { return ['b' => 1]; }); }
}
class T_NotBoot {}
$booted = Application::create($root, ['debug' => false, 'config' => ['bootstrappers' => [T_Boot::class]]]);
$booted->boot();
check('boot loads routes/core.php', $booted->handle(req('GET', '/health'))->status(), 200);
check('boot runs bootstrappers', [T_Boot::$calls, $booted->handle(req('GET', '/from-bootstrapper'))->payload()['data']], [1, ['b' => 1]]);
$booted->validateRoutes();
$booted->boot();
check('boot is idempotent (no duplicate routes)', [T_Boot::$calls, thrown(function () use ($booted) { $booted->validateRoutes(); })], [1, '']);
check('boot with no bootstrappers is fine', thrown(function () use ($root) { Application::create($root)->boot(); }), '');
check('a bootstrapper must implement the interface', thrown(function () use ($root) { Application::create($root, ['config' => ['bootstrappers' => [T_NotBoot::class]]])->boot(); }), LogicException::class);

// ---- duplicate routes ----
$dup = newApp($root, $logged, $state);
$dup->router()->get('/things/{id:num}', function () { return 'a'; });
$dup->router()->get('/things/{other}', function () { return 'b'; });
check('duplicate route shape (different param names) is rejected', thrown(function () use ($dup) { $dup->validateRoutes(); }), LogicException::class);
$ok2 = newApp($root, $logged, $state);
$ok2->router()->get('/things', function () { return 'a'; });
$ok2->router()->post('/things', function () { return 'b'; });
check('same path with different methods is fine', thrown(function () use ($ok2) { $ok2->validateRoutes(); }), '');
finish('kernel_app_test');
```

### CREATE `tests/logger_test.php`

Content (copy exactly; it ends with a newline):

```php
<?php
require __DIR__ . '/_kernel.php';

use App\Core\Log\Logger;

$dir = sys_get_temp_dir() . '/log11b_' . bin2hex(random_bytes(4));
$log = new Logger($dir . '/nested/logs', 'info');
$log->debug('hidden');
check('debug is below the minimum level: nothing written', is_file($log->currentFile()), false);
$log->info('hello', ['request_id' => 'req_1', 'user' => 7]);
$log->error('boom ی', ['obj' => new stdClass(), 'deep' => [[[[[['x' => 1]]]]]]]);
check('folder created on demand', is_dir($dir . '/nested/logs'), true);
check('file name has the UTC date', basename($log->currentFile()), 'app-' . gmdate('Y-m-d') . '.log');
$lines = array_values(array_filter(explode("\n", (string) file_get_contents($log->currentFile()))));
check('two lines written', count($lines), 2);
$a = json_decode($lines[0], true);
check('line 1 level', $a['level'], 'info');
check('line 1 message', $a['message'], 'hello');
check('line 1 context kept', $a['context'], ['request_id' => 'req_1', 'user' => 7]);
check('line 1 has a UTC time', (bool) preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/D', $a['time']), true);
$b = json_decode($lines[1], true);
check('persian text stays readable (not \\u escaped)', strpos($lines[1], 'boom ی') !== false, true);
check('objects are reduced to their class name', $b['context']['obj'], '[object stdClass]');
check('deep arrays are cut', is_array($b['context']['deep']), true);
check('unknown level is ignored without error', thrown(function () use ($log) { $log->log('nope', 'x'); }), '');
check('unknown minimum level is rejected', thrown(function () { new Logger('x', 'loud'); }), 'InvalidArgumentException');

// An unwritable target must not throw: the file name is used as a folder name, so mkdir fails.
$blocker = $dir . '/blocker';
file_put_contents($blocker, 'x');
$bad = new Logger($blocker . '/sub', 'info');
$prev = ini_set('error_log', $dir . '/php-errors.log');
check('unwritable folder does not throw', thrown(function () use ($bad) { $bad->error('fallback'); }), '');
ini_set('error_log', (string) $prev);
check('fallback went to error_log()', strpos((string) @file_get_contents($dir . '/php-errors.log'), 'fallback') !== false, true);

// cleanup
foreach (glob($dir . '/nested/logs/*') ?: [] as $f) { unlink($f); }
@rmdir($dir . '/nested/logs'); @rmdir($dir . '/nested'); @unlink($blocker); @unlink($dir . '/php-errors.log'); @rmdir($dir);
finish('logger_test');
```

### CREATE `tests/legacy_gateway_test.php`

Content (copy exactly; it ends with a newline):

```php
<?php
require __DIR__ . '/_kernel.php';

use App\Bootstrap\Application;
use App\Core\Http\LegacyGateway;
use App\Core\Http\Request;

$root = dirname(__DIR__);

// ---- unit: resolve() ----
$pub = sys_get_temp_dir() . '/gw11b_' . bin2hex(random_bytes(4));
mkdir($pub . '/modules/x', 0777, true);
foreach (['login.php', 'modules/x/index.php', 'secret.php'] as $f) { file_put_contents($pub . '/' . $f, "<?php\n"); }
$gw = new LegacyGateway($pub, ['login.php', '/modules/x/index.php', 'missing.php']);
check('listed file resolves', $gw->resolve('/login.php'), $pub . '/login.php');
check('listed nested file resolves', $gw->resolve('/modules/x/index.php'), $pub . '/modules/x/index.php');
check('file on disk but not listed is refused', $gw->resolve('/secret.php'), null);
check('listed but missing on disk is refused', $gw->resolve('/missing.php'), null);
foreach (['/LOGIN.PHP', '/login.php/', '/login.php/extra', '/login.PHP', '//login.php', '/./login.php', '/modules/../login.php', '/%6Cogin.php', '/login.php%00', "/login.php\0", '\\login.php', 'login.php', '', '/', '/modules', '/modules/x'] as $p) {
    check('refused: ' . json_encode($p), $gw->resolve($p), null);
}
check('files() is sorted and normalised', $gw->files(), ['login.php', 'missing.php', 'modules/x/index.php']);
$sv = $gw->serverVars('/login.php', $pub . '/login.php');
check('serverVars SCRIPT_NAME', $sv['SCRIPT_NAME'], '/login.php');
check('serverVars SCRIPT_FILENAME', $sv['SCRIPT_FILENAME'], $pub . '/login.php');
unlink($pub . '/login.php'); unlink($pub . '/modules/x/index.php'); unlink($pub . '/secret.php'); rmdir($pub . '/modules/x'); rmdir($pub . '/modules'); rmdir($pub);

// ---- drift: public/ and config/legacy.php must list exactly the same files ----
$listed = (require $root . '/config/legacy.php');
$onDisk = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/public', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->isFile() && strtolower($f->getExtension()) === 'php') {
        $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($root . '/public') + 1));
        if ($rel !== 'index.php' && strpos($rel, 'uploads/') !== 0) { $onDisk[] = $rel; }
    }
}
sort($onDisk, SORT_STRING);
$sortedListed = $listed; sort($sortedListed, SORT_STRING);
check('every public PHP file (except index.php) is in config/legacy.php', array_values(array_diff($onDisk, $listed)), []);
check('every entry of config/legacy.php exists on disk', array_values(array_diff($listed, $onDisk)), []);
check('config/legacy.php has no duplicates', count($listed), count(array_unique($listed)));
check('index.php is never in the allowlist', in_array('index.php', $listed, true), false);

// ---- the fail-closed guard exists, and it comes before anything else that matters ----
$config = (string) file_get_contents($root . '/config/config.php');
check('config.php has the APP_GATEWAY guard', strpos($config, "!defined('APP_GATEWAY')") !== false, true);
check('guard answers 404', (bool) preg_match("/!defined\('APP_GATEWAY'\).{0,400}http_response_code\(404\)/s", $config), true);
check('guard is before env loading', strpos($config, "!defined('APP_GATEWAY')") < strpos($config, 'load_local_env()'), true);
$index = (string) file_get_contents($root . '/public/index.php');
check('index.php defines APP_GATEWAY before config.php', strpos($index, "define('APP_GATEWAY', true)") < strpos($index, "require_once __DIR__ . '/../config/config.php'"), true);
$htaccess = (string) file_get_contents($root . '/public/.htaccess');
check('.htaccess sends everything except static folders to index.php', (bool) preg_match('/RewriteCond %\{REQUEST_URI\} !\^\/\(assets\|uploads\|ui-kit\)\//', $htaccess) && strpos($htaccess, 'RewriteRule ^ index.php [L]') !== false, true);
check('.htaccess has no "file exists" escape hatch', strpos($htaccess, '!-f') === false && strpos($htaccess, '!-d') === false, true);

// ---- the real application: router wins, then the allowlist ----
$app = Application::create($root, ['logger' => function () {}]);
$app->boot();
$req = function (string $m, string $u): Request { return new Request($m, $u); };
check('router path (/health) is not legacy', $app->legacyTarget($req('GET', '/health')), null);
check('router path with another method is not legacy (405 belongs to the router)', $app->legacyTarget($req('POST', '/health')), null);
check('/ is a route, not legacy', $app->legacyTarget($req('GET', '/')), null);
check('/index.php is a route, not legacy', $app->legacyTarget($req('GET', '/index.php')), null);
check('login page is served by the gateway', $app->legacyTarget($req('GET', '/login.php')), $root . '/public/login.php');
check('POST to a legacy API is served by the gateway', $app->legacyTarget($req('POST', '/api/media/upload.php')), $root . '/public/api/media/upload.php');
check('query string does not matter', $app->legacyTarget($req('GET', '/api/media/info.php?id=3')), $root . '/public/api/media/info.php');
foreach (['/includes/auth.php', '/config/config.php', '/modules/reports/index.php', '/login.php/x', '/nothing'] as $u) {
    check('unknown to both: ' . $u, $app->legacyTarget($req('GET', $u)), null);
}
// enterLegacy sets what the page guard reads
$keep = [$_SERVER['SCRIPT_NAME'] ?? null, $_SERVER['SCRIPT_FILENAME'] ?? null, $_SERVER['PHP_SELF'] ?? null, $_SERVER['PATH_TRANSLATED'] ?? null];
$r = $req('GET', '/modules/media/index.php');
$app->enterLegacy($r, $root . '/public/modules/media/index.php');
check('enterLegacy sets SCRIPT_NAME to the URL path', $_SERVER['SCRIPT_NAME'], '/modules/media/index.php');
check('enterLegacy sets SCRIPT_FILENAME', $_SERVER['SCRIPT_FILENAME'], $root . '/public/modules/media/index.php');
foreach (['SCRIPT_NAME', 'SCRIPT_FILENAME', 'PHP_SELF', 'PATH_TRANSLATED'] as $i => $k) { if ($keep[$i] === null) { unset($_SERVER[$k]); } else { $_SERVER[$k] = $keep[$i]; } }
check('Application exposes the gateway and the logger', [get_class($app->legacy()), get_class($app->logger())], ['App\\Core\\Http\\LegacyGateway', 'App\\Core\\Log\\Logger']);
finish('legacy_gateway_test');
```

### CREATE `tests/gateway_http_test.php`

Content (copy exactly; it ends with a newline):

```php
<?php
// Part 11B — real HTTP test with PHP's built-in server (no Apache needed).
//  A) WITHOUT the router script, every legacy file is requested directly (what happens when .htaccess is not honoured): all must answer 404.
//  B) WITH tools/verify/dev_router.php (behaves like .htaccess): the kernel answers, unknown paths are 404, source files are never served.
// Skipped (and says so) when the server cannot be started here.
require __DIR__ . '/_kernel.php';

$root = dirname(__DIR__);
if (!function_exists('proc_open')) { echo "gateway_http_test: OK (skipped: proc_open is disabled)\n"; exit(0); }

function gwStart(string $root, ?string $router): ?array {
    $probe = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    if (!$probe) { return null; }
    $port = (int) substr((string) strrchr((string) stream_socket_get_name($probe, false), ':'), 1);
    fclose($probe);
    $cmd = escapeshellarg(PHP_BINARY) . ' -S 127.0.0.1:' . $port . ' -t ' . escapeshellarg($root . '/public') . ($router ? ' ' . escapeshellarg($router) : '');
    $proc = @proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['file', sys_get_temp_dir() . '/gw11b_srv_out.log', 'a'], 2 => ['file', sys_get_temp_dir() . '/gw11b_srv_err.log', 'a']], $pipes);
    if (!is_resource($proc)) { return null; }
    for ($i = 0; $i < 50; $i++) {
        $s = @fsockopen('127.0.0.1', $port, $e, $m, 0.2);
        if ($s) { fclose($s); return ['proc' => $proc, 'pipes' => $pipes, 'port' => $port]; }
        usleep(100000);
    }
    proc_terminate($proc);
    return null;
}
function gwStop(array $srv): void { foreach ($srv['pipes'] as $p) { if (is_resource($p)) { fclose($p); } } proc_terminate($srv['proc']); proc_close($srv['proc']); }
function gwGet(int $port, string $path, string $method = 'GET'): array {
    $ctx = stream_context_create(['http' => ['method' => $method, 'follow_location' => 0, 'ignore_errors' => true, 'timeout' => 10]]);
    $body = @file_get_contents('http://127.0.0.1:' . $port . $path, false, $ctx);
    $status = 0; $headers = [];
    foreach ((array) ($http_response_header ?? []) as $i => $line) {
        if ($i === 0 && preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) { $status = (int) $m[1]; }
        elseif (strpos($line, ':') !== false) { [$k, $v] = explode(':', $line, 2); $headers[strtolower(trim($k))] = trim($v); }
    }
    return ['status' => $status, 'headers' => $headers, 'body' => $body === false ? '' : $body];
}

// ---- A) direct access, no router ----
$a = gwStart($root, null);
if ($a === null) { echo "gateway_http_test: OK (skipped: the built-in web server could not be started)\n"; exit(0); }
$legacy = require $root . '/config/legacy.php';
foreach ($legacy as $f) {
    $r = gwGet($a['port'], '/' . $f);
    check("direct access to /$f is 404", $r['status'], 404);
    check("direct access to /$f leaks nothing", strpos($r['body'], '<html') === false && strpos($r['body'], '<?php') === false, true);
}
$r = gwGet($a['port'], '/index.php/x');
check('front controller direct: unknown path is a 404 page of the kernel', $r['status'], 404);
gwStop($a);

// ---- B) through the router script (same behaviour as .htaccess) ----
$b = gwStart($root, $root . '/tools/verify/dev_router.php');
if ($b === null) { echo "gateway_http_test: OK (skipped part B: server could not be started)\n"; exit(0); }
$r = gwGet($b['port'], '/health');
check('/health 200', $r['status'], 200);
check('/health has a request id header', isset($r['headers']['x-request-id']), true);
check('/health is a success envelope', (json_decode($r['body'], true)['success'] ?? null), true);
check('/health has security headers', $r['headers']['x-content-type-options'] ?? '', 'nosniff');
$r = gwGet($b['port'], '/api/v1/health');
check('/api/v1/health 200', $r['status'], 200);
$r = gwGet($b['port'], '/nothing-here', 'GET');
check('unknown path 404', $r['status'], 404);
$r = gwGet($b['port'], '/api/v1/nothing-here');
check('unknown API path 404', $r['status'], 404);
foreach (['/includes/auth.php', '/config/config.php', '/database/schema.sql', '/app/autoload.php', '/login.php/extra', '/modules/reports/index.php', '/LOGIN.PHP', '/%6Cogin.php'] as $p) {
    $r = gwGet($b['port'], $p);
    check("$p is 404 through the front controller", $r['status'], 404);
    check("$p shows no source", strpos($r['body'], '<?php') === false, true);
}
$r = gwGet($b['port'], '/assets/css/app/00-tokens.css');
check('static css still served', $r['status'], 200);
$r = gwGet($b['port'], '/health', 'POST');
check('POST /health is 405', $r['status'], 405);
$r = gwGet($b['port'], '/logout.php');
check('legacy logout page runs through the gateway (303, not 404)', $r['status'], 303);
$r = gwGet($b['port'], '/modules/dashboard/index.php');
check('legacy page runs through the gateway and redirects an anonymous visitor to login', [$r['status'], strpos($r['headers']['location'] ?? '', 'login') !== false], [302, true]);
gwStop($b);
@unlink(sys_get_temp_dir() . '/gw11b_srv_out.log'); @unlink(sys_get_temp_dir() . '/gw11b_srv_err.log');
finish('gateway_http_test');
```

### STEP 7 — documentation, hand-off set, manifest, changelog

Four edits to existing hand-off files, then three new files.

### EDIT `docs/ai/HANDOFF.md` — E5

Current-state section: add the 11B line.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```markdown
- 11A — baseline safety: logout is POST + CSRF; every file in `public/api/` has a method guard (`tests/api_method_guards_test.php`); `public/api/media/upload.php` is POST-only and read-only file info lives in `public/api/media/info.php` (GET, needs `media` view); tools: `tools/gates/php80_gate.php` (static PHP 8.0 scan), `tools/ai/mark_part.php` (writes `docs/ai/state.json`); test `tests/persian_letters_test.php` forbids Arabic look-alike letters.

```

**REPLACE IT WITH:**

```markdown
- 11A — baseline safety: logout is POST + CSRF; every file in `public/api/` has a method guard (`tests/api_method_guards_test.php`); `public/api/media/upload.php` is POST-only and read-only file info lives in `public/api/media/info.php` (GET, needs `media` view); tools: `tools/gates/php80_gate.php` (static PHP 8.0 scan), `tools/ai/mark_part.php` (writes `docs/ai/state.json`); test `tests/persian_letters_test.php` forbids Arabic look-alike letters.
- 11B — HTTP kernel: `public/index.php` is the **only** entry point (see `docs/kernel.md`); old pages/APIs run only through the allowlist `config/legacy.php`; `config/config.php` answers 404 to any legacy file reached directly; kernel in `app/` (router, middleware, container, validator, logger); `php tools/verify/http_smoke.php <url>` checks a running site. New endpoints are never written in the old response shape.

```

### EDIT `docs/ai/DECISIONS.md` — E6

Append decisions D-017 … D-021.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```markdown
| D-016 | `tools/ai/mark_part.php` lets the executor write only `executed`, `partial`, `blocked`; it keeps other keys of the part entry. `verified` and `released` are written by the owner. | The executor cannot know whether the real stack passed; the status must not claim it. |
```

**REPLACE IT WITH:**

```markdown
| D-016 | `tools/ai/mark_part.php` lets the executor write only `executed`, `partial`, `blocked`; it keeps other keys of the part entry. `verified` and `released` are written by the owner. | The executor cannot know whether the real stack passed; the status must not claim it. |
| D-017 | The kernel keeps its own small interfaces; no `psr/*` package. A PSR-7/15 adapter can be added later at one seam (`Application::dispatch`); decision point in 11G. | Package budget, complexity, and the kernel is small and tested. Details in `docs/kernel.md`. |
| D-018 | `public/index.php` is the only entry point. Apache rewrites every request except `/assets/`, `/uploads/`, `/ui-kit/` to it; old PHP files run only through `config/legacy.php` (temporary bridge, removal criterion in `docs/kernel.md`). | The old rewrite served real files directly, so old pages bypassed the kernel. |
| D-019 | Fail closed: `config/config.php` answers 404 under a web server unless `APP_GATEWAY` is defined (only `public/index.php` defines it). | If `.htaccess` is not honoured the panel must be unavailable, never exposed. |
| D-020 | New routes answer the v2 JSON envelope; old files keep v1 until the part named in the migration table of `docs/kernel.md` replaces them. | One migration plan with an owner part per file avoids two permanent formats. |
| D-021 | One owner per cross-cutting capability (logging 11B; rate limit, events, queue, cache 11F2; CSP 11E1; readiness 11H). Table in `docs/kernel.md`. | Prevents two parts building the same thing. |
```

### EDIT `docs/ai/FILE-MAP.md` — E7

Append the 11B section.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```markdown
| `docs/ai/manifests/11A.json` | new | sha256 of the frozen code files of 11A |
```

**REPLACE IT WITH:**

```markdown
| `docs/ai/manifests/11A.json` | new | sha256 of the frozen code files of 11A |

## Added or changed by Part 11B (HTTP kernel and single entry point)
| Path | Kind | What it is |
|---|---|---|
| `app/**` | new | kernel: autoloader, Config, Container, Http (Request, Response, Router, Route, Pipeline, LegacyGateway, ExceptionHandler), middleware, Validation, Log, Bootstrap/Application |
| `routes/core.php`, `config/kernel.php`, `config/legacy.php` | new | core routes; kernel settings; allowlist of old files |
| `public/index.php`, `public/.htaccess` | changed | single entry point; rewrite block |
| `config/config.php` | changed | fail-closed `APP_GATEWAY` guard |
| `.gitignore` | changed | ignores `storage/logs/` |
| `tools/verify/http_smoke.php`, `tools/verify/dev_router.php` | new | black-box check of a running site; local server that behaves like `.htaccess` |
| `tests/_kernel.php`, `kernel_*_test.php`, `legacy_gateway_test.php`, `gateway_http_test.php`, `logger_test.php` | new | kernel, gateway, HTTP and logger tests |
| `docs/kernel.md` | new | how the kernel works, PSR decision, envelope timeline, homes of capabilities |
| `docs/ai/manifests/11B.json` | new | sha256 of the frozen files of 11B |
```

### EDIT `docs/ai/RELEASE-BLOCKERS.md` — E8

RB-3 is mitigated by code and HTTP tests; it closes with the real-Apache test in 11H.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → stop this step, see rule 3):

```markdown
| RB-3 | Legacy PHP files under `public/` are reachable directly by URL (Apache serves existing files). | 11B (legacy gateway, single entry point) | open |
| RB-4 | No test has run on a real Apache + MySQL stack. | 11H | open |
```

**REPLACE IT WITH:**

```markdown
| RB-3 | Legacy PHP files under `public/` were reachable directly by URL (Apache served existing files). | 11B (single entry point + fail-closed guard + tests) — **mitigated**; closes with the real-Apache run of `tools/verify/http_smoke.php` in 11H | mitigated |
| RB-4 | No test has run on a real Apache + MySQL stack. | 11H | open |
```

### CREATE `docs/kernel.md`

Content (copy exactly; it ends with a newline):

````markdown
# HTTP kernel (Part 11B)

Every web request enters through **one** file: `public/index.php`. The kernel (`app/`, namespace `App\`) answers its own routes; the old procedural pages and APIs under `public/` are reached only through an allowlist (the *legacy gateway*) until later parts replace them.

```
Apache (.htaccess) -> public/index.php -> Router has the path?
    yes -> RequestId -> SecurityHeaders -> route middleware (auth, csrf, permission:x.y) -> handler -> Response
    no  -> LegacyGateway: is the path listed in config/legacy.php and does the file exist?
              yes -> the old file runs (same global scope, SCRIPT_NAME set to the URL)
              no  -> standard 404 (HTML page, or JSON envelope for API/XHR requests)
```
Any exception becomes a standard error response (`ExceptionHandler`); the client never sees file paths or SQL; the details go to `storage/logs/app-YYYY-MM-DD.log` with the request id.

## Single entry point (release blocker RB-3)
1. `public/.htaccess` sends **every** request except `/assets/`, `/uploads/`, `/ui-kit/` to `index.php`. There is no "if the file exists, serve it" exception.
2. Fail closed: `config/config.php` answers `404` and stops when it runs under a web server without `APP_GATEWAY` (only `public/index.php` defines it). So even when `.htaccess` is **not honoured** (mod_rewrite off, `AllowOverride None`) no legacy page can run directly: the panel then shows 404 everywhere instead of being exposed. The log line says what to check.
3. `tests/legacy_gateway_test.php` fails when a PHP file is added to `public/` without being listed in `config/legacy.php` (or listed without existing). `tests/gateway_http_test.php` proves (with PHP's built-in server) that direct access answers 404 and the front controller answers correctly.
4. Black-box check of a real site: `php tools/verify/http_smoke.php http://localhost` (run it on the XAMPP server after every deployment; part 11H makes it a release gate).

**If the panel answers 404 on every page after this part:** open the Apache config of the site and make sure `mod_rewrite` is loaded and the `public/` folder has `AllowOverride All` (XAMPP: `httpd.conf`, `LoadModule rewrite_module`, and the `<Directory>` block of the site). Restart Apache.

### Legacy gateway: temporary bridge
- Allowlist: `config/legacy.php` (public-relative paths; URL must equal `/` + path exactly: no `%`-encoding, no `..`, no extra path info, case-sensitive).
- Removal criterion: a file leaves the list in the part that replaces it with a route (table below). When the list is empty, `App\Core\Http\LegacyGateway`, `config/legacy.php` and the `APP_GATEWAY` branch in `public/index.php` are deleted.

## Where things are
| Piece | File |
|---|---|
| Autoloader for `App\` | `app/autoload.php` (no Composer step needed) |
| Container, Config | `app/Core/Container/`, `app/Core/Config/` |
| Request, Response, Router, Route, Pipeline, HttpException, ExceptionHandler, LegacyGateway | `app/Core/Http/` |
| Middleware | `app/Core/Http/Middleware/` |
| Validator | `app/Core/Validation/` |
| Logger | `app/Core/Log/Logger.php` |
| Application (wiring, `boot()`), `Bootstrapper` | `app/Bootstrap/` |
| Core routes (`/`, `/health`, `/api/v1/health`) | `routes/core.php` |
| Settings | `config/kernel.php` (+ `config/legacy.php`) |
| Front controller | `public/index.php` (+ rewrite block in `public/.htaccess`) |
| Local server like Apache | `php -S 127.0.0.1:8080 -t public tools/verify/dev_router.php` |

## Add a route
Routes live in a file that returns `function (Router $router, Application $app): void` (see `routes/core.php`).
```php
$router->get('/api/v1/things/{id:num}', [ThingController::class, 'show'])->middleware('auth', 'permission:things.view')->name('things.show');
$router->post('/api/v1/things', [ThingController::class, 'create'])->middleware('auth', 'csrf', 'permission:things.edit');
```
Param types: `{id}` one segment, `{id:num}` digits, `{slug:slug}` lowercase-dash, `{path:any}` rest of the path. A handler is `function (Request $request, array $params)` or `[Class::class, 'method']` (the class is built by the container) or an invokable class. It returns a `Response`, an array (becomes the success envelope), a string (HTML) or `null` (204). **The router always wins over the legacy gateway**: when a route exists for a path, that path is never served by an old file.

## PSR decision (why the kernel is not PSR-7/11/15/3)
Decision D-017. The kernel follows the PSR *ideas* (request/response objects, middleware onion, container `get/has`, logger levels) with its own small interfaces. No `psr/*` package is installed, because (a) the owner's XAMPP project has no package budget for a framework-style stack, (b) PSR-7 immutability costs complexity we do not need yet, (c) the kernel is about 1500 lines and fully tested. What keeps the door open: handlers receive our `Request` and return our `Response` through one narrow seam (`Application::dispatch`), so a PSR-7/15 adapter can be added **later in one place** (decision point: part 11G, if a third-party package is ever wanted). Until then nobody may add `psr/*` to `composer.json` without a new decision row.

## API envelope and the migration timeline
New code (routes) answers **v2**: success `{"success":true,"data":...,"meta":{"request_id":"..."}}`, error `{"success":false,"error":{"code","message","fields"?},"meta":{...}}`. Codes: `UNAUTHENTICATED` 401, `FORBIDDEN` 403, `CSRF_INVALID` 403, `NOT_FOUND` 404, `METHOD_NOT_ALLOWED` 405, `VALIDATION_FAILED` 422, `INTERNAL_ERROR` 500.

The old files answer **v1** (`{"success":bool,"message":...,"data":...}`) and keep doing so until they are moved. Rule: from 11B on, a new endpoint is never written in v1. Each legacy file is replaced in exactly one part:

| Legacy file(s) | Replaced in | Why there |
|---|---|---|
| `login.php`, `logout.php`, `api/staff/*` (5), `modules/settings/staff.php`, `modules/settings/profile.php` | 11F1 | identity, staff and roles are rebuilt there |
| `api/media/*` (3), `api/notifications/mark_read.php`, `modules/media/index.php` | 11F2 | the file reference service and notifications are rebuilt there |
| `modules/dashboard/index.php` | 11E3 | the dashboard becomes the widget registry |
| `modules/settings/general.php` | 11F2 | settings become a service |
| the JS client (`public/assets/js/admin.js`) reads v1 and v2 | 11E4 | one helper hides the difference; after the last v1 endpoint is gone a test forbids v1 |

## Homes of cross-cutting capabilities (one owner each)
| Capability | Owner (part → place) | State after 11B |
|---|---|---|
| Application logging | 11B → `App\Core\Log\Logger`, `storage/logs/` | **exists** (JSON lines; used by the exception handler; module loader uses it from 11C) |
| Request observability | 11B request id + `/health`; 11H readiness (`/health/ready` with DB check) and log review | request id and liveness exist |
| Audit trail (who did what) | existing `logActivity()` + `activity_logs`; wrapped as a service in 11F2 | unchanged |
| Rate limiting | 11F2 → `App\Core\RateLimit`; login lockout stays legacy until 11F1/11F2 moves it | not yet |
| Events | 11F2 → `App\Core\Events` | not yet |
| Jobs / queue | 11F2 → `App\Core\Queue` (DB-backed, worker in `bin/console`) | not yet |
| Cache | 11F2 → `App\Core\Cache` (file driver) | not yet |
| Content-Security-Policy | 11E1, designed together with the view engine (nonce per request) | not yet; `SecurityHeaders` sends only the three basic headers |
| Configuration | `config/*.php` read through `App\Core\Config\Config` | exists |

## Middleware aliases
`auth` (JSON 401 for API/XHR, redirect to login for pages), `csrf` (POST/PUT/PATCH/DELETE; token from `X-CSRF-TOKEN`, `X-CSRFTOKEN`, form or JSON field `csrf_token`), `permission:<module>.<action>` (view|edit|delete). They call the existing functions in `includes/auth.php`, `csrf.php`, `permissions.php`, loaded only when used.

## Validation
```php
$data = Validator::make($request->json() ?? [], ['email' => 'required|email|max:190'], ['email' => 'ایمیل'])->validatedOrFail(); // 422 + fields on failure
```
Rules: required nullable string integer numeric boolean array email url date min max between in regex. `unique`/`exists` come with the database part.

## Check it
`php tests/run_all.php` · `php tools/gates/php80_gate.php` · `php tools/verify/http_smoke.php http://localhost` (a running site) · health: open `/health` in the browser.
````

### CREATE `docs/ai/manifests/11B.json`

Content (copy exactly; it ends with a newline):

```json
{
  "files": {
    "app/Bootstrap/Application.php": "83fa44c2256ff486ce6ad982ae52ecc3856ecb1705cbfb255394aca67f4b6618",
    "app/Bootstrap/Bootstrapper.php": "fde27e630361395c6529721aef7ca4b0d55f7a1e55bfbe3b605427fa530f202b",
    "app/Core/Config/Config.php": "eb9653a2df71f6f6847b77de6a4ddf9a76f01b92ba5383bd6c2609663e064b19",
    "app/Core/Container/Container.php": "607a69d09d9da8ecfe852c1c99fca2bc20accc07efd57ac03729cc6024b597a6",
    "app/Core/Container/ContainerException.php": "b009cdefd14a8381ace68981a7b3fdd6067ee2f180050a84cd527fb938253081",
    "app/Core/Http/ExceptionHandler.php": "608cb082c6c2bca5519b3b4498b8419934a255a12192a736d39af89e4eedb9d8",
    "app/Core/Http/HttpException.php": "c32696531cef7a7ce2b8913267cf1ce86d9041eaa379947d51995260aa4e2cf8",
    "app/Core/Http/LegacyGateway.php": "a9fb43631550ff694542b52dcde4f59043b11844737a6abfd707798f99c9b8bd",
    "app/Core/Http/Middleware/AuthMiddleware.php": "cc5d53bdf6dfaf8ac7c93dfa0280fdaf4cfbfc6cd2c33341694613514efe7ab0",
    "app/Core/Http/Middleware/CsrfMiddleware.php": "5769786ac62495b3021338598b92def1d85b3ddfeb153cc8f15875c1a16cb09a",
    "app/Core/Http/Middleware/MiddlewareInterface.php": "77f61b308f0a8077686a6e8b7b8b66699da7df4a9f53fc20e091fb3a9ecf1c36",
    "app/Core/Http/Middleware/PermissionMiddleware.php": "02ec200d7020522cab86d47066853470c3933bd5f077d0a6d98254891bb89e11",
    "app/Core/Http/Middleware/RequestId.php": "6abebe66178fddd561bd3aa554bfc9ea5a6b697e4713f0bad1373be02d1125c4",
    "app/Core/Http/Middleware/SecurityHeaders.php": "ec815029677bde1a6e66db9d77bc2fd646249da10b4dc8d3ee96016144a6752a",
    "app/Core/Http/Pipeline.php": "be5aaac6f5aaffbfe93b769bce84b3e9572b3f0b894d0ea430ec8413200c0f9b",
    "app/Core/Http/Request.php": "63b16dad88d4355ace5c9630931edf3c8757255ec2d42a4f4dacc283bfb98f62",
    "app/Core/Http/Response.php": "a891388ba642e0e89058732078f1ed28b4f2f99bcea2757ed0b44abad0990c58",
    "app/Core/Http/Route.php": "8cfb579ca3cde6516c50d258b4e25188b7d97e048dcb42e92da118e5a70321e5",
    "app/Core/Http/Router.php": "b0c3f018cffc0d9794bf5757155653dfab7e7b83b9317892537ce9e1f1cd9d56",
    "app/Core/Log/Logger.php": "9c053156a8c3ce6345f786d59f058c0115e3d480bf0ce0645f76dd93f105dd44",
    "app/Core/Validation/ValidationResult.php": "2e0a96f0f33ee5e35f3ff5a62a510693a81f0564bd1039c596dd9ea3357b9e78",
    "app/Core/Validation/Validator.php": "a13b349facd919a945f638d9190b7241999e8c3dbbb89a9ca3ab56584f64f78b",
    "app/autoload.php": "051481092634b903b1a4cee5a20676cffde15d9436622361e6e1a720420c4b10",
    "config/config.php": "86c493ec10dd2f2394e10267ab39c035f6cfc2fbca398a1c6bc9b8e2bcc20fde",
    "config/kernel.php": "0e642d9ce9f63f08de3a902688e83e98eaae5d0ccc5251df7f238ed70d3a3cbe",
    "config/legacy.php": "3993b65b7f4c918fe5a4e466b2a27492d9c2b518e6a7d4f8865f03c3af42a330",
    "public/.htaccess": "35ad265be07b50ee140f28006de46523ef25630f6e1532f4da9a2bd5c676f28a",
    "public/index.php": "7aa72ad27c040038d605a404c079eefaf98fbc23fb43e162b788adfd799c93a8",
    "routes/core.php": "4ae2fb85dff139e5319ad53dcfcd490a7338c1dac5a2da618f5d0808104fc788",
    "tests/_kernel.php": "d2b4b20da6992ab0331646b876a8937bc7ca4ef8e50dcdb05208effc0655d06a",
    "tests/gateway_http_test.php": "66d87cc9db50252699c0e6f5d83032edfcfa5816aab85598e3f04f72db4f1a42",
    "tests/kernel_app_test.php": "d5dcefeead3f0d4dbc5da75b63b7adee283c699661fe1789ada290ab4182652c",
    "tests/kernel_container_config_test.php": "49f4af5c9eb5f030c11dae6176c98ce75bb2be005b660a07d84a98545bbc107c",
    "tests/kernel_http_test.php": "d5346be9a9f4bca140a7861f021d579dbe14232b8500a90199916ff818d66c9d",
    "tests/kernel_validator_test.php": "c12594034fa7b6a73ed745a515e23527cadcd46718c0fda8e9b702a82cc4882b",
    "tests/legacy_gateway_test.php": "16f71fa1ab08dc7150216da8a7b041fee9c0e3d746aff48ccdb6e965654df56c",
    "tests/logger_test.php": "58993c91624d4bccfb69179ee15087623dd2cb938dfc4c69f015800883dcce2c",
    "tools/verify/dev_router.php": "bd6ac8ab6a9be7087e17a43518636cc4d20e89f73e00be0ac84aaec8f6d0aa20",
    "tools/verify/http_smoke.php": "7cb5ba2cd654a65407e015a403a304f462eb29a61ec2d77704f9a691c1cb3164"
  },
  "part": "11B"
}
```

### CREATE `changelogs/changelog-part-11b-http-kernel.md`

Content (copy exactly; it ends with a newline):

```markdown
# changelog — Part 11B (HTTP kernel and single entry point)

**Written from:** `master-prompt-11B-http-kernel.md` (revision 2). Author of the code: the prompt author (tested in a Linux sandbox, PHP 8.3, SQLite, no MySQL, no Apache). The executing agent copied files and ran the checks; it did no engineering.

## 1. What changed
- Single entry point: `public/.htaccess` sends every request except `/assets/`, `/uploads/`, `/ui-kit/` to `public/index.php`. The old PHP pages and APIs run only through the allowlist `config/legacy.php` (`App\Core\Http\LegacyGateway`); the router wins over the gateway.
- Fail closed: `config/config.php` answers 404 when a legacy file is reached without the front controller (`APP_GATEWAY`).
- Kernel in `app/`: Config, Container, Request, Response, Router, Pipeline, middleware (`RequestId`, `SecurityHeaders`, `auth`, `csrf`, `permission`), ExceptionHandler, Validator, Application, Logger (`storage/logs/app-YYYY-MM-DD.log`).
- Core routes: `/`, `/index.php`, `/health`, `/api/v1/health`.
- Tools: `tools/verify/http_smoke.php` (checks a running site), `tools/verify/dev_router.php` (local server that behaves like `.htaccess`).
- Tests: kernel (4 files), `legacy_gateway_test.php`, `gateway_http_test.php`, `logger_test.php`. `docs/kernel.md` documents the flow, the PSR decision (D-017), the API envelope migration timeline and the homes of cross-cutting capabilities.
- Hand-off docs: HANDOFF, DECISIONS (D-017…D-021), FILE-MAP, RELEASE-BLOCKERS (RB-3 mitigated), manifest `11B.json`.

## 2. Not verified by the author (be honest)
Apache `mod_rewrite` and `AllowOverride` on the real XAMPP (the rewrite block was never run by Apache; its effect was imitated with PHP's built-in server and `tools/verify/dev_router.php`); the legacy bridges against a real MySQL session; Windows paths; PHP 8.0 runtime. The owner runs `php tools/verify/http_smoke.php http://localhost` on XAMPP (Step 11 of the prompt).

## Backup and baseline
(The executing agent writes the backup path, the PHP version, the baseline test output and the Step 1 output here.)

## Deviations from the prompt
(The executing agent replaces this line with `none`, or with one bullet per deviation: what, why, which file.)

## Smoke test on the owner's server
(The executing agent writes here the full output of `php tools/verify/http_smoke.php <url>` if the site was running, or "not run" and why.)

## Gate outputs (verbatim)
(The executing agent pastes below this line the real output of every command the prompt's Acceptance Checklist asks for, unedited.)
```

### STEP 8 — verification (run all, record real output)

```text
php -l on every PHP file created or edited in Steps 3–6 (list below)
php tools/gates/verify_manifest.php docs/ai/manifests/11B.json
php tests/run_all.php
php tools/gates/php80_gate.php
php database/tools/check_cms_removed.php
```

PHP files to lint:

```text
app/autoload.php
app/Core/Config/Config.php
app/Core/Container/ContainerException.php
app/Core/Container/Container.php
app/Core/Http/HttpException.php
app/Core/Http/Request.php
app/Core/Http/Response.php
app/Core/Http/Route.php
app/Core/Http/Router.php
app/Core/Http/Middleware/MiddlewareInterface.php
app/Core/Http/Pipeline.php
app/Core/Http/Middleware/RequestId.php
app/Core/Http/Middleware/SecurityHeaders.php
app/Core/Http/Middleware/AuthMiddleware.php
app/Core/Http/Middleware/CsrfMiddleware.php
app/Core/Http/Middleware/PermissionMiddleware.php
app/Core/Http/ExceptionHandler.php
app/Core/Http/LegacyGateway.php
app/Core/Log/Logger.php
app/Core/Validation/ValidationResult.php
app/Core/Validation/Validator.php
app/Bootstrap/Bootstrapper.php
app/Bootstrap/Application.php
config/kernel.php
config/legacy.php
routes/core.php
tools/verify/dev_router.php
tools/verify/http_smoke.php
tests/_kernel.php
tests/kernel_container_config_test.php
tests/kernel_http_test.php
tests/kernel_validator_test.php
tests/kernel_app_test.php
tests/logger_test.php
tests/legacy_gateway_test.php
tests/gateway_http_test.php
public/index.php
config/config.php
```

Expected:
- manifest: last line `manifest 11B: 39 ok, 0 bad, 0 info`. A `MISMATCH` is a copy error: re-copy that file from this prompt (never edit the manifest).
- tests: **24 lines**, all `OK` (17 + `gateway_http_test`, `kernel_app_test`, `kernel_container_config_test`, `kernel_http_test`, `kernel_validator_test`, `legacy_gateway_test`, `logger_test`). `gateway_http_test` may print `OK (skipped: …)` when the built-in web server cannot start on this machine: write the reason in the changelog.
- gate: `violations: 0`. `check_cms_removed.php`: `cms leftovers: 0`.

### STEP 9 — smoke test on the real site (the first real test of the rewrite)

The prompt author could not run Apache. If the owner's site is running, run:

```text
php tools/verify/http_smoke.php http://localhost
```

Use the address the owner opens the panel with (ask once if you do not know it; try `http://localhost` and `http://127.0.0.1` if the owner is not there; if nothing answers write "not run: no server answered" and go on). Add `--skip-db` only if the database is stopped, and `--no-verify` only for a self-signed https certificate.

Expected last line: `http smoke: 18 passed, 0 failed, 0 skipped`. Paste the whole output into the changelog (section "Smoke test").

- **Every line fails with 404** → Apache does not honour `public/.htaccess` (this is the fail-closed behaviour working). Do **not** change any file. Write it in Deviations, set the part status to `partial` in Step 10, and tell the owner in Persian to enable `mod_rewrite` and `AllowOverride All` for the `public/` folder (XAMPP: `httpd.conf`, `LoadModule rewrite_module`, the `<Directory>` block of the site; restart Apache). The exact text is in `docs/kernel.md`.
- **A few lines fail** → record each failing line in Deviations; status `partial`; do not fix.

### STEP 10 — finish the changelog and the state file

1. In `changelogs/changelog-part-11b-http-kernel.md` fill the four placeholder sections: "Backup and baseline", "Deviations from the prompt" (`none` or one bullet each), "Smoke test on the owner's server", "Gate outputs (verbatim)" (the real output of Steps 1, 8 and 9).
2. Run `php tools/ai/mark_part.php 11B executed "http kernel and single entry point applied"`. Use `partial` instead of `executed` when Step 9 failed or any step was stopped; use `blocked` if Step 1 failed. It must print `state.json: 11B = <status>`.

## 8. Acceptance Checklist

- [ ] 11A was executed; pre-flight outputs and the backup path are in the changelog.
- [ ] `php tools/gates/verify_manifest.php docs/ai/manifests/11B.json`: `39 ok, 0 bad, 0 info`.
- [ ] `public/index.php` defines `APP_GATEWAY` before requiring `config/config.php`; `config/config.php` has the guard; `public/.htaccess` has the rewrite block and **no** `!-f` / `!-d`.
- [ ] `config/legacy.php` lists exactly 16 files (or the drift test passes for the owner's tree).
- [ ] `php tests/run_all.php`: 24 lines, all `OK`.
- [ ] `php tools/gates/php80_gate.php`: `violations: 0`. `check_cms_removed.php`: `cms leftovers: 0`.
- [ ] Step 9 done: smoke output pasted (18 passed) **or** "not run" with the reason, **or** failures recorded with status `partial`.
- [ ] `docs/kernel.md` exists; DECISIONS has D-017…D-021; RELEASE-BLOCKERS shows RB-3 `mitigated`; FILE-MAP has the 11B section; `state.json` shows `11B`.
- [ ] `composer.json`, `composer.lock`, `database/` untouched; no existing page or API edited.
- [ ] Changelog complete.

## 9. Final message to the owner (Persian)

Write a short Persian report: what was done (by step), the real outputs (manifest line, number of test lines, smoke line or "اجرا نشد"), every deviation, and what you could **not** run. Warn the owner clearly: «از این مرحله به بعد همهٔ درخواست‌ها از `public/index.php` عبور می‌کنند؛ اگر Apache فایل `.htaccess` را اعمال نکند همهٔ صفحه‌ها 404 می‌دهند (این رفتار عمداً امن است)». Then give these **manual checks for the owner**:

1. پنل را باز کن و وارد شو؛ پیشخوان باید مثل قبل باز شود.
2. به «رسانه و عکس‌ها»، «تنظیمات عمومی» و «همکاران» برو؛ همه باید مثل قبل باز شوند.
3. آدرس `/health` را در مرورگر باز کن؛ باید یک JSON با `"success":true` ببینی.
4. آدرس `/includes/auth.php` و `/config/config.php` را باز کن؛ هر دو باید 404 بدهند (هرگز کد PHP نمایش داده نشود).
5. اگر همهٔ صفحه‌ها 404 شدند: `mod_rewrite` و `AllowOverride All` را برای پوشهٔ `public/` فعال کن و Apache را دوباره راه‌اندازی کن (راهنما: `docs/kernel.md`)، سپس دوباره `php tools/verify/http_smoke.php http://localhost` را اجرا کن.
