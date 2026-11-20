<?php
// GET /api/builder/load.php — returns one canvas plus its block tree as JSON.
// This file is served from two depths (api/ and public/api/); the two copies
// are byte-identical. The project root is located by walking up until
// config/database.php is found, so no hard-coded depth is needed.

$builderBase = __DIR__;
while (!file_exists($builderBase . '/config/database.php') && dirname($builderBase) !== $builderBase) {
    $builderBase = dirname($builderBase);
}
require_once $builderBase . '/config/database.php';
require_once $builderBase . '/includes/auth.php';
require_once $builderBase . '/includes/permissions.php';
require_once $builderBase . '/includes/functions.php';
require_once $builderBase . '/includes/builder/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'متد مجاز نیست'], 405);
}

requireLogin();
requirePermission('pages', 'view');

$pdo = getPDO();

/** Builds an unsaved (id: null) canvas shape per §8.2. */
$blankCanvas = function (string $buildableType, ?int $templateId): array {
    return [
        'id' => null,
        'buildable_type' => $buildableType,
        'template_id' => $templateId,
        'title' => '',
        'slug' => null,
        'status' => 'draft',
        'version' => null,
        'published_at' => null,
        'updated_at' => null,
        'updated_at_jalali' => '-',
    ];
};

try {
    // Form 1: load by canvas id.
    if (isset($_GET['canvas_id']) && $_GET['canvas_id'] !== '') {
        $canvasId = filter_var($_GET['canvas_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($canvasId === false) {
            jsonResponse(['success' => false, 'message' => 'شناسه نامعتبر'], 400);
        }
        $result = BuilderCanvasRepository::load($pdo, (int) $canvasId);
        if ($result === null) {
            jsonResponse(['success' => false, 'message' => 'بوم یافت نشد'], 404);
        }
        if (($result['canvas']['buildable_type'] ?? '') === 'template') {
            requirePermission('templates', 'view');
        }
        jsonResponse(['success' => true, 'data' => $result]);
    }

    $buildableType = $_GET['buildable_type'] ?? null;
    $isNew = isset($_GET['new']) && (string) $_GET['new'] === '1';
    $templateIdRaw = $_GET['template_id'] ?? null;

    // Form 2: blank unsaved canvas.
    if ($isNew) {
        if ($buildableType !== 'page' && $buildableType !== 'template') {
            jsonResponse(['success' => false, 'message' => 'شناسه نامعتبر'], 400);
        }
        $templateId = null;
        if ($templateIdRaw !== null && $templateIdRaw !== '') {
            $templateId = filter_var($templateIdRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($templateId === false) {
                jsonResponse(['success' => false, 'message' => 'شناسه نامعتبر'], 400);
            }
            $templateId = (int) $templateId;
        }
        if ($buildableType === 'page') {
            $templateId = null;
        }
        if ($buildableType === 'template') {
            requirePermission('templates', 'view');
        }
        jsonResponse([
            'success' => true,
            'data' => [
                'canvas' => $blankCanvas($buildableType, $templateId),
                'tree' => BuilderCanvasRepository::defaultTree(),
            ],
        ]);
    }

    // Form 3: load the canvas attached to a template (or a blank one).
    if ($buildableType === 'template' && $templateIdRaw !== null && $templateIdRaw !== '') {
        requirePermission('templates', 'view');
        $templateId = filter_var($templateIdRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($templateId === false) {
            jsonResponse(['success' => false, 'message' => 'شناسه نامعتبر'], 400);
        }
        $templateId = (int) $templateId;
        $stmt = $pdo->prepare('SELECT id FROM templates WHERE id = ? LIMIT 1');
        $stmt->execute([$templateId]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            jsonResponse(['success' => false, 'message' => 'بوم یافت نشد'], 404);
        }
        $result = BuilderCanvasRepository::findByTemplate($pdo, $templateId);
        if ($result === null) {
            $result = [
                'canvas' => $blankCanvas('template', $templateId),
                'tree' => BuilderCanvasRepository::defaultTree(),
            ];
        }
        jsonResponse(['success' => true, 'data' => $result]);
    }

    jsonResponse(['success' => false, 'message' => 'شناسه نامعتبر'], 400);
} catch (Throwable $e) {
    error_log('builder/load.php: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'خطا در بارگذاری'], 500);
}
