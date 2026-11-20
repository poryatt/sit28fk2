<?php
// POST /api/builder/delete.php — hard-deletes one page canvas (Part 8).
// Template canvases are refused. The slug becomes free again; blocks and media
// references disappear through the existing ON DELETE CASCADE. Library files
// are never touched. The project root is located by walking up, like load.php.

$builderBase = __DIR__;
while (!file_exists($builderBase . '/config/database.php') && dirname($builderBase) !== $builderBase) {
    $builderBase = dirname($builderBase);
}
require_once $builderBase . '/config/database.php';
require_once $builderBase . '/includes/auth.php';
require_once $builderBase . '/includes/permissions.php';
require_once $builderBase . '/includes/functions.php';
require_once $builderBase . '/includes/csrf.php';
require_once $builderBase . '/includes/builder/pages_list.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'متد مجاز نیست'], 405);
}

requireLogin();
requirePermission('articles', 'delete');

if (!csrf_verify($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    jsonResponse(['success' => false, 'message' => 'توکن نامعتبر'], 403);
}

$id = (int) ($_POST['canvas_id'] ?? 0);
if ($id < 1) {
    jsonResponse(['success' => false, 'message' => 'شناسه نامعتبر'], 400);
}

try {
    $result = BuilderPagesList::deletePage(getPDO(), $id);
    if ($result === 'deleted') {
        logActivity('builder_delete', 'articles', 'حذف صفحه ' . $id);
        jsonResponse(['success' => true, 'message' => 'صفحه حذف شد']);
    }
    if ($result === 'not_a_page') {
        jsonResponse(['success' => false, 'message' => 'بوم قالب از این بخش قابل حذف نیست'], 409);
    }
    jsonResponse(['success' => false, 'message' => 'صفحه یافت نشد'], 404);
} catch (Throwable $e) {
    error_log('builder/delete.php: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'خطا در حذف صفحه'], 500);
}
