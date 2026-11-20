<?php
// Phase 4 — [pre-code]: additive; run FIRST in P4.
return [
    'description' => 'Files: media_library sha256 + purpose + UNIQUE(purpose, sha256), templates package columns',
    'up' => function (PDO $pdo, Migrator $m): void {
        if (!$m->columnExists('media_library', 'sha256')) {
            $pdo->exec("ALTER TABLE media_library ADD COLUMN sha256 CHAR(64) CHARACTER SET ascii DEFAULT NULL AFTER mime_type");
        }
        if (!$m->columnExists('media_library', 'purpose')) {
            $pdo->exec("ALTER TABLE media_library ADD COLUMN purpose ENUM('library','site_asset','avatar') NOT NULL DEFAULT 'library' AFTER file_type");
        }
        // NULL sha256 (legacy rows) never collides: UNIQUE allows many NULLs
        $m->ensureIndex('media_library', 'uq_media_purpose_sha256', '(purpose, sha256)', true);
        $m->ensureIndex('media_library', 'idx_media_purpose_created', '(purpose, created_at)');

        if (!$m->columnExists('templates', 'package_path')) {
            $pdo->exec("ALTER TABLE templates ADD COLUMN package_path VARCHAR(500) DEFAULT NULL AFTER preview_url");
        }
        if (!$m->columnExists('templates', 'package_sha256')) {
            $pdo->exec("ALTER TABLE templates ADD COLUMN package_sha256 CHAR(64) CHARACTER SET ascii DEFAULT NULL AFTER package_path");
        }
    },
];
