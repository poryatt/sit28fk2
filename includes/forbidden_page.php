<?php
// includes/forbidden_page.php — the themed 403 page. permissions.php::denyAccess() prints it (Part 10B).
//   forbiddenPageHtml(false) = a complete document (the guard ran before any output)
//   forbiddenPageHtml(true)  = only a card, for the rare case where the shell has already started printing
// No database, no session, no user data: it must work for a signed-out or half-loaded request.

if (!function_exists('forbiddenPageHtml')) {
    function forbiddenPageHtml(bool $inline): string
    {
        $title = '۴۰۳ - دسترسی غیر مجاز';
        $text = 'شما مجوز دسترسی به این بخش را ندارید.';
        $back = 'بازگشت به پیشخوان';
        if ($inline) {
            return '<section class="card"><div class="empty-state"><span class="empty-state__icon"><i class="ti ti-lock" aria-hidden="true"></i></span>'
                . '<p class="empty-state__title">' . $title . '</p><p class="empty-state__text">' . $text . '</p>'
                . '<a class="btn btn-primary" href="/modules/dashboard/index.php">' . $back . '</a></div></section>';
        }
        require_once __DIR__ . '/vendor.php';
        require_once __DIR__ . '/ui_assets.php';
        $js = (defined('PUBLIC_PATH') ? PUBLIC_PATH : dirname(__DIR__) . '/public') . '/assets/js/app.js';
        ob_start();
        ?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $title ?></title>
<script>
(function () {
  var t = null;
  try { t = localStorage.getItem('panel-theme'); } catch (e) {}
  if (t !== 'light' && t !== 'dark') { t = (window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light'; }
  document.documentElement.setAttribute('data-bs-theme', t);
})();
</script>
<?= vendorFontPreload() ?>
<?= vendorLinkTag('bootstrap', 'css/bootstrap.rtl.min.css') ?>
<?= vendorLinkTag('tabler-icons', 'tabler-icons.min.css') ?>
<?= uiStylesheets() ?>
<script src="/assets/js/app.js?v=<?= (int) @filemtime($js) ?>" defer></script>
</head>
<body>
<main class="auth">
  <div class="auth-card">
    <div class="auth-card__brand">
      <span class="auth-card__mark" aria-hidden="true"><i class="ti ti-lock"></i></span>
      <h1 class="auth-card__title"><?= $title ?></h1>
      <p class="mb-0"><?= $text ?></p>
    </div>
    <a class="btn btn-primary" href="/modules/dashboard/index.php"><?= $back ?></a>
  </div>
</main>
</body>
</html>
<?php
        return (string) ob_get_clean();
    }
}
