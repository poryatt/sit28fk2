<?php
// Syntax-checks every inline script block in *.php files and every *.js under public/assets/js with `node --check`.
// Usage: php database/tools/check_inline_js.php [--root=<panel dir>]   Exit 1 on any syntax error, 0 when clean or when node is missing (SKIP).
if (PHP_SAPI !== 'cli') { exit(1); }
$root = realpath(__DIR__ . '/../..');
foreach ($argv as $a) { if (strpos($a, '--root=') === 0) { $root = realpath(substr($a, 7)); } }
if (!$root || !is_dir($root)) { fwrite(STDERR, "bad --root\n"); exit(2); }

exec('node --version 2>&1', $out, $rc);
if ($rc !== 0) { echo "SKIP  node not found on PATH — install Node.js or do the manual browser-console check (see README)\n"; exit(0); }

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'chkjs_' . bin2hex(random_bytes(4)) . '.js';
$checked = 0; $bad = 0;
$check = function (string $label, string $js) use ($tmp, &$checked, &$bad) {
    file_put_contents($tmp, $js);
    $o = []; exec('node --check ' . escapeshellarg($tmp) . ' 2>&1', $o, $code);
    $checked++;
    if ($code !== 0) {
        $bad++;
        echo "FAIL  $label\n";
        foreach (array_slice($o, 0, 6) as $l) { echo '      ' . str_replace($tmp, '<inline>', $l) . "\n"; }
    }
};
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $p = $f->getPathname(); $rel = str_replace('\\', '/', substr($p, strlen($root) + 1));
    if (strpos($rel, 'vendor/') === 0 || strpos($rel, 'storage/') === 0 || strpos($rel, 'public/uploads/') === 0) { continue; }
    if (substr($p, -4) === '.php') {
        $src = (string)file_get_contents($p);
        // Built from parts so this tool's own source can never match itself.
        $rx = '#<' . 'script(?![^>]*\bsrc=)[^>]*>(.*?)</' . 'script>#si';
        if (!preg_match_all($rx, $src, $m)) { continue; }
        foreach ($m[1] as $i => $js) {
            $js = preg_replace('/<\?=.*?\?>/s', '[]', $js);   // an echoed value is an expression
            $js = preg_replace('/<\?php.*?\?>/s', '', $js);   // control-flow tags
            $check("$rel (script #" . ($i + 1) . ')', $js);
        }
    } elseif (substr($p, -3) === '.js' && strpos($rel, 'public/assets/js/') === 0) {
        $check($rel, (string)file_get_contents($p));
    }
}
@unlink($tmp);
echo "checked $checked script(s); syntax errors: $bad\n";
exit($bad > 0 ? 1 : 0);
