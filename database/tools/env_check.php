<?php
// Environment probe for the hardening job (P0). CLI only. Never prints secrets.
// Reports: PHP/DB versions, sql_mode, time zone, required extensions, whether CHECK is enforced.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../../config/env.php';
load_local_env();

echo "== PHP ==\n";
echo "version: " . PHP_VERSION . "\n";
echo "php.ini: " . (php_ini_loaded_file() ?: '(none)') . "\n";
echo "date.timezone: " . (ini_get('date.timezone') ?: '(unset)') . "\n";
echo "APP_TIMEZONE: " . (defined('APP_TIMEZONE') ? APP_TIMEZONE : '(config.php not loaded yet — P1 defines Asia/Tehran)') . "\n";

$need = ['pdo_mysql', 'mbstring', 'fileinfo', 'gd', 'zip', 'sodium', 'exif'];
foreach ($need as $ext) {
    $loaded = extension_loaded($ext);
    echo "extension $ext: " . ($loaded ? "OK" : "MISSING") . "\n";
}
if (!extension_loaded('gd')) {
    echo "ACTION: enable gd in php.ini (remove ';' before 'extension=gd') and restart Apache/PHP.\n";
}
if (!extension_loaded('sodium')) {
    echo "NOTE: sodium is only required for Phase 6 (secret encryption). Enable it before P6 (remove ';' before 'extension=sodium').\n";
}

require_once __DIR__ . '/../../includes/image_support.php';
echo "image processing: " . imageProcessingMode() . " (fallback: " . imageFallbackPolicyCurrent() . ")\n";
if (imageProcessingMode() === 'none') {
    echo "ACTION: enable gd in php.ini (remove ';' before 'extension=gd') and restart Apache/PHP, or set IMAGE_PROCESSING_FALLBACK=refuse to disable image uploads.\n";
}

echo "\n== environment ==\n";
$appEnv = getenv('APP_ENV') ?: '(unset → treated as local)';
echo "APP_ENV: $appEnv\n";
echo "DB_HOST: " . (getenv('DB_HOST') ?: '(unset → 127.0.0.1)') . "\n";
echo "DB_NAME: " . (getenv('DB_NAME') ?: '(unset → business_panel)') . "\n";
echo "DB_USER: " . (getenv('DB_USER') ?: '(unset → root)') . "\n";
echo "DB_PASS: " . (getenv('DB_PASS') === false ? '(unset)' : '(set, value hidden)') . "\n";
echo "DB_MIGRATE_USER: " . (getenv('DB_MIGRATE_USER') ?: '(unset → falls back to DB_USER)') . "\n";
echo "TRUSTED_PROXIES: " . (getenv('TRUSTED_PROXIES') ?: '(unset → empty)') . "\n";

echo "\n== database ==\n";
$host = getenv('DB_HOST') ?: '127.0.0.1';
$name = getenv('DB_NAME') ?: 'business_panel';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    echo "connection: FAILED (" . $e->getMessage() . ")\n";
    exit(1);
}
$version = (string)$pdo->query("SELECT VERSION()")->fetchColumn();
echo "connection: OK as '$user' to '$name' @ '$host'\n";
echo "db version: $version\n";
$isMaria = stripos($version, 'mariadb') !== false;
echo "server flavour: " . ($isMaria ? "MariaDB" : "MySQL") . "\n";
$sqlMode = (string)$pdo->query("SELECT @@SESSION.sql_mode")->fetchColumn();
echo "sql_mode: $sqlMode\n";
echo "db time NOW(): " . (string)$pdo->query("SELECT NOW()")->fetchColumn() . "\n";
echo "db @@session.time_zone: " . (string)$pdo->query("SELECT @@SESSION.time_zone")->fetchColumn() . "\n";
echo "php date(): " . date('Y-m-d H:i:s P') . "\n";

// Whether CHECK constraints are enforced (MariaDB >= 10.2.1 and MySQL >= 8.0.16 enforce; MySQL 5.7 parses and ignores).
$checkEnforced = false;
if ($isMaria) {
    if (preg_match('/^(\d+)\.(\d+)\.(\d+)/', $version, $m)) {
        $checkEnforced = ((int)$m[1] > 10) || ((int)$m[1] === 10 && (int)$m[2] >= 2);
    } else {
        $checkEnforced = true;
    }
} else {
    if (preg_match('/^(\d+)\.(\d+)\.(\d+)/', $version, $m)) {
        $checkEnforced = ((int)$m[1] > 8) || ((int)$m[1] === 8 && (int)$m[2] >= 0 && (int)$m[3] >= 16) || ((int)$m[1] === 8 && (int)$m[2] > 0);
    }
}
echo "CHECK enforced: " . ($checkEnforced ? "yes" : "NO (MySQL 5.7 parses and ignores CHECK — code-level normalisation is the real guarantee)") . "\n";

echo "\n== P6: secrets + production readiness (never prints secret values) ==\n";
$appEnvRaw = getenv('APP_ENV');
$isProd = ($appEnvRaw ?: 'local') !== 'local';
echo "APP_ENV: " . ($appEnvRaw ?: '(unset)') . " (treated as production: " . ($isProd ? 'yes' : 'no') . ")\n";
$sk = getenv('APP_SECRET_KEY');
if (!is_string($sk) || $sk === '') {
    echo "APP_SECRET_KEY: MISSING (required for secret encryption)\n";
} else {
    $raw = base64_decode($sk, true);
    echo "APP_SECRET_KEY: " . (($raw !== false && strlen($raw) === 32) ? "present and valid" : "INVALID (must be base64 of exactly 32 bytes)") . "\n";
}
echo "sodium extension: " . (extension_loaded('sodium') ? "OK" : "MISSING (required for Phase 6)") . "\n";
$dbUserShown = getenv('DB_USER') ?: '(unset → root)';
echo "DB_USER: $dbUserShown" . (($isProd && strtolower((string)(getenv('DB_USER') ?: 'root')) === 'root') ? "  <-- REFUSED in production" : "") . "\n";
function shorthandBytes(string $v): int {
    $v = trim($v);
    if (!preg_match('/^([\d.]+)\s*([KMG]?)/i', $v, $m)) { return 0; }
    $n = (float)$m[1];
    switch (strtoupper($m[2])) {
        case 'G': return (int)($n * 1024 * 1024 * 1024);
        case 'M': return (int)($n * 1024 * 1024);
        case 'K': return (int)($n * 1024);
        default: return (int)$n;
    }
}
$limits = [
    'upload_max_filesize' => 50 * 1024 * 1024,
    'post_max_size' => 55 * 1024 * 1024,
    'memory_limit' => 256 * 1024 * 1024,
    'max_file_uploads' => 20,
];
foreach ($limits as $ini => $need) {
    $cur = ini_get($ini);
    if ($ini === 'memory_limit' && trim((string)$cur) === '-1') { echo "php.ini $ini: unlimited (OK)\n"; continue; }
    $have = shorthandBytes((string)$cur);
    echo "php.ini $ini: $cur" . ($have < $need ? "  <-- WARN below recommended" : " (OK)") . "\n";
}
