<?php
// Phase 2 — [post-code]: run LAST in P2, after remove.php is a soft delete (a real DELETE of a user with content now fails by design).
return [
    'description' => 'FK rules: creators/uploaders RESTRICT, activity_logs.panel_user_id nullable + SET NULL',
    'up' => function (PDO $pdo, Migrator $m): void {
        $m->replaceForeignKey('builder_canvases', 'fk_builder_canvases_creator', 'created_by', 'panel_users', 'id', 'RESTRICT');
        $m->replaceForeignKey('media_library', 'fk_media_uploader', 'uploaded_by', 'panel_users', 'id', 'RESTRICT');

        // SET NULL needs a nullable column. MySQL refuses to alter a column that an FK still guards,
        // so: drop the old FK first, widen the column, then re-add the FK with the new rule.
        if ($m->fkExists('activity_logs', 'fk_logs_user') && $m->fkRule('activity_logs', 'fk_logs_user') !== 'SET NULL') {
            $pdo->exec("ALTER TABLE activity_logs DROP FOREIGN KEY fk_logs_user");
        }
        if (!$m->isNullable('activity_logs', 'panel_user_id')) {
            $pdo->exec("ALTER TABLE activity_logs MODIFY panel_user_id INT UNSIGNED DEFAULT NULL");
        }
        $m->replaceForeignKey('activity_logs', 'fk_logs_user', 'panel_user_id', 'panel_users', 'id', 'SET NULL');
    },
];
