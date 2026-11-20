<?php
// includes/vendor.php — the ONE list of third-party front-end libraries (Part 10A).
// Local-first: when public/assets/vendor/<lib>/<version>/<file> exists the page uses it; otherwise the pinned CDN URL
// (same version) is used, with its SRI hash where one is known. Nothing here downloads anything:
// `php database/tools/fetch_vendor.php` (run by the owner, with internet) fills the local folder.

if (!function_exists('vendorLibraries')) {

    /** @return array<string, array{version:string, cdn:string, files:array<string, array{sha384:?string, local:?string}>}> */
    function vendorLibraries(): array
    {
        return [
            'bootstrap' => [
                'version' => '5.3.3',
                'cdn' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/',
                'files' => [
                    'css/bootstrap.rtl.min.css' => ['sha384' => 'sha384-dpuaG1suU0eT09tx5plTaGMLBsfDLzUCCUXOY2j/LSvXYuG6Bqs43ALlhIqAJVRb', 'local' => null],
                    'js/bootstrap.bundle.min.js' => ['sha384' => 'sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz', 'local' => null],
                ],
            ],
            'sweetalert2' => [
                'version' => '11.26.25',
                'cdn' => 'https://cdn.jsdelivr.net/npm/sweetalert2@11.26.25/dist/',
                'files' => [
                    'sweetalert2.all.min.js' => ['sha384' => 'sha384-nLoOnA/BDh8A/jxqtckg4DumuCGOBYUnNJLZdQz/zfYNp3wcjGSoWTAzgko06G/2', 'local' => null],
                ],
            ],
            'tabler-icons' => [
                'version' => '3.49.0',
                'cdn' => 'https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.49.0/dist/',
                'files' => [
                    // No SRI published by the project: fetch_vendor.php prints the sha384 of what it downloaded, record it in MANIFEST.md.
                    'tabler-icons.min.css' => ['sha384' => null, 'local' => null],
                ],
            ],
            'vazirmatn' => [
                'version' => '33.0.3',
                'cdn' => 'https://cdn.jsdelivr.net/npm/vazirmatn@33.0.3/fonts/webfonts/',
                'files' => [
                    // Brackets are URL-encoded in the CDN URL; the local copy gets a plain name (05-fonts.css points at it).
                    'Vazirmatn%5Bwght%5D.woff2' => ['sha384' => null, 'local' => 'Vazirmatn-Variable.woff2'],
                ],
            ],
        ];
    }

    /**
     * Resolve one library file.
     * @return array{url:string, integrity:?string, local:bool}
     */
    function vendorAsset(string $lib, string $file): array
    {
        $libs = vendorLibraries();
        if (!isset($libs[$lib]['files'][$file])) {
            throw new InvalidArgumentException("Unknown vendor file $lib/$file");
        }
        $def = $libs[$lib];
        $meta = $def['files'][$file];
        $localName = $meta['local'] ?? $file;
        $rel = '/assets/vendor/' . $lib . '/' . $def['version'] . '/' . $localName;
        $abs = (defined('PUBLIC_PATH') ? PUBLIC_PATH : dirname(__DIR__) . '/public') . $rel;
        if (is_file($abs)) {
            return ['url' => $rel . '?v=' . (int) filemtime($abs), 'integrity' => null, 'local' => true];
        }
        return ['url' => $def['cdn'] . $file, 'integrity' => $meta['sha384'], 'local' => false];
    }

    function vendorLinkTag(string $lib, string $file): string
    {
        $a = vendorAsset($lib, $file);
        $extra = $a['integrity'] ? ' integrity="' . htmlspecialchars($a['integrity'], ENT_QUOTES) . '" crossorigin="anonymous"' : '';
        return '<link href="' . htmlspecialchars($a['url'], ENT_QUOTES) . '" rel="stylesheet"' . $extra . '>' . "\n";
    }

    function vendorScriptTag(string $lib, string $file, bool $defer = false): string
    {
        $a = vendorAsset($lib, $file);
        $extra = $a['integrity'] ? ' integrity="' . htmlspecialchars($a['integrity'], ENT_QUOTES) . '" crossorigin="anonymous"' : '';
        return '<script src="' . htmlspecialchars($a['url'], ENT_QUOTES) . '"' . ($defer ? ' defer' : '') . $extra . '></script>' . "\n";
    }

    /** Preload the font only when the local file exists (a preload that 404s only costs a request and a console warning). */
    function vendorFontPreload(): string
    {
        $a = vendorAsset('vazirmatn', 'Vazirmatn%5Bwght%5D.woff2');
        if (!$a['local']) {
            return '';
        }
        return '<link rel="preload" href="' . htmlspecialchars($a['url'], ENT_QUOTES) . '" as="font" type="font/woff2" crossorigin>' . "\n";
    }
}
