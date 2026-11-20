<?php
// Loads config/env.local.php (git-ignored) into the process environment.
// Real environment variables (Apache SetEnv, CI, systemd) always win.
function load_local_env(): void {
    static $done = false;
    if ($done) { return; }
    $done = true;
    $file = __DIR__ . '/env.local.php';
    if (!is_file($file)) { return; }
    $vars = require $file;
    if (!is_array($vars)) { return; }
    foreach ($vars as $k => $v) {
        if (!is_string($k) || !preg_match('/^[A-Z][A-Z0-9_]*$/', $k)) { continue; }
        if (getenv($k) === false) {
            putenv($k . '=' . (string)$v);
            $_ENV[$k] = (string)$v;
        }
    }
}
