<?php
// POST /api/builder/save.php — creates or replaces one canvas plus its whole
// block tree, transactionally. Accepts a JSON request body (not a form post).
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
require_once $builderBase . '/includes/csrf.php';
require_once $builderBase . '/includes/builder/bootstrap.php';

// 1. Method must be POST.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'متد مجاز نیست'], 405);
}

// 2. Login first; the body is not parsed for an unauthenticated client.
requireLogin();

// 3. CSRF: token from the X-CSRF-TOKEN header, falling back to a csrf_token
// key inside the JSON body. When absent from the header, the decision is
// deferred until after step 5 (JSON decode).
$headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_X_CSRFTOKEN'] ?? null;
$csrfFromHeader = is_string($headerToken) && $headerToken !== '';
if ($csrfFromHeader && !csrf_verify($headerToken)) {
    jsonResponse(['success' => false, 'message' => 'توکن نامعتبر'], 403);
}

// 4. Size guard: reject bodies over 2 MB.
$maxBytes = 2 * 1024 * 1024;
$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > $maxBytes) {
    jsonResponse(['success' => false, 'message' => 'حجم دادهی ارسالی بیش از حد مجاز است'], 413);
}
$raw = file_get_contents('php://input', false, null, 0, $maxBytes + 1);
if ($raw === false) {
    $raw = '';
}
if (strlen($raw) > $maxBytes) {
    jsonResponse(['success' => false, 'message' => 'حجم دادهی ارسالی بیش از حد مجاز است'], 413);
}

// 5. Decode. Malformed JSON, or anything that is not a JSON object, is rejected.
$body = json_decode($raw, false);
if (!($body instanceof stdClass)) {
    jsonResponse(['success' => false, 'message' => 'ساختار ارسالی نامعتبر است'], 400);
}

// Deferred CSRF decision from step 3.
if (!$csrfFromHeader) {
    $bodyToken = isset($body->csrf_token) && is_string($body->csrf_token) ? $body->csrf_token : null;
    if (!csrf_verify($bodyToken)) {
        jsonResponse(['success' => false, 'message' => 'توکن نامعتبر'], 403);
    }
}

// Normalise canvas_id: null / absent / 0 => create; positive int => update.
$rawCanvasId = $body->canvas_id ?? null;
$isCreate = ($rawCanvasId === null || $rawCanvasId === 0);
$canvasId = null;
if (!$isCreate) {
    if (is_int($rawCanvasId) && $rawCanvasId > 0) {
        $canvasId = $rawCanvasId;
    } else {
        jsonResponse(['success' => false, 'message' => 'ساختار ارسالی نامعتبر است'], 400);
    }
}

$pdo = getPDO();

// 6. Permissions. pages.edit always; templates.edit additionally when the
// effective buildable_type is 'template' (stored on update, posted on create).
requirePermission('pages', 'edit');

$stored = null;
if (!$isCreate) {
    $stmt = $pdo->prepare('SELECT id, buildable_type, template_id, status FROM builder_canvases WHERE id = ?');
    $stmt->execute([$canvasId]);
    $stored = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$stored) {
        jsonResponse(['success' => false, 'message' => 'بوم یافت نشد'], 404);
    }
}

$postedBuildableType = $body->buildable_type ?? null;
$effectiveBuildableType = $isCreate ? $postedBuildableType : $stored['buildable_type'];
if ($effectiveBuildableType === 'template') {
    requirePermission('templates', 'edit');
}

// 7. Scalar field validation (first failure wins) plus the tree-is-an-array
// check and the template_id exists / immutable / duplicate checks.
$invalid = function () {
    jsonResponse(['success' => false, 'message' => 'ساختار ارسالی نامعتبر است'], 400);
};

// buildable_type.
if ($isCreate) {
    if ($postedBuildableType !== 'page' && $postedBuildableType !== 'template') {
        $invalid();
    }
    $buildableType = $postedBuildableType;
} else {
    $buildableType = $postedBuildableType;
    if ($buildableType !== 'page' && $buildableType !== 'template') {
        // Missing or invalid on update: fall back to the stored (immutable) value.
        $buildableType = $stored['buildable_type'];
    }
}

// template_id. Immutable after creation.
$templateIdPosted = array_key_exists('template_id', (array) $body) ? $body->template_id : null;
if ($templateIdPosted !== null && !is_int($templateIdPosted)) {
    $invalid();
}
if ($isCreate) {
    if ($buildableType === 'page') {
        if ($templateIdPosted !== null) {
            $invalid();
        }
        $templateId = null;
    } else {
        if (!is_int($templateIdPosted) || $templateIdPosted <= 0) {
            $invalid();
        }
        $stmt = $pdo->prepare('SELECT id FROM templates WHERE id = ? LIMIT 1');
        $stmt->execute([$templateIdPosted]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $invalid();
        }
        $stmt = $pdo->prepare('SELECT id FROM builder_canvases WHERE template_id = ? LIMIT 1');
        $stmt->execute([$templateIdPosted]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            jsonResponse(['success' => false, 'message' => 'برای این قالب قبلاً یک بوم ساخته شده است'], 409);
        }
        $templateId = $templateIdPosted;
    }
} else {
    $storedTemplateId = $stored['template_id'] === null ? null : (int) $stored['template_id'];
    // Absent key means "unchanged"; any posted value differing from stored is rejected.
    if (array_key_exists('template_id', (array) $body) && $templateIdPosted !== $storedTemplateId) {
        $invalid();
    }
    $templateId = $storedTemplateId;
}

// title: required, trimmed, 1-255 chars.
if (!isset($body->title) || !is_string($body->title)) {
    $invalid();
}
$title = trim($body->title);
if ($title === '' || mb_strlen($title, 'UTF-8') > 255) {
    $invalid();
}

// status: draft | published; absent => draft on create, unchanged on update.
if (!array_key_exists('status', (array) $body) || $body->status === null) {
    $status = $isCreate ? 'draft' : $stored['status'];
} elseif ($body->status === 'draft' || $body->status === 'published') {
    $status = $body->status;
} else {
    $invalid();
}

// tree must be a JSON array (a PHP array after decoding with assoc=false).
if (!array_key_exists('tree', (array) $body) || !is_array($body->tree)) {
    $invalid();
}
$tree = $body->tree;

// 8. Tree validation.
$treeErrors = BuilderTreeValidator::validate($tree);
if (!empty($treeErrors)) {
    jsonResponse(['success' => false, 'message' => $treeErrors[0], 'errors' => array_values($treeErrors)], 400);
}

// 8b. Optimistic lock: updates must say which version they were loaded from (M13).
$expectedVersion = null;
if (!$isCreate) {
    $rawVersion = $body->expected_version ?? null;
    if (!is_int($rawVersion) || $rawVersion < 1) {
        $invalid();
    }
    $expectedVersion = $rawVersion;
}

// 9. Save.
try {
    $result = BuilderCanvasRepository::save($pdo, $canvasId, [
        'buildable_type' => $buildableType,
        'template_id' => $templateId,
        'title' => $title,
        'status' => $status,
        'tree' => $tree,
    ], (int) $_SESSION['user_id'], $expectedVersion);
} catch (BuilderVersionConflictException $e) {
    jsonResponse(['success' => false, 'message' => 'این صفحه توسط شخص دیگری تغییر کرده است. صفحه را دوباره بارگذاری کنید.'], 409);
} catch (InvalidArgumentException $e) {
    error_log('builder/save.php: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'داده‌ی یکی از بلاک‌ها نامعتبر است'], 400);
} catch (PDOException $e) {
    error_log('builder/save.php: ' . $e->getMessage());
    $sqlState = $e->getCode();
    $driverCode = $e->errorInfo[1] ?? null;
    if (($sqlState === '23000' || (string) $sqlState === '23000') && (int) $driverCode === 1062 && $buildableType === 'template') {
        jsonResponse(['success' => false, 'message' => 'برای این قالب قبلاً یک بوم ساخته شده است'], 409);
    }
    jsonResponse(['success' => false, 'message' => 'خطا در ذخیرهسازی'], 500);
} catch (RuntimeException $e) {
    if ($e->getMessage() === 'Canvas not found') {
        jsonResponse(['success' => false, 'message' => 'بوم یافت نشد'], 404);
    }
    error_log('builder/save.php: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'خطا در ذخیرهسازی'], 500);
} catch (Throwable $e) {
    error_log('builder/save.php: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'خطا در ذخیرهسازی'], 500);
}

// 10. Activity log. A logging failure must never fail the save.
try {
    logActivity('builder_save', 'pages', 'ذخیره بوم ' . $result['canvas']['id']);
} catch (Throwable $e) {
    // Intentionally ignored.
}

// 11. Respond.
jsonResponse([
    'success' => true,
    'message' => 'بوم با موفقیت ذخیره شد',
    'data' => [
        'canvas' => $result['canvas'],
        'block_count' => $result['block_count'],
        'buildable_type_ignored' => $result['buildable_type_ignored'],
    ],
]);
