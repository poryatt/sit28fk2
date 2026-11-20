<?php
// GET /api/builder/media.php — read-only media library access for the editor's
// picker (Part 8). Gated by pages.view (NOT media.edit), so staff who may edit
// pages but not the media module can still see images in the editor.
// Only purpose='library' rows are ever returned (never avatars or site assets).

$builderBase = __DIR__;
while (!file_exists($builderBase . '/config/database.php') && dirname($builderBase) !== $builderBase) {
    $builderBase = dirname($builderBase);
}
require_once $builderBase . '/config/database.php';
require_once $builderBase . '/includes/auth.php';
require_once $builderBase . '/includes/permissions.php';
require_once $builderBase . '/includes/functions.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'متد مجاز نیست'], 405);
}

requireLogin();
requirePermission('pages', 'view');
header('Cache-Control: no-store');

$pdo = getPDO();
$fields = 'id, file_name, file_path, file_type, mime_type, width, height';

try {
    // Form 1: one file by id.
    if (isset($_GET['id']) && $_GET['id'] !== '') {
        $id = filter_var($_GET['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            jsonResponse(['success' => false, 'message' => 'شناسه نامعتبر'], 400);
        }
        $stmt = $pdo->prepare("SELECT $fields FROM media_library WHERE id = ? AND purpose = 'library' LIMIT 1");
        $stmt->execute([(int) $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            jsonResponse(['success' => false, 'message' => 'یافت نشد'], 404);
        }
        $row['id'] = (int) $row['id'];
        $row['width'] = $row['width'] !== null ? (int) $row['width'] : null;
        $row['height'] = $row['height'] !== null ? (int) $row['height'] : null;
        jsonResponse(['success' => true, 'data' => $row]);
    }

    // Form 2: a filtered, paged list.
    $type = $_GET['type'] ?? 'all';
    if (!in_array($type, ['all', 'image', 'video'], true)) {
        $type = 'all';
    }
    $q = (isset($_GET['q']) && is_string($_GET['q'])) ? trim($_GET['q']) : '';
    if (mb_strlen($q, 'UTF-8') > 100) {
        $q = mb_substr($q, 0, 100, 'UTF-8');
    }
    $page = 1;
    if (isset($_GET['page']) && is_numeric($_GET['page'])) {
        $n = (int) $_GET['page'];
        if ($n >= 1) {
            $page = $n;
        }
    }
    $per = 24;

    $where = ["purpose = 'library'"];
    $params = [];
    if ($type === 'image') {
        $where[] = "file_type = 'image'";
    } elseif ($type === 'video') {
        $where[] = "file_type = 'video'";
    }
    if ($q !== '') {
        $like = '%' . str_replace(['|', '%', '_'], ['||', '|%', '|_'], $q) . '%';
        $where[] = "file_name LIKE ? ESCAPE '|'";
        $params[] = $like;
    }
    $whereSql = 'WHERE ' . implode(' AND ', $where);

    $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM media_library $whereSql");
    $totalStmt->execute($params);
    $total = (int) $totalStmt->fetchColumn();
    $pages = max(1, (int) ceil($total / $per));
    $page = min(max(1, $page), $pages);
    $offset = ($page - 1) * $per;

    $stmt = $pdo->prepare("SELECT $fields FROM media_library $whereSql ORDER BY created_at DESC, id DESC LIMIT $per OFFSET $offset");
    $stmt->execute($params);
    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $r['id'] = (int) $r['id'];
        $r['width'] = $r['width'] !== null ? (int) $r['width'] : null;
        $r['height'] = $r['height'] !== null ? (int) $r['height'] : null;
        $items[] = $r;
    }

    jsonResponse(['success' => true, 'data' => ['items' => $items, 'page' => $page, 'pages' => $pages, 'total' => $total]]);
} catch (Throwable $e) {
    error_log('builder/media.php: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'خطا در دریافت فهرست رسانه'], 500);
}
