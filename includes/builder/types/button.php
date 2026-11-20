<?php
// Content block type: button (Part 4).
// Text label and/or media image + destination link + new-tab toggle.

BuilderBlockRegistry::register([
    'type' => 'button',
    'label' => 'دکمه',
    'icon' => 'ti-link',
    'category' => 'content',
    'allows_children' => false,
    'min_children' => 0,
    'max_children' => 0,
    'supports_text_formatting' => false,
    'allowed_child_types' => [],
    'allowed_parent_types' => [],
    'default_data' => ['label' => 'دکمه', 'url' => '', 'open_new_tab' => false, 'media_id' => null],
    'sanitize' => function (stdClass $data, PDO $pdo): stdClass {
        $label = isset($data->label) && is_string($data->label) ? trim($data->label) : '';
        $label = mb_substr($label, 0, 200, 'UTF-8');
        if ($label === '') {
            $label = 'دکمه';
        }
        $rawUrl = isset($data->url) && is_string($data->url) ? trim($data->url) : '';
        $url = '';
        if ($rawUrl !== '') {
            $safe = BuilderHtmlSanitizer::safeUrl(mb_substr($rawUrl, 0, 2000, 'UTF-8'));
            $url = $safe === null ? '' : $safe;
        }
        $mediaId = null;
        if (isset($data->media_id) && is_int($data->media_id) && $data->media_id > 0) {
            $mediaId = $data->media_id;
        } elseif (isset($data->media_id) && is_numeric($data->media_id) && (int) $data->media_id > 0) {
            $mediaId = (int) $data->media_id;
        }
        if ($mediaId !== null) {
            $found = BuilderMediaRefs::existingIds($pdo, [$mediaId]);
            if (count($found) !== 1) {
                throw new InvalidArgumentException('Unknown media id');
            }
        }
        $out = new stdClass();
        $out->label = $label;
        $out->url = $url;
        $out->open_new_tab = !empty($data->open_new_tab);
        $out->media_id = $mediaId;
        return $out;
    },
    'extract_media' => function (stdClass $data): array {
        if (isset($data->media_id) && is_int($data->media_id) && $data->media_id > 0) {
            return [$data->media_id];
        }
        return [];
    },
]);
