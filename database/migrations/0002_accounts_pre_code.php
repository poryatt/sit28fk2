<?php
// Phase 2 — [pre-code]: purely additive, safe before the new account code exists. Run FIRST in P2.
return [
    'description' => 'Accounts: removed status, removed_at, must_change_password, password_changed_at, login/log indexes',
    'up' => function (PDO $pdo, Migrator $m): void {
        if ($m->columnType('panel_users', 'status') !== "enum('active','suspended','removed')") {
            $pdo->exec("ALTER TABLE panel_users MODIFY status ENUM('active','suspended','removed') NOT NULL DEFAULT 'active'");
        }
        if (!$m->columnExists('panel_users', 'removed_at')) {
            $pdo->exec("ALTER TABLE panel_users ADD COLUMN removed_at DATETIME DEFAULT NULL AFTER last_login_at");
        }
        if (!$m->columnExists('panel_users', 'must_change_password')) {
            $pdo->exec("ALTER TABLE panel_users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER password_hash");
        }
        if (!$m->columnExists('panel_users', 'password_changed_at')) {
            $pdo->exec("ALTER TABLE panel_users ADD COLUMN password_changed_at DATETIME DEFAULT NULL AFTER must_change_password");
        }

        // Every account that still carries the published seed hash must change its password (D13).
        $seed = '$2y$10$cnvXc0McHVEK5Nml0ZvMwepHQ8jERISPDuFgn9BuwBsO6CZ5utTO.';
        $pdo->prepare("UPDATE panel_users SET must_change_password = 1 WHERE password_hash = ?")->execute([$seed]);

        $m->ensureIndex('login_attempts', 'idx_la_ip', '(ip_address, attempted_at)');
        $m->ensureIndex('login_attempts', 'idx_la_time', '(attempted_at)');
        $m->ensureIndex('login_attempts', 'idx_la_ident_ip', '(identifier, ip_address, attempted_at)');
        $m->ensureIndex('activity_logs', 'idx_logs_created', '(created_at)');
        $m->ensureIndex('activity_logs', 'idx_logs_user_time', '(panel_user_id, created_at)');
    },
];
