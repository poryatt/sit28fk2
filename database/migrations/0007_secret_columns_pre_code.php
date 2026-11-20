<?php
// Phase 6 — [pre-code]: widen the columns that will hold "v1:" + base64(nonce || ciphertext). Run FIRST in P6.
return [
    'description' => 'Secrets: widen marketplace_connections.api_key / api_secret to VARCHAR(512)',
    'up' => function (PDO $pdo, Migrator $m): void {
        foreach (['api_key', 'api_secret'] as $col) {
            if ($m->columnType('marketplace_connections', $col) !== 'varchar(512)') {
                $pdo->exec("ALTER TABLE marketplace_connections MODIFY $col VARCHAR(512) DEFAULT NULL");
            }
        }
    },
];
