<?php
// Client script list for the visual page builder (Part 0).
// Part 1 loops over this to emit <script> tags — no hard-coded list.

final class BuilderAssets
{
    /** @return string[] Ordered list of builder JS web paths. */
    public static function clientScripts(): array
    {
        $scripts = [
            '/assets/js/builder/block-registry.js',
            '/assets/js/builder/builder-core.js',
            '/assets/js/builder/builder-tree.js',
        ];

        if (defined('PUBLIC_PATH')) {
            $files = glob(PUBLIC_PATH . '/assets/js/builder/types/*.js');
            if (is_array($files)) {
                sort($files, SORT_STRING);
                foreach ($files as $file) {
                    $scripts[] = '/assets/js/builder/types/' . basename($file);
                }
            }
        }

        return $scripts;
    }
}
