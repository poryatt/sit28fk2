<?php
// Part 10C: removes the legacy "sales" module from the database.
// Read-side cleanup (DELETE permissions row) always runs; the DDL (DROP TABLE) is guarded.
//   MIGRATE_DROP_SALES=1  → required ONLY when marketplace_connections still holds connection rows with
//                           api_key/api_secret values (owner decision, database backup taken first).
//   Without keys (fresh/unused installs) the removal proceeds with no extra env var.
// Tables are dropped in FK-dependency order (marketplace_connections first, marketplaces last).
//   php database/migrate.php --status | grep 0008   shows pending state.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

return [
    'description' => 'Part 10C: delete the sales permission row (and orphan grants) + guarded DROP of marketplace connections/marketplaces',
    'up' => function (PDO $pdo, Migrator $m): void {
        $guard = getenv('MIGRATE_DROP_SALES') === '1';

        // 1) permission row + leftover staff grants (both idempotent)
        if ($m->tableExists('permissions')) {
            $pdo->beginTransaction();
            try {
                $d1 = $pdo->prepare("DELETE FROM permissions WHERE module_key = 'sales'");
                $d1->execute();
                if ($m->tableExists('panel_user_permissions')) {
                    if ($m->columnExists('panel_user_permissions', 'module_key')) {
                        $d2 = $pdo->prepare("DELETE FROM panel_user_permissions WHERE module_key = 'sales'");
                        $d2->execute();
                    } else {
                        // schema joins grants via permission_id → permissions.id (real production schema)
                        $d2 = $pdo->prepare("DELETE pup FROM panel_user_permissions pup
                                             JOIN permissions p ON p.id = pup.permission_id
                                             WHERE p.module_key = 'sales'");
                        $d2->execute();
                    }
                    echo 'panel_user_permissions sales rows deleted: ' . $d2->rowCount() . "\n";
                }
                $pdo->commit();
                echo 'permissions sales row deleted: ' . $d1->rowCount() . "\n";
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                throw $e;
            }
        }

        // 2) table drops — guarded
        if (!$m->tableExists('marketplace_connections') && !$m->tableExists('marketplaces')) {
            echo 'marketplace tables already removed: nothing to do' . "\n";
            return;
        }

        $holding = 0;
        if ($m->tableExists('marketplace_connections')) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM marketplace_connections
                                 WHERE (api_key IS NOT NULL AND api_key <> '') OR (api_secret IS NOT NULL AND api_secret <> '')");
            $holding = (int)$stmt->fetchColumn();
        }

        if (!$guard) {
            echo "skipping DROP TABLE (set MIGRATE_DROP_SALES=1 to remove marketplace data — take a database backup first";
            if ($holding > 0) {
                echo '; WARNING: marketplace_connections currently holds ' . $holding . ' row(s) with remembered keys; their data is NOT recoverable afterwards';
            }
            echo ")\n";
            return;
        }

        $pdo->exec("DROP TABLE IF EXISTS marketplace_connections");
        $pdo->exec("DROP TABLE IF EXISTS marketplaces");
        echo 'dropped marketplace_connections & marketplaces (rows with remembered keys: ' . $holding . ")\n";
    },
];
