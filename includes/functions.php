<?php
// توابع عمومی

function e(?string $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function toPersianDigits($input): string {
    $en = ['0','1','2','3','4','5','6','7','8','9'];
    $fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    return str_replace($en, $fa, (string)$input);
}

function formatToman($amount): string {
    if ($amount === null || $amount === '') return '۰';
    $formatted = number_format((float)$amount);
    return toPersianDigits($formatted) . ' تومان';
}

function toJalali(?string $gregorianDate, string $format = 'Y/m/d'): string {
    if (!$gregorianDate) return '-';
    try {
        $ts = strtotime($gregorianDate);
        if ($ts === false) return '-';
        // اگر کتابخانه morilog/jalali نصب باشد از آن استفاده کن
        if (class_exists('\Morilog\Jalali\Jalalian')) {
            $j = \Morilog\Jalali\Jalalian::fromDateTime(date('Y-m-d H:i:s', $ts));
            $out = $j->format($format);
            return toPersianDigits($out);
        }
        // fallback ساده: اگر کتابخانه نیست، از تابع jdate استفاده کن اگر وجود دارد
        if (function_exists('jdate')) {
            return toPersianDigits(jdate($format, $ts));
        }
        // fallback نهایی: تبدیل تقریبی ساده (برای نمایش – در تولید حتما morilog نصب شود)
        // فعلا همان میلادی را با ارقام فارسی برمی‌گردانیم
        return toPersianDigits(date($format, $ts));
    } catch (Throwable $e) {
        return '-';
    }
}

function toJalaliDateTime(?string $gregorianDate): string {
    return toJalali($gregorianDate, 'Y/m/d H:i');
}

function slugify(string $text): string {
    $text = trim($text);
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = trim($text, '-');
    $text = mb_strtolower($text, 'UTF-8');
    // اگر slug فارسی بود اجازه بده فارسی بماند، در غیر اینصورت لاتین
    $text = preg_replace('~[^-\w\x{0600}-\x{06FF}]+~u', '', $text);
    if (empty($text)) return 'n-' . bin2hex(random_bytes(3));
    return $text;
}

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function ensureUploadDir(string $path): void {
    if (!is_dir($path)) mkdir($path, 0755, true);
}

function generateRandomFilename(string $ext): string {
    return bin2hex(random_bytes(16)) . '.' . strtolower($ext);
}

function currentUrl(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/');
}

// ---- P1 additions: request identity, security headers, password policy, safe display names ----

function isApiRequest(): bool {
    return (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false)
        || (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false)
        || (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false);
}

function getClientIp(): string {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    $ip = filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
    $trusted = defined('TRUSTED_PROXIES') ? TRUSTED_PROXIES : [];
    if ($trusted && in_array($ip, $trusted, true)) {
        $cf = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
        if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP)) { return $cf; }
        $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($xff !== '') {
            foreach (array_reverse(array_map('trim', explode(',', $xff))) as $cand) {
                if (filter_var($cand, FILTER_VALIDATE_IP) && !in_array($cand, $trusted, true)) { return $cand; }
            }
        }
    }
    return $ip;
}

function send_security_headers(): void {
    if (headers_sent()) { return; }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
}

/** Returns a Persian error message, or null when the password satisfies the policy. Length is measured in bytes (bcrypt limit). */
function validatePasswordPolicy(string $pw): ?string {
    $min = defined('PASSWORD_MIN_LENGTH') ? PASSWORD_MIN_LENGTH : 8;
    $max = defined('PASSWORD_MAX_BYTES') ? PASSWORD_MAX_BYTES : 72;
    $len = strlen($pw);
    if ($len < $min || $len > $max) {
        return 'رمز عبور باید بین ۸ تا ۷۲ کاراکتر باشد';
    }
    return null;
}

/** Display-only file name from the client: no directory part, no control or markup characters, max 255 chars. */
function sanitizeClientFileName(string $name): string {
    $name = preg_replace('~^.*[\\\\/]~u', '', $name);
    if ($name === null) { return 'file'; }
    $name = preg_replace('~[\x00-\x1F\x7F<>"\'`\\\\/]~u', '', $name);
    if ($name === null) { return 'file'; }
    $name = mb_substr(trim($name), 0, 255, 'UTF-8');
    return $name === '' ? 'file' : $name;
}

// ---- P2 addition ----

/** The only way passwords are hashed in this project (explicit cost: PHP 8.4 changed the default). */
function hashPassword(string $plain): string {
    return password_hash($plain, PASSWORD_BCRYPT, ['cost' => 10]);
}
