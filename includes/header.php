<?php
// includes/header.php — entry point included by every module page (the include contract is unchanged).
// Page variables read here: $pageTitle, $breadcrumb, $currentModule, and the new optional $mainClass
// (e.g. 'app-content--flat' for pages that host #builder-root: no enter animation, no containing block).
require_once __DIR__ . '/page_boot.php';
require_once __DIR__ . '/vendor.php';
require_once __DIR__ . '/ui_assets.php';
require_once __DIR__ . '/layout_helpers.php';

$user = currentUser();
$csrf = csrf_token();
$pdo = getPDO();
if (NOTIFICATIONS_ENABLED) {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE panel_user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$user['id']]);
    $notifications = $stmt->fetchAll();
    $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE panel_user_id = ? AND is_read = 0");
    $stmt2->execute([$user['id']]);
    $unreadCount = (int) $stmt2->fetchColumn();
} else {
    $notifications = [];
    $unreadCount = 0;
}
$siteSettings = $pdo->query("SELECT * FROM site_settings WHERE id = 1 LIMIT 1")->fetch();
$pageTitle = $pageTitle ?? 'پنل مدیریت';
$breadcrumb = $breadcrumb ?? '';
$currentModule = $currentModule ?? '';
$mainClass = $mainClass ?? '';
$theme = (($user['theme_preference'] ?? 'light') === 'dark') ? 'dark' : 'light';
$sidebarState = (($_COOKIE['panel_sidebar'] ?? '') === 'collapsed') ? 'collapsed' : 'expanded';
$navGroups = uiNavVisibleGroups(require __DIR__ . '/nav.php', 'hasPermission', isOwner());

require __DIR__ . '/layout/head.php';     // <!DOCTYPE> … <body>, skip link, <div class="app">
require __DIR__ . '/layout/sidebar.php';  // <aside id="app-sidebar"> + backdrop
require __DIR__ . '/layout/topbar.php';   // <div class="app-main"><header class="app-topbar"> … <main id="app-content">
