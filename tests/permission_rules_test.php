<?php
require __DIR__ . '/../includes/permission_rules.php';
$fail = 0;
function check(string $label, $actual, $expected): void {
    global $fail;
    if ($actual !== $expected) { $fail++; fwrite(STDERR, "FAIL: $label\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n"); }
}
$all = ['view' => true, 'edit' => true, 'delete' => true];
$none = ['view' => false, 'edit' => false, 'delete' => false];
check('F,T,T', normalizePermissionFlags(false, true, true), $none);
check('T,F,T', normalizePermissionFlags(true, false, true), ['view' => true, 'edit' => false, 'delete' => false]);
check('T,T,T', normalizePermissionFlags(true, true, true), $all);
check('F,F,F', normalizePermissionFlags(false, false, false), $none);
check('T,T,F', normalizePermissionFlags(true, true, false), ['view' => true, 'edit' => true, 'delete' => false]);
check('empty flags view', permissionFlagsAllow([], 'view'), false);
check('unknown action', permissionFlagsAllow($all, 'publish'), false);
check('view allowed', permissionFlagsAllow($all, 'view'), true);
check('edit denied', permissionFlagsAllow(['view' => true, 'edit' => false, 'delete' => false], 'edit'), false);
check('path 1', normalizeScriptPath('/modules/media/index.php'), '/modules/media/index.php');
check('path 2', normalizeScriptPath('/panel/public/modules/settings/staff.php'), '/modules/settings/staff.php');
check('path 3', normalizeScriptPath('C:\\x\\modules\\media\\index.php'), '/modules/media/index.php');
check('path api', normalizeScriptPath('/api/staff/invite.php'), null);
check('path traversal', normalizeScriptPath('/modules/../index.php'), null);
check('path no php', normalizeScriptPath('/modules/media/index.txt'), null);
$map = ['/modules/media/index.php' => ['module' => 'media', 'action' => 'view']];
check('guard null', resolvePageGuard(null, $map), ['deny' => true]);
check('guard unknown', resolvePageGuard('/modules/zz_probe/index.php', $map), ['deny' => true]);
check('guard known', resolvePageGuard('/modules/media/index.php', $map), ['module' => 'media', 'action' => 'view']);
if ($fail > 0) { fwrite(STDERR, "$fail failure(s)\n"); exit(1); }
echo "permission_rules_test: OK\n";
