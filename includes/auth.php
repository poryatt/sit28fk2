<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/permission_rules.php';
require_once __DIR__ . '/auth_rules.php';

session_boot();

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

/** Explicit column list: password_hash must never travel into templates. */
function currentUser(bool $refresh = false): ?array {
    if (!isLoggedIn()) return null;
    static $cached = null;
    static $cachedId = 0;
    $id = (int)$_SESSION['user_id'];
    if (!$refresh && $cached !== null && $cachedId === $id) return $cached;
    $stmt = getPDO()->prepare(
        "SELECT id, full_name, username, email, phone, avatar_path, role, status, theme_preference,
                must_change_password, password_changed_at, last_login_at
         FROM panel_users WHERE id = ? LIMIT 1"
    );
    $stmt->execute([$id]);
    $cached = $stmt->fetch() ?: null;
    $cachedId = $id;
    return $cached;
}

function requireLogin(): void {
    if (!isLoggedIn()) { authFail('expired'); }
    $u = currentUser();
    if (!$u || ($u['status'] ?? '') !== 'active') {
        $reason = ($u && ($u['status'] ?? '') === 'suspended') ? 'suspended' : 'invalid';
        logoutUser();
        authFail($reason);
    }
    // A password change (anywhere) kills every session that was authenticated before it.
    if (sessionIsStale($u['password_changed_at'] ?? null, (int)($_SESSION['auth_at'] ?? 0))) {
        logoutUser();
        authFail('invalid');
    }
    if (time() - (int)($_SESSION['last_seen'] ?? time()) > SESSION_IDLE_SECONDS) {
        logoutUser();
        authFail('timeout');
    }
    $_SESSION['last_seen'] = time();
    // Seed / invited accounts: only the profile page and logout are reachable until the password is changed.
    if (!empty($u['must_change_password']) && !mustChangeAllowedScript($_SERVER['SCRIPT_NAME'] ?? '')) {
        if (isApiRequest()) {
            jsonResponse(['success' => false, 'message' => 'ابتدا باید رمز عبور خود را تغییر دهید.', 'must_change_password' => true], 403);
        }
        header('Location: /modules/settings/profile.php?must_change=1');
        exit;
    }
}

/** Never returns. API → JSON 401; page → redirect to /login.php[?error=…]. */
function authFail(string $reason): void {
    if (isApiRequest()) {
        jsonResponse(['success' => false, 'message' => 'نشست شما منقضی شده است. دوباره وارد شوید.'], 401);
    }
    header('Location: /login.php' . ($reason === 'expired' ? '' : '?error=' . $reason));
    exit;
}

/** Call only after the credentials were verified and the account is active. */
function loginUser(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['auth_at'] = time();
    $_SESSION['last_seen'] = time();
    getPDO()->prepare("UPDATE panel_users SET last_login_at = NOW() WHERE id = ?")->execute([(int)$user['id']]);
    logActivity('login', 'auth', 'ورود به پنل');
}

function logoutUser(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
}

function isOwner(): bool {
    $u = currentUser();
    return $u !== null && ($u['role'] ?? '') === 'owner' && ($u['status'] ?? '') === 'active';
}

function logActivity(string $action_type, string $module_key, ?string $description = null): void {
    if (!isLoggedIn()) return;
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare("INSERT INTO activity_logs (panel_user_id, action_type, module_key, description, ip_address) VALUES (?,?,?,?,?)");
        $stmt->execute([
            (int)$_SESSION['user_id'],
            mb_substr($action_type, 0, 60, 'UTF-8'),
            mb_substr($module_key, 0, 50, 'UTF-8'),
            $description === null ? null : mb_substr($description, 0, 500, 'UTF-8'),
            getClientIp(),
        ]);
    } catch (Throwable $e) {
        // A logging failure must never break the real operation, but it must not be silent either.
        error_log('logActivity failed: ' . $e->getMessage());
    }
}
