<?php
// Orphan-file report for public uploads and private storage. CLI only.
//   php database/tools/gc_files.php           → report only
//   php database/tools/gc_files.php --delete  → removes the candidates
// Only safe-named files (hex names we write) that are at least 24 hours old are
// candidates (a file may be mid-upload). Never touches quarantine/.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../config/database.php';

$delete = in_array('--delete', $argv, true);
echo $delete ? "MODE: delete\n" : "MODE: report only (use --delete to remove)\n";

$pdo = getPDO();
$candidates = [];

$pubShapes = [
    'media' => '#^[0-9a-f]{32}\.(jpg|png|webp|mp4|webm|ico)$#',
    'avatars' => '#^[0-9a-f]{32}\.(jpg|png|webp|mp4|webm|ico)$#',
];
$libRows = $pdo->query("SELECT file_path FROM media_library")->fetchAll(PDO::FETCH_COLUMN);
$libSet = array_flip($libRows);
$refSet = $libSet;
$site = $pdo->query("SELECT logo_path, favicon_path FROM site_settings WHERE id=1")->fetch();
if ($site) { foreach (['logo_path', 'favicon_path'] as $c) { if (!empty($site[$c])) { $refSet[$site[$c]] = 1; } } }
foreach ($pdo->query("SELECT avatar_path FROM panel_users WHERE avatar_path IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN) as $p) { $refSet[$p] = 1; }

foreach ($pubShapes as $bucket => $shape) {
    $dir = UPLOAD_BASE . '/' . $bucket;
    if (!is_dir($dir)) { continue; }
    foreach (glob($dir . '/*') ?: [] as $f) {
        if (!is_file($f)) { continue; }
        $base = basename($f);
        if (!preg_match($shape, $base)) { continue; }                       // not one of ours: leave alone
        if ((time() - (int)filemtime($f)) < 86400) { continue; }            // younger than 24 h: may be mid-upload
        $web = '/uploads/' . $bucket . '/' . $base;
        if (isset($refSet[$web])) { continue; }
        $candidates[] = $f;
    }
}

$privShapes = [
    'licenses' => ['#^[0-9a-f]{32}\.(pdf|jpg|png|webp)$#', "SELECT certificate_file FROM licenses", 'certificate'],
    'templates' => ['#^[0-9a-f]{32}\.zip$#', "SELECT package_path FROM templates", 'package'],
];
foreach ($privShapes as $kind => [$shape, $sql, $label]) {
    $dir = STORAGE_PRIVATE . '/' . $kind;
    if (!is_dir($dir)) { continue; }
    $used = [];
    try {
        foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN) as $p) { if ($p) { $used[$p] = 1; } }
    } catch (Throwable $e) { echo "PROBLEM querying $label refs: " . $e->getMessage() . "\n"; continue; }
    foreach (glob($dir . '/*') ?: [] as $f) {
        if (!is_file($f)) { continue; }
        $base = basename($f);
        if (!preg_match($shape, $base)) { continue; }
        if ((time() - (int)filemtime($f)) < 86400) { continue; }
        if (isset($used[$kind . '/' . $base])) { continue; }
        $candidates[] = $f;
    }
}

if (empty($candidates)) { echo "no orphan candidates\n"; exit(0); }
foreach ($candidates as $f) {
    if ($delete) {
        echo (@unlink($f) ? "deleted $f" : "PROBLEM cannot delete $f") . "\n";
    } else {
        echo "orphan $f\n";
    }
}
echo count($candidates) . " candidate(s)" . ($delete ? " processed" : " (report only)") . "\n";
