<?php
// Structural block types for the visual page builder (Part 0).
// Metadata-only registrations: no render, no settings form. Part 2 renders them.

BuilderBlockRegistry::register([
    'type' => 'header',
    'label' => 'هدر',
    'icon' => 'ti-layout-navbar',
    'category' => 'structure',
    'allows_children' => true,
    'min_children' => 0,
    'max_children' => 0,
    'supports_text_formatting' => false,
    'allowed_child_types' => [],
    'allowed_parent_types' => [],
    'default_data' => [],
]);

BuilderBlockRegistry::register([
    'type' => 'body',
    'label' => 'بدنه',
    'icon' => 'ti-file-text',
    'category' => 'structure',
    'allows_children' => true,
    'min_children' => 0,
    'max_children' => 0,
    'supports_text_formatting' => false,
    'allowed_child_types' => [],
    'allowed_parent_types' => [],
    'default_data' => [],
]);

BuilderBlockRegistry::register([
    'type' => 'footer',
    'label' => 'فوتر',
    'icon' => 'ti-layout-rows',
    'category' => 'structure',
    'allows_children' => true,
    'min_children' => 0,
    'max_children' => 0,
    'supports_text_formatting' => false,
    'allowed_child_types' => [],
    'allowed_parent_types' => [],
    'default_data' => [],
]);

BuilderBlockRegistry::register([
    'type' => 'column_container',
    'label' => 'ستونبندی',
    'icon' => 'ti-columns-3',
    'category' => 'structure',
    'allows_children' => true,
    'min_children' => 2,
    'max_children' => 4,
    'supports_text_formatting' => false,
    'allowed_child_types' => ['column'],
    'allowed_parent_types' => [],
    'default_data' => [],
]);

BuilderBlockRegistry::register([
    'type' => 'column',
    'label' => 'ستون',
    'icon' => 'ti-layout-sidebar',
    'category' => 'structure',
    'allows_children' => true,
    'min_children' => 0,
    'max_children' => 0,
    'supports_text_formatting' => false,
    'allowed_child_types' => [],
    'allowed_parent_types' => ['column_container'],
    'default_data' => [],
]);
