<?php
// Tracks which media_library rows a canvas uses (table builder_media_refs, FK media_id ... ON DELETE RESTRICT).
// All write methods run inside the caller's transaction (BuilderCanvasRepository::save).

final class BuilderMediaRefs
{
    /**
     * @param int[] $ids
     * @return int[] the subset of $ids that exist in media_library
     */
    public static function existingIds(PDO $pdo, array $ids): array
    {
        $ids = self::uniqueInts($ids);
        if (!$ids) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare('SELECT id FROM media_library WHERE id IN (' . $in . ')');
        $stmt->execute($ids);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Replaces the reference set of one canvas. Throws InvalidArgumentException when an id is unknown.
     * @param int[] $mediaIds
     */
    public static function sync(PDO $pdo, int $canvasId, array $mediaIds): void
    {
        $mediaIds = self::uniqueInts($mediaIds);
        if ($mediaIds && count(self::existingIds($pdo, $mediaIds)) !== count($mediaIds)) {
            throw new InvalidArgumentException('Unknown media id');
        }
        $pdo->prepare('DELETE FROM builder_media_refs WHERE canvas_id = ?')->execute([$canvasId]);
        if ($mediaIds) {
            $insert = $pdo->prepare('INSERT INTO builder_media_refs (canvas_id, media_id) VALUES (?, ?)');
            foreach ($mediaIds as $mediaId) {
                $insert->execute([$canvasId, $mediaId]);
            }
        }
    }

    /** @return array<int, array{id:int,title:string}> canvases that use this media file (at most $limit) */
    public static function canvasesUsing(PDO $pdo, int $mediaId, int $limit = 6): array
    {
        $limit = max(1, min(50, $limit));
        $stmt = $pdo->prepare('SELECT c.id, c.title FROM builder_media_refs r JOIN builder_canvases c ON c.id = r.canvas_id WHERE r.media_id = ? ORDER BY c.id LIMIT ' . $limit);
        $stmt->execute([$mediaId]);
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = ['id' => (int)$row['id'], 'title' => (string)$row['title']];
        }
        return $rows;
    }

    public static function countUsing(PDO $pdo, int $mediaId): int
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM builder_media_refs WHERE media_id = ?');
        $stmt->execute([$mediaId]);
        return (int)$stmt->fetchColumn();
    }

    /** @return int[] */
    private static function uniqueInts(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            $i = (int)$id;
            if ($i > 0) {
                $out[$i] = $i;
            }
        }
        return array_values($out);
    }
}
