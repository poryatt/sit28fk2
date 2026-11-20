<?php
// Content block type: story (Part 6).
// Instagram-style circles: per-circle title + cover, multi-media items per circle.
// Video items are mp4 only (matching the media library). Max 10 circles, 10 items each.

BuilderBlockRegistry::register([
    'type' => 'story',
    'label' => 'استوری',
    'icon' => 'ti-circle',
    'category' => 'content',
    'allows_children' => false,
    'min_children' => 0,
    'max_children' => 0,
    'supports_text_formatting' => false,
    'allowed_child_types' => [],
    'allowed_parent_types' => [],
    'default_data' => ['circles' => []],
    'sanitize' => function (stdClass $data, PDO $pdo): stdClass {
        $circles = [];
        if (isset($data->circles) && is_array($data->circles)) {
            foreach (array_slice(array_values($data->circles), 0, 10) as $c) {
                if (!($c instanceof stdClass)) {
                    continue;
                }
                $title = isset($c->title) && is_string($c->title) ? trim($c->title) : '';
                $title = mb_substr($title, 0, 100, 'UTF-8');
                if ($title === '') {
                    $title = 'استوری';
                }
                $coverId = null;
                if (isset($c->cover_media_id) && is_int($c->cover_media_id) && $c->cover_media_id > 0) {
                    $coverId = $c->cover_media_id;
                } elseif (isset($c->cover_media_id) && is_numeric($c->cover_media_id) && (int) $c->cover_media_id > 0) {
                    $coverId = (int) $c->cover_media_id;
                }
                if ($coverId !== null) {
                    $found = BuilderMediaRefs::existingIds($pdo, [$coverId]);
                    if (count($found) !== 1) {
                        throw new InvalidArgumentException('Unknown media id');
                    }
                }
                $items = [];
                if (isset($c->items) && is_array($c->items)) {
                    foreach (array_slice(array_values($c->items), 0, 10) as $it) {
                        if (!($it instanceof stdClass)) {
                            continue;
                        }
                        $mid = null;
                        if (isset($it->media_id) && is_int($it->media_id) && $it->media_id > 0) {
                            $mid = $it->media_id;
                        } elseif (isset($it->media_id) && is_numeric($it->media_id) && (int) $it->media_id > 0) {
                            $mid = (int) $it->media_id;
                        }
                        if ($mid === null) {
                            continue;
                        }
                        $found = BuilderMediaRefs::existingIds($pdo, [$mid]);
                        if (count($found) !== 1) {
                            throw new InvalidArgumentException('Unknown media id');
                        }
                        $dur = isset($it->duration) ? (int) $it->duration : 5;
                        if ($dur < 1) {
                            $dur = 1;
                        }
                        if ($dur > 15) {
                            $dur = 15;
                        }
                        $rawLink = isset($it->link) && is_string($it->link) ? trim($it->link) : '';
                        $link = '';
                        if ($rawLink !== '') {
                            $safe = BuilderHtmlSanitizer::safeUrl(mb_substr($rawLink, 0, 2000, 'UTF-8'));
                            $link = $safe === null ? '' : $safe;
                        }
                        $item = new stdClass();
                        $item->media_id = $mid;
                        $item->duration = $dur;
                        $item->link = $link;
                        $items[] = $item;
                    }
                }
                if (empty($items) && $coverId === null) {
                    continue;
                }
                $circle = new stdClass();
                $circle->title = $title;
                $circle->cover_media_id = $coverId;
                $circle->items = $items;
                $circles[] = $circle;
            }
        }
        $out = new stdClass();
        $out->circles = $circles;
        return $out;
    },
    'extract_media' => function (stdClass $data): array {
        $ids = [];
        if (isset($data->circles) && is_array($data->circles)) {
            foreach ($data->circles as $c) {
                if (!($c instanceof stdClass)) {
                    continue;
                }
                if (isset($c->cover_media_id) && is_int($c->cover_media_id) && $c->cover_media_id > 0) {
                    $ids[] = $c->cover_media_id;
                }
                if (isset($c->items) && is_array($c->items)) {
                    foreach ($c->items as $it) {
                        if ($it instanceof stdClass && isset($it->media_id) && is_int($it->media_id) && $it->media_id > 0) {
                            $ids[] = $it->media_id;
                        }
                    }
                }
            }
        }
        return array_values(array_unique($ids));
    },
]);
