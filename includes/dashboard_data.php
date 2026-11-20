<?php
// Dashboard data providers (Part 10C). Read-only, prepared/static SQL, no HTML, no rendering.
// dashboardActivityLabel() is pure and unit-testable without a database.

/**
 * Persian one-line label for an activity_logs row joined with permissions.module_label.
 * Rows of a removed module (old rows are kept as audit trail) have no module_label and get the generic fallback.
 */
function dashboardActivityLabel(array $row): string
{
    $desc = trim((string)($row['description'] ?? ''));
    if ($desc !== '') {
        return mb_substr($desc, 0, 140, 'UTF-8');
    }
    $module = trim((string)($row['module_label'] ?? ''));
    return $module !== '' ? 'فعالیت در «' . $module . '»' : 'فعالیت ثبت‌شده';
}

/** Owner-only recent-activity feed: newest first, no IP address, $limit clamped to 1..20. Caller checks isOwner(). */
function dashboardRecentActivity(PDO $pdo, int $limit = 6): array
{
    $limit = max(1, min(20, $limit));
    $stmt = $pdo->query("SELECT l.id, l.action_type, l.module_key, l.description, l.created_at,
                                u.full_name, p.module_label
                         FROM activity_logs l
                         JOIN panel_users u ON u.id = l.panel_user_id
                         LEFT JOIN permissions p ON p.module_key = l.module_key
                         ORDER BY l.id DESC
                         LIMIT $limit");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['label'] = dashboardActivityLabel($r);
    }
    unset($r);
    return $rows;
}
