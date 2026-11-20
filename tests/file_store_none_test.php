<?php
// GD-less pass-through path for image uploads (Part 9).
// Forces FILESTORE_TESTING_MODE='none' so it runs on any machine (with or without GD).
define('FILESTORE_TESTING', true);
define('FILESTORE_TESTING_MODE', 'none');
$tmpRoot = sys_get_temp_dir() . '/fs_none_' . bin2hex(random_bytes(4));
define('UPLOAD_BASE', $tmpRoot . '/public/uploads');
define('UPLOAD_MEDIA', UPLOAD_BASE . '/media');
define('UPLOAD_AVATARS', UPLOAD_BASE . '/avatars');
define('STORAGE_PRIVATE', $tmpRoot . '/storage/private');
define('MAX_IMAGE_SIZE', 5 * 1024 * 1024);
define('MAX_VIDEO_SIZE', 50 * 1024 * 1024);
require __DIR__ . '/../includes/file_store.php';

$fail = 0;
function check(string $label, $actual, $expected): void {
    global $fail;
    if ($actual !== $expected) { $fail++; fwrite(STDERR, "FAIL: $label\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n"); }
}
function fileOf(string $bytes, string $name = 'u.bin'): array {
    $t = tempnam(sys_get_temp_dir(), 'up');
    file_put_contents($t, $bytes);
    return ['name' => $name, 'tmp_name' => $t, 'error' => UPLOAD_ERR_OK, 'size' => strlen($bytes)];
}
function rejects(callable $fn, ?string $msg = null): bool {
    try { $fn(); } catch (FileStoreException $e) { return $msg === null || $e->getMessage() === $msg; }
    return false;
}

// 1x1 transparent PNG (base64 literal), the same trick the spec asks for.
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mNgYAAAAAMAASsJTYQAAAAASUVORK5CYII=');
check('png literal has PNG magic', imageMagicMatches(substr($png, 0, 12), 'png'), true);

// 1. A valid PNG is stored through the pass-through path, unchanged, with dimensions.
$r = FileStore::storeImage(fileOf($png, 'x.png'), 'media', MAX_IMAGE_SIZE);
check('passthrough png stored', is_file($r['abs']), true);
check('passthrough png ext', $r['ext'], 'png');
check('passthrough png path shape', isSafePublicUploadPath($r['path']), true);
check('passthrough png dims', [$r['width'], $r['height']], [1, 1]);
check('passthrough png bytes unchanged', md5((string)file_get_contents($r['abs'])), md5($png));
FileStore::remove($r['path']);

// 2. A valid PNG with an appended PHP payload is refused.
check('png with <?php refused', rejects(function () use ($png) {
    FileStore::storeImage(fileOf($png . "<?php echo 'pwned'; ?>", 'x.png'), 'media');
}, 'تصویر معتبر نیست'), true);

// 3. Policy 'refuse' (via env) refuses a valid image with the GD message.
putenv('IMAGE_PROCESSING_FALLBACK=refuse');
check('policy refuse active', imageFallbackPolicyCurrent(), 'refuse');
check('valid image refused when policy=refuse', rejects(function () use ($png) {
    FileStore::storeImage(fileOf($png, 'x.png'), 'media');
}, 'پردازش تصویر در سرور فعال نیست (افزونه GD). از مدیر سرور بخواهید آن را فعال کند.'), true);
putenv('IMAGE_PROCESSING_FALLBACK');
check('policy back to passthrough', imageFallbackPolicyCurrent(), 'passthrough');

// 4. Valid PNG signature but garbage body: magic ok, dimensions fail.
check('png signature with garbage refused', rejects(function () {
    FileStore::storeImage(fileOf("\x89PNG\r\n\x1a\n" . str_repeat("\x00", 16), 'x.png'), 'media');
}), true);

// cleanup
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmpRoot, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($it as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
@rmdir($tmpRoot);
if ($fail > 0) { fwrite(STDERR, "$fail failure(s)\n"); exit(1); }
echo "file_store_none_test: OK\n";