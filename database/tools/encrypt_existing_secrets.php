<?php
// One-off encryption of plaintext secrets already in the database. CLI only. Dry-run by default.
//   php database/tools/encrypt_existing_secrets.php           → dry run (counts + row ids only, never values)
//   php database/tools/encrypt_existing_secrets.php --apply  → encrypts (decrypt-and-compare before writing)
// Requires APP_SECRET_KEY (exit 1 otherwise). Take a database backup first.
// Idempotent: a second run reports 0.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/secrets.php';

try {
    secret_key();
} catch (RuntimeException $e) {
    fwrite(STDERR, "APP_SECRET_KEY is missing or invalid: set it in env.local.php or a real environment variable first.\n");
    exit(1);
}

$apply = in_array('--apply', $argv, true);
echo $apply ? "MODE: apply\n" : "MODE: dry run (use --apply to write)\n";

$pdo = getPDO();
$found = 0;

// licenses zarinpal api_key inside credentials JSON
$zrows = $pdo->query("SELECT id, credentials FROM licenses WHERE license_type='zarinpal'")->fetchAll();
$zjobs = [];
foreach ($zrows as $r) {
    $creds = json_decode((string)$r['credentials'], true);
    $k = is_array($creds) ? (string)($creds['api_key'] ?? '') : '';
    if ($k !== '' && !secret_is_encrypted($k)) { $zjobs[] = [(int)$r['id'], $k]; }
}

$found = count($zjobs);
echo "plaintext zarinpal keys: " . count($zjobs) . "\n";
if (!$apply) {
    foreach ($zjobs as [$id]) { echo "would encrypt licenses#$id api_key\n"; }
    echo "done: $found row(s) pending\n";
    exit(0);
}

try {
    $pdo->beginTransaction();
    foreach ($zjobs as [$id, $plain]) {
        $row = $pdo->prepare("SELECT credentials FROM licenses WHERE id = ?");
        $row->execute([$id]);
        $creds = json_decode((string)$row->fetchColumn(), true);
        if (!is_array($creds)) { $creds = []; }
        $enc = secret_encrypt($plain);
        if (secret_decrypt($enc) !== $plain) { throw new RuntimeException("round-trip mismatch for licenses#$id"); }
        $creds['api_key'] = $enc;
        $pdo->prepare("UPDATE licenses SET credentials = ? WHERE id = ?")->execute([json_encode($creds, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $id]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    fwrite(STDERR, "FAILED licenses: " . $e->getMessage() . "\n");
    exit(1);
}
echo "done: encrypted $found value(s)\n";
