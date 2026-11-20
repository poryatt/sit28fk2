<?php
// Phase 3 — [pre-code]: additive or rule-tightening with a preflight; run FIRST in P3.
return [
    'description' => 'Builder: block_uid ascii_bin, canvases.version, template FK CASCADE, composite parent FK, builder_media_refs',
    'up' => function (PDO $pdo, Migrator $m): void {
        // Preflight (diagnostics D07/D08): refuse to tighten constraints over inconsistent data. Fix the rows by hand, re-run.
        $badCross = $m->count("SELECT COUNT(*) FROM builder_blocks b JOIN builder_blocks p ON p.id = b.parent_id WHERE p.canvas_id <> b.canvas_id");
        if ($badCross > 0) {
            throw new RuntimeException("Preflight failed: $badCross builder_blocks row(s) have a parent in another canvas (diagnostic D08). Fix them, then re-run.");
        }

        // 1) L3: exact, case-sensitive, ASCII-only uid
        if ($m->collation('builder_blocks', 'block_uid') !== 'ascii_bin') {
            $pdo->exec("ALTER TABLE builder_blocks MODIFY block_uid CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL");
        }

        // 2) M13: optimistic-lock counter
        if (!$m->columnExists('builder_canvases', 'version')) {
            $pdo->exec("ALTER TABLE builder_canvases ADD COLUMN version INT UNSIGNED NOT NULL DEFAULT 1 AFTER status");
        }

        // 3) M1: a template's canvas must not outlive the template
        $m->replaceForeignKey('builder_canvases', 'fk_builder_canvases_template', 'template_id', 'templates', 'id', 'CASCADE');

        // 4) M2: a block's parent must live in the same canvas (composite FK; NULL parent = root is exempt)
        $m->ensureIndex('builder_blocks', 'uq_blocks_canvas_id', '(canvas_id, id)', true);
        if (!$m->fkExists('builder_blocks', 'fk_builder_blocks_parent_same_canvas')) {
            if ($m->fkExists('builder_blocks', 'fk_builder_blocks_parent')) {
                $pdo->exec("ALTER TABLE builder_blocks DROP FOREIGN KEY fk_builder_blocks_parent");
            }
            $pdo->exec("ALTER TABLE builder_blocks
                        ADD CONSTRAINT fk_builder_blocks_parent_same_canvas
                        FOREIGN KEY (canvas_id, parent_id) REFERENCES builder_blocks (canvas_id, id) ON DELETE CASCADE");
        }

        // 5) M5: which media files a canvas uses; the DB itself refuses to delete a file that is in use
        if (!$m->tableExists('builder_media_refs')) {
            $pdo->exec("CREATE TABLE builder_media_refs (
                canvas_id INT UNSIGNED NOT NULL,
                media_id  INT UNSIGNED NOT NULL,
                PRIMARY KEY (canvas_id, media_id),
                KEY idx_bmr_media (media_id),
                CONSTRAINT fk_bmr_canvas FOREIGN KEY (canvas_id) REFERENCES builder_canvases(id) ON DELETE CASCADE,
                CONSTRAINT fk_bmr_media  FOREIGN KEY (media_id)  REFERENCES media_library(id)    ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
    },
];
