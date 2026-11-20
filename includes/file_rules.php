<?php
// Pure rules for uploaded files. No DB, no filesystem writes. Unit-tested in tests/file_rules_test.php.

/** Persian message for a PHP UPLOAD_ERR_* code (never UPLOAD_ERR_OK). */
function uploadErrorMessage(int $code): string {
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE: return 'حجم فایل بیش از حد مجاز سرور است';
        case UPLOAD_ERR_PARTIAL:   return 'فایل به‌صورت ناقص ارسال شد. دوباره تلاش کنید';
        case UPLOAD_ERR_NO_FILE:   return 'فایلی ارسال نشده';
        default:                   return 'خطای سرور در دریافت فایل';
    }
}

function imageExtForMime(string $mime): ?string {
    $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    return $map[$mime] ?? null;
}

function videoExtForMime(string $mime): ?string {
    $map = ['video/mp4' => 'mp4', 'video/webm' => 'webm'];
    return $map[$mime] ?? null;
}

function certificateExtForMime(string $mime): ?string {
    $map = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    return $map[$mime] ?? null;
}

/** MIME types finfo may report for a real .ico file. */
function isIcoMime(string $mime): bool {
    return in_array($mime, ['image/vnd.microsoft.icon', 'image/x-icon'], true);
}

/** An .ico file starts with 00 00 01 00 (ICONDIR, type 1). */
function hasIcoMagic(string $firstBytes): bool {
    return strlen($firstBytes) >= 4 && substr($firstBytes, 0, 4) === "\x00\x00\x01\x00";
}

/** Public paths we ever write: /uploads/{media|avatars}/<32 hex>.<ext>. */
function isSafePublicUploadPath(string $path): bool {
    return (bool)preg_match('#^/uploads/(media|avatars)/[0-9a-f]{32}\.(jpg|png|webp|mp4|webm|ico)$#', $path);
}

/** Private paths are stored relative to storage/private: licenses|templates/<32 hex>.<ext>. */
function isSafePrivatePath(string $rel): bool {
    return (bool)preg_match('#^(licenses|templates)/[0-9a-f]{32}\.(pdf|jpg|png|webp|zip)$#', $rel);
}

/** Image sanity limits before handing bytes to GD (decompression-bomb guard). */
function imageDimensionsAllowed(int $w, int $h): bool {
    return $w >= 1 && $h >= 1 && $w <= 10000 && $h <= 10000 && ($w * $h) <= 40000000;
}

/** True when the leading bytes match the magic number of the given validated image extension. */
function imageMagicMatches(string $firstBytes, string $ext): bool {
    if ($ext === 'jpg') {
        return strlen($firstBytes) >= 3 && substr($firstBytes, 0, 3) === "\xFF\xD8\xFF";
    }
    if ($ext === 'png') {
        return strlen($firstBytes) >= 8 && substr($firstBytes, 0, 8) === "\x89PNG\r\n\x1a\n";
    }
    if ($ext === 'webp') {
        return strlen($firstBytes) >= 12 && substr($firstBytes, 0, 4) === 'RIFF' && substr($firstBytes, 8, 4) === 'WEBP';
    }
    return false;
}

/** True when the bytes contain a server-script marker. Used only on the GD-less pass-through path. */
function containsServerScriptMarkers(string $bytes): bool {
    return (bool)preg_match('/<\?(?:php|=)|<script\b/i', $bytes);
}

/**
 * Image fallback policy when GD is missing: 'passthrough' | 'refuse'.
 * An explicit $envValue of either wins; otherwise production refuses and everything else passes through.
 */
function imageFallbackPolicy(?string $envValue, ?string $appEnv): string {
    if ($envValue === 'passthrough' || $envValue === 'refuse') {
        return $envValue;
    }
    return ($appEnv === 'production') ? 'refuse' : 'passthrough';
}

/** Entry names inside an uploaded template zip. Directories (trailing /) are judged by path only. */
function zipEntryIsSafe(string $name): bool {
    if ($name === '' || strlen($name) > 255) { return false; }
    if (strpos($name, "\0") !== false || strpos($name, '\\') !== false) { return false; }
    if ($name[0] === '/' || preg_match('#^[A-Za-z]:#', $name)) { return false; }
    foreach (explode('/', $name) as $segment) {
        if ($segment === '..') { return false; }
    }
    return true;
}

/** Extensions allowed for FILES inside a template zip (no php, no svg, no dotfiles). */
function zipEntryExtensionAllowed(string $name): bool {
    if (substr($name, -1) === '/') { return true; }
    $base = basename($name);
    if ($base === '' || $base[0] === '.') { return false; }
    $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
    $allowed = ['html','htm','css','js','json','txt','md','png','jpg','jpeg','gif','webp','ico',
                'woff','woff2','ttf','otf','eot','mp4','webm','map'];
    return in_array($ext, $allowed, true);
}
