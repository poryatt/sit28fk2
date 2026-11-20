<?php
// Content block type: image_video (Part 4).
// One media_library row (image or mp4 video) + alt + caption + video flags.
// Media is referenced by integer media_id; extract_media() feeds builder_media_refs.

BuilderBlockRegistry::register([
    'type' => 'image_video',
    'label' => 'تصویر / ویدیو',
    'icon' => 'ti-photo',
    'category' => 'content',
    'allows_children' => false,
    'min_children' => 0,
    'max_children' => 0,
    'supports_text_formatting' => false,
    'allowed_child_types' => [],
    'allowed_parent_types' => [],
    'default_data' => ['kind' => 'image', 'media_id' => null, 'alt' => '', 'caption' => '', 'autoplay' => false, 'loop' => false, 'muted' => true],
    'sanitize' => function (stdClass $data, PDO $pdo): stdClass {
        $kind = isset($data->kind) && $data->kind === 'video' ? 'video' : 'image';
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
        $alt = isset($data->alt) && is_string($data->alt) ? trim($data->alt) : '';
        $alt = mb_substr($alt, 0, 255, 'UTF-8');
        $caption = isset($data->caption) && is_string($data->caption) ? trim($data->caption) : '';
        $caption = mb_substr($caption, 0, 500, 'UTF-8');
        $out = new stdClass();
        $out->kind = $kind;
        $out->media_id = $mediaId;
        $out->alt = $alt;
        $out->caption = $caption;
        $out->autoplay = !empty($data->autoplay);
        $out->loop = !empty($data->loop);
        $out->muted = !isset($data->muted) || !empty($data->muted);
        return $out;
    },
    'extract_media' => function (stdClass $data): array {
        if (isset($data->media_id) && is_int($data->media_id) && $data->media_id > 0) {
            return [$data->media_id];
        }
        return [];
    },
]);
