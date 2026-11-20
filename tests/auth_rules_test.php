<?php
date_default_timezone_set('Asia/Tehran');
require __DIR__ . '/../includes/auth_rules.php';
$fail = 0;
function check(string $label, $actual, $expected): void {
    global $fail;
    if ($actual !== $expected) { $fail++; fwrite(STDERR, "FAIL: $label\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n"); }
}
check('lock none', loginLockReason(0, 0), null);
check('lock 4/19', loginLockReason(4, 19), null);
check('lock identifier at 5', loginLockReason(5, 5), 'identifier');
check('lock ip at 20', loginLockReason(1, 20), 'ip');
check('identifier wins over ip', loginLockReason(9, 99), 'identifier');
check('custom limits', loginLockReason(2, 0, 2, 20), 'identifier');
$now = time();
check('stale null', sessionIsStale(null, $now), false);
check('stale empty', sessionIsStale('', $now), false);
check('stale changed after auth', sessionIsStale(date('Y-m-d H:i:s', $now + 60), $now), true);
check('stale changed before auth', sessionIsStale(date('Y-m-d H:i:s', $now - 60), $now), false);
check('stale same second', sessionIsStale(date('Y-m-d H:i:s', $now), $now), false);
check('stale garbage fails closed', sessionIsStale('not-a-date', $now), true);
check('stale missing auth_at', sessionIsStale('2026-01-01 00:00:00', 0), true);
check('allowed profile', mustChangeAllowedScript('/modules/settings/profile.php'), true);
check('allowed logout', mustChangeAllowedScript('/logout.php'), true);
check('allowed nested profile', mustChangeAllowedScript('/panel/public/modules/settings/profile.php'), true);
check('windows path', mustChangeAllowedScript('C:\\x\\public\\logout.php'), true);
check('denied dashboard', mustChangeAllowedScript('/modules/dashboard/index.php'), false);
check('denied api', mustChangeAllowedScript('/api/staff/invite.php'), false);
check('denied lookalike', mustChangeAllowedScript('/modules/settings/profile.php.bak'), false);
if ($fail > 0) { fwrite(STDERR, "$fail failure(s)\n"); exit(1); }
echo "auth_rules_test: OK\n";
