<?php
// Copy to config/env.local.php (git-ignored) and edit. Production values come from REAL environment
// variables (Apache SetEnv / PHP-FPM env[] / systemd / CI secrets), never from a committed file.
return [
    'APP_ENV'  => 'local',            // anything other than 'local' = production rules (fail closed)
    'DB_HOST'  => '127.0.0.1',
    'DB_NAME'  => 'business_panel',
    'DB_USER'  => 'root',             // local only; production must use a dedicated DML-only user
    'DB_PASS'  => '',
    // 'DB_MIGRATE_USER' => 'panel_migrator',   // used by database/migrate.php only (DDL rights)
    // 'DB_MIGRATE_PASS' => '',                  // run database/tools/create_db_users.sql once, then put the generated passwords here (local) or in real env vars (production)
    // 'TRUSTED_PROXIES' => '203.0.113.10',      // comma-separated; only these may set X-Forwarded-For / CF-Connecting-IP
    // 'APP_SECRET_KEY'  => '',                  // P6: php database/tools/gen_secret_key.php
    // 'IMAGE_PROCESSING_FALLBACK' => 'refuse',  // 'passthrough' | 'refuse'. When GD is missing: passthrough stores validated images without re-encoding; refuse disables image upload. Default: refuse in production, passthrough otherwise.
];
