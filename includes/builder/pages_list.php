<?php
// Pages manager — data access + pure filter normalisation (Part 8).
// No block-type knowledge, no rendering, no HTML. DB access is prepared-only
// and never interpolates user input; LIMIT/OFFSET are interpolated only after
// (int) casts. The pure helpers (normalizeFilters) are unit-tested without a DB.

final class BuilderPagesList
{
    /** Normalises the list query string. Pure, no DB. Unknown/absent status ⇒ 'all'. */
    public static function normalizeFilters(array $get): array
    {
        $status = 'all';
        if (isset($get['status']) && is_string($get['status'])
            && in_array($get['status'], ['all', 'published', 'draft'], true)) {
            $status = $get['status'];
        }

        $q = '';
        if (isset($get['q']) && is_string($get['q'])) {
            $q = mb_substr(trim($get['q']), 0, 100, 'UTF-8');
        }

        $page = 1;
        if (isset($get['page']) && is_numeric($get['page'])) {
            $n = (int) $get['page'];
            if ($n >= 1) {
                $page = $n;
            }
        }

        return ['status' => $status, 'q' => $q, 'page' => $page];
    }

    /** LIKE pattern for a user string, matching the media module's escaping. */
    private static function likePattern(string $q): string
    {
        return '%' . str_replace(['|', '%', '_'], ['||', '|%', '|_'], $q) . '%';
    }

    /** Builds a list URL. Pure. Keeps status, drops an empty q, adds page only when overridden and > 1. */
    public static function url(array $filters, array $overrides = []): string
    {
        $status = array_key_exists('status', $overrides) ? $overrides['status'] : ($filters['status'] ?? 'all');
        $q = array_key_exists('q', $overrides) ? $overrides['q'] : ($filters['q'] ?? '');
        $params = ['status' => $status];
        if ($q !== '' && $q !== null) {
            $params['q'] = $q;
        }
        if (array_key_exists('page', $overrides)) {
            $page = (int) $overrides['page'];
            if ($page > 1) {
                $params['page'] = $page;
            }
        }
        return '/modules/articles/index.php?' . http_build_query($params);
    }

    /** Unfiltered count of page canvases: 0 means no page exists at all. */
    public static function totalPages(PDO $pdo): int
    {
        return (int) $pdo->query("SELECT COUNT(*) FROM builder_canvases WHERE buildable_type = 'page'")->fetchColumn();
    }

    /** Page-canvas status counts respecting the search term. */
    public static function counts(PDO $pdo, string $q): array
    {
        $sql = "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) AS published,
                    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) AS draft
                FROM builder_canvases
                WHERE buildable_type = 'page'";
        $params = [];
        if ($q !== '') {
            $sql .= " AND (title LIKE ? ESCAPE '|' OR slug LIKE ? ESCAPE '|')";
            $like = self::likePattern($q);
            $params[] = $like;
            $params[] = $like;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'all' => (int) ($row['total'] ?? 0),
            'published' => (int) ($row['published'] ?? 0),
            'draft' => (int) ($row['draft'] ?? 0),
        ];
    }

    /** One page of page canvases, newest first. Clamps the requested page to the last page. */
    public static function fetchPage(PDO $pdo, array $filters, int $perPage = 20): array
    {
        $perPage = max(1, (int) $perPage);
        $status = $filters['status'] ?? 'all';
        $q = (string) ($filters['q'] ?? '');
        $page = max(1, (int) ($filters['page'] ?? 1));

        $where = ["c.buildable_type = 'page'"];
        $params = [];
        if ($status === 'published' || $status === 'draft') {
            $where[] = 'c.status = ?';
            $params[] = $status;
        }
        if ($q !== '') {
            $where[] = "(c.title LIKE ? ESCAPE '|' OR c.slug LIKE ? ESCAPE '|')";
            $like = self::likePattern($q);
            $params[] = $like;
            $params[] = $like;
        }
        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM builder_canvases c $whereSql");
        $totalStmt->execute($params);
        $total = (int) $totalStmt->fetchColumn();

        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT c.id, c.title, c.slug, c.status, c.published_at, c.updated_at,
                       (SELECT COUNT(*) FROM builder_blocks b WHERE b.canvas_id = c.id) AS block_count,
                       u.full_name AS creator_name
                FROM builder_canvases c
                LEFT JOIN panel_users u ON u.id = c.created_by
                $whereSql
                ORDER BY c.updated_at DESC, c.id DESC
                LIMIT $perPage OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['rows' => $rows, 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    /** The $limit most recently updated page canvases for the dashboard (id, title, slug, status, updated_at). Limit clamped to 1..20. */
    public static function latest(PDO $pdo, int $limit = 6): array
    {
        $limit = max(1, min(20, $limit));
        $stmt = $pdo->query("SELECT id, title, slug, status, updated_at
                             FROM builder_canvases
                             WHERE buildable_type = 'page'
                             ORDER BY updated_at DESC, id DESC
                             LIMIT $limit");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Hard-deletes a page canvas. Returns 'deleted' | 'not_found' | 'not_a_page'. */
    public static function deletePage(PDO $pdo, int $id): string
    {
        $lookup = $pdo->prepare('SELECT buildable_type FROM builder_canvases WHERE id = ? LIMIT 1');
        $lookup->execute([$id]);
        $row = $lookup->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return 'not_found';
        }
        if (($row['buildable_type'] ?? '') !== 'page') {
            return 'not_a_page';
        }
        $del = $pdo->prepare("DELETE FROM builder_canvases WHERE id = ? AND buildable_type = 'page'");
        $del->execute([$id]);
        if ($del->rowCount() === 0) {
            return 'not_found';
        }
        return 'deleted';
    }
}
