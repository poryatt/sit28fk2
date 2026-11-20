<?php
// Part 10C gate: the removed "sales" module must leave no trace in live code. CLI only. Exit 0 = clean, 1 = leftovers.
//   php database/tools/check_removed.php
// Allowed leftovers (they are history or legacy-safe on purpose): database/migrations/*, database/schema.sql (frozen baseline),
// changelogs/*, and database/tools/diagnose.php (its D18 check is guarded by tableExists and covers databases that are not yet migrated).
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = realpath(__DIR__ . '/../..');
$skipDirs = ['vendor', 'uploads', 'node_modules', 'changelogs', 'migrations', '.git', 'storage'];
$skipFiles = ['database/schema.sql', 'database/tools/diagnose.php', 'database/tools/check_removed.php'];
$patterns = [
    '/\bsales\b/i', '/sales_(report|connect|disconnect)/i', '/marketplace/i', '/افزایش فروش/u', '/مارکت‌?پلیس/u',
];
$hits = 0;
$it = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    function (SplFileInfo $f) use ($skipDirs) {
        return !($f->isDir() && in_array($f->getFilename(), $skipDirs, true));
    }
));
foreach ($it as $file) {
    /** @var SplFileInfo $file */
    if (!$file->isFile() || !in_array(strtolower($file->getExtension()), ['php', 'js', 'css', 'html', 'md', 'sql'], true)) { continue; }
    $rel = str_replace('\\', '/', substr($file->getRealPath(), strlen($root) + 1));
    if (in_array($rel, $skipFiles, true)) { continue; }
    $lines = file($file->getRealPath(), FILE_IGNORE_NEW_LINES) ?: [];
    foreach ($lines as $i => $line) {
        foreach ($patterns as $re) {
            if (preg_match($re, $line)) {
                $hits++;
                echo $rel . ':' . ($i + 1) . '  [' . $re . ']  ' . mb_substr(trim($line), 0, 100) . "\n";
                break;
            }
        }
    }
}
foreach (['public/modules/sales', 'public/api/sales'] as $dir) {
    if (file_exists($root . '/' . $dir)) { $hits++; echo "$dir still exists\n"; }
}
echo $hits === 0 ? "sales leftovers: 0\n" : "sales leftovers: $hits\n";
exit($hits === 0 ? 0 : 1);
