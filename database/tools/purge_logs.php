<?php
// Retention for the two tables that grow forever. CLI only. Run from cron / Task Scheduler (e.g. daily).
//   php database/tools/purge_logs.php            → deletes and prints counts
//   php database/tools/purge_logs.php --dry-run  → only prints what would be deleted
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../config/database.php';   // app credentials: DML only is enough

const LOGIN_ATTEMPTS_KEEP_DAYS = 7;
const ACTIVITY_LOGS_KEEP_DAYS = 180;
const BATCH = 5000;

$dry = in_array('--dry-run', $argv, true);
$pdo = getPDO();
$jobs = [
    ['login_attempts', 'attempted_at', LOGIN_ATTEMPTS_KEEP_DAYS],
    ['activity_logs',  'created_at',   ACTIVITY_LOGS_KEEP_DAYS],
];
foreach ($jobs as [$table, $col, $days]) {
    $days = (int)$days;
    if ($dry) {
        $n = (int)$pdo->query("SELECT COUNT(*) FROM $table WHERE $col < (NOW() - INTERVAL $days DAY)")->fetchColumn();
        echo "$table: $n row(s) older than $days days (dry run)\n";
        continue;
    }
    $total = 0;
    do {
        $affected = $pdo->exec("DELETE FROM $table WHERE $col < (NOW() - INTERVAL $days DAY) LIMIT " . BATCH);
        $total += (int)$affected;
    } while ($affected === BATCH);
    echo "$table: deleted $total row(s) older than $days days\n";
}
