<?php
// Content block type: slider (Part 5).
// Per-slide image + optional link, autoplay interval, prev/next, dots, pause-on-hover.
// Images only (no video slides). Max 10 slides.

BuilderBlockRegistry::register([
    'type' => 'slider',
    'label' => 'اسلایدر',
    'icon' => 'ti-carousel-horizontal',
    'category' => 'content',
    'allows_children' => false,
    'min_children' => 0,
    'max_children' => 0,
    'supports_text_formatting' => false,
    'allowed_child_types' => [],
    'allowed_parent_types' => [],
    'default_data' => ['slides' => [], 'autoplay' => true, 'interval' => 4000, 'pause_on_hover' => true],
    'sanitize' => function (stdClass $data, PDO $pdo): stdClass {
        $slides = [];
        if (isset($data->slides) && is_array($data->slides)) {
            foreach (array_slice(array_values($data->slides), 0, 10) as $s) {
                if (!($s instanceof stdClass)) {
                    continue;
                }
                $mid = null;
                if (isset($s->media_id) && is_int($s->media_id) && $s->media_id > 0) {
                    $mid = $s->media_id;
                } elseif (isset($s->media_id) && is_numeric($s->media_id) && (int) $s->media_id > 0) {
                    $mid = (int) $s->media_id;
                }
                if ($mid === null) {
                    continue;
                }
                $found = BuilderMediaRefs::existingIds($pdo, [$mid]);
                if (count($found) !== 1) {
                    throw new InvalidArgumentException('Unknown media id');
                }
                $rawLink = isset($s->link) && is_string($s->link) ? trim($s->link) : '';
                $link = '';
                if ($rawLink !== '') {
                    $safe = BuilderHtmlSanitizer::safeUrl(mb_substr($rawLink, 0, 2000, 'UTF-8'));
                    $link = $safe === null ? '' : $safe;
                }
                $caption = isset($s->caption) && is_string($s->caption) ? trim($s->caption) : '';
                $caption = mb_substr($caption, 0, 500, 'UTF-8');
                $slide = new stdClass();
                $slide->media_id = $mid;
                $slide->link = $link;
                $slide->caption = $caption;
                $slides[] = $slide;
            }
        }
        $interval = isset($data->interval) ? (int) $data->interval : 4000;
        if ($interval < 1000) {
            $interval = 1000;
        }
        if ($interval > 15000) {
            $interval = 15000;
        }
        $out = new stdClass();
        $out->slides = $slides;
        $out->autoplay = !isset($data->autoplay) || !empty($data->autoplay);
        $out->interval = $interval;
        $out->pause_on_hover = !isset($data->pause_on_hover) || !empty($data->pause_on_hover);
        return $out;
    },
    'extract_media' => function (stdClass $data): array {
        $ids = [];
        if (isset($data->slides) && is_array($data->slides)) {
            foreach ($data->slides as $s) {
                if ($s instanceof stdClass && isset($s->media_id) && is_int($s->media_id) && $s->media_id > 0) {
                    $ids[] = $s->media_id;
                }
            }
        }
        return array_values(array_unique($ids));
    },
]);
