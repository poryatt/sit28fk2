<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/permission_rules.php';

/** One query per user per request (static cache keyed by user id). NULL from the LEFT JOIN ⇒ false. */
function loadPermissionMap(int $userId): array {
    static $cache = [];
    if (isset($cache[$userId])) return $cache[$userId];
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT p.module_key, pup.can_view, pup.can_edit, pup.can_delete
        FROM permissions p
        LEFT JOIN panel_user_permissions pup ON pup.permission_id = p.id AND pup.panel_user_id = ?
        ORDER BY p.id");
    $stmt->execute([$userId]);
    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $map[$row['module_key']] = normalizePermissionFlags((bool)($row['can_view'] ?? false), (bool)($row['can_edit'] ?? false), (bool)($row['can_delete'] ?? false));
    }
    $cache[$userId] = $map;
    return $map;
}

function hasPermission(string $moduleKey, string $action = 'view'): bool {
    $user = currentUser();
    if (!$user) return false;
    if (($user['status'] ?? '') !== 'active') return false;
    if (($user['role'] ?? '') === 'owner') return true;
    $map = loadPermissionMap((int)$user['id']);
    return permissionFlagsAllow($map[$moduleKey] ?? [], $action);
}

function denyAccess(): void {
    if (isApiRequest()) {
        jsonResponse(['success' => false, 'message' => 'دسترسی غیر مجاز'], 403);
    }
    // Output may already have started (a page that checked a finer permission after header.php): then print a card, not a document.
    $started = headers_sent() || (ob_get_level() > 0 && ob_get_length() > 0);
    if (!headers_sent()) { http_response_code(403); }
    require_once __DIR__ . '/forbidden_page.php';
    echo forbiddenPageHtml($started);
    exit;
}

function requirePermission(string $moduleKey, string $action = 'view'): void {
    if (!hasPermission($moduleKey, $action)) { denyAccess(); }
}

function requireOwner(): void {
    if (!isOwner()) { denyAccess(); }
}

function enforcePageGuard(): void {
    static $map = null;
    if ($map === null) { $map = require __DIR__ . '/access_map.php'; }
    $rule = resolvePageGuard(normalizeScriptPath($_SERVER['SCRIPT_NAME'] ?? ''), $map);
    if (!empty($rule['deny']))       { denyAccess(); }
    if (!empty($rule['login_only'])) { return; }
    if (!empty($rule['owner_only'])) { requireOwner(); return; }
    requirePermission((string)($rule['module'] ?? ''), (string)($rule['action'] ?? 'view'));
}

function getUserPermissions(int $userId): array {
    $pdo = getPDO();
    $stmt = $pdo->prepare("
        SELECT p.module_key, pup.can_view, pup.can_edit, pup.can_delete
        FROM permissions p
        LEFT JOIN panel_user_permissions pup ON pup.permission_id = p.id AND pup.panel_user_id = ?
        ORDER BY p.id
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}
