<?php
// database/tools/fetch_vendor.php — makes the front-end libraries fully local.   php database/tools/fetch_vendor.php [--force]
// Run by the OWNER on a machine with internet, never automatically and never by the agent. It reads the pinned list in
// includes/vendor.php and downloads each file from jsDelivr into public/assets/vendor/<lib>/<version>/.
//   - existing files are skipped (use --force to re-download)
//   - a known SRI hash (sha384) is verified; a mismatch deletes the file and fails the run
//   - tabler-icons: every url(...) inside the CSS is downloaded next to it (fonts/…), query strings stripped
//   - vazirmatn: the variable font is saved as Vazirmatn-Variable.woff2 (the name 05-fonts.css expects)
//   - for files without a published hash it prints the sha384 so it can be pasted into public/assets/vendor/MANIFEST.md
// Prints one line per file, exits 1 if any download or hash check failed.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

define('PUBLIC_PATH', dirname(__DIR__, 2) . '/public');
require_once dirname(__DIR__, 2) . '/includes/vendor.php';

$force = in_array('--force', $argv, true);
$failures = 0;

function fv_get(string $url): ?string
{
    $ctx = stream_context_create(['http' => ['timeout' => 40, 'follow_location' => 1, 'user_agent' => 'panel-fetch-vendor/1.0'], 'ssl' => ['verify_peer' => true]]);
    $data = @file_get_contents($url, false, $ctx);
    return ($data === false || $data === '') ? null : $data;
}

function fv_sri(string $bytes): string
{
    return 'sha384-' . base64_encode(hash('sha384', $bytes, true));
}

function fv_save(string $abs, string $bytes): void
{
    if (!is_dir(dirname($abs))) { mkdir(dirname($abs), 0775, true); }
    file_put_contents($abs, $bytes);
}

foreach (vendorLibraries() as $lib => $def) {
    $base = PUBLIC_PATH . '/assets/vendor/' . $lib . '/' . $def['version'] . '/';
    foreach ($def['files'] as $file => $meta) {
        $localName = $meta['local'] ?? $file;
        $abs = $base . $localName;
        $url = $def['cdn'] . $file;
        if (is_file($abs) && !$force) { echo "skip   $lib/$localName (exists)\n"; $bytes = file_get_contents($abs); }
        else {
            $bytes = fv_get($url);
            if ($bytes === null) { echo "FAIL   $lib/$localName: cannot download $url\n"; $failures++; continue; }
            if ($meta['sha384'] !== null && fv_sri($bytes) !== $meta['sha384']) {
                echo "FAIL   $lib/$localName: SRI mismatch (expected {$meta['sha384']}, got " . fv_sri($bytes) . ")\n"; $failures++; continue;
            }
            fv_save($abs, $bytes);
            echo "ok     $lib/$localName (" . strlen($bytes) . " bytes)" . ($meta['sha384'] === null ? ' ' . fv_sri($bytes) : '') . "\n";
        }
        // CSS files: fetch every url(...) they reference (webfonts), relative to the CSS file.
        if (substr($localName, -4) === '.css' && preg_match_all('/url\(\s*["\']?([^"\')?#]+)(?:[?#][^"\')]*)?["\']?\s*\)/', $bytes, $m)) {
            foreach (array_unique($m[1]) as $ref) {
                if (preg_match('#^(https?:|data:|/)#', $ref)) { continue; }
                $refUrl = $def['cdn'] . dirname($file) . '/' . $ref;
                $refUrl = ($file === basename($file)) ? $def['cdn'] . $ref : $refUrl;
                $refAbs = $base . (dirname($localName) !== '.' ? dirname($localName) . '/' : '') . $ref;
                $refAbs = preg_replace('#/\./#', '/', $refAbs);
                if (is_file($refAbs) && !$force) { echo "skip   $lib/$ref (exists)\n"; continue; }
                $rb = fv_get($refUrl);
                if ($rb === null) { echo "FAIL   $lib/$ref: cannot download $refUrl\n"; $failures++; continue; }
                fv_save($refAbs, $rb);
                echo "ok     $lib/$ref (" . strlen($rb) . " bytes)\n";
            }
        }
    }
}
echo $failures === 0 ? "\nAll vendor files are local.\n" : "\n$failures failure(s).\n";
exit($failures === 0 ? 0 : 1);
