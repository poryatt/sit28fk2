<?php
// One-off backfill for the P4 file registry. CLI only. Dry-run by default (prints what it would do).
//   php database/tools/backfill_files.php                 → dry run
//   php database/tools/backfill_files.php --apply         → performs the changes
//   php database/tools/backfill_files.php --apply --dedupe → additionally repoints duplicate assets
// Uses getPDO() and the app constants; never touches a file outside UPLOAD_BASE / STORAGE_PRIVATE.
// Take a fresh backup of public/uploads/ and the database before --apply.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/file_store.php';

$apply = in_array('--apply', $argv, true);
$dedupe = in_array('--dedupe', $argv, true);
if ($dedupe && !$apply) { fwrite(STDERR, "--dedupe needs --apply\n"); exit(2); }
echo $apply ? "MODE: apply" . ($dedupe ? " + dedupe" : "") . "\n" : "MODE: dry run (use --apply to write)\n";

$pdo = getPDO();
$counts = ['ok' => 0, 'skip' => 0, 'problem' => 0];
function say(string $kind, string $msg): void {
    global $counts;
    $counts[strtolower($kind)]++;
    echo "$kind $msg\n";
}
/** Absolute path under UPLOAD_BASE, or null when outside / missing. */
function uploadAbs(?string $webPath): ?string {
    if ($webPath === null || strpos($webPath, '/uploads/') !== 0 || strpos($webPath, '..') !== false) { return null; }
    $abs = UPLOAD_BASE . substr($webPath, strlen('/uploads'));
    $base = realpath(UPLOAD_BASE);
    $real = realpath($abs);
    if ($base === false || $real === false || strpos($real, $base . DIRECTORY_SEPARATOR) !== 0) { return null; }
    return $real;
}

// ---- 1) hash legacy library rows ----
// Documented limitation: legacy hashes are of the STORED bytes (re-encoded images), not the original upload bytes.
// Dedupe (UNIQUE purpose+sha256) therefore protects new uploads only (Appendix B.6).
echo "--- 1) legacy library hashes ---\n";
$rows = $pdo->query("SELECT id, file_path FROM media_library WHERE sha256 IS NULL")->fetchAll();
foreach ($rows as $r) {
    $abs = uploadAbs($r['file_path']);
    if ($abs === null) { say('SKIP', "media#{$r['id']}: file missing ({$r['file_path']})"); continue; }
    $hash = hash_file('sha256', $abs);
    if ($hash === false) { say('PROBLEM', "media#{$r['id']}: cannot hash"); continue; }
    if (!$apply) { say('SKIP', "media#{$r['id']}: would set sha256 (dry run)"); continue; }
    try {
        $pdo->prepare("UPDATE media_library SET sha256 = ? WHERE id = ?")->execute([$hash, (int)$r['id']]);
        say('OK', "media#{$r['id']}: hashed");
    } catch (PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) === 1062) {
            $dup = $pdo->prepare("SELECT id FROM media_library WHERE sha256 = ? AND id <> ? LIMIT 1");
            $dup->execute([$hash, (int)$r['id']]);
            say('PROBLEM', "media#{$r['id']}: duplicate content of media#" . $dup->fetchColumn() . " (human decides)");
        } else { say('PROBLEM', "media#{$r['id']}: " . $e->getMessage()); }
    }
}

// ---- 2) register unregistered public assets ----
echo "--- 2) unregistered public assets ---\n";
$ownerId = (int)$pdo->query("SELECT id FROM panel_users WHERE role='owner' ORDER BY id LIMIT 1")->fetchColumn();
$assets = []; // path => ['purpose'=>..., 'user'=>...]
foreach ($pdo->query("SELECT id, avatar_path FROM panel_users WHERE avatar_path LIKE '/uploads/%'")->fetchAll() as $u) {
    $assets[$u['avatar_path']] = ['purpose' => 'avatar', 'user' => (int)$u['id']];
}
$site = $pdo->query("SELECT logo_path, favicon_path FROM site_settings WHERE id=1")->fetch();
if ($site) {
    foreach (['logo_path', 'favicon_path'] as $c) {
        if (!empty($site[$c]) && strpos($site[$c], '/uploads/') === 0 && !isset($assets[$site[$c]])) {
            $assets[$site[$c]] = ['purpose' => 'site_asset', 'user' => $ownerId];
        }
    }
}
$registered = $pdo->query("SELECT file_path FROM media_library")->fetchAll(PDO::FETCH_COLUMN);
$regSet = array_flip($registered);
$finfo = finfo_open(FILEINFO_MIME_TYPE);
foreach ($assets as $path => $meta) {
    if (isset($regSet[$path])) { say('SKIP', "asset $path: already registered"); continue; }
    $abs = uploadAbs($path);
    if ($abs === null) { say('PROBLEM', "asset $path: file missing"); continue; }
    $mime = $finfo ? (string)finfo_file($finfo, $abs) : '';
    $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
    $w = null; $h = null;
    if ($ext !== 'ico') { $gi = @getimagesize($abs); if ($gi) { $w = (int)$gi[0]; $h = (int)$gi[1]; } }
    if (!$apply) { say('SKIP', "asset $path: would register as {$meta['purpose']} (dry run)"); continue; }
    try {
        $pdo->prepare("INSERT INTO media_library (file_name, file_path, file_type, purpose, mime_type, file_size_kb, width, height, uploaded_by) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([basename($path), $path, 'image', $meta['purpose'], $mime, (int)ceil(filesize($abs) / 1024), $w, $h, $meta['user']]);
        say('OK', "asset $path: registered as {$meta['purpose']}");
    } catch (PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) !== 1062) { say('PROBLEM', "asset $path: " . $e->getMessage()); continue; }
        // Same bytes already registered under another path.
        if (!$dedupe) { say('PROBLEM', "asset $path: duplicate asset (use --dedupe)"); continue; }
        $st = $pdo->prepare("SELECT file_path FROM media_library WHERE purpose = ? AND sha256 = ? LIMIT 1");
        $st->execute([$meta['purpose'], hash_file('sha256', $abs)]);
        $existing = $st->fetchColumn();
        if (!$existing) { say('PROBLEM', "asset $path: duplicate but existing row not found"); continue; }
        $pdo->prepare("UPDATE panel_users SET avatar_path = ? WHERE avatar_path = ?")->execute([$existing, $path]);
        $pdo->prepare("UPDATE site_settings SET logo_path = ? WHERE logo_path = ?")->execute([$existing, $path]);
        $pdo->prepare("UPDATE site_settings SET favicon_path = ? WHERE favicon_path = ?")->execute([$existing, $path]);
        @unlink($abs);
        say('OK', "asset $path: deduped to $existing");
    }
}
if ($finfo) { finfo_close($finfo); }

// ---- 3) move legacy licence certificates ----
echo "--- 3) legacy licence certificates ---\n";
$certMime = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
foreach ($pdo->query("SELECT id, license_type, certificate_file FROM licenses WHERE certificate_file LIKE '/uploads/%'")->fetchAll() as $lic) {
    $abs = uploadAbs($lic['certificate_file']);
    if ($abs === null) { say('PROBLEM', "license#{$lic['id']}: missing file ({$lic['certificate_file']})"); continue; }
    $fi = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $fi ? (string)finfo_file($fi, $abs) : '';
    if ($fi) { finfo_close($fi); }
    if (!isset($certMime[$mime])) {
        if (!$apply) { say('SKIP', "license#{$lic['id']}: would QUARANTINE ($mime, dry run)"); continue; }
        $qdir = STORAGE_PRIVATE . '/quarantine';
        if (!is_dir($qdir)) { @mkdir($qdir, 0755, true); }
        $dest = $qdir . '/' . bin2hex(random_bytes(16)) . '.' . strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        if (@rename($abs, $dest)) {
            $pdo->prepare("UPDATE licenses SET certificate_file = NULL WHERE id = ?")->execute([(int)$lic['id']]);
            say('OK', "license#{$lic['id']}: QUARANTINED ($mime)");
        } else { say('PROBLEM', "license#{$lic['id']}: cannot quarantine"); }
        continue;
    }
    if (!$apply) { say('SKIP', "license#{$lic['id']}: would move to storage/private (dry run)"); continue; }
    $rel = 'licenses/' . bin2hex(random_bytes(16)) . '.' . $certMime[$mime];
    $dest = STORAGE_PRIVATE . '/' . $rel;
    if (!is_dir(STORAGE_PRIVATE . '/licenses')) { @mkdir(STORAGE_PRIVATE . '/licenses', 0755, true); }
    if (!@copy($abs, $dest)) { say('PROBLEM', "license#{$lic['id']}: copy failed"); continue; }
    $pdo->prepare("UPDATE licenses SET certificate_file = ? WHERE id = ?")->execute([$rel, (int)$lic['id']]);
    @unlink($abs);
    say('OK', "license#{$lic['id']}: moved to $rel");
}

// ---- 4) orphan template zips ----
echo "--- 4) orphan template zips ---\n";
$tplDir = UPLOAD_BASE . '/templates';
if (is_dir($tplDir)) {
    foreach (glob($tplDir . '/*') ?: [] as $f) {
        if (!is_file($f)) { continue; }
        if (!$apply) { say('SKIP', "orphan " . basename($f) . ": would move to quarantine (dry run)"); continue; }
        $qdir = STORAGE_PRIVATE . '/quarantine';
        if (!is_dir($qdir)) { @mkdir($qdir, 0755, true); }
        $dest = $qdir . '/' . basename($f);
        if (@rename($f, $dest)) { say('OK', "orphan " . basename($f) . ": quarantined"); }
        else { say('PROBLEM', "orphan " . basename($f) . ": cannot move"); }
    }
} else { say('SKIP', "no public/uploads/templates/ dir"); }

// ---- 5) SVG report ----
echo "--- 5) SVG assets ---\n";
$site = $pdo->query("SELECT logo_path, favicon_path FROM site_settings WHERE id=1")->fetch();
if ($site) {
    foreach (['logo_path' => 'logo', 'favicon_path' => 'favicon'] as $c => $label) {
        if (!empty($site[$c]) && substr(strtolower($site[$c]), -4) === '.svg') {
            say('PROBLEM', "svg asset ($label {$site[$c]}) — owner must upload a PNG/JPG/ICO in Settings → General");
        }
    }
}

echo "done: {$counts['ok']} OK, {$counts['skip']} SKIP, {$counts['problem']} PROBLEM\n";
