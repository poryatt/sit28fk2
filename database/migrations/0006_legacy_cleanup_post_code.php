<?php
// Phase 5 — [post-code]: run LAST in P5, after dashboard, delete.php, activate.php and templates/index.php stopped using the legacy objects.
return [
    'description' => 'Drop legacy tables and templates.is_active, settings FK RESTRICT, remove category "all"',
    'up' => function (PDO $pdo, Migrator $m): void {
        // Guard: the legacy tables are expected to be empty. Non-empty needs a human decision.
        $legacy = ['page_blocks', 'article_blocks', 'pages', 'articles', 'article_categories', 'media_usage'];
        $nonEmpty = [];
        foreach ($legacy as $t) {
            if ($m->tableExists($t) && $m->count("SELECT COUNT(*) FROM `$t`") > 0) { $nonEmpty[] = $t; }
        }
        if ($nonEmpty && getenv('MIGRATE_DROP_LEGACY') !== '1') {
            throw new RuntimeException('Legacy table(s) with rows: ' . implode(', ', $nonEmpty)
                . '. Back up, then re-run with MIGRATE_DROP_LEGACY=1 if the data is really disposable.');
        }
        // Children first, then parents (FK order).
        $pdo->exec("DROP TABLE IF EXISTS page_blocks, article_blocks, pages, articles, article_categories, media_usage");

        // H5: site_settings.active_template_id becomes the only source of truth.
        if ($m->columnExists('templates', 'is_active')) {
            $pdo->exec("UPDATE site_settings SET active_template_id = (SELECT id FROM templates WHERE is_active = 1 ORDER BY id LIMIT 1)
                        WHERE id = 1 AND active_template_id IS NULL
                          AND EXISTS (SELECT 1 FROM templates WHERE is_active = 1)");
            if ($m->indexExists('templates', 'idx_templates_active')) {
                $pdo->exec("ALTER TABLE templates DROP INDEX idx_templates_active");
            }
            $pdo->exec("ALTER TABLE templates DROP COLUMN is_active");
        }
        // Deleting the active template must fail loudly instead of silently leaving the site without one.
        $m->replaceForeignKey('site_settings', 'fk_settings_template', 'active_template_id', 'templates', 'id', 'RESTRICT');

        $pdo->exec("DELETE FROM template_categories WHERE slug = 'all'");
    },
];
