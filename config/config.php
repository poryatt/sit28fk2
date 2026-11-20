<?php
require_once __DIR__ . '/env.php';
load_local_env();

// مسیرها
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/vendor/autoload.php';
define('PUBLIC_PATH', BASE_PATH . '/public');
define('UPLOAD_BASE', PUBLIC_PATH . '/uploads');
define('UPLOAD_MEDIA', UPLOAD_BASE . '/media');
define('UPLOAD_AVATARS', UPLOAD_BASE . '/avatars');
define('STORAGE_PRIVATE', BASE_PATH . '/storage/private');       // outside public/: never web-served

// محدودیت آپلود
define('MAX_IMAGE_SIZE', 5 * 1024 * 1024); // 5MB
define('MAX_VIDEO_SIZE', 50 * 1024 * 1024); // 50MB
define('MAX_CERT_BYTES', 5 * 1024 * 1024);                       // licence certificate (PDF/JPG/PNG/WEBP)
define('MAX_TEMPLATE_ZIP_BYTES', 50 * 1024 * 1024);              // the old code allowed 20 MB; raised to 50 MB (author's decision, record it)
define('MAX_ZIP_FILES', 500);                                    // entries inside a template zip
define('MAX_ZIP_UNPACKED_BYTES', 100 * 1024 * 1024);             // sum of uncompressed sizes (zip-bomb guard)

// تنظیمات امنیتی
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCK_MINUTES', 15);
define('CSRF_TOKEN_NAME', 'csrf_token');

// تنظیمات سایت
define('SITE_CHARSET', 'utf8mb4');

// ===== P1: environment, time zone, session, error handling =====
// Any APP_ENV value other than exactly 'local' is treated as production (fail safe).
define('APP_ENV', (getenv('APP_ENV') ?: 'local') === 'local' ? 'local' : 'production');
define('APP_TIMEZONE', 'Asia/Tehran');
define('DB_TIMEZONE_OFFSET', '+03:30');
date_default_timezone_set(APP_TIMEZONE);
define('SESSION_IDLE_SECONDS', 3600);
define('LOGIN_MAX_ATTEMPTS_PER_IP', 20);
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_MAX_BYTES', 72);
define('TRUSTED_PROXIES', array_values(array_filter(array_map('trim', explode(',', (string)(getenv('TRUSTED_PROXIES') ?: ''))))));
ini_set('log_errors', '1');
if (APP_ENV === 'production') { ini_set('display_errors', '0'); }
set_exception_handler(function (Throwable $e): void {
    error_log('Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) { http_response_code(500); }
    if (function_exists('isApiRequest') && isApiRequest()) {
        if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); }
        echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور'], JSON_UNESCAPED_UNICODE);
    } else {
        if (!headers_sent()) { header('Content-Type: text/html; charset=utf-8'); }
        echo '<h1 style="text-align:center;margin-top:50px;font-family:tahoma">خطای داخلی سرور</h1>';
        if (APP_ENV === 'local') { echo '<pre dir="ltr">' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>'; }
    }
});

// ===== P2: accounts =====
// The published seed hash (schema.sql). Any account whose password is this (or whose NEW password verifies against it) is refused.
define('SEED_PASSWORD_HASH', '$2y$10$cnvXc0McHVEK5Nml0ZvMwepHQ8jERISPDuFgn9BuwBsO6CZ5utTO.');
// bcrypt cost-10 hash of a random discarded string: password_verify() runs against it when the user does not exist (timing parity).
define('LOGIN_DUMMY_HASH', '$2y$10$gabpQVj0WCB74y5TAOIHO.tqD/ssMH/LR4sSRomD2k8Kr/v5nvlsq');

define('NOTIFICATIONS_ENABLED', false); // no code produces notifications yet; flip to true when something does
