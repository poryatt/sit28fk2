<?php
require __DIR__ . '/../includes/secrets.php';
// sodium is enabled in php.ini by the server operator (P0/P6); without it nothing here can run.
if (!extension_loaded('sodium')) { echo "SKIP (sodium)\n"; exit(0); }
$fail = 0;
function check(string $label, $actual, $expected): void {
    global $fail;
    if ($actual !== $expected) { $fail++; fwrite(STDERR, "FAIL: $label\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n"); }
}
$key = sodium_crypto_secretbox_keygen();
$enc = secret_encrypt('my-api-key-123456', $key);
check('prefix', substr($enc, 0, 3), 'v1:');
check('is_encrypted', secret_is_encrypted($enc), true);
check('plain not encrypted', secret_is_encrypted('my-api-key-123456'), false);
check('null not encrypted', secret_is_encrypted(null), false);
check('roundtrip', secret_decrypt($enc, $key), 'my-api-key-123456');
check('nonce random', secret_encrypt('x', $key) !== secret_encrypt('x', $key), true);
check('wrong key', secret_decrypt($enc, sodium_crypto_secretbox_keygen()), null);
$tampered = substr($enc, 0, -2) . (substr($enc, -2) === 'AA' ? 'BB' : 'AA');
check('tampered', secret_decrypt($tampered, $key), null);
check('garbage', secret_decrypt('v1:%%%', $key), null);
check('short', secret_decrypt('v1:' . base64_encode('short'), $key), null);
check('plain payload', secret_decrypt('plain', $key), null);
check('unicode roundtrip', secret_decrypt(secret_encrypt('کلید-رمز', $key), $key), 'کلید-رمز');
check('max length payload fits 512', strlen(secret_encrypt(str_repeat('k', 255), $key)) <= 512, true);
check('mask short', secret_mask('abc'), '••••••••');
check('mask long', secret_mask('abcdefghijkl1234'), '••••••••1234');
check('mask empty', secret_mask(''), '');
check('mask null', secret_mask(null), '');
putenv('APP_SECRET_KEY');
$threw = false; try { secret_key(); } catch (RuntimeException $e) { $threw = true; }
check('missing key throws', $threw, true);
putenv('APP_SECRET_KEY=' . base64_encode('too-short'));
$threw = false; try { secret_key(); } catch (RuntimeException $e) { $threw = true; }
check('short key throws', $threw, true);
putenv('APP_SECRET_KEY=' . base64_encode($key));
check('env key works', secret_decrypt(secret_encrypt('v'), null), 'v');
if ($fail > 0) { fwrite(STDERR, "$fail failure(s)\n"); exit(1); }
echo "secrets_test: OK\n";
