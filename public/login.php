<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/vendor.php';
require_once __DIR__ . '/../includes/ui_assets.php';

send_security_headers();

if (!empty($_SESSION['user_id'])) { header('Location: /modules/dashboard/index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'توکن امنیتی نامعتبر است.';
    } else {
        $identifier = mb_substr(trim((string)($_POST['identifier'] ?? '')), 0, 190, 'UTF-8');
        $password = (string)($_POST['password'] ?? '');
        $ip = getClientIp();
        $pdo = getPDO();
        $window = "attempted_at > (NOW() - INTERVAL " . (int)LOGIN_LOCK_MINUTES . " MINUTE)";

        $st = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND ip_address = ? AND is_successful = 0 AND $window");
        $st->execute([$identifier, $ip]);
        $failsIdentifierIp = (int)$st->fetchColumn();
        $st = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND is_successful = 0 AND $window");
        $st->execute([$ip]);
        $failsIp = (int)$st->fetchColumn();

        $lock = loginLockReason($failsIdentifierIp, $failsIp, LOGIN_MAX_ATTEMPTS, LOGIN_MAX_ATTEMPTS_PER_IP);
        if ($lock === 'identifier') {
            $error = 'حساب شما به دلیل تلاش‌های ناموفق به مدت ۱۵ دقیقه قفل شده است.';
        } elseif ($lock === 'ip') {
            $error = 'تعداد تلاش‌های ناموفق از این شبکه بیش از حد مجاز است. ۱۵ دقیقه دیگر دوباره تلاش کنید.';
        } else {
            // '@' → e-mail, otherwise username: one deterministic row, never "email OR username".
            $col = (strpos($identifier, '@') !== false) ? 'email' : 'username';
            $st = $pdo->prepare("SELECT id, role, status, password_hash, must_change_password FROM panel_users WHERE $col = ? AND status <> 'removed' LIMIT 1");
            $st->execute([$identifier]);
            $user = $st->fetch();

            // Constant work whether or not the user exists (timing parity).
            $verified = password_verify($password, $user ? $user['password_hash'] : LOGIN_DUMMY_HASH);
            $ok = $user && $verified;
            $suspended = $ok && $user['status'] !== 'active';

            $pdo->prepare("INSERT INTO login_attempts (identifier, ip_address, is_successful) VALUES (?,?,?)")
                ->execute([$identifier, $ip, ($ok && !$suspended) ? 1 : 0]);
            if (random_int(1, 100) === 1) {
                $pdo->exec("DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 7 DAY)");
            }

            if ($suspended) {
                $error = 'حساب شما مسدود شده است.';
            } elseif ($ok) {
                // A good login clears this identifier+IP's failures (4 fails + 1 success + 1 fail must not lock).
                $pdo->prepare("DELETE FROM login_attempts WHERE identifier = ? AND ip_address = ? AND is_successful = 0")
                    ->execute([$identifier, $ip]);
                loginUser($user);
                header('Location: ' . (!empty($user['must_change_password']) ? '/modules/settings/profile.php?must_change=1' : '/modules/dashboard/index.php'));
                exit;
            } else {
                $error = 'نام کاربری یا رمز عبور اشتباه است.';
            }
        }
    }
}
$_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$notice = '';
$errCode = $_GET['error'] ?? '';
if ($errCode === 'suspended') { $notice = 'حساب شما مسدود است.'; }
elseif ($errCode === 'timeout') { $notice = 'نشست شما به دلیل عدم فعالیت منقضی شد. دوباره وارد شوید.'; }
elseif ($errCode === 'invalid') { $notice = 'نشست شما معتبر نیست. دوباره وارد شوید.'; }
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ورود به پنل مدیریت</title>
<script>
/* First paint without a flash. There is no signed-in user here, so: the choice stored by PanelUI, else the OS setting. */
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
<script src="/assets/js/app.js?v=<?= (int) @filemtime(PUBLIC_PATH . '/assets/js/app.js') ?>" defer></script>
</head>
<body>
<main class="auth">
  <button type="button" class="icon-btn theme-toggle auth__theme" data-theme-toggle aria-pressed="false" aria-label="تغییر تم" data-tip="تغییر تم" data-tip-pos="bottom"><i class="ti ti-moon" aria-hidden="true"></i><i class="ti ti-sun" aria-hidden="true"></i></button>
  <div class="auth-card">
    <div class="auth-card__brand">
      <span class="auth-card__mark" aria-hidden="true"><i class="ti ti-layout-dashboard"></i></span>
      <h1 class="auth-card__title">ورود به پنل مدیریت</h1>
    </div>
    <?php if ($error): ?><div class="alert alert-danger d-flex align-items-start gap-2" role="alert"><i class="ti ti-alert-circle" aria-hidden="true"></i><span><?= e($error) ?></span></div><?php endif; ?>
    <?php if ($notice): ?><div class="alert alert-warning d-flex align-items-start gap-2" role="status"><i class="ti ti-info-circle" aria-hidden="true"></i><span><?= e($notice) ?></span></div><?php endif; ?>
    <form method="post" novalidate>
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
      <div class="field">
        <label class="field__label" for="login-identifier">ایمیل یا نام کاربری</label>
        <span class="input-icon"><i class="ti ti-user" aria-hidden="true"></i><input id="login-identifier" name="identifier" class="form-control input-ltr" maxlength="190" required autocomplete="username" autocapitalize="none" spellcheck="false" autofocus></span>
      </div>
      <div class="field">
        <label class="field__label" for="login-password">رمز عبور</label>
        <span class="input-icon input-icon--action"><i class="ti ti-lock" aria-hidden="true"></i><input id="login-password" type="password" name="password" class="form-control input-ltr" required autocomplete="current-password"><button type="button" class="icon-btn" data-password-toggle aria-pressed="false" aria-label="نمایش رمز عبور" data-label-show="نمایش رمز عبور" data-label-hide="پنهان کردن رمز عبور"><i class="ti ti-eye" aria-hidden="true"></i></button></span>
      </div>
      <button type="submit" class="btn btn-primary">ورود</button>
    </form>
  </div>
</main>
</body>
</html>
