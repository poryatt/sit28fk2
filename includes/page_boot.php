<?php
// Everything a module page needs BEFORE it prints a single byte:
// session, login check, central page guard, security headers.
// header.php requires this file; a page that must handle a POST (redirect, session_regenerate_id,
// 403) before output requires it first. Safe to require twice.
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/permissions.php';

if (!defined('PAGE_BOOT_DONE')) {
    define('PAGE_BOOT_DONE', true);
    send_security_headers();
    requireLogin();
    enforcePageGuard();
}
