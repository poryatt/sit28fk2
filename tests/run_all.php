<?php
// CLI test runner: executes every tests/*_test.php with PHP_BINARY, one line per file.
// Exit code 1 if any file exits non-zero. Plain PHP, no PHPUnit.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$files = glob(__DIR__ . '/*_test.php') ?: [];
sort($files, SORT_STRING);
if (empty($files)) {
    echo "run_all: no test files yet — OK\n";
    exit(0);
}
$fail = 0;
foreach ($files as $file) {
    $name = basename($file);
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1';
    $out = [];
    $code = 0;
    exec($cmd, $out, $code);
    $last = $out ? end($out) : '';
    if ($code !== 0) {
        $fail++;
        echo "$name: FAIL (exit $code) — $last\n";
        foreach ($out as $line) { echo "    $line\n"; }
    } else {
        echo "$name: OK — $last\n";
    }
}
exit($fail > 0 ? 1 : 0);
