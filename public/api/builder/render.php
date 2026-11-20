<?php
// GET /api/builder/render.php?canvas_id=N — returns the live HTML page for a canvas.
// The read-only twin of save.php: same login + pages.view gate (+ templates.view
// for template canvases). Text html is re-cleaned inside BuilderRenderer.

$renderBase = __DIR__;
while (!file_exists($renderBase . '/config/database.php') && dirname($renderBase) !== $renderBase) {
    $renderBase = dirname($renderBase);
}
require_once $renderBase . '/config/database.php';
require_once $renderBase . '/includes/auth.php';
require_once $renderBase . '/includes/permissions.php';
require_once $renderBase . '/includes/functions.php';
require_once $renderBase . '/includes/builder/bootstrap.php';
require_once $renderBase . '/includes/builder/renderer.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'متد مجاز نیست'], 405);
}

requireLogin();
requirePermission('pages', 'view');

$canvasId = filter_var($_GET['canvas_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($canvasId === false) {
    jsonResponse(['success' => false, 'message' => 'شناسه نامعتبر'], 400);
}

$pdo = getPDO();
$result = BuilderCanvasRepository::load($pdo, (int) $canvasId);
if ($result === null) {
    jsonResponse(['success' => false, 'message' => 'بوم یافت نشد'], 404);
}
if (($result['canvas']['buildable_type'] ?? '') === 'template') {
    requirePermission('templates', 'view');
}

header('Content-Type: text/html; charset=utf-8');
echo BuilderRenderer::renderPage($pdo, $result['canvas'], $result['tree']);
