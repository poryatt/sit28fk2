<?php
// The ONLY code allowed to write or delete uploaded files. Requires includes/file_rules.php.
// Constants used: UPLOAD_MEDIA, UPLOAD_AVATARS, UPLOAD_BASE, STORAGE_PRIVATE, MAX_IMAGE_SIZE, MAX_VIDEO_SIZE.
require_once __DIR__ . '/file_rules.php';
require_once __DIR__ . '/image_support.php';

/** Message is Persian and safe to show to the user. */
final class FileStoreException extends RuntimeException {}

final class FileStore
{
    /** @return array{path:string,abs:string,mime:string,ext:string,size_kb:int,width:?int,height:?int,sha256:string,file_type:string} */
    public static function storeImage(array $file, string $bucket, ?int $maxBytes = null): array
    {
        $tmp = self::checkUpload($file, $maxBytes ?? MAX_IMAGE_SIZE);
        $mime = self::mimeOf($tmp);
        $ext = imageExtForMime($mime);
        if ($ext === null) {
            throw new FileStoreException('فرمت تصویر مجاز نیست (jpg/png/webp)');
        }
        $bytes = file_get_contents($tmp);
        if ($bytes === false) {
            throw new FileStoreException('خطا در ذخیره فایل');
        }
        if (!imageMagicMatches(substr($bytes, 0, 12), $ext)) {
            throw new FileStoreException('تصویر معتبر نیست');
        }
        $info = @getimagesize($tmp);
        if ($info === false || !imageDimensionsAllowed((int)$info[0], (int)$info[1])) {
            throw new FileStoreException('ابعاد تصویر بیش از حد مجاز است یا تصویر معتبر نیست');
        }
        $sha256 = hash_file('sha256', $tmp);

        $dir = self::bucketDir($bucket);
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $abs = $dir . '/' . $name;

        if (self::imageMode() === 'gd') {
            $image = @imagecreatefromstring($bytes);
            if ($image === false) {
                throw new FileStoreException('تصویر معتبر نیست');
            }
            if ($ext === 'jpg' && function_exists('exif_read_data')) {
                $exif = @exif_read_data($tmp);
                $angle = [3 => 180, 6 => -90, 8 => 90][(int)($exif['Orientation'] ?? 1)] ?? 0;
                if ($angle !== 0) {
                    $rotated = imagerotate($image, $angle, 0);
                    if ($rotated !== false) { $image = $rotated; }
                }
            }
            $ok = false;
            if ($ext === 'jpg') {
                $ok = imagejpeg($image, $abs, 85);
            } elseif ($ext === 'png') {
                imagealphablending($image, false);
                imagesavealpha($image, true);
                $ok = imagepng($image, $abs, 6);
            } elseif (function_exists('imagewebp')) {
                imagepalettetotruecolor($image);
                imagealphablending($image, false);
                imagesavealpha($image, true);
                $ok = imagewebp($image, $abs, 85);
            } else {
                // GD without WebP support: keep the validated original bytes (EXIF may remain; recorded in the changelog).
                $ok = file_put_contents($abs, $bytes) !== false;
            }
            if (!$ok || !is_file($abs) || filesize($abs) === 0) {
                if (is_file($abs)) { @unlink($abs); }
                throw new FileStoreException('خطا در ذخیره فایل');
            }
        } else {
            // GD is not available. Per policy, either refuse or store the validated bytes unchanged.
            if (imageFallbackPolicyCurrent() === 'refuse') {
                throw new FileStoreException('پردازش تصویر در سرور فعال نیست (افزونه GD). از مدیر سرور بخواهید آن را فعال کند.');   // STR_GD_REFUSE
            }
            if (containsServerScriptMarkers($bytes)) {
                throw new FileStoreException('تصویر معتبر نیست');
            }
            if (file_put_contents($abs, $bytes) === false || !is_file($abs) || filesize($abs) === 0) {
                if (is_file($abs)) { @unlink($abs); }
                throw new FileStoreException('خطا در ذخیره فایل');
            }
            error_log('FileStore: GD missing, image stored without re-encoding');
        }

        $final = @getimagesize($abs);
        return [
            'path' => '/uploads/' . $bucket . '/' . $name, 'abs' => $abs, 'mime' => $mime, 'ext' => $ext,
            'size_kb' => (int)ceil(filesize($abs) / 1024),
            'width' => $final ? (int)$final[0] : (int)$info[0], 'height' => $final ? (int)$final[1] : (int)$info[1],
            'sha256' => $sha256, 'file_type' => 'image',
        ];
    }

    public static function storeVideo(array $file): array
    {
        $tmp = self::checkUpload($file, MAX_VIDEO_SIZE);
        $mime = self::mimeOf($tmp);
        $ext = videoExtForMime($mime);
        if ($ext === null) {
            throw new FileStoreException('فرمت ویدیو مجاز نیست (mp4/webm)');
        }
        $sha256 = hash_file('sha256', $tmp);
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $abs = self::bucketDir('media') . '/' . $name;
        self::moveUploaded($tmp, $abs);
        return [
            'path' => '/uploads/media/' . $name, 'abs' => $abs, 'mime' => $mime, 'ext' => $ext,
            'size_kb' => (int)ceil(filesize($abs) / 1024), 'width' => null, 'height' => null,
            'sha256' => $sha256, 'file_type' => 'video',
        ];
    }

    /** Favicon in .ico format only (GD cannot re-encode ICO, so the bytes are validated by MIME + magic bytes + size). */
    public static function storeIco(array $file, int $maxBytes = 1048576): array
    {
        $tmp = self::checkUpload($file, $maxBytes);
        $mime = self::mimeOf($tmp);
        $head = (string)file_get_contents($tmp, false, null, 0, 4);
        if (!isIcoMime($mime) || !hasIcoMagic($head)) {
            throw new FileStoreException('فایل ICO معتبر نیست');
        }
        $sha256 = hash_file('sha256', $tmp);
        $name = bin2hex(random_bytes(16)) . '.ico';
        $abs = self::bucketDir('media') . '/' . $name;
        self::moveUploaded($tmp, $abs);
        return [
            'path' => '/uploads/media/' . $name, 'abs' => $abs, 'mime' => 'image/x-icon', 'ext' => 'ico',
            'size_kb' => (int)ceil(filesize($abs) / 1024), 'width' => null, 'height' => null,
            'sha256' => $sha256, 'file_type' => 'image',
        ];
    }

    /**
     * Private file (outside public/): returns the path RELATIVE to STORAGE_PRIVATE, e.g. "licenses/<hex>.pdf".
     * @param array<string,string> $mimeToExt allowed MIME => extension
     * @return array{rel:string,abs:string,mime:string,ext:string,size_kb:int,sha256:string}
     */
    public static function storePrivate(array $file, string $kind, array $mimeToExt, int $maxBytes): array
    {
        if (!in_array($kind, ['licenses', 'templates'], true)) {
            throw new InvalidArgumentException('bad private kind');
        }
        $tmp = self::checkUpload($file, $maxBytes);
        $mime = self::mimeOf($tmp);
        if (!isset($mimeToExt[$mime])) {
            throw new FileStoreException('فرمت فایل مجاز نیست');
        }
        $ext = $mimeToExt[$mime];
        $sha256 = hash_file('sha256', $tmp);
        $dir = STORAGE_PRIVATE . '/' . $kind;
        self::ensureDir($dir);
        $rel = $kind . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
        $abs = STORAGE_PRIVATE . '/' . $rel;
        self::moveUploaded($tmp, $abs);
        return ['rel' => $rel, 'abs' => $abs, 'mime' => $mime, 'ext' => $ext, 'size_kb' => (int)ceil(filesize($abs) / 1024), 'sha256' => $sha256];
    }

    /** Deletes a public upload by its web path. Never throws; failures are logged. */
    public static function remove(?string $publicPath): bool
    {
        if ($publicPath === null || $publicPath === '' || !isSafePublicUploadPath($publicPath)) {
            return false;
        }
        $abs = UPLOAD_BASE . substr($publicPath, strlen('/uploads'));
        return self::unlinkInside($abs, UPLOAD_BASE);
    }

    /** Deletes a private file by its relative path. Never throws; failures are logged. */
    public static function removePrivate(?string $rel): bool
    {
        if ($rel === null || $rel === '' || !isSafePrivatePath($rel)) {
            return false;
        }
        return self::unlinkInside(STORAGE_PRIVATE . '/' . $rel, STORAGE_PRIVATE);
    }

    /** Absolute path of a private file, or null when the stored value is not a valid private path / file is missing. */
    public static function privateAbsolute(?string $rel): ?string
    {
        if ($rel === null || !isSafePrivatePath($rel)) { return null; }
        $abs = STORAGE_PRIVATE . '/' . $rel;
        return is_file($abs) ? $abs : null;
    }

    // ---- internals ----

    /** Test seam: FILESTORE_TESTING_MODE forces 'gd'|'none'; otherwise the real capability decides. */
    private static function imageMode(): string
    {
        if (defined('FILESTORE_TESTING') && FILESTORE_TESTING === true && defined('FILESTORE_TESTING_MODE')) {
            $mode = (string)FILESTORE_TESTING_MODE;
            if ($mode === 'gd' || $mode === 'none') {
                return $mode;
            }
        }
        return imageProcessingMode();
    }

    private static function checkUpload(array $file, int $maxBytes): string
    {
        $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            throw new FileStoreException(uploadErrorMessage($err));
        }
        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !self::isUploaded($tmp)) {
            throw new FileStoreException('فایلی ارسال نشده');
        }
        $size = @filesize($tmp);
        if ($size === false || $size <= 0) {
            throw new FileStoreException('فایل خالی است');
        }
        if ($size > $maxBytes) {
            throw new FileStoreException('حجم فایل بیش از حد مجاز است');
        }
        return $tmp;
    }

    private static function mimeOf(string $tmp): string
    {
        if (!function_exists('finfo_open')) {
            throw new FileStoreException('افزونه fileinfo در سرور فعال نیست');
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string)finfo_file($finfo, $tmp) : '';
        if ($finfo) { finfo_close($finfo); }
        return $mime;
    }

    private static function bucketDir(string $bucket): string
    {
        if ($bucket !== 'media' && $bucket !== 'avatars') {
            throw new InvalidArgumentException('bad bucket');
        }
        $dir = $bucket === 'media' ? UPLOAD_MEDIA : UPLOAD_AVATARS;
        self::ensureDir($dir);
        return $dir;
    }

    private static function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new FileStoreException('خطا در ایجاد پوشه ذخیره‌سازی');
        }
    }

    private static function isUploaded(string $tmp): bool
    {
        return (defined('FILESTORE_TESTING') && FILESTORE_TESTING === true) || is_uploaded_file($tmp);
    }

    private static function moveUploaded(string $tmp, string $dest): void
    {
        $ok = (defined('FILESTORE_TESTING') && FILESTORE_TESTING === true) ? copy($tmp, $dest) : move_uploaded_file($tmp, $dest);
        if (!$ok || !is_file($dest)) {
            throw new FileStoreException('خطا در ذخیره فایل');
        }
    }

    private static function unlinkInside(string $abs, string $baseDir): bool
    {
        $base = realpath($baseDir);
        $real = realpath($abs);
        if ($base === false || $real === false) { return false; }
        if (strpos($real, $base . DIRECTORY_SEPARATOR) !== 0) { return false; }
        if (!@unlink($real)) {
            error_log('FileStore: could not delete ' . $real);
            return false;
        }
        return true;
    }
}
