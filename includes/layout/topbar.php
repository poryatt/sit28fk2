<?php
// includes/layout/topbar.php — opens .app-main, prints the sticky top bar (the page's only <h1>) and opens <main>.
$crumbs = uiBreadcrumbParts((string) $breadcrumb, (string) $pageTitle);
?>
<div class="app-main">
<header class="app-topbar">
  <button type="button" class="icon-btn" data-sidebar-toggle aria-controls="app-sidebar" aria-expanded="<?= $sidebarState === 'collapsed' ? 'false' : 'true' ?>" aria-label="جمع کردن یا باز کردن منو"><i class="ti ti-layout-sidebar-right-collapse toggle-icon" aria-hidden="true"></i><i class="ti ti-menu-2 menu-icon" aria-hidden="true"></i></button>
  <div class="app-topbar__titles">
    <h1 class="app-topbar__title"><?= e($pageTitle) ?></h1>
    <?php if ($crumbs): ?>
    <ol class="app-topbar__crumb crumbs" aria-label="مسیر صفحه">
      <?php foreach ($crumbs as $i => $crumb): ?><li<?= $i === count($crumbs) - 1 ? ' aria-current="page"' : '' ?>><?= e($crumb) ?></li><?php endforeach; ?>
    </ol>
    <?php endif; ?>
  </div>
  <span class="app-topbar__spacer"></span>
  <div class="app-topbar__actions">
    <button type="button" class="icon-btn theme-toggle" data-theme-toggle aria-pressed="<?= $theme === 'dark' ? 'true' : 'false' ?>" aria-label="تغییر تم" data-tip="تغییر تم" data-tip-pos="bottom"><i class="ti ti-moon" aria-hidden="true"></i><i class="ti ti-sun" aria-hidden="true"></i></button>
    <?php if (NOTIFICATIONS_ENABLED): ?>
    <div class="dropdown">
      <button type="button" class="icon-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="اعلان‌ها"><i class="ti ti-bell" aria-hidden="true"></i><?php if ($unreadCount > 0): ?><span class="icon-btn__badge"><?= toPersianDigits($unreadCount) ?></span><?php endif; ?></button>
      <ul class="dropdown-menu dropdown-menu-end notif-menu">
        <li class="notif-menu__title">اعلان‌ها</li>
        <?php if (empty($notifications)): ?><li class="notif-menu__empty">اعلانی وجود ندارد</li>
        <?php else: foreach ($notifications as $n): ?>
        <li class="notif-menu__item<?= $n['is_read'] ? '' : ' is-unread' ?>"><strong><?= e($n['title']) ?></strong><span><?= e($n['message']) ?></span></li>
        <?php endforeach; endif; ?>
        <li class="notif-menu__foot"><button type="button" class="btn btn-link btn-sm" id="markAllRead">علامت‌گذاری همه به عنوان خوانده شده</button></li>
      </ul>
    </div>
    <?php endif; ?>
    <div class="dropdown">
      <button type="button" class="user-chip" data-bs-toggle="dropdown" aria-expanded="false" aria-label="حساب کاربری">
        <img class="user-chip__avatar" src="<?= e($user['avatar_path'] ?: '/assets/img/default-avatar.svg') ?>" alt="" width="36" height="36">
        <span class="user-chip__text"><span class="user-chip__name"><?= e($user['full_name']) ?></span><span class="user-chip__role"><?= $user['role'] === 'owner' ? 'مالک' : 'همکار' ?></span></span>
      </button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="/modules/settings/profile.php"><i class="ti ti-user-circle" aria-hidden="true"></i>پروفایل من</a></li>
        <li><a class="dropdown-item" href="/logout.php"><i class="ti ti-logout" aria-hidden="true"></i>خروج</a></li>
      </ul>
    </div>
  </div>
</header>
<main class="app-content<?= $mainClass !== '' ? ' ' . e($mainClass) : '' ?>" id="app-content" tabindex="-1">
