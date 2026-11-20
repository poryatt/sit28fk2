<?php
require __DIR__ . '/../includes/builder/pages_list.php';
$fail = 0;
function check(string $label, $actual, $expected): void {
    global $fail;
    if ($actual !== $expected) { $fail++; fwrite(STDERR, "FAIL: $label\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n"); }
}

check('empty input', BuilderPagesList::normalizeFilters([]), ['status' => 'all', 'q' => '', 'page' => 1]);
check('status published', BuilderPagesList::normalizeFilters(['status' => 'published']), ['status' => 'published', 'q' => '', 'page' => 1]);
check('status draft', BuilderPagesList::normalizeFilters(['status' => 'draft']), ['status' => 'draft', 'q' => '', 'page' => 1]);
check('status all', BuilderPagesList::normalizeFilters(['status' => 'all']), ['status' => 'all', 'q' => '', 'page' => 1]);
check('status evil', BuilderPagesList::normalizeFilters(['status' => 'evil']), ['status' => 'all', 'q' => '', 'page' => 1]);
check('q trims spaces', BuilderPagesList::normalizeFilters(['q' => '  hello  ']), ['status' => 'all', 'q' => 'hello', 'page' => 1]);
check('q cut to 100', BuilderPagesList::normalizeFilters(['q' => str_repeat('a', 150)]), ['status' => 'all', 'q' => str_repeat('a', 100), 'page' => 1]);
check('q array', BuilderPagesList::normalizeFilters(['q' => ['x']]), ['status' => 'all', 'q' => '', 'page' => 1]);
check('page 0', BuilderPagesList::normalizeFilters(['page' => '0'])['page'], 1);
check('page -3', BuilderPagesList::normalizeFilters(['page' => '-3'])['page'], 1);
check('page abc', BuilderPagesList::normalizeFilters(['page' => 'abc'])['page'], 1);
check("page '2'", BuilderPagesList::normalizeFilters(['page' => '2'])['page'], 2);
check('page 2', BuilderPagesList::normalizeFilters(['page' => 2])['page'], 2);
check('mb q 100 persian', mb_strlen(BuilderPagesList::normalizeFilters(['q' => str_repeat('م', 150)])['q'], 'UTF-8'), 100);

// Part 9: BuilderPagesList::url (pure)
check('url default', BuilderPagesList::url(['status' => 'all', 'q' => '', 'page' => 1]), '/modules/articles/index.php?status=all');
check('url keeps status', BuilderPagesList::url(['status' => 'all', 'q' => '', 'page' => 1], ['status' => 'published']), '/modules/articles/index.php?status=published');
check('url drops empty q', BuilderPagesList::url(['status' => 'all', 'q' => '', 'page' => 1]), '/modules/articles/index.php?status=all');
check('url keeps q', BuilderPagesList::url(['status' => 'all', 'q' => 'x', 'page' => 1]), '/modules/articles/index.php?status=all&q=x');
check('url page=1 omitted', BuilderPagesList::url(['status' => 'all', 'q' => '', 'page' => 1], ['page' => 1]), '/modules/articles/index.php?status=all');
check('url page=3 kept', BuilderPagesList::url(['status' => 'all', 'q' => '', 'page' => 1], ['page' => 3]), '/modules/articles/index.php?status=all&page=3');
check('url page=0 omitted', BuilderPagesList::url(['status' => 'all', 'q' => '', 'page' => 1], ['page' => 0]), '/modules/articles/index.php?status=all');
check('url encodes special chars', BuilderPagesList::url(['status' => 'all', 'q' => 'a&b', 'page' => 1]), '/modules/articles/index.php?status=all&q=a%26b');
check('url encodes spaces', BuilderPagesList::url(['status' => 'all', 'q' => 'hello world', 'page' => 1]), '/modules/articles/index.php?status=all&q=hello+world');
check('url keeps status+q while paging', BuilderPagesList::url(['status' => 'draft', 'q' => 'x', 'page' => 2], ['page' => 3]), '/modules/articles/index.php?status=draft&q=x&page=3');

if ($fail > 0) { fwrite(STDERR, "$fail failure(s)\n"); exit(1); }
echo "builder_pages_list_test: OK\n";
