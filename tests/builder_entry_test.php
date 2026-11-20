<?php
require __DIR__ . '/../includes/builder/entry.php';
$fail = 0;
function check(string $label, $actual, $expected): void {
    global $fail;
    if ($actual !== $expected) { $fail++; fwrite(STDERR, "FAIL: $label\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n"); }
}

check('no params, no active template', builderResolveEntry([], null), ['kind' => 'none']);
check('no params, active template', builderResolveEntry([], 4), ['kind' => 'active_template', 'template_id' => 4]);
check('canvas_id=5', builderResolveEntry(['canvas_id' => '5'], null), ['kind' => 'canvas', 'canvas_id' => 5]);
check('canvas_id=0', builderResolveEntry(['canvas_id' => '0'], null), ['kind' => 'invalid']);
check('canvas_id=abc', builderResolveEntry(['canvas_id' => 'abc'], null), ['kind' => 'invalid']);
check('page + new', builderResolveEntry(['buildable_type' => 'page', 'new' => '1'], null), ['kind' => 'page_new']);
check('page alone', builderResolveEntry(['buildable_type' => 'page'], null), ['kind' => 'invalid']);
check('template id', builderResolveEntry(['buildable_type' => 'template', 'template_id' => '3'], null), ['kind' => 'template', 'template_id' => 3, 'new' => false]);
check('template id + new', builderResolveEntry(['buildable_type' => 'template', 'template_id' => '3', 'new' => '1'], null), ['kind' => 'template', 'template_id' => 3, 'new' => true]);
check('template without id', builderResolveEntry(['buildable_type' => 'template'], null), ['kind' => 'invalid']);
check('unknown buildable_type', builderResolveEntry(['buildable_type' => 'whatever'], null), ['kind' => 'invalid']);
check('new alone', builderResolveEntry(['new' => '1'], null), ['kind' => 'invalid']);
check('template_id=0', builderResolveEntry(['buildable_type' => 'template', 'template_id' => '0'], null), ['kind' => 'invalid']);

if ($fail > 0) { fwrite(STDERR, "$fail failure(s)\n"); exit(1); }
echo "builder_entry_test: OK\n";
