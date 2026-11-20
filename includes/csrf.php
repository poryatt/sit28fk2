<?php
require_once __DIR__ . '/session.php';
session_boot();

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

function requireCsrf(): void {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_X_CSRFTOKEN'] ?? null;
    if (!csrf_verify($token)) {
        if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false || strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
            jsonResponse(['success'=>false,'message'=>'توکن امنیتی نامعتبر است.'], 403);
        }
        http_response_code(403);
        die('توکن امنیتی نامعتبر است.');
    }
}
