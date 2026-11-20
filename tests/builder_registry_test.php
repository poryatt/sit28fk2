<?php
require __DIR__ . '/../includes/builder/block_registry.php';
$fail = 0;
function check(string $label, $actual, $expected): void {
    global $fail;
    if ($actual !== $expected) { $fail++; fwrite(STDERR, "FAIL: $label\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n"); }
}
function def(string $type, array $extra = []): array {
    return array_merge([
        'type' => $type, 'label' => 'x', 'icon' => 'bi-x', 'category' => 'content',
        'allows_children' => false, 'min_children' => 0, 'max_children' => 0,
        'supports_text_formatting' => false, 'allowed_child_types' => [], 'allowed_parent_types' => [],
        'default_data' => [],
    ], $extra);
}
function throws(callable $fn): bool { try { $fn(); } catch (InvalidArgumentException $e) { return true; } return false; }
$pdo = (new ReflectionClass(PDO::class))->newInstanceWithoutConstructor();

check('leaf without sanitize rejected', throws(function () { BuilderBlockRegistry::register(def('t_leaf_a')); }), true);
check('leaf with null sanitize rejected', throws(function () { BuilderBlockRegistry::register(def('t_leaf_b', ['sanitize' => null])); }), true);
check('leaf with sanitize accepted', throws(function () { BuilderBlockRegistry::register(def('t_leaf_c', ['sanitize' => function (stdClass $d, $pdo) { return $d; }])); }), false);
check('container without sanitize accepted', throws(function () {
    BuilderBlockRegistry::register(def('t_box_a', ['allows_children' => true, 'category' => 'structure']));
}), false);
check('non-callable extract_media rejected', throws(function () {
    BuilderBlockRegistry::register(def('t_leaf_d', ['sanitize' => function (stdClass $d, $pdo) { return $d; }, 'extract_media' => 'nope_not_a_function']));
}), true);

BuilderBlockRegistry::register(def('t_media_a', [
    'sanitize' => function (stdClass $d, $pdo) { $d->clean = true; return $d; },
    'extract_media' => function (stdClass $d) { return [3, 3, 7]; },
]));
$out = BuilderBlockRegistry::sanitizeData('t_media_a', (object)['a' => 1], $pdo);
check('sanitize runs', $out->clean ?? null, true);
check('extract dedupes', BuilderBlockRegistry::extractMedia('t_media_a', new stdClass), [3, 7]);
check('extract unknown type', BuilderBlockRegistry::extractMedia('nope', new stdClass), []);
check('sanitize unknown type passthrough', BuilderBlockRegistry::sanitizeData('nope', (object)['k' => 1], $pdo)->k, 1);

BuilderBlockRegistry::register(def('t_bad_ret', [
    'sanitize' => function (stdClass $d, $pdo) { return ['array']; },
    'extract_media' => function (stdClass $d) { return ['7']; },
]));
check('sanitize must return stdClass', throws(function () use ($pdo) { BuilderBlockRegistry::sanitizeData('t_bad_ret', new stdClass, $pdo); }), true);
check('extract must return ints', throws(function () { BuilderBlockRegistry::extractMedia('t_bad_ret', new stdClass); }), true);

BuilderBlockRegistry::register(def('t_throw', ['sanitize' => function (stdClass $d, $pdo) { throw new InvalidArgumentException('bad'); }]));
check('sanitize may reject', throws(function () use ($pdo) { BuilderBlockRegistry::sanitizeData('t_throw', new stdClass, $pdo); }), true);

// the five real structural types must still register
require __DIR__ . '/../includes/builder/types/structural.php';
check('structural registered', BuilderBlockRegistry::exists('column_container') && BuilderBlockRegistry::exists('footer'), true);
if ($fail > 0) { fwrite(STDERR, "$fail failure(s)\n"); exit(1); }
echo "builder_registry_test: OK\n";
