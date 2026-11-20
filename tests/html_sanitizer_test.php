<?php
// Plain-PHP CLI test. Exit code 0 on success, 1 on failure.
require __DIR__ . '/../includes/builder/html_sanitizer.php';

$fail = 0;
function check(string $label, $actual, $expected): void {
    global $fail;
    if ($actual !== $expected) {
        $fail++;
        fwrite(STDERR, "FAIL: $label\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n");
    }
}
$c = static function (string $h): string { return BuilderHtmlSanitizer::clean($h); };

check('script dropped, onclick stripped', $c('<script>alert(1)</script><b onclick="x()">hi</b>'), '<b>hi</b>');
check('javascript: href unwrapped', $c('<a href="javascript:alert(1)">x</a>'), 'x');
check('obfuscated javascript:', $c("<a href=\" JaVa\tScRiPt:alert(1)\">x</a>"), 'x');
check('entity-encoded javascript:', $c('<a href="&#106;avascript:alert(1)">x</a>'), 'x');
check('data: href', $c('<a href="data:text/html;base64,AAAA">x</a>'), 'x');
check('https href kept + rel', $c('<a href="https://example.com/a?b=1&c=2" onclick="x">go</a>'), '<a href="https://example.com/a?b=1&amp;c=2" rel="noopener noreferrer">go</a>');
check('mailto kept', $c('<a href="mailto:a@b.co">m</a>'), '<a href="mailto:a@b.co" rel="noopener noreferrer">m</a>');
check('relative path kept', $c('<a href="/page/about">p</a>'), '<a href="/page/about" rel="noopener noreferrer">p</a>');
check('protocol-relative rejected', $c('<a href="//evil.com">p</a>'), 'p');
check('backslash trick rejected', $c('<a href="/\\evil.com">p</a>'), 'p');
check('img removed', $c('<img src=x onerror=alert(1)>'), '');
check('svg dropped with content', $c('a<svg><script>alert(1)</script></svg>b'), 'ab');
check('iframe dropped', $c('<iframe src="//evil"></iframe>ok'), 'ok');
check('div becomes p', $c('<div>line</div>'), '<p>line</p>');
check('br kept', $c('a<br>b'), 'a<br>b');
check('nested kept', $c('<b><i>x</i></b>'), '<b><i>x</i></b>');
check('unknown tag unwrapped', $c('<font color="red">t</font>'), 't');
check('unclosed closed', $c('<b>unclosed'), '<b>unclosed</b>');
check('escaped text stays text', $c('&lt;script&gt;alert(1)&lt;/script&gt;'), '&lt;script&gt;alert(1)&lt;/script&gt;');
check('persian preserved', $c('<p>سلام <b>دنیا</b></p>'), '<p>سلام <b>دنیا</b></p>');
check('noscript mXSS dropped', $c('<noscript><p title="</noscript><img src=x onerror=alert(1)>"></noscript>'), '');
check('comment dropped', $c('a<!-- x -->b'), 'ab');
check('empty', $c('   '), '');
check('NUL stripped', $c("a\0b"), 'ab');
check('style attr stripped', $c('<p style="position:fixed">x</p>'), '<p>x</p>');
check('safeUrl bare relative rejected', BuilderHtmlSanitizer::safeUrl('foo.html'), null);
check('safeUrl anchor', BuilderHtmlSanitizer::safeUrl('#top'), '#top');
check('safeUrl tel', BuilderHtmlSanitizer::safeUrl('tel:+98912'), 'tel:+98912');
check('safeUrl vbscript', BuilderHtmlSanitizer::safeUrl('vbscript:x'), null);
$deep = str_repeat('<b>', 200) . 'x' . str_repeat('</b>', 200);
check('deep nesting does not crash', is_string($c($deep)), true);
check('oversize truncated not crash', is_string(BuilderHtmlSanitizer::clean(str_repeat('<b>a</b>', 10000), BuilderHtmlSanitizer::DEFAULT_TAGS, 1000)), true);

if ($fail > 0) { fwrite(STDERR, "$fail failure(s)\n"); exit(1); }
echo "html_sanitizer_test: OK\n";
