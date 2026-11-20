<?php
// Image processing capability probe + fallback policy (Part 9).
// Pure: no DB, no filesystem writes. Requires the pure file_rules.php helpers.
require_once __DIR__ . '/file_rules.php';

/** 'gd' when the three GD functions the store uses all exist, else 'none'. */
function imageProcessingMode(): string {
    if (function_exists('imagecreatefromstring') && function_exists('imagejpeg') && function_exists('imagepng')) {
        return 'gd';
    }
    return 'none';
}

/**
 * The fallback policy in force for this request: 'passthrough' | 'refuse'.
 * IMAGE_PROCESSING_FALLBACK overrides; otherwise APP_ENV=production refuses, local (or unset) passes through.
 */
function imageFallbackPolicyCurrent(): string {
    $env = getenv('IMAGE_PROCESSING_FALLBACK');
    $appEnv = getenv('APP_ENV');
    return imageFallbackPolicy(
        ($env === false || $env === '') ? null : (string)$env,
        ($appEnv === false || $appEnv === '') ? null : (string)$appEnv
    );
}