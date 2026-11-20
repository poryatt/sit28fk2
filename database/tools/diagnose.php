<?php
// Diagnostic suite for the hardening job (P0 §P0.5, full D01–D26).
// CLI only. Read-only queries. Usage: php database/tools/diagnose.php [--phase=N]
// --phase=N runs only checks with since <= N. A check whose table/column does not
// exist yet is SKIP, not FAIL. Exit code 1 if any FAIL.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../lib/Migrator.php';

$phase = null;
foreach ($argv as $a) {
    if (preg_match('/^--phase=(\d+)$/', $a, $m)) { $phase = (int)$m[1]; }
}
if ($phase === null) { $phase = 99; }

try {
    $pdo = getPDO();
} catch (Throwable $e) {
    fwrite(STDERR, "Cannot connect to database.\n");
    exit(1);
}
$m = new Migrator($pdo);

$isMaria = stripos((string)$pdo->query("SELECT VERSION()")->fetchColumn(), 'mariadb') !== false;

$results = [];
function report(string $id, int $since, string $status, string $detail): void {
    global $results, $phase;
    if ($since > $phase) { return; }
    $results[] = [$id, $status, $detail];
    echo "$id [$status] $detail\n";
}
function skipIfMissingTable(string $id, int $since, string $title, string $table): bool {
    global $m;
    if (!$m->tableExists($table)) { report($id, $since, 'SKIP', "$title — table $table does not exist yet"); return true; }
    return false;
}

// ---- D01 (since 1): impossible permission rows → 0 rows ----
if (1 <= $phase) {
    if (!$m->tableExists('panel_user_permissions')) {
        report('D01', 1, 'SKIP', 'impossible permission rows — table panel_user_permissions does not exist yet');
    } else {
        $rows = (int)$pdo->query("SELECT COUNT(*) FROM panel_user_permissions WHERE (can_edit=1 AND can_view=0) OR (can_delete=1 AND can_edit=0)")->fetchColumn();
        report('D01', 1, $rows === 0 ? 'PASS' : 'FAIL', "impossible permission rows: $rows (expect 0)");
    }
}

// ---- D02 (since 1): staff with no permission row → 0 rows ----
if (1 <= $phase) {
    if (!$m->tableExists('panel_users') || !$m->tableExists('panel_user_permissions')) {
        report('D02', 1, 'SKIP', 'staff with no permission row — table missing');
    } else {
        $hasStatus = $m->columnExists('panel_users', 'status');
        $filter = $hasStatus ? "WHERE u.role='staff' AND u.status<>'removed'" : "WHERE u.role='staff'";
        $rows = $pdo->query("SELECT u.id, u.username FROM panel_users u LEFT JOIN panel_user_permissions p ON p.panel_user_id=u.id $filter GROUP BY u.id, u.username HAVING COUNT(p.id)=0")->fetchAll();
        $n = count($rows);
        $who = $n ? ' (' . implode(',', array_map(function ($r) { return $r['id'] . ':' . $r['username']; }, $rows)) . ')' : '';
        report('D02', 1, $n === 0 ? 'PASS' : 'FAIL', "staff with no permission row: $n (expect 0)$who");
    }
}

// ---- D03 (since 1): seed password still in use → WARN if rows ----
if (1 <= $phase) {
    if (!$m->tableExists('panel_users')) {
        report('D03', 1, 'SKIP', 'seed password — table panel_users does not exist yet');
    } else {
        $seed = '$2y$10$cnvXc0McHVEK5Nml0ZvMwepHQ8jERISPDuFgn9BuwBsO6CZ5utTO.';
        $st = $pdo->prepare("SELECT id, username FROM panel_users WHERE password_hash = ?");
        $st->execute([$seed]);
        $rows = $st->fetchAll();
        $n = count($rows);
        $who = $n ? ' (' . implode(',', array_map(function ($r) { return $r['id'] . ':' . $r['username']; }, $rows)) . ')' : '';
        report('D03', 1, $n === 0 ? 'PASS' : 'WARN', "accounts with seed password: $n$who");
    }
}

// ---- D04 (since 1): CHECK chk_pup_logic ----
if (1 <= $phase) {
    if (!$m->tableExists('panel_user_permissions')) {
        report('D04', 1, 'SKIP', 'chk_pup_logic — table panel_user_permissions does not exist yet');
    } elseif ($m->checkExists('panel_user_permissions', 'chk_pup_logic')) {
        report('D04', 1, 'PASS', 'CHECK chk_pup_logic present');
    } else {
        if (!$isMaria) {
            $v = (string)$pdo->query("SELECT VERSION()")->fetchColumn();
            if (preg_match('/^5\.7\./', $v)) {
                report('D04', 1, 'WARN', 'CHECK chk_pup_logic absent on MySQL 5.7 (parses and ignores CHECK)');
            } else {
                report('D04', 1, 'FAIL', 'CHECK chk_pup_logic absent (expect present)');
            }
        } else {
            report('D04', 1, 'FAIL', 'CHECK chk_pup_logic absent (expect present)');
        }
    }
}

// ---- D05 (since 2): usernames violating policy → WARN ----
if (2 <= $phase) {
    if (!$m->tableExists('panel_users')) {
        report('D05', 2, 'SKIP', 'username policy — table panel_users does not exist yet');
    } else {
        $hasStatus = $m->columnExists('panel_users', 'status');
        $rows = $pdo->query("SELECT id, username FROM panel_users" . ($hasStatus ? " WHERE status<>'removed'" : ""))->fetchAll();
        $bad = [];
        foreach ($rows as $r) {
            if (!preg_match('/^[A-Za-z0-9_.-]{3,30}$/D', (string)$r['username'])) { $bad[] = $r['id'] . ':' . $r['username']; }
        }
        report('D05', 2, empty($bad) ? 'PASS' : 'WARN', 'usernames violating policy: ' . count($bad) . ($bad ? ' (' . implode(',', $bad) . ')' : ''));
    }
}

// ---- D06 (since 2): login_attempts / activity_logs size + oldest → INFO ----
if (2 <= $phase) {
    $parts = [];
    if ($m->tableExists('login_attempts')) {
        $r = $pdo->query("SELECT COUNT(*), MIN(attempted_at) FROM login_attempts")->fetch(PDO::FETCH_NUM);
        $parts[] = "login_attempts={$r[0]} oldest=" . ($r[1] ?? '-');
    } else {
        $parts[] = "login_attempts table missing";
    }
    if ($m->tableExists('activity_logs')) {
        $r = $pdo->query("SELECT COUNT(*), MIN(created_at) FROM activity_logs")->fetch(PDO::FETCH_NUM);
        $parts[] = "activity_logs={$r[0]} oldest=" . ($r[1] ?? '-');
    } else {
        $parts[] = "activity_logs table missing";
    }
    report('D06', 2, 'INFO', implode('; ', $parts));
}

// ---- D07 (since 3): canvases with inconsistent type/template → 0 rows ----
if (3 <= $phase) {
    if (!$m->tableExists('builder_canvases')) {
        report('D07', 3, 'SKIP', 'canvas type/template consistency — table builder_canvases does not exist yet');
    } else {
        $n = (int)$pdo->query("SELECT COUNT(*) FROM builder_canvases WHERE (buildable_type='template' AND template_id IS NULL) OR (buildable_type='page' AND template_id IS NOT NULL)")->fetchColumn();
        report('D07', 3, $n === 0 ? 'PASS' : 'FAIL', "inconsistent canvases: $n (expect 0)");
    }
}

// ---- D08 (since 3): block whose parent is in another canvas → 0 rows ----
if (3 <= $phase) {
    if (!$m->tableExists('builder_blocks')) {
        report('D08', 3, 'SKIP', 'cross-canvas parent — table builder_blocks does not exist yet');
    } else {
        $n = (int)$pdo->query("SELECT COUNT(*) FROM builder_blocks b JOIN builder_blocks p ON p.id=b.parent_id WHERE p.canvas_id<>b.canvas_id")->fetchColumn();
        report('D08', 3, $n === 0 ? 'PASS' : 'FAIL', "blocks with parent in another canvas: $n (expect 0)");
    }
}

// ---- D09 (since 3): duplicate sort_order among siblings → 0 rows ----
if (3 <= $phase) {
    if (!$m->tableExists('builder_blocks')) {
        report('D09', 3, 'SKIP', 'duplicate sort_order — table builder_blocks does not exist yet');
    } else {
        $n = (int)$pdo->query("SELECT COUNT(*) FROM (SELECT canvas_id, parent_id, sort_order, COUNT(*) n FROM builder_blocks GROUP BY canvas_id, parent_id, sort_order HAVING n>1) t")->fetchColumn();
        report('D09', 3, $n === 0 ? 'PASS' : 'FAIL', "duplicate sort_order groups: $n (expect 0)");
    }
}

// ---- D10 (since 3): canvases whose root count ≠ 3 → INFO ----
if (3 <= $phase) {
    if (!$m->tableExists('builder_canvases') || !$m->tableExists('builder_blocks')) {
        report('D10', 3, 'SKIP', 'root count — builder tables do not exist yet');
    } else {
        $rows = $pdo->query("SELECT c.id, COUNT(b.id) roots FROM builder_canvases c LEFT JOIN builder_blocks b ON b.canvas_id=c.id AND b.parent_id IS NULL GROUP BY c.id HAVING roots<>3")->fetchAll();
        $n = count($rows);
        report('D10', 3, 'INFO', "canvases with root count <> 3: $n (0 roots is legal; load supplies the default tree)");
    }
}

// ---- D11 (since 2/3/5): FK delete rules ----
if (2 <= $phase) {
    $map = [
        ['fk_builder_canvases_creator', 'builder_canvases', 'RESTRICT', 2],
        ['fk_media_uploader', 'media_library', 'RESTRICT', 2],
        ['fk_logs_user', 'activity_logs', 'SET NULL', 2],
        ['fk_builder_canvases_template', 'builder_canvases', 'CASCADE', 3],
        ['fk_builder_blocks_parent_same_canvas', 'builder_blocks', 'CASCADE', 3],
        ['fk_bmr_canvas', 'builder_media_refs', 'CASCADE', 3],
        ['fk_bmr_media', 'builder_media_refs', 'RESTRICT', 3],
        ['fk_settings_template', 'site_settings', 'RESTRICT', 5],
    ];
    $due = array_values(array_filter($map, function ($e) use ($phase) { return $e[3] <= $phase; }));
    if (empty($due)) {
        report('D11', 2, 'SKIP', 'no FK in the expected map is due at this phase yet');
    } else {
        $problems = [];
        foreach ($due as $e) {
            [$fk, $table, $want, $fkPhase] = $e;
            if (!$m->tableExists($table)) { $problems[] = "$fk: table $table missing"; continue; }
            $got = $m->fkRule($table, $fk);
            if ($got === null) { $problems[] = "$fk: missing (expect $want)"; }
            elseif (strtoupper($got) !== $want) { $problems[] = "$fk: $got (expect $want)"; }
        }
        report('D11', 2, empty($problems) ? 'PASS' : 'FAIL', empty($problems) ? 'FK delete rules match the expected map' : implode('; ', $problems));
    }
}

// ---- D12 (since 4): duplicate (purpose,sha256) → 0 rows ----
if (4 <= $phase) {
    if (!$m->tableExists('media_library') || !$m->columnExists('media_library', 'sha256') || !$m->columnExists('media_library', 'purpose')) {
        report('D12', 4, 'SKIP', 'duplicate (purpose,sha256) — columns do not exist yet');
    } else {
        $n = (int)$pdo->query("SELECT COUNT(*) FROM (SELECT purpose, sha256, COUNT(*) n FROM media_library WHERE sha256 IS NOT NULL GROUP BY purpose, sha256 HAVING n>1) t")->fetchColumn();
        report('D12', 4, $n === 0 ? 'PASS' : 'FAIL', "duplicate (purpose,sha256) groups: $n (expect 0)");
    }
}

// ---- D13 (since 4): media_library rows with NULL sha256 → INFO ----
if (4 <= $phase) {
    if (!$m->tableExists('media_library') || !$m->columnExists('media_library', 'sha256')) {
        report('D13', 4, 'SKIP', 'NULL sha256 — column does not exist yet');
    } else {
        $n = (int)$pdo->query("SELECT COUNT(*) FROM media_library WHERE sha256 IS NULL")->fetchColumn();
        report('D13', 4, 'INFO', "media rows with NULL sha256: $n (run the backfill tool to reduce)");
    }
}

// ---- D14 (since 4): licence certificates under /uploads/ → 0 ----
if (4 <= $phase) {
    if (!$m->tableExists('licenses')) {
        report('D14', 4, 'SKIP', 'licence certificates — table licenses does not exist yet');
    } else {
        $n = (int)$pdo->query("SELECT COUNT(*) FROM licenses WHERE certificate_file LIKE '/uploads/%'")->fetchColumn();
        report('D14', 4, $n === 0 ? 'PASS' : 'FAIL', "licence certificates under /uploads/: $n (expect 0)");
    }
}

// ---- D15 (since 5): legacy tables gone ----
if (5 <= $phase) {
    $legacy = ['pages', 'page_blocks', 'articles', 'article_blocks', 'article_categories', 'media_usage'];
    $present = [];
    foreach ($legacy as $t) { if ($m->tableExists($t)) { $present[] = $t; } }
    report('D15', 5, empty($present) ? 'PASS' : 'FAIL', empty($present) ? 'legacy tables gone' : 'legacy tables still present: ' . implode(',', $present));
}

// ---- D16 (since 5): templates.is_active absent; active_template_id valid ----
if (5 <= $phase) {
    $notes = [];
    $ok = true;
    if ($m->tableExists('templates') && $m->columnExists('templates', 'is_active')) { $ok = false; $notes[] = 'templates.is_active still exists'; }
    if ($m->tableExists('site_settings')) {
        $row = $pdo->query("SELECT active_template_id FROM site_settings WHERE id=1")->fetch();
        $aid = $row ? $row['active_template_id'] : null;
        if ($aid !== null) {
            $st = $pdo->prepare("SELECT id FROM templates WHERE id = ?");
            $st->execute([$aid]);
            if (!$st->fetch()) { $ok = false; $notes[] = "active_template_id=$aid points to a missing template"; }
        }
    }
    report('D16', 5, $ok ? 'PASS' : 'FAIL', $notes ? implode('; ', $notes) : 'templates.is_active absent; active_template_id valid');
}

// ---- D17 (since 5): template_categories has no slug 'all' ----
if (5 <= $phase) {
    if (!$m->tableExists('template_categories')) {
        report('D17', 5, 'SKIP', 'template_categories table does not exist yet');
    } else {
        $n = (int)$pdo->query("SELECT COUNT(*) FROM template_categories WHERE slug = 'all'")->fetchColumn();
        report('D17', 5, $n === 0 ? 'PASS' : 'FAIL', "template_categories with slug 'all': $n (expect 0)");
    }
}

// ---- D18 (since 6): plaintext secrets → 0 ----
if (6 <= $phase) {
    $problems = [];
    if ($m->tableExists('marketplace_connections')) {
        $rows = $pdo->query("SELECT id, api_key, api_secret FROM marketplace_connections")->fetchAll();
        foreach ($rows as $r) {
            foreach (['api_key', 'api_secret'] as $c) {
                $v = $r[$c];
                if ($v !== null && $v !== '' && strncmp($v, 'v1:', 3) !== 0) { $problems[] = "marketplace_connections#{$r['id']}.$c plaintext"; }
            }
        }
    }
    if ($m->tableExists('licenses')) {
        $rows = $pdo->query("SELECT id, license_type, credentials FROM licenses WHERE license_type='zarinpal'")->fetchAll();
        foreach ($rows as $r) {
            $creds = json_decode((string)$r['credentials'], true);
            $k = is_array($creds) ? (string)($creds['api_key'] ?? '') : '';
            if ($k !== '' && strncmp($k, 'v1:', 3) !== 0) { $problems[] = "licenses#{$r['id']} api_key plaintext"; }
        }
    }
    report('D18', 6, empty($problems) ? 'PASS' : 'FAIL', $problems ? implode('; ', $problems) : 'no plaintext secrets');
}

// ---- D19 (since 2): LOGIN_DUMMY_HASH / SEED_PASSWORD_HASH are valid bcrypt ----
if (2 <= $phase) {
    if (!defined('LOGIN_DUMMY_HASH') || !defined('SEED_PASSWORD_HASH')) {
        report('D19', 2, 'SKIP', 'LOGIN_DUMMY_HASH / SEED_PASSWORD_HASH not defined yet (P2 adds them)');
    } else {
        $ok = true;
        $notes = [];
        foreach (['LOGIN_DUMMY_HASH' => LOGIN_DUMMY_HASH, 'SEED_PASSWORD_HASH' => SEED_PASSWORD_HASH] as $name => $h) {
            $info = password_get_info($h);
            if ($info['algoName'] !== 'bcrypt' || (int)($info['options']['cost'] ?? 0) !== 10) {
                $ok = false; $notes[] = "$name is not bcrypt cost 10";
            }
        }
        report('D19', 2, $ok ? 'PASS' : 'FAIL', $notes ? implode('; ', $notes) : 'both hashes are bcrypt cost 10');
    }
}

// ---- D20 (since 2): removed staff keep no permission rows and no avatar → 0 ----
if (2 <= $phase) {
    if (!$m->tableExists('panel_users')) {
        report('D20', 2, 'SKIP', 'removed staff — table panel_users does not exist yet');
    } elseif (!$m->columnExists('panel_users', 'status')) {
        report('D20', 2, 'SKIP', "removed staff — status enum has no 'removed' yet");
    } else {
        $rows = $pdo->query("SELECT u.id FROM panel_users u WHERE u.status='removed' AND (u.avatar_path IS NOT NULL OR EXISTS (SELECT 1 FROM panel_user_permissions p WHERE p.panel_user_id=u.id))")->fetchAll();
        $n = count($rows);
        report('D20', 2, $n === 0 ? 'PASS' : 'FAIL', "removed staff with avatar/permission rows: $n (expect 0)");
    }
}

// ---- D21 (since 3): block_uid collation ascii_bin; canvases.version exists ----
if (3 <= $phase) {
    if (!$m->tableExists('builder_blocks') || !$m->tableExists('builder_canvases')) {
        report('D21', 3, 'SKIP', 'builder uid/version — builder tables do not exist yet');
    } else {
        $ok = true;
        $notes = [];
        $col = $m->collation('builder_blocks', 'block_uid');
        if ($col !== 'ascii_bin') { $ok = false; $notes[] = "block_uid collation=$col (expect ascii_bin)"; }
        if (!$m->columnExists('builder_canvases', 'version')) { $ok = false; $notes[] = 'builder_canvases.version missing'; }
        report('D21', 3, $ok ? 'PASS' : 'FAIL', $notes ? implode('; ', $notes) : 'block_uid ascii_bin; canvases.version exists');
    }
}

// ---- D22 (since 4): public asset paths registered in media_library → INFO ----
if (4 <= $phase) {
    if (!$m->tableExists('media_library')) {
        report('D22', 4, 'SKIP', 'asset registration — table media_library does not exist yet');
    } else {
        $paths = [];
        if ($m->tableExists('site_settings')) {
            $s = $pdo->query("SELECT logo_path, favicon_path FROM site_settings WHERE id=1")->fetch();
            if ($s) { foreach (['logo_path', 'favicon_path'] as $c) { if (!empty($s[$c]) && strpos($s[$c], '/uploads/') === 0) { $paths[] = $s[$c]; } } }
        }
        if ($m->tableExists('panel_users')) {
            foreach ($pdo->query("SELECT avatar_path FROM panel_users WHERE avatar_path LIKE '/uploads/%'")->fetchAll(PDO::FETCH_COLUMN) as $p) { $paths[] = $p; }
        }
        $paths = array_values(array_unique($paths));
        $unreg = [];
        if ($paths) {
            $reg = $pdo->query("SELECT file_path FROM media_library")->fetchAll(PDO::FETCH_COLUMN);
            $regSet = array_flip($reg);
            foreach ($paths as $p) { if (!isset($regSet[$p])) { $unreg[] = $p; } }
        }
        report('D22', 4, 'INFO', $unreg ? ('unregistered asset paths: ' . count($unreg) . ' (' . implode(',', $unreg) . ') — run backfill_files.php') : ('all ' . count($paths) . ' public asset path(s) registered'));
    }
}

// ---- D23 (since 8): published canvases with an empty slug → 0 rows ----
if (8 <= $phase) {
    if (!$m->tableExists('builder_canvases')) {
        report('D23', 8, 'SKIP', 'published empty slug — table builder_canvases does not exist yet');
    } else {
        $n = (int)$pdo->query("SELECT COUNT(*) FROM builder_canvases WHERE status='published' AND (slug IS NULL OR slug='')")->fetchColumn();
        report('D23', 8, $n === 0 ? 'PASS' : 'FAIL', "published canvases with empty slug: $n (expect 0)");
    }
}

// ---- D24 (since 8): published canvases with published_at NULL → 0 rows ----
if (8 <= $phase) {
    if (!$m->tableExists('builder_canvases')) {
        report('D24', 8, 'SKIP', 'published_at — table builder_canvases does not exist yet');
    } else {
        $n = (int)$pdo->query("SELECT COUNT(*) FROM builder_canvases WHERE status='published' AND published_at IS NULL")->fetchColumn();
        report('D24', 8, $n === 0 ? 'PASS' : 'WARN', "published canvases with published_at NULL: $n (expect 0)");
    }
}

// ---- D25 (since 8): builder table collation matches panel_users → WARN on drift ----
if (8 <= $phase) {
    $builderTables = ['builder_canvases', 'builder_blocks', 'builder_media_refs'];
    $present = [];
    foreach ($builderTables as $t) { if ($m->tableExists($t)) { $present[] = $t; } }
    if (!$m->tableExists('panel_users') || empty($present)) {
        report('D25', 8, 'SKIP', 'collation parity — panel_users or builder tables missing');
    } else {
        $q = $pdo->prepare("SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $q->execute(['panel_users']);
        $ref = (string)$q->fetchColumn();
        $bad = [];
        foreach ($present as $t) {
            $q->execute([$t]);
            $c = (string)$q->fetchColumn();
            if ($c !== '' && strcasecmp($c, $ref) !== 0) { $bad[] = "$t=$c"; }
        }
        report('D25', 8, empty($bad) ? 'PASS' : 'WARN', empty($bad) ? "builder table collations match panel_users ($ref)" : ("collations differ from panel_users ($ref): " . implode(', ', $bad)));
    }
}

// ---- D26 (since 8): builder_media_refs orphan media_id → FAIL; site_name INFO ----
if (8 <= $phase) {
    $st = 'PASS';
    $notes = [];
    if (!$m->tableExists('builder_media_refs') || !$m->tableExists('media_library')) {
        $st = 'SKIP';
        $notes[] = 'orphan media refs — builder_media_refs/media_library missing';
    } else {
        $n = (int)$pdo->query("SELECT COUNT(*) FROM builder_media_refs r LEFT JOIN media_library m ON m.id = r.media_id WHERE m.id IS NULL")->fetchColumn();
        if ($n > 0) { $st = 'FAIL'; $notes[] = "builder_media_refs rows with missing media_id: $n (expect 0)"; }
    }
    if ($m->tableExists('site_settings') && $m->tableExists('builder_canvases')) {
        $row = $pdo->query("SELECT s.site_name, c.title FROM site_settings s LEFT JOIN builder_canvases c ON c.buildable_type='template' AND c.template_id = s.active_template_id WHERE s.id = 1")->fetch();
        if ($row && $row['title'] !== null && (string)$row['site_name'] !== (string)$row['title']) {
            $notes[] = "INFO site_settings.site_name ({$row['site_name']}) differs from active template canvas title ({$row['title']})";
        }
    }
    report('D26', 8, $st, $notes ? implode('; ', $notes) : 'no orphan media refs');
}

$fail = 0;
foreach ($results as $r) { if ($r[1] === 'FAIL') { $fail++; } }
if (empty($results)) { echo "No checks due at this phase.\n"; }
echo "summary: " . count($results) . " check(s), $fail FAIL\n";
exit($fail > 0 ? 1 : 0);
