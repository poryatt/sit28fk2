<?php
// Prints a fresh APP_SECRET_KEY (base64 of 32 random bytes). CLI only. Writes nothing.
// Store it in config/env.local.php or a real environment variable; if you lose it the stored secrets cannot be recovered.
// Never send it through chat, e-mail or the changelog.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
echo base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)), PHP_EOL;
fwrite(STDERR, "Store it in env.local.php or a real environment variable; if you lose it the stored secrets cannot be recovered.\n");
