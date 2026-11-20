<?php
// Needs GD. Uses a temp directory; never touches the real uploads folder.
if (!extension_loaded('gd')) { echo "SKIP (gd)\n"; exit(0); }
define('FILESTORE_TESTING', true);
$tmpRoot = sys_get_temp_dir() . '/fs_test_' . bin2hex(random_bytes(4));
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

$im = imagecreatetruecolor(40, 20); imagefill($im, 0, 0, imagecolorallocate($im, 200, 10, 10));
ob_start(); imagejpeg($im); $jpeg = ob_get_clean();
ob_start(); imagepng($im); $png = ob_get_clean();
$webp = null; if (function_exists('imagewebp')) { ob_start(); imagewebp($im); $webp = ob_get_clean(); }

// 1. JPEG with trailing PHP payload is re-encoded: payload must vanish.
$evil = $jpeg . "<?php echo 'pwned'; ?>";
$r = FileStore::storeImage(fileOf($evil, 'x.jpg'), 'media');
check('jpeg stored', is_file($r['abs']), true);
check('jpeg ext', $r['ext'], 'jpg');
check('jpeg path shape', isSafePublicUploadPath($r['path']), true);
check('payload stripped', strpos((string)file_get_contents($r['abs']), '<?php') === false, true);
check('dims', [$r['width'], $r['height']], [40, 20]);
check('sha256 is of the original upload', $r['sha256'], hash('sha256', $evil));
check('remove works', FileStore::remove($r['path']), true);
check('file gone', is_file($r['abs']), false);
check('remove twice', FileStore::remove($r['path']), false);

// 2. PNG alpha path
$r2 = FileStore::storeImage(fileOf($png, 'a.png'), 'avatars');
check('png avatar bucket', strpos($r2['path'], '/uploads/avatars/') === 0, true);
FileStore::remove($r2['path']);

// 3. WebP
if ($webp !== null) { $r3 = FileStore::storeImage(fileOf($webp, 'a.webp'), 'media'); check('webp ext', $r3['ext'], 'webp'); FileStore::remove($r3['path']); }

// 4. rejections
check('html disguised as jpg', rejects(function () { FileStore::storeImage(fileOf('<html><script>alert(1)</script></html>', 'a.jpg'), 'media'); }, 'فرمت تصویر مجاز نیست (jpg/png/webp)'), true);
check('svg refused', rejects(function () { FileStore::storeImage(fileOf('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'a.svg'), 'media'); }), true);
check('gif refused', rejects(function () { FileStore::storeImage(fileOf("GIF89a\x01\x00\x01\x00\x00\x00\x00;", 'a.gif'), 'media'); }), true);
check('truncated image', rejects(function () use ($jpeg) { FileStore::storeImage(fileOf(substr($jpeg, 0, 50), 'a.jpg'), 'media'); }), true);
check('empty file', rejects(function () { FileStore::storeImage(fileOf('', 'a.jpg'), 'media'); }, 'فایل خالی است'), true);
check('too big', rejects(function () use ($jpeg) { FileStore::storeImage(fileOf($jpeg, 'a.jpg'), 'media', 10); }, 'حجم فایل بیش از حد مجاز است'), true);
check('upload error mapped', rejects(function () { FileStore::storeImage(['error' => UPLOAD_ERR_INI_SIZE, 'tmp_name' => '', 'name' => 'a'], 'media'); }, 'حجم فایل بیش از حد مجاز سرور است'), true);
check('bad bucket', (function () use ($jpeg) { try { FileStore::storeImage(fileOf($jpeg), '../etc'); } catch (InvalidArgumentException $e) { return true; } return false; })(), true);

// 5. ICO
$ico = "\x00\x00\x01\x00\x01\x00\x10\x10\x00\x00\x01\x00\x20\x00" . str_repeat("\x00", 40);
$rIco = (function () use ($ico) { try { return FileStore::storeIco(fileOf($ico, 'f.ico')); } catch (FileStoreException $e) { return $e->getMessage(); } })();
check('ico handled (stored or rejected by finfo build)', is_array($rIco) || $rIco === 'فایل ICO معتبر نیست', true);
if (is_array($rIco)) { FileStore::remove($rIco['path']); }
check('png as ico refused', rejects(function () use ($png) { FileStore::storeIco(fileOf($png, 'f.ico')); }, 'فایل ICO معتبر نیست'), true);

// 6. private
$pdf = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF";
$p = FileStore::storePrivate(fileOf($pdf, 'c.pdf'), 'licenses', ['application/pdf' => 'pdf'], 1024 * 1024);
check('private rel shape', isSafePrivatePath($p['rel']), true);
check('private under storage', strpos($p['abs'], STORAGE_PRIVATE . '/licenses/') === 0, true);
check('private absolute lookup', FileStore::privateAbsolute($p['rel']), $p['abs']);
check('private html refused', rejects(function () { FileStore::storePrivate(fileOf('<html></html>', 'c.pdf'), 'licenses', ['application/pdf' => 'pdf'], 1024); }, 'فرمت فایل مجاز نیست'), true);
check('private bad kind', (function () use ($pdf) { try { FileStore::storePrivate(fileOf($pdf), '../x', ['application/pdf' => 'pdf'], 1024); } catch (InvalidArgumentException $e) { return true; } return false; })(), true);
check('removePrivate traversal refused', FileStore::removePrivate('licenses/../../x.pdf'), false);
check('removePrivate works', FileStore::removePrivate($p['rel']), true);
check('privateAbsolute after delete', FileStore::privateAbsolute($p['rel']), null);

// 7. remove refuses anything outside the safe shape / base dir
file_put_contents($tmpRoot . '/victim.jpg', 'x');
check('remove traversal refused', FileStore::remove('/uploads/media/../../../victim.jpg'), false);
check('victim intact', is_file($tmpRoot . '/victim.jpg'), true);
check('remove null', FileStore::remove(null), false);

// cleanup
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmpRoot, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($it as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
@rmdir($tmpRoot);
if ($fail > 0) { fwrite(STDERR, "$fail failure(s)\n"); exit(1); }
echo "file_store_test: OK\n";
