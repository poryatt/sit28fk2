# MASTER PROMPT — Part 11B: HTTP kernel (front controller, router, middleware, validator)

> **Series:** Part 11 — Admin Framework / Starter Kit. **Execution order: 11A → 11B → 11C (strictly in this order).** This file is **11B**. It needs 11A applied.
> **Prepared by:** Claude (prompt author) for the owner, Pouya. **Executor:** the OpenCode agent (you).
> **Target:** PHP 8.0 / MySQL / XAMPP on Windows, Apache DocumentRoot = `public/`.

> **Verification banner (honest).** Author-tested in a Linux sandbox (PHP 8.3, no MySQL, no Apache) on a copy of the real repository with 11A applied first: 206 kernel unit checks (container, config, request/response, router, pipeline, middleware, exception handler, validator, application) pass, mutation-tested (deliberately broken code makes the tests fail); the front controller was also exercised over HTTP with PHP's built-in server (`/health`, `/api/v1/health`, 404, 405, HEAD, existing pages still served). Static PHP-8.0 gate: 0 violations.
> **NOT verified by the author:** Apache `mod_rewrite` and `AllowOverride` on the owner's XAMPP, the legacy bridges (`auth`, `csrf`, `permission`) against a real MySQL-backed session (tests use fakes), Windows paths, PHP 8.0 runtime.

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

Add a small **HTTP kernel** next to the existing panel, without changing any existing page or API:

- `public/index.php` becomes the **front controller**. Apache sends to it only URLs that are not real files/folders (new rewrite block in `public/.htaccess`).
- Kernel pieces (namespace `App\`, folder `app/`): Config, Container (constructor autowiring), Request, Response (standard API envelopes), Router (params, groups, names, 404/405), Pipeline, middleware (`RequestId`, `SecurityHeaders`, `auth`, `csrf`, `permission:<module>.<action>`), ExceptionHandler (no leaks in production), Validator (Persian messages), Application (`boot()` with a `bootstrappers` extension point).
- Core routes: `/` (same redirect as the old index page), `/index.php`, `/health`, `/api/v1/health`.
- Tests and `docs/kernel.md`.

**Changes / completions the prompt author made to the owner's spec (stated openly):**
- Blueprint §7 asks for: bootstrap, router, request/response/middleware, container, validation, exception handling, health route. All are here. **Not in 11B** (later parts): logging/audit, events/outbox, jobs, cache, rate-limit middleware, CSP, route cache.
- The blueprint §6 tree puts the container in `app/Bootstrap/Container.php` and a `ModuleLoader` in `app/Bootstrap/`. Here the container is `app/Core/Container/Container.php` (it is a core service), and the module loader arrives in 11C as `app/Bootstrap/ModuleBootstrapper.php`. Tests stay flat in `tests/` (the repository's own runner `tests/run_all.php` discovers `tests/*_test.php`); the blueprint's `Unit/Integration/...` folders are not created.
- "Path A, close to PSR": the classes follow PSR ideas (immutable-ish request, middleware onion, container `get/has`) but are **not** PSR-7/15/11 compliant — no `psr/*` package is installed (see section 4).
- Standard API envelope (blueprint §11) is used by **new** code only. Existing `public/api/*.php` keep their old response shape until a later part migrates them one by one.
- `/api/v1/health` is the first `/api/v1/` route; versioning of the old APIs is a later part.

**Out of scope:** any edit to existing pages, APIs, `config/config.php`, `composer.json`, `composer.lock`, the database.

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
│   │   │   ├── Pipeline.php   [NEW]
│   │   │   ├── Request.php   [NEW]
│   │   │   ├── Response.php   [NEW]
│   │   │   ├── Route.php   [NEW]
│   │   │   └── Router.php   [NEW]
│   │   └── Validation/
│   │       ├── ValidationResult.php   [NEW]
│   │       └── Validator.php   [NEW]
│   └── autoload.php   [NEW]
├── changelogs/
│   └── changelog-part-11b-http-kernel.md   [NEW]
├── config/
│   └── kernel.php   [NEW]
├── docs/
│   ├── ai/
│   │   ├── manifests/
│   │   │   └── 11B.json   [NEW]
│   │   ├── DECISIONS.md   [CHANGED]
│   │   ├── FILE-MAP.md   [CHANGED]
│   │   └── HANDOFF.md   [CHANGED]
│   └── kernel.md   [NEW]
├── public/
│   ├── .htaccess   [CHANGED]
│   └── index.php   [CHANGED]
├── routes/
│   └── core.php   [NEW]
└── tests/
    ├── _kernel.php   [NEW]
    ├── kernel_app_test.php   [NEW]
    ├── kernel_container_config_test.php   [NEW]
    ├── kernel_http_test.php   [NEW]
    └── kernel_validator_test.php   [NEW]
```

## 3. Database design (PK/FK) — **no schema change**

- **No table, column, index, PK or FK is created, altered or dropped; no migration.** Do not open `database/`.
- Reason: the kernel handles HTTP only. It reads the session through the existing functions (`isLoggedIn`, `csrf_verify`, `hasPermission`), which already use the existing tables (`panel_users`, `permissions`, …) unchanged. The health route deliberately does **not** touch the database, so it stays a pure liveness probe.
- Request ids are generated per request and are not stored.
- Table design with PK/FK reasons (audit log, settings, module state) belongs to the database part (11D).

## 4. Library decision

**No Composer package is added; `composer.json` and `composer.lock` are NOT edited.** The kernel is own code with its own tests.

| Option considered | Decision | Reason |
|---|---|---|
| `nikic/fast-route`, `league/route` (router) | not used | The router needed here is ~150 lines incl. tests; the author could not reach Packagist to add and lock a package, and a hand-edited `composer.json` without a regenerated `composer.lock` makes Composer warn about a stale lock file. |
| `league/container`, `php-di` | not used | Same; the container is ~120 lines. |
| `symfony/http-foundation`, `nyholm/psr7` | not used | Would force PSR-7 object shapes into every file now; path A keeps the kernel light and replaceable behind its own small classes. |
| `respect/validation`, `illuminate/validation` | not used | The validator needs 20 rules with Persian messages; ready-made packages ship English messages and a large dependency tree. |
| Composer PSR-4 autoload entry for `App\` | **not added now** | Instead `app/autoload.php` registers its own PSR-4 loader, so the kernel works without anyone running `composer dump-autoload`. The owner may add the PSR-4 entry later and run Composer himself. |

## 5. "Must NOT do" table

| Do NOT | Why |
|---|---|
| Edit `composer.json`, `composer.lock`, or run any `composer` command | See library decision. |
| Edit `config/config.php`, `config/database.php`, or any existing page/API | They are verified parts of earlier work; the kernel coexists with them. |
| Change the rewrite block (remove `<IfModule>`, drop the `/assets|/uploads` exclusion, change conditions) | `<IfModule>` keeps the site working when `mod_rewrite` is off; the conditions keep real files served as before. |
| "Fix" strict-typing, add `readonly`, enums, `never`, `new` in initializers, first-class callables | The project must run on PHP 8.0; the gate would fail. |
| Add routes, middleware, or health details (PHP version, DB status) | Not in this part; `/health` must not leak versions. |
| Move kernel classes to other folders or rename namespaces | Tests, the manifest and 11C depend on the exact paths. |
| Change `config/kernel.php` values | They are exact. (Later parts edit this file by instruction.) |
| Run the tests with other tools (PHPUnit) or add test frameworks | The repository uses its own plain-PHP runner. |
| Touch the database or run migrations | Nothing in this part needs it. |
| Rewrite Persian strings | They are exact (ی ک only). |

## 6. Prerequisites (check first — STOP if any fails)

Work from the **project root**.

1. 11A must be applied: run `php tools/gates/verify_manifest.php docs/ai/manifests/11A.json` → last line `manifest 11A: 9 ok, 0 bad`; and `docs/ai/state.json` must contain `"11A"` with `"status": "done"`. Otherwise STOP: tell the owner to run 11A first.
2. These must **exist**: `public/index.php`, `public/.htaccess`, `includes/auth.php`, `includes/csrf.php`, `includes/permissions.php`, `config/config.php`, `docs/ai/FILE-MAP.md`, `docs/ai/DECISIONS.md`, `docs/ai/HANDOFF.md`.
3. These must **not exist** (otherwise 11B was already applied → STOP and ask): `app/`, `routes/`, `config/kernel.php`, `docs/kernel.md`.
4. `php -v` → PHP 8.0 or newer (record). `php tests/run_all.php` → all lines `OK`; record the line count (baseline; author's copy 16).

### Backup
Copy the whole project folder to `../panel-backups/pre-11B/` (exclude `vendor/`, `node_modules/`, `.git/`; Windows example: `robocopy . ..\panel-backups\pre-11B /E /XD vendor node_modules .git`). If it exists use `pre-11B-2`. Write the path in the changelog.

## 7. Steps

Do them in order; run each step's check.

### STEP 1 — create the kernel classes

Create the folders as needed. After each file run `php -l <file>` → `No syntax errors detected`.

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
        $this->container->instance(self::class, $this);
        $this->container->instance(Container::class, $this->container);
        $this->container->instance(Config::class, $config);
        $this->container->instance(Router::class, $this->router);
        $this->container->instance(ExceptionHandler::class, $errors);
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
        $app = new self($basePath, new Config($items), new ExceptionHandler($debug, $options['logger'] ?? null));

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

### STEP 2 — routes and settings

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

### CREATE `config/kernel.php`

Content (copy exactly; it ends with a newline):

```php
<?php
// config/kernel.php — Part 11B. Settings of the HTTP kernel. Returns a plain array (read through App\Core\Config\Config).
return [
    'login_url' => '/login.php',
    'home_url'  => '/modules/dashboard/index.php',
    'api_prefix' => '/api/v1',
    // Classes run by Application::boot() after routes/core.php. Each implements App\Bootstrap\Bootstrapper.
    'bootstrappers' => [],
];
```

### STEP 3 — switch the front controller on

Two edits. After them run `php -l public/index.php`.

### EDIT `public/index.php` — E0

REPLACE THE WHOLE FILE (old redirect page becomes the front controller).

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → STOP, see rule 3):

```php
<?php
require_once __DIR__ . '/../includes/auth.php';
if (!isLoggedIn()) { header('Location: /login.php'); exit; }
header('Location: /modules/dashboard/index.php'); exit;
```

**REPLACE IT WITH:**

```php
<?php
// Front controller (Part 11B). Existing PHP pages and APIs are still served directly by Apache (see public/.htaccess);
// only URLs that do not match a real file or folder reach this file.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/autoload.php';

$app = App\Bootstrap\Application::create(dirname(__DIR__));
$app->boot();
$app->run();
```

### EDIT `public/.htaccess` — E1

append the front-controller rewrite block (guarded by IfModule).

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → STOP, see rule 3):

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

# Part 11B: front controller. Real files and folders are served as before; everything else goes to index.php
# (the kernel answers 404/405 and the new routes). If mod_rewrite is not enabled this block is skipped and the site works as before.
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_URI} !^/(assets|uploads)/
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ index.php [L]
</IfModule>
```

### STEP 4 — create the tests

`tests/_kernel.php` is a helper (the runner ignores it because its name does not end in `_test.php`). The other four are tests.

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

### STEP 5 — documentation, manifest, changelog

### CREATE `docs/kernel.md`

Content (copy exactly; it ends with a newline):

````markdown
# HTTP kernel (Part 11B)

The panel keeps its existing PHP pages and APIs. Next to them there is a small kernel (`app/`, namespace `App\`) that answers
every URL which is **not** a real file or folder (Apache rewrites those to `public/index.php`).

```
Request -> RequestId -> SecurityHeaders -> Router (404/405) -> route middleware (auth, csrf, permission:x.y) -> handler -> Response
```
Any exception becomes a standard error response (`ExceptionHandler`); the client never sees file paths or SQL.

## Where things are
| Piece | File |
|---|---|
| Autoloader for `App\` | `app/autoload.php` (no Composer step needed) |
| Container, Config | `app/Core/Container/`, `app/Core/Config/` |
| Request, Response, Router, Route, Pipeline, HttpException, ExceptionHandler | `app/Core/Http/` |
| Middleware | `app/Core/Http/Middleware/` |
| Validator | `app/Core/Validation/` |
| Application (wiring, `boot()`), `Bootstrapper` | `app/Bootstrap/` |
| Core routes (`/`, `/health`, `/api/v1/health`) | `routes/core.php` |
| Settings | `config/kernel.php` |
| Front controller | `public/index.php` (+ rewrite block in `public/.htaccess`) |

## Add a route
Routes live in a file that returns `function (Router $router, Application $app): void` (see `routes/core.php`).
```php
$router->get('/api/v1/things/{id:num}', [ThingController::class, 'show'])->middleware('auth', 'permission:things.view')->name('things.show');
$router->post('/api/v1/things', [ThingController::class, 'create'])->middleware('auth', 'csrf', 'permission:things.edit');
```
Param types: `{id}` one segment, `{id:num}` digits, `{slug:slug}` lowercase-dash, `{path:any}` rest of the path.
A handler is `function (Request $request, array $params)` or `[Class::class, 'method']` (the class is built by the container) or an invokable class.
It returns a `Response`, an array (becomes the success envelope), a string (HTML) or `null` (204).

## API envelope
Success `{"success":true,"data":...,"meta":{"request_id":"..."}}`. Error `{"success":false,"error":{"code","message","fields"?},"meta":{...}}`.
Codes used: `UNAUTHENTICATED` 401, `FORBIDDEN` 403, `CSRF_INVALID` 403, `NOT_FOUND` 404, `METHOD_NOT_ALLOWED` 405, `VALIDATION_FAILED` 422, `INTERNAL_ERROR` 500.
The old `public/api/*.php` files still answer in their old shape (`{"success","message"}`); they are migrated in a later part, not here.

## Middleware aliases
`auth` (JSON 401 for API/XHR, redirect to login for pages), `csrf` (POST/PUT/PATCH/DELETE; token from `X-CSRF-TOKEN`, `X-CSRFTOKEN`, form or JSON field `csrf_token`),
`permission:<module>.<action>` (view|edit|delete). They call the existing functions in `includes/auth.php`, `csrf.php`, `permissions.php`, loaded only when used.
Not in this part: rate limiting, CSP, request logging.

## Validation
```php
$data = Validator::make($request->json() ?? [], ['email' => 'required|email|max:190'], ['email' => 'ایمیل'])->validatedOrFail(); // 422 + fields on failure
```
Rules: required nullable string integer numeric boolean array email url date min max between in regex. `unique`/`exists` come with the database part.

## Check it
`php tests/run_all.php` · `php tools/gates/php80_gate.php` · health: open `/health` in the browser.
````

### CREATE `docs/ai/manifests/11B.json`

Content (copy exactly; it ends with a newline):

```json
{
 "part": "11B",
 "files": {
  "app/Bootstrap/Application.php": "cfb103dc031292c089eb2d3130022f2da7264400e4ab79104d98fca765952c52",
  "app/Bootstrap/Bootstrapper.php": "fde27e630361395c6529721aef7ca4b0d55f7a1e55bfbe3b605427fa530f202b",
  "app/Core/Config/Config.php": "eb9653a2df71f6f6847b77de6a4ddf9a76f01b92ba5383bd6c2609663e064b19",
  "app/Core/Container/Container.php": "607a69d09d9da8ecfe852c1c99fca2bc20accc07efd57ac03729cc6024b597a6",
  "app/Core/Container/ContainerException.php": "b009cdefd14a8381ace68981a7b3fdd6067ee2f180050a84cd527fb938253081",
  "app/Core/Http/ExceptionHandler.php": "608cb082c6c2bca5519b3b4498b8419934a255a12192a736d39af89e4eedb9d8",
  "app/Core/Http/HttpException.php": "c32696531cef7a7ce2b8913267cf1ce86d9041eaa379947d51995260aa4e2cf8",
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
  "app/Core/Http/Router.php": "f7833a4146d80018bbbc46f43a77d737e040bc7c7a56426b3c308d9b9803da5a",
  "app/Core/Validation/ValidationResult.php": "2e0a96f0f33ee5e35f3ff5a62a510693a81f0564bd1039c596dd9ea3357b9e78",
  "app/Core/Validation/Validator.php": "a13b349facd919a945f638d9190b7241999e8c3dbbb89a9ca3ab56584f64f78b",
  "app/autoload.php": "051481092634b903b1a4cee5a20676cffde15d9436622361e6e1a720420c4b10",
  "public/index.php": "8c841d043d84f75014a00b2e82f23cf5259202edb3d8f42d974f1cd93f57a4cc",
  "tests/_kernel.php": "d2b4b20da6992ab0331646b876a8937bc7ca4ef8e50dcdb05208effc0655d06a",
  "tests/kernel_app_test.php": "d5dcefeead3f0d4dbc5da75b63b7adee283c699661fe1789ada290ab4182652c",
  "tests/kernel_container_config_test.php": "49f4af5c9eb5f030c11dae6176c98ce75bb2be005b660a07d84a98545bbc107c",
  "tests/kernel_http_test.php": "d5346be9a9f4bca140a7861f021d579dbe14232b8500a90199916ff818d66c9d",
  "tests/kernel_validator_test.php": "c12594034fa7b6a73ed745a515e23527cadcd46718c0fda8e9b702a82cc4882b"
 }
}
```

### CREATE `changelogs/changelog-part-11b-http-kernel.md`

Content (copy exactly; it ends with a newline):

```markdown
# changelog — Part 11B (HTTP kernel)

**Written from:** `master-prompt-11B-http-kernel.md`. Code by the prompt author, tested in a Linux sandbox (PHP 8.3, no MySQL): 206 unit checks + an HTTP smoke test with PHP's built-in server. The executing agent only copied files and ran checks.

## 1. What was added
- Kernel in `app/` (namespace `App\`, own autoloader `app/autoload.php`): Config, Container (autowiring), Request, Response (API envelopes), Router/Route (groups, names, 404/405), Pipeline, HttpException, ExceptionHandler, middleware (RequestId, SecurityHeaders, Auth, Csrf, Permission), Validator (Persian messages), Application (+ `boot()` and the `Bootstrapper` contract).
- `routes/core.php`: `/`, `/index.php` (same redirect as the old index), `/health`, `/api/v1/health`.
- `config/kernel.php`, `docs/kernel.md`, five test files (`tests/_kernel.php` is a helper, not a test).
- `public/index.php` is now the front controller. `public/.htaccess` got a rewrite block inside `<IfModule mod_rewrite.c>`; real files/folders and `/assets/`, `/uploads/` are served as before.
- No new Composer package; `composer.json` / `composer.lock` untouched. No database change.

## 2. Behaviour that changes for users
Unknown URLs now get the kernel's 404 page (Persian, with a request id) or a JSON 404 under `/api/`, instead of Apache's default page. Everything else is as before.

## 3. Not verified by the author (be honest)
Apache `mod_rewrite` / `AllowOverride` on the owner's XAMPP, Windows paths, the legacy bridges against a real MySQL session (tests use fakes), PHP 8.0 runtime.

## Deviations from the prompt
(The executing agent replaces this line with `none`, or with one bullet per deviation: what, why, which file.)

## Gate outputs (verbatim)
(The executing agent pastes below this line the real output of every command the prompt's Acceptance Checklist asks for, unedited.)
```

### STEP 6 — hand-off docs (append rows)

Three edits to the files created by 11A. Each FIND is the last row/line of the earlier text, so your addition lands at the end of the table / before the 'Not done yet' bullet.

### EDIT `docs/ai/FILE-MAP.md` — D1

append this part's rows.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → STOP, see rule 3):

```markdown
| `tests/browser/__pycache__/`, `tools/ui/__pycache__/` | deleted | bytecode noise |
```

**REPLACE IT WITH:**

```markdown
| `tests/browser/__pycache__/`, `tools/ui/__pycache__/` | deleted | bytecode noise |

## Part 11B — HTTP kernel
| File | Kind | Purpose |
|---|---|---|
| `app/autoload.php` | new | PSR-4 autoloader for `App\` (no Composer step) |
| `app/Core/Config/Config.php` | new | dot-access config bag |
| `app/Core/Container/Container.php`, `ContainerException.php` | new | service container with constructor autowiring |
| `app/Core/Http/Request.php`, `Response.php` | new | request/response objects, API envelopes |
| `app/Core/Http/Router.php`, `Route.php` | new | route table, groups, names, 404/405 |
| `app/Core/Http/Pipeline.php` | new | middleware onion |
| `app/Core/Http/HttpException.php`, `ExceptionHandler.php` | new | error -> standard response |
| `app/Core/Http/Middleware/*.php` (7 files) | new | MiddlewareInterface, RequestId, SecurityHeaders, Auth, Csrf, Permission |
| `app/Core/Validation/Validator.php`, `ValidationResult.php` | new | rule-string validator (Persian messages) |
| `app/Bootstrap/Application.php`, `Bootstrapper.php` | new | wiring, `boot()`, bootstrapper contract |
| `routes/core.php` | new | `/`, `/index.php`, `/health`, `/api/v1/health` |
| `config/kernel.php` | new | kernel settings (mutable: later parts edit it) |
| `public/index.php` | changed | old redirect replaced by the front controller |
| `public/.htaccess` | changed | rewrite block (guarded by IfModule) appended |
| `tests/_kernel.php`, `kernel_container_config_test.php`, `kernel_http_test.php`, `kernel_validator_test.php`, `kernel_app_test.php` | new | 206 checks |
| `docs/kernel.md` | new | how the kernel works / how to add a route |
| `docs/ai/manifests/11B.json` | new | sha256 of the frozen files of 11B |
| `changelogs/changelog-part-11b-http-kernel.md` | new | what the agent did and saw |
```

### EDIT `docs/ai/DECISIONS.md` — D2

append this part's decisions.

**FIND** (whole lines; the final line break belongs to the text; it must occur **exactly once** in the file — if it occurs 0 or 2+ times → STOP, see rule 3):

```markdown
| D-007 | 11A | Two stray `__pycache__` folders are deleted and `.gitignore` ignores them. The agent runs no git commands. | They are build noise; the local copy may not be a git repo. |
```

**REPLACE IT WITH:**

```markdown
| D-007 | 11A | Two stray `__pycache__` folders are deleted and `.gitignore` ignores them. The agent runs no git commands. | They are build noise; the local copy may not be a git repo. |
| D-008 | 11B | Kernel code lives in `app/` (namespace `App\`) with its own autoloader `app/autoload.php`. `composer.json` and `composer.lock` are NOT edited. | The kernel must work even if nobody runs `composer dump-autoload`; a hand-edited composer.json without a regenerated lock gives a lock-mismatch warning. The owner may add a PSR-4 entry later and run Composer himself. |
| D-009 | 11B | No new Composer packages: container, router, validator, etc. are own small classes with unit tests. | Path A (light kernel). The author's sandbox could not reach Packagist, so no package could be added or verified. |
| D-010 | 11B | Front controller = `public/index.php` + a rewrite block in `public/.htaccess` wrapped in `<IfModule mod_rewrite.c>`; `/assets/` and `/uploads/` are excluded. | Real files keep working exactly as before; if mod_rewrite is off the site still works (only the new routes 404). |
| D-011 | 11B | New code answers with the blueprint envelope (`success/data/meta.request_id`, `success:false/error{code,message,fields}`). Old `public/api/*.php` keep their old shape until a later part migrates them. | One step at a time; changing every API now would break the JS that reads them. |
| D-012 | 11B | Middleware `auth`, `csrf`, `permission:<module>.<action>` call the existing procedural functions lazily (`includes/auth.php`, `csrf.php`, `permissions.php`). | One source of truth for sessions, CSRF and permissions; nothing is loaded for routes that do not use them. |
| D-013 | 11B | Extension point: `config/kernel.php` key `bootstrappers` (classes implementing `Bootstrapper`) runs in `Application::boot()`; `boot()` is idempotent. | Later parts plug in by editing config, without editing frozen kernel files. |
| D-014 | 11B | Validator: Persian messages, stops at first failing rule per field, no `unique`/`exists`/wildcards. | Keeps the first version small; DB rules arrive with the database part. |
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
- After 11B: HTTP kernel in `app/` (see `docs/kernel.md`): front controller `public/index.php` (+ rewrite in `public/.htaccess`), `/health`, `/api/v1/health`, standard JSON errors, Validator, middleware `auth|csrf|permission:x.y`. Old pages and APIs are served as before.
- Not done yet (outline only, no prompt written): 11D database core v2 + setup wizard + no default credentials; 11E UI foundation v2
  (CSP, inline-JS removal); 11F service modules; 11G CMS as a module; 11H HRM pilot; 11I release/CI/docs.
```

### STEP 7 — verification (run all, record real output)

```text
php tools/gates/verify_manifest.php docs/ai/manifests/11B.json
php tools/gates/verify_manifest.php docs/ai/manifests/11A.json
php tests/run_all.php
php tools/gates/php80_gate.php
php -l public/index.php
```

Expected:
- 11B manifest: last line `manifest 11B: 27 ok, 0 bad`, no `MISSING`/`MISMATCH`. A `MISMATCH` means you mis-copied that file: re-copy it from this prompt (never edit the manifest) and run again.
- 11A manifest still `9 ok, 0 bad` (you must not have disturbed it).
- tests: every line `OK`, no `FAIL`; line count = baseline + 4. The new lines: `kernel_app_test`, `kernel_container_config_test`, `kernel_http_test`, `kernel_validator_test`.
- gate: `violations: 0`. A violation in an existing owner file → do not fix, record it under Deviations.
- `php -l public/index.php` → `No syntax errors detected`.

### STEP 8 — finish the changelog and the state file

1. In `changelogs/changelog-part-11b-http-kernel.md`: replace the line under "Deviations from the prompt" with `none` or one bullet per deviation; paste the verbatim outputs of Step 7 (and the prerequisite baseline output and backup path) under "Gate outputs (verbatim)".
2. Run `php tools/ai/mark_part.php 11B done "http kernel applied"` (`partial`/`blocked` if a step was stopped). It must print `state.json: 11B = done`.

## 8. Acceptance Checklist

- [ ] 11A verified at the start (`9 ok, 0 bad`); baseline tests green; backup path recorded.
- [ ] 21 kernel files + `routes/core.php` + `config/kernel.php` created; `public/index.php` and `public/.htaccess` edited exactly.
- [ ] 5 test files created; `php tests/run_all.php` all `OK`, baseline + 4 lines.
- [ ] `docs/ai/manifests/11B.json` verifies `27 ok, 0 bad`.
- [ ] `php tools/gates/php80_gate.php` → `violations: 0` (or recorded as information).
- [ ] `docs/kernel.md` exists; `FILE-MAP.md`, `DECISIONS.md`, `HANDOFF.md` got the 11B rows (D-008 … D-014).
- [ ] `docs/ai/state.json` shows `11B` = `done`.
- [ ] Changelog complete (Deviations, outputs, backup path).
- [ ] `composer.json`, `composer.lock`, `config/config.php`, `database/` untouched.

## 9. Final message to the owner (Persian)

Write a short Persian report: steps done, real outputs, deviations, and these **manual checks for the owner** (you cannot do them): 
1. Apache must have `mod_rewrite` enabled and `AllowOverride All` for the project folder (XAMPP: `httpd.conf`: `LoadModule rewrite_module modules/mod_rewrite.so` not commented; the `<Directory>` of the DocumentRoot with `AllowOverride All`; restart Apache after changing). Without it nothing breaks, but the new routes answer with Apache's 404.
2. Open `http://localhost/health` (or your local host name) → JSON with `"success":true`.
3. Open `/no-such-page` → a Persian 404 page with a tracking code.
4. Open `/` → login page (or dashboard if logged in). Log in and open the dashboard and one more page → everything works as before.
