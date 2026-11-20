<?php
// Sets a new owner password from the command line (fresh installs, forgotten password, leaked seed password).
//   php database/tools/reset_owner_password.php <username>
// The password is read as one line from STDIN (never from argv: shell history and the process list would leak it):
//   Linux/macOS:  read -s -p "New password: " P; echo; printf '%s\n' "$P" | php database/tools/reset_owner_password.php admin
//   Windows:      php database/tools/reset_owner_password.php admin     (type the password, press Enter; it is echoed on screen)
// The script never prints the password.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$username = $argv[1] ?? '';
if ($username === '') { fwrite(STDERR, "Usage: php reset_owner_password.php <username>   (password on STDIN)\n"); exit(2); }
$pw = rtrim((string)fgets(STDIN), "\r\n");
if (($err = validatePasswordPolicy($pw)) !== null) { fwrite(STDERR, "Password rejected by policy (8-72 bytes).\n"); exit(2); }
if (password_verify($pw, SEED_PASSWORD_HASH)) { fwrite(STDERR, "That password is the published seed password and is forbidden.\n"); exit(2); }

$pdo = getPDO();
$st = $pdo->prepare("SELECT id FROM panel_users WHERE username = ? AND role = 'owner' AND status <> 'removed' LIMIT 1");
$st->execute([$username]);
$id = $st->fetchColumn();
if (!$id) { fwrite(STDERR, "No active owner with that username.\n"); exit(1); }
$pdo->prepare("UPDATE panel_users SET password_hash = ?, must_change_password = 0, password_changed_at = ?, status = 'active' WHERE id = ?")
    ->execute([hashPassword($pw), date('Y-m-d H:i:s'), (int)$id]);
$pdo->prepare("DELETE FROM login_attempts WHERE identifier = ?")->execute([$username]);
echo "Owner password updated; every existing session of that user is now invalid.\n";
