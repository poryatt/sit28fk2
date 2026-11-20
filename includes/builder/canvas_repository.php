<?php
// Canvas persistence for the visual page builder (Part 0).
// Owns the save transaction: canvas row + full block-tree replace.

/** Thrown by save() when expected_version no longer matches the stored version (someone else saved first). */
final class BuilderVersionConflictException extends RuntimeException
{
}

final class BuilderCanvasRepository
{
    /** A fresh [header, body, footer] tree with new uids. */
    public static function defaultTree(): array
    {
        $tree = [];
        foreach (['header', 'body', 'footer'] as $type) {
            $tree[] = [
                'uid' => bin2hex(random_bytes(16)),
                'type' => $type,
                'data' => BuilderBlockRegistry::defaultData($type),
                'children' => [],
            ];
        }
        return $tree;
    }

    /**
     * @return array{canvas: array, tree: array}|null
     */
    public static function load(PDO $pdo, int $canvasId): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM builder_canvases WHERE id = ?');
        $stmt->execute([$canvasId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $stmt = $pdo->prepare('SELECT id, parent_id, block_uid, block_type, data FROM builder_blocks WHERE canvas_id = ? ORDER BY parent_id IS NULL DESC, parent_id ASC, sort_order ASC');
        $stmt->execute([$canvasId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            return ['canvas' => self::formatCanvas($row), 'tree' => self::defaultTree()];
        }

        // Single pass: index by id, attach children, collect roots.
        $byId = [];
        foreach ($rows as $r) {
            $data = json_decode($r['data'], false);
            if (!($data instanceof stdClass)) {
                $data = BuilderBlockRegistry::defaultData($r['block_type']);
            }
            $byId[(int) $r['id']] = [
                'uid' => $r['block_uid'],
                'type' => $r['block_type'],
                'data' => $data,
                'children' => [],
                '_parent_id' => $r['parent_id'] === null ? null : (int) $r['parent_id'],
            ];
        }

        $tree = [];
        foreach ($byId as $id => &$node) {
            $parentId = $node['_parent_id'];
            unset($node['_parent_id']);
            if ($parentId === null || !isset($byId[$parentId])) {
                $tree[] = &$node;
            } else {
                $byId[$parentId]['children'][] = &$node;
            }
        }
        unset($node);

        // Re-index children lists so they encode as JSON arrays.
        $reindex = function (array &$nodes) use (&$reindex): void {
            $nodes = array_values($nodes);
            foreach ($nodes as &$n) {
                if (!empty($n['children'])) {
                    $reindex($n['children']);
                } else {
                    $n['children'] = [];
                }
            }
            unset($n);
        };
        $reindex($tree);

        return ['canvas' => self::formatCanvas($row), 'tree' => $tree];
    }

    /**
     * @return array{canvas: array, tree: array}|null
     */
    public static function findByTemplate(PDO $pdo, int $templateId): ?array
    {
        $stmt = $pdo->prepare('SELECT id FROM builder_canvases WHERE template_id = ? LIMIT 1');
        $stmt->execute([$templateId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return self::load($pdo, (int) $row['id']);
    }

    /**
     * Creates or replaces one canvas plus its whole block tree, transactionally.
     * Called only after validation has passed.
     *
     * $payload keys: buildable_type (string), template_id (?int),
     *                title (string), status (string), tree (array of stdClass nodes).
     * $expectedVersion is REQUIRED when updating ($canvasId !== null) and ignored when creating.
     *
     * @return array{canvas: array, block_count: int, buildable_type_ignored: bool}
     * @throws BuilderVersionConflictException when the canvas was saved by someone else since it was loaded
     * @throws InvalidArgumentException when a block's sanitize hook rejects its data or a referenced media id does not exist
     */
    public static function save(PDO $pdo, ?int $canvasId, array $payload, int $userId, ?int $expectedVersion = null): array
    {
        if ($canvasId !== null && ($expectedVersion === null || $expectedVersion < 1)) {
            throw new InvalidArgumentException('expected_version is required when updating a canvas');
        }
        $buildableTypeIgnored = false;
        $stored = null;

        if ($canvasId !== null) {
            $stmt = $pdo->prepare('SELECT id, slug, status, published_at, buildable_type, template_id FROM builder_canvases WHERE id = ?');
            $stmt->execute([$canvasId]);
            $stored = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$stored) {
                throw new RuntimeException('Canvas not found');
            }
        }

        $pdo->beginTransaction();
        try {
            if ($stored === null) {
                // New canvas: insert with slug = NULL; the slug is set in step 4.
                $templateId = $payload['template_id'] ?? null;
                $stmt = $pdo->prepare('INSERT INTO builder_canvases (buildable_type, template_id, title, slug, status, created_by, updated_by) VALUES (?,?,?,?,?,?,?)');
                $stmt->execute([
                    $payload['buildable_type'],
                    $templateId,
                    $payload['title'],
                    null,
                    $payload['status'],
                    $userId,
                    $userId,
                ]);
                $canvasId = (int) $pdo->lastInsertId();
                $storedBuildableType = $payload['buildable_type'];
                $storedTemplateId = $templateId === null ? null : (int) $templateId;
                $storedSlug = null;
                $storedStatus = $payload['status'];
                $storedPublishedAt = null;
            } else {
                // Existing canvas: buildable_type and template_id are immutable.
                $storedBuildableType = $stored['buildable_type'];
                $storedTemplateId = $stored['template_id'] === null ? null : (int) $stored['template_id'];
                $storedSlug = $stored['slug'];
                $storedStatus = $stored['status'];
                $storedPublishedAt = $stored['published_at'];
                if ($payload['buildable_type'] !== $storedBuildableType) {
                    $buildableTypeIgnored = true;
                }
                // Optimistic lock: the row is only updated while its version still equals the one the client loaded.
                $stmt = $pdo->prepare('UPDATE builder_canvases SET title = ?, status = ?, updated_by = ?, updated_at = NOW(), version = version + 1 WHERE id = ? AND version = ?');
                $stmt->execute([$payload['title'], $payload['status'], $userId, $canvasId, $expectedVersion]);
                if ($stmt->rowCount() !== 1) {
                    throw new BuilderVersionConflictException('Canvas was modified by someone else');
                }
                $storedStatus = $payload['status'];
            }

            // Step 4: slug. A stored non-empty slug is never regenerated.
            if ($storedSlug === null || $storedSlug === '') {
                $candidate = function_exists('slugify') ? slugify($payload['title']) : '';
                if ($candidate === '') {
                    $candidate = 'canvas-' . $canvasId;
                }
                // The UNIQUE index is the real guard: if another request wins the race for a slug (error 1062),
                // move on to the next candidate instead of failing the whole save (L11).
                $slugStmt = $pdo->prepare('UPDATE builder_canvases SET slug = ? WHERE id = ?');
                $slugSet = false;
                for ($n = 1; $n <= 50 && !$slugSet; $n++) {
                    $try = $n === 1 ? $candidate : $candidate . '-' . $n;
                    if (self::slugTaken($pdo, $try, $canvasId)) {
                        continue;
                    }
                    try {
                        $slugStmt->execute([$try, $canvasId]);
                        $slugSet = true;
                    } catch (PDOException $e) {
                        if ((int) ($e->errorInfo[1] ?? 0) !== 1062) {
                            throw $e;
                        }
                    }
                }
                if (!$slugSet) {
                    $slugStmt->execute(['canvas-' . $canvasId, $canvasId]);
                }
            }

            // Step 5: full replace of the block rows.
            $stmt = $pdo->prepare('DELETE FROM builder_blocks WHERE canvas_id = ?');
            $stmt->execute([$canvasId]);

            // Step 6: insert the tree in pre-order, depth first.
            // The tree already passed validation (depth <= 8), so a bounded
            // recursive helper is safe here.
            $blockCount = 0;
            $mediaIds = [];
            $insertStmt = $pdo->prepare('INSERT INTO builder_blocks (canvas_id, parent_id, block_uid, block_type, sort_order, data) VALUES (?,?,?,?,?,?)');
            $insertNode = null;
            $insertNode = function (stdClass $node, ?int $parentId, int $sortOrder) use (&$insertNode, &$blockCount, &$mediaIds, $insertStmt, $pdo, $canvasId): void {
                // H7: the type's sanitize hook is the only thing allowed to decide what reaches the database.
                $data = BuilderBlockRegistry::sanitizeData($node->type, $node->data, $pdo);
                foreach (BuilderBlockRegistry::extractMedia($node->type, $data) as $mediaId) {
                    $mediaIds[$mediaId] = $mediaId;
                }
                // No JSON_UNESCAPED_SLASHES-less storage: "/uploads/x" must be stored as-is (M5).
                $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($encoded === false) {
                    throw new InvalidArgumentException('Block data cannot be encoded');
                }
                $insertStmt->execute([$canvasId, $parentId, $node->uid, $node->type, $sortOrder, $encoded]);
                $blockCount++;
                $newId = (int) $pdo->lastInsertId();
                foreach (array_values($node->children) as $childIndex => $child) {
                    $insertNode($child, $newId, $childIndex);
                }
            };
            foreach (array_values($payload['tree']) as $index => $node) {
                $insertNode($node, null, $index);
            }

            // Step 6b: media references (FK ... ON DELETE RESTRICT keeps in-use files from being deleted).
            BuilderMediaRefs::sync($pdo, $canvasId, array_values($mediaIds));

            // Step 7: title write-through, only for the ACTIVE template's canvas.
            if ($storedBuildableType === 'template' && $storedTemplateId !== null) {
                $stmt = $pdo->prepare('SELECT active_template_id FROM site_settings WHERE id = 1');
                $stmt->execute();
                $settings = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($settings && (int) $settings['active_template_id'] === (int) $storedTemplateId) {
                    $stmt = $pdo->prepare('UPDATE site_settings SET site_name = ? WHERE id = 1');
                    $stmt->execute([$payload['title']]);
                }
            }

            // Step 8: stamp published_at once.
            if ($storedStatus === 'published' && $storedPublishedAt === null) {
                $stmt = $pdo->prepare('UPDATE builder_canvases SET published_at = NOW() WHERE id = ?');
                $stmt->execute([$canvasId]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $fresh = self::load($pdo, $canvasId);
        return [
            'canvas' => $fresh['canvas'],
            'block_count' => $blockCount,
            'buildable_type_ignored' => $buildableTypeIgnored,
        ];
    }

    private static function slugTaken(PDO $pdo, string $slug, int $exceptId): bool
    {
        $stmt = $pdo->prepare('SELECT id FROM builder_canvases WHERE slug = ? AND id <> ? LIMIT 1');
        $stmt->execute([$slug, $exceptId]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /** Formats a canvas row per §8.2. */
    public static function formatCanvas(array $row): array
    {
        $updatedAt = $row['updated_at'] ?? null;
        $jalali = '-';
        if ($updatedAt !== null && function_exists('toJalaliDateTime')) {
            $jalali = toJalaliDateTime($updatedAt);
        }
        return [
            'id' => $row['id'] === null ? null : (int) $row['id'],
            'buildable_type' => $row['buildable_type'],
            'template_id' => $row['template_id'] === null ? null : (int) $row['template_id'],
            'title' => $row['title'],
            'slug' => $row['slug'],
            'status' => $row['status'],
            'version' => isset($row['version']) ? (int) $row['version'] : null,
            'published_at' => $row['published_at'],
            'updated_at' => $updatedAt,
            'updated_at_jalali' => $jalali,
        ];
    }
}
