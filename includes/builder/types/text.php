<?php
// Content block type: text (Part 4).
// Inline rich text. Data: { html, align, color, bg }.
// html is cleaned with an extended whitelist (headings, lists, blockquote, links).
// align/color/bg are block-level design fields validated here so the client
// never needs inline <span style=""> (which the sanitizer strips).

BuilderBlockRegistry::register([
    'type' => 'text',
    'label' => 'متن',
    'icon' => 'ti-heading',
    'category' => 'content',
    'allows_children' => false,
    'min_children' => 0,
    'max_children' => 0,
    'supports_text_formatting' => true,
    'allowed_child_types' => [],
    'allowed_parent_types' => [],
    'default_data' => ['html' => '', 'align' => 'right'],
    'sanitize' => function (stdClass $data, PDO $pdo): stdClass {
        $allowedTags = ['b', 'strong', 'i', 'em', 'u', 'a', 'br', 'p', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'blockquote'];
        $raw = isset($data->html) && is_string($data->html) ? $data->html : '';
        $html = BuilderHtmlSanitizer::clean($raw, $allowedTags, 20000);
        $align = isset($data->align) && is_string($data->align) ? $data->align : 'right';
        if (!in_array($align, ['right', 'center', 'left', 'justify'], true)) {
            $align = 'right';
        }
        $color = isset($data->color) && is_string($data->color) ? trim($data->color) : '';
        if ($color !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $color = '';
        }
        $bg = isset($data->bg) && is_string($data->bg) ? trim($data->bg) : '';
        if ($bg !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $bg)) {
            $bg = '';
        }
        $out = new stdClass();
        $out->html = $html;
        $out->align = $align;
        if ($color !== '') {
            $out->color = $color;
        }
        if ($bg !== '') {
            $out->bg = $bg;
        }
        return $out;
    },
]);
