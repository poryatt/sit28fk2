<?php
// Phase 1 — [post-code]: run LAST in P1, after save_permissions.php normalises flags.
return [
    'description' => 'Permissions: normalise rows, backfill dashboard.view for never-configured staff, add chk_pup_logic',
    'up' => function (PDO $pdo, Migrator $m): void {
        // 1) order matters: edit depends on view, delete depends on edit
        $pdo->exec("UPDATE panel_user_permissions SET can_edit = 0 WHERE can_view = 0 AND can_edit = 1");
        $pdo->exec("UPDATE panel_user_permissions SET can_delete = 0 WHERE can_edit = 0 AND can_delete = 1");

        // 2) staff that never had a single row get dashboard.view (staff with rows, even all-zero, are left alone)
        $pdo->exec("INSERT INTO panel_user_permissions (panel_user_id, permission_id, can_view, can_edit, can_delete)
                    SELECT u.id, p.id, 1, 0, 0
                    FROM panel_users u
                    JOIN permissions p ON p.module_key = 'dashboard'
                    WHERE u.role = 'staff'
                      AND NOT EXISTS (SELECT 1 FROM panel_user_permissions x WHERE x.panel_user_id = u.id)");

        // 3) safety net. Enforced on MariaDB >= 10.2.1 and MySQL >= 8.0.16; MySQL 5.7 parses and ignores it.
        if (!$m->checkExists('panel_user_permissions', 'chk_pup_logic')) {
            $pdo->exec("ALTER TABLE panel_user_permissions
                        ADD CONSTRAINT chk_pup_logic CHECK ((can_edit = 0 OR can_view = 1) AND (can_delete = 0 OR can_edit = 1))");
        }
    },
];
