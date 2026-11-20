<!DOCTYPE html>
<html lang="fa" dir="rtl" data-bs-theme="<?= e($theme) ?>" data-sidebar="<?= e($sidebarState) ?>" data-theme-url="/api/staff/toggle_theme.php">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> - <?= e($siteSettings['site_name'] ?? 'پنل مدیریت') ?></title>
<?= vendorFontPreload() ?>
<?= vendorLinkTag('bootstrap', 'css/bootstrap.rtl.min.css') ?>
<?= vendorLinkTag('tabler-icons', 'tabler-icons.min.css') ?>
<?= uiStylesheets() ?>
<script>window.CSRF_TOKEN=<?= json_encode($csrf, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="/assets/js/app.js?v=<?= (int) @filemtime(PUBLIC_PATH . '/assets/js/app.js') ?>" defer></script>
</head>
<body>
<a class="skip-link visually-hidden-focusable" href="#app-content">رفتن به محتوای اصلی</a>
<div class="app">
