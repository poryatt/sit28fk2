<?php
// Resolves the builder entry query string into exactly one descriptor (Part 8).
// Pure: reads only its arguments (never $_GET directly), touches no DB.
// Used by modules/pages/index.php to replace the previous branch ladder.

function builderResolveEntry(array $get, ?int $activeTemplateId): array
{
    $hasCanvas = isset($get['canvas_id']) && $get['canvas_id'] !== '';
    $hasBuildable = isset($get['buildable_type']) && $get['buildable_type'] !== '';
    $hasTemplate = isset($get['template_id']) && $get['template_id'] !== '';
    $hasNew = isset($get['new']) && $get['new'] !== '';
    $hasAny = $hasCanvas || $hasBuildable || $hasTemplate || $hasNew;

    // 1. Explicit canvas id wins; it must be an integer >= 1.
    if ($hasCanvas) {
        $canvasId = filter_var($get['canvas_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($canvasId === false) {
            return ['kind' => 'invalid'];
        }
        return ['kind' => 'canvas', 'canvas_id' => (int) $canvasId];
    }

    // 2. Brand-new empty page.
    if ($hasBuildable && $get['buildable_type'] === 'page' && $hasNew && (string) $get['new'] === '1') {
        return ['kind' => 'page_new'];
    }

    // 3. Template canvas by template id.
    if ($hasBuildable && $get['buildable_type'] === 'template' && $hasTemplate) {
        $templateId = filter_var($get['template_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($templateId !== false) {
            return ['kind' => 'template', 'template_id' => (int) $templateId, 'new' => ($hasNew && (string) $get['new'] === '1')];
        }
    }

    // 4. Any other parameter combination is invalid.
    if ($hasAny) {
        return ['kind' => 'invalid'];
    }

    // 5. No parameters: the active template, or nothing at all.
    if ($activeTemplateId !== null && $activeTemplateId >= 1) {
        return ['kind' => 'active_template', 'template_id' => (int) $activeTemplateId];
    }
    return ['kind' => 'none'];
}
