<?php
// Application-level secret encryption (libsodium secretbox). Format: "v1:" . base64(nonce(24) . ciphertext).
// The key lives in the environment (APP_SECRET_KEY = base64 of 32 random bytes), never in the database or the repo.

const SECRET_PREFIX = 'v1:';

/** @throws RuntimeException when the key is missing or malformed. */
function secret_key(): string {
    $b64 = getenv('APP_SECRET_KEY');
    if (!is_string($b64) || $b64 === '') {
        throw new RuntimeException('APP_SECRET_KEY is not set');
    }
    $key = base64_decode($b64, true);
    if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
        throw new RuntimeException('APP_SECRET_KEY must be base64 of exactly 32 bytes');
    }
    return $key;
}

function secret_is_encrypted(?string $value): bool {
    return $value !== null && strncmp($value, SECRET_PREFIX, strlen(SECRET_PREFIX)) === 0;
}

function secret_require_sodium(): void {
    if (!function_exists('sodium_crypto_secretbox')) {
        throw new RuntimeException('sodium extension is not enabled');
    }
}

/** @throws RuntimeException */
function secret_encrypt(string $plain, ?string $key = null): string {
    secret_require_sodium();
    $key = $key ?? secret_key();
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $cipher = sodium_crypto_secretbox($plain, $nonce, $key);
    return SECRET_PREFIX . base64_encode($nonce . $cipher);
}

/** Returns null when the payload is not ours, is tampered with, or the key is wrong. Never throws on bad payloads. */
function secret_decrypt(?string $payload, ?string $key = null): ?string {
    secret_require_sodium();
    if (!secret_is_encrypted($payload)) { return null; }
    $raw = base64_decode(substr($payload, strlen(SECRET_PREFIX)), true);
    if ($raw === false || strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) { return null; }
    $key = $key ?? secret_key();
    $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $plain = sodium_crypto_secretbox_open($cipher, $nonce, $key);
    return $plain === false ? null : $plain;
}

/** What the UI may show for a stored secret: bullets plus at most the last 4 characters (only when the secret is long). */
function secret_mask(?string $plain): string {
    if ($plain === null || $plain === '') { return ''; }
    $len = mb_strlen($plain, 'UTF-8');
    return $len >= 12 ? '••••••••' . mb_substr($plain, -4, null, 'UTF-8') : '••••••••';
}
