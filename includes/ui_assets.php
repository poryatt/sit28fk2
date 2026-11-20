<?php
// includes/ui_assets.php — emits the design-system stylesheets in name order. A new NN-name.css dropped into
// public/assets/css/app/ is loaded by itself. ?v=<filemtime> busts the cache on every edit.

if (!function_exists('uiStylesheets')) {
    function uiStylesheets(): string
    {
        $dir = (defined('PUBLIC_PATH') ? PUBLIC_PATH : dirname(__DIR__) . '/public') . '/assets/css/app';
        $files = glob($dir . '/*.css') ?: [];
        sort($files, SORT_STRING);
        $out = '';
        foreach ($files as $file) {
            $out .= '<link href="/assets/css/app/' . htmlspecialchars(basename($file), ENT_QUOTES) . '?v=' . (int) filemtime($file) . '" rel="stylesheet">' . "\n";
        }
        return $out;
    }

    function uiScriptTag(string $path): string
    {
        $abs = (defined('PUBLIC_PATH') ? PUBLIC_PATH : dirname(__DIR__) . '/public') . $path;
        $v = is_file($abs) ? (int) filemtime($abs) : 0;
        return '<script src="' . htmlspecialchars($path, ENT_QUOTES) . '?v=' . $v . '"></script>' . "\n";
    }
}
