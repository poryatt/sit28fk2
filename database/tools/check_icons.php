<?php
// Part 10C gate for the icon swap. CLI only.  php database/tools/check_icons.php
//   1) No Bootstrap Icons usage may remain in PHP/JS (class prefix bi / bi-*, or the library name) nor in CSS (a `.bi` selector). -> failure = exit 1
//   2) Every Tabler icon name used (ti-<name>) must exist in the vendored Tabler CSS.                      -> failure = exit 1
//      When that CSS file is not on disk the names are NOT verified: the tool says so and does not fail for it.
// Skipped on purpose: vendor, uploads, node_modules, changelogs, docs, migrations, storage, .git and this file.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = realpath(__DIR__ . '/../..');
$skipDirs = ['vendor', 'uploads', 'node_modules', 'changelogs', 'docs', 'migrations', '.git', 'storage', 'tests'];
$biPatterns = [
    'bi- class'    => '/(?<![A-Za-z0-9_-])bi-[a-z0-9][a-z0-9-]*/',
    '"bi " class'  => '/class\s*=\s*\\\\?["\'][^"\']*(?<![A-Za-z0-9_-])bi(?![A-Za-z0-9_-])/',
    "'bi ' concat" => '/[\'"]bi [\'"]\s*\+|[\'"]bi [a-z-]*[\'"]/',
    'string ending in bi + concat' => '/(?<![A-Za-z0-9_-])bi [\'"]\s*\+/',
    'library name' => '/bootstrap-icons/i',
];
$cssBiPattern = ['css .bi selector' => '/\.bi(?![A-Za-z0-9_-])/'];
$tiRe = '/(?<![A-Za-z0-9_-])ti-([a-z0-9]+(?:-[a-z0-9]+)*)/';

$biHits = 0;
$used = [];   // name => first "file:line"
$it = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    function (SplFileInfo $f) use ($skipDirs) {
        return !($f->isDir() && in_array($f->getFilename(), $skipDirs, true));
    }
));
foreach ($it as $file) {
    /** @var SplFileInfo $file */
    if (!$file->isFile() || !in_array(strtolower($file->getExtension()), ['php', 'js', 'css'], true)) { continue; }
    $isCss = strtolower($file->getExtension()) === 'css';
    if ($file->getRealPath() === __FILE__) { continue; }
    $rel = str_replace('\\', '/', substr($file->getRealPath(), strlen($root) + 1));
    $lines = file($file->getRealPath(), FILE_IGNORE_NEW_LINES) ?: [];
    foreach ($lines as $i => $line) {
        foreach (($isCss ? $cssBiPattern : $biPatterns) as $label => $re) {
            if (preg_match($re, $line, $mm)) {
                $biHits++;
                echo $rel . ':' . ($i + 1) . '  [' . $label . ']  ' . trim($mm[0]) . "\n";
            }
        }
        if (preg_match_all($tiRe, $line, $all)) {
            foreach ($all[1] as $n) { if (!isset($used[$n])) { $used[$n] = $rel . ':' . ($i + 1); } }
        }
    }
}

echo $biHits === 0 ? "bootstrap-icons leftovers: 0\n" : "bootstrap-icons leftovers: $biHits\n";

ksort($used);
$cssFile = null;
foreach (glob($root . '/public/assets/vendor/tabler-icons/*/tabler-icons*.css') ?: [] as $c) { $cssFile = $c; }
$missing = 0;
if ($cssFile === null) {
    echo 'tabler icon names used: ' . count($used) . "\n";
    echo "vendor missing: icon names NOT verified (restore public/assets/vendor/tabler-icons/ with php database/tools/fetch_vendor.php, then run this tool again)\n";
} else {
    $css = (string)file_get_contents($cssFile);
    preg_match_all('/\.ti-([a-z0-9]+(?:-[a-z0-9]+)*)\s*:\s*before/', $css, $defined);
    $have = array_flip($defined[1]);
    foreach ($used as $n => $where) {
        if (!isset($have[$n])) { $missing++; echo "missing in Tabler CSS: ti-$n  (first use $where)\n"; }
    }
    echo 'tabler icon names used: ' . count($used) . ", missing: $missing (checked against " . basename($cssFile) . ', ' . count($have) . " names)\n";
}
exit(($biHits === 0 && $missing === 0) ? 0 : 1);
