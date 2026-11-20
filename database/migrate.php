<?php
// Applies pending migrations in filename order.   php database/migrate.php [--status]
// Uses DB_MIGRATE_USER / DB_MIGRATE_PASS (DDL rights) when set, otherwise DB_USER / DB_PASS.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/lib/Migrator.php';
load_local_env();

$host = getenv('DB_HOST') ?: '127.0.0.1';
$name = getenv('DB_NAME') ?: 'business_panel';
$user = getenv('DB_MIGRATE_USER') ?: (getenv('DB_USER') ?: 'root');
$pass = getenv('DB_MIGRATE_PASS') !== false ? getenv('DB_MIGRATE_PASS') : (getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("SET time_zone = '+03:30'");
} catch (PDOException $e) {
    fwrite(STDERR, "Cannot connect to database '$name' as '$user': " . $e->getMessage() . "\n");
    exit(1);
}

// Two runners at once (two terminals, CI + a human) must not interleave DDL.
if ((int)$pdo->query("SELECT GET_LOCK('panel_migrate', 5)")->fetchColumn() !== 1) {
    fwrite(STDERR, "Another migration run holds the lock. Try again.\n");
    exit(1);
}

$pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(100) NOT NULL PRIMARY KEY,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=ascii");

$applied = $pdo->query("SELECT version FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN);
$files = glob(__DIR__ . '/migrations/*.php') ?: [];
sort($files, SORT_STRING);

if (in_array('--status', $argv, true)) {
    foreach ($files as $file) {
        $v = basename($file, '.php');
        echo (in_array($v, $applied, true) ? '[applied] ' : '[pending] ') . $v . "\n";
    }
    exit(0);
}

$migrator = new Migrator($pdo);
$ran = 0;
foreach ($files as $file) {
    $version = basename($file, '.php');
    if (in_array($version, $applied, true)) { continue; }
    $def = require $file;
    if (!is_array($def) || !isset($def['up']) || !is_callable($def['up'])) {
        fwrite(STDERR, "Migration $version is malformed.\n");
        exit(1);
    }
    echo '→ ' . $version . ': ' . ($def['description'] ?? '') . "\n";
    try {
        $def['up']($pdo, $migrator);
        $pdo->prepare("INSERT INTO schema_migrations (version) VALUES (?)")->execute([$version]);
        $ran++;
    } catch (Throwable $e) {
        // Not recorded: every step is guarded, so the same command can simply be run again after the cause is fixed.
        fwrite(STDERR, "FAILED $version: " . $e->getMessage() . "\n");
        exit(1);
    }
}
echo $ran === 0 ? "Nothing to migrate.\n" : "Done: $ran migration(s) applied.\n";
