<?php
// includes/layout/sidebar.php — renders $navGroups (see includes/nav.php). No plan box, no user card (the user menu lives in the top bar).
$brandUrl = $navGroups[0]['items'][0]['url'] ?? '/modules/settings/profile.php';
$scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
?>
<aside id="app-sidebar" class="app-sidebar" aria-label="منوی اصلی">
  <a class="app-brand" href="<?= e($brandUrl) ?>">
    <span class="brand-mark"><i class="ti ti-layout-dashboard" aria-hidden="true"></i></span>
    <span class="app-brand__text"><strong>پنل مدیریت</strong><small><?= e($siteSettings['site_name'] ?? 'وب‌سایت من') ?></small></span>
  </a>
  <nav class="app-nav" aria-label="منوی اصلی">
    <?php foreach ($navGroups as $group): ?>
    <p class="nav-group-title"><?= e($group['title']) ?></p>
    <ul class="nav-list">
      <?php foreach ($group['items'] as $item): $active = uiNavActive($item, $currentModule, $scriptName); ?>
      <li><a class="nav-link-item" href="<?= e($item['url']) ?>" data-tip="<?= e($item['label']) ?>"<?= $active ? ' aria-current="page"' : '' ?>><i class="ti <?= e($item['icon']) ?>" aria-hidden="true"></i><span class="nav-label"><?= e($item['label']) ?></span></a></li>
      <?php endforeach; ?>
    </ul>
    <?php endforeach; ?>
  </nav>
  <div class="app-sidebar__foot">
    <a class="nav-link-item nav-link-item--danger" href="/logout.php" data-tip="خروج از حساب"><i class="ti ti-logout" aria-hidden="true"></i><span class="nav-label">خروج از حساب</span></a>
  </div>
</aside>
<div class="app-backdrop" data-drawer-close></div>
