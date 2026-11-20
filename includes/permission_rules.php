<?php
// Pure rules for panel roles & permissions. No side effects. Unit-tested in tests/permission_rules_test.php.

/** Hierarchy: delete ⇒ edit ⇒ view. Anything below a missing level is cleared. */
function normalizePermissionFlags(bool $view, bool $edit, bool $delete): array {
    $v = $view;
    $e = $v && $edit;
    $d = $e && $delete;
    return ['view' => $v, 'edit' => $e, 'delete' => $d];
}

/** $flags is the result of normalizePermissionFlags() (or []). Unknown action ⇒ false. */
function permissionFlagsAllow(array $flags, string $action): bool {
    switch ($action) {
        case 'view':   return !empty($flags['view']);
        case 'edit':   return !empty($flags['edit']);
        case 'delete': return !empty($flags['delete']);
        default:       return false;
    }
}

/** '/x/modules/media/index.php' → '/modules/media/index.php'; anything not under /modules/ → null. */
function normalizeScriptPath(string $scriptName): ?string {
    $p = str_replace('\\', '/', $scriptName);
    if (preg_match('#(/modules/[A-Za-z0-9_\-]+(?:/[A-Za-z0-9_\-]+)*\.php)$#', $p, $m)) {
        return $m[1];
    }
    return null;
}

/** Returns the rule array for a path, or ['deny' => true] when the path is null or not in the map. */
function resolvePageGuard(?string $path, array $map): array {
    if ($path === null || !isset($map[$path]) || !is_array($map[$path])) {
        return ['deny' => true];
    }
    return $map[$path];
}
