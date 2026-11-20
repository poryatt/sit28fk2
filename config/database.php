<?php
require_once __DIR__ . '/config.php';

function getPDO(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    // Production fails closed: every setting must come from the environment and root is refused.
    $host = getenv('DB_HOST');
    $db   = getenv('DB_NAME');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS');
    if (APP_ENV === 'production') {
        if (!$host || !$db || !$user || $pass === false || $pass === '' || strtolower((string)$user) === 'root') {
            error_log('Refusing to start: production needs DB_HOST, DB_NAME, DB_USER (not root) and a non-empty DB_PASS in the environment.');
            failDatabase();
        }
    } else {
        $host = $host ?: '127.0.0.1';
        $db   = $db   ?: 'business_panel';
        $user = $user ?: 'root';
        $pass = ($pass === false) ? '' : $pass;
    }

    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("SET time_zone = '" . DB_TIMEZONE_OFFSET . "'");
    } catch (PDOException $e) {
        error_log('DB connection failed: ' . $e->getMessage());
        failDatabase();
    }
    return $pdo;
}

/** Never returns. Generic message only. */
function failDatabase(): void {
    if (!headers_sent()) { http_response_code(500); }
    if (function_exists('isApiRequest') && isApiRequest()) {
        if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); }
        echo json_encode(['success' => false, 'message' => 'خطا در اتصال به پایگاه داده'], JSON_UNESCAPED_UNICODE);
    } else {
        if (!headers_sent()) { header('Content-Type: text/plain; charset=utf-8'); }
        echo 'خطا در اتصال به پایگاه داده';
    }
    exit;
}
