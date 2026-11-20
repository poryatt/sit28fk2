<?php
// Single entry point for the visual page builder server side (Part 0).
// Safe to include twice. New block types are picked up from types/*.php
// with no edit to any other file.

if (!defined('BUILDER_BOOTSTRAP_LOADED')) {
    define('BUILDER_BOOTSTRAP_LOADED', true);

    $builderBase = __DIR__;

    $builderConfig = $builderBase . '/../../config/config.php';
    if (!defined('BASE_PATH') && file_exists($builderConfig)) {
        require_once $builderConfig;
    }

    $builderDatabase = $builderBase . '/../../config/database.php';
    if (file_exists($builderDatabase) && !function_exists('getPDO')) {
        require_once $builderDatabase;
    }

    require_once $builderBase . '/block_registry.php';
    require_once $builderBase . '/tree_validator.php';
    require_once $builderBase . '/canvas_repository.php';
    require_once $builderBase . '/html_sanitizer.php';
    require_once $builderBase . '/media_refs.php';
    require_once $builderBase . '/builder_assets.php';

    $builderTypeFiles = glob($builderBase . '/types/*.php');
    if (is_array($builderTypeFiles)) {
        sort($builderTypeFiles, SORT_STRING);
        foreach ($builderTypeFiles as $builderTypeFile) {
            require_once $builderTypeFile;
        }
    }
}
