<?php
// Part 10C tests for includes/dashboard_data.php (no bootstrap, CLI only).
// SQL part runs on SQLite; skips cleanly when pdo_sqlite is missing.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/dashboard_data.php';

$pass = 0; $fail = 0;
function ok(bool $cond, string $name): void {
    global $pass, $fail;
    if ($cond) { $pass++; echo "ok $name\n"; }
    else { $fail++; echo "FAIL $name\n"; }
}

// dashboardActivityLabel() is pure: description wins in full, module label gets a wrapper, blank/unknown gets the generic fallback.
ok(dashboardActivityLabel(['description' => 'ورود با موفقیت انجام شد']) === 'ورود با موفقیت انجام شد', 'label: description wins in full');
ok(dashboardActivityLabel(['description' => '', 'module_label' => 'بلاگ']) === 'فعالیت در «بلاگ»', 'label: module wrapper');
ok(dashboardActivityLabel([]) === 'فعالیت ثبت‌شده', 'label: generic fallback');
ok(dashboardActivityLabel(['description' => '   ']) === 'فعالیت ثبت‌شده', 'label: blank description falls back to generic');
ok(mb_strlen(dashboardActivityLabel(['description' => str_repeat('و', 500)]), 'UTF-8') === 140, 'label: clamped to 140 chars');

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "SKIP 5 SQL assertions: pdo_sqlite driver is not enabled (extension=pdo_sqlite in php.ini)\n";
} else {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    foreach ([
        'CREATE TABLE panel_users (id INTEGER PRIMARY KEY, full_name TEXT NOT NULL)',
        'CREATE TABLE permissions (module_key TEXT PRIMARY KEY, module_label TEXT NULL)',
        'CREATE TABLE activity_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, panel_user_id INTEGER NOT NULL,
            action_type TEXT NOT NULL, module_key TEXT NULL, description TEXT NULL, created_at TEXT NOT NULL)',
    ] as $sql) { $pdo->exec($sql); }
    $pdo->exec("INSERT INTO panel_users (id, full_name) VALUES (1, 'همکار'), (2, 'مدیر سیستم')");
    $pdo->exec("INSERT INTO permissions (module_key, module_label) VALUES ('pages', 'صفحات')");
    $pdo->exec("INSERT INTO activity_logs (panel_user_id, action_type, module_key, description, created_at) VALUES
        (2, 'page_update', 'pages', 'صفحه «خانه» ویرایش شد', '2026-01-02 10:00:00'),
        (2, 'login', NULL, NULL, '2026-01-03 09:00:00'),
        (1, 'page_update', 'legacy_module', 'old row of a removed module', '2026-01-04 08:00:00')");
    $rows = dashboardRecentActivity($pdo, 2);
    ok(count($rows) === 2, 'limit 2 returns exactly 2 rows');
    ok($rows[0]['label'] === 'old row of a removed module' && $rows[1]['label'] === 'فعالیت ثبت‌شده', 'newest first + description wins');
    ok(array_keys($rows[0]) === ['id','action_type','module_key','description','created_at','full_name','module_label','label'], 'row shape: no ip_address column');
    for ($i = 4; $i <= 23; $i++) {
        $pdo->prepare("INSERT INTO activity_logs (panel_user_id, action_type, module_key, description, created_at) VALUES (2, 'page_update', NULL, 'row $i', '2026-02-01 00:00:00')")->execute();
    }
    $all = dashboardRecentActivity($pdo, 25);
    ok(count($all) === 20, 'limit clamps to max 20 (23 rows in table)');
    ok(array_map('intval', array_column($all, 'id')) === range(23, 4), 'rows arrive newest first after clamping');
}

echo "passed: $pass / failed: $fail\n";
exit($fail === 0 ? 0 : 1);
