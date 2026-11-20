<?php
// Runs the getClientIp cases in child processes because TRUSTED_PROXIES is a constant.
$fail = 0;
function check(string $label, $actual, $expected): void {
    global $fail;
    if ($actual !== $expected) { $fail++; fwrite(STDERR, "FAIL: $label\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n"); }
}
$file = realpath(__DIR__ . '/../includes/functions.php');   // real project: includes/functions.php
function child(string $file, string $definePrefix, string $server): string {
    $code = $definePrefix . '$_SERVER = ' . $server . '; require ' . var_export($file, true) . '; echo getClientIp();';
    return trim((string)shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code)));
}
check('no trusted proxies ignores XFF', child($file, '', "['REMOTE_ADDR'=>'1.2.3.4','HTTP_X_FORWARDED_FOR'=>'9.9.9.9']"), '1.2.3.4');
check('trusted proxy honours XFF', child($file, "define('TRUSTED_PROXIES',['1.2.3.4']);", "['REMOTE_ADDR'=>'1.2.3.4','HTTP_X_FORWARDED_FOR'=>'9.9.9.9']"), '9.9.9.9');
check('trusted proxy prefers CF header', child($file, "define('TRUSTED_PROXIES',['1.2.3.4']);", "['REMOTE_ADDR'=>'1.2.3.4','HTTP_CF_CONNECTING_IP'=>'8.8.8.8','HTTP_X_FORWARDED_FOR'=>'9.9.9.9']"), '8.8.8.8');
check('spoofed XFF leftmost ignored', child($file, "define('TRUSTED_PROXIES',['1.2.3.4']);", "['REMOTE_ADDR'=>'1.2.3.4','HTTP_X_FORWARDED_FOR'=>'6.6.6.6, 9.9.9.9']"), '9.9.9.9');
check('untrusted remote ignores CF', child($file, "define('TRUSTED_PROXIES',['1.2.3.4']);", "['REMOTE_ADDR'=>'5.5.5.5','HTTP_CF_CONNECTING_IP'=>'8.8.8.8']"), '5.5.5.5');
check('garbage remote', child($file, '', "['REMOTE_ADDR'=>'nonsense']"), '0.0.0.0');

require $file;
check('sanitize: markup removed', strpbrk(sanitizeClientFileName('<img src=x onerror=alert(1)>.jpg'), '<>"\'') === false, true);
check('sanitize: windows path', sanitizeClientFileName('C:\\fake\\path\\a.png'), 'a.png');
check('sanitize: unix path', sanitizeClientFileName('../../etc/passwd'), 'passwd');
check('sanitize: empty', sanitizeClientFileName(''), 'file');
check('sanitize: only markup', sanitizeClientFileName('<>"'), 'file');
check('sanitize: invalid utf8', sanitizeClientFileName("\xff\xfe.jpg"), 'file');
check('sanitize: persian kept', sanitizeClientFileName('عکس تست.jpg'), 'عکس تست.jpg');
check('sanitize: length', mb_strlen(sanitizeClientFileName(str_repeat('a', 400)), 'UTF-8'), 255);
check('policy short', validatePasswordPolicy('short') !== null, true);
check('policy 73 bytes', validatePasswordPolicy(str_repeat('a', 73)) !== null, true);
check('policy ok', validatePasswordPolicy('goodpass1'), null);
check('policy 72 ok', validatePasswordPolicy(str_repeat('a', 72)), null);
check('hash is bcrypt', password_get_info(hashPassword('x'))['algoName'], 'bcrypt');
check('hash cost 10', password_get_info(hashPassword('x'))['options']['cost'], 10);
if ($fail > 0) { fwrite(STDERR, "$fail failure(s)\n"); exit(1); }
echo "functions_test: OK\n";
