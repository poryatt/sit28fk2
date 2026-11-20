<?php
// Pure rules for authentication. No DB, no session, no headers. Unit-tested in tests/auth_rules_test.php.

/**
 * Decides whether a login attempt must be refused because of too many recent failures.
 * $failsIdentifierIp = failed attempts for this identifier from this IP in the lock window.
 * $failsIp           = failed attempts from this IP (any identifier) in the lock window.
 * Returns 'identifier' | 'ip' | null (null = allowed to try).
 */
function loginLockReason(int $failsIdentifierIp, int $failsIp, int $maxIdentifierIp = 5, int $maxIp = 20): ?string {
    if ($failsIdentifierIp >= $maxIdentifierIp) { return 'identifier'; }
    if ($failsIp >= $maxIp) { return 'ip'; }
    return null;
}

/**
 * A session is stale when the password was changed after that session was authenticated.
 * $passwordChangedAt is the DATETIME string from panel_users (or null); $authAt is $_SESSION['auth_at'] (unix time, 0 if missing).
 * An unparsable date fails closed (stale).
 */
function sessionIsStale(?string $passwordChangedAt, int $authAt): bool {
    if ($passwordChangedAt === null || $passwordChangedAt === '') { return false; }
    $ts = strtotime($passwordChangedAt);
    if ($ts === false) { return true; }
    return $ts > $authAt;
}

/** While must_change_password = 1 only the profile page and logout are reachable. */
function mustChangeAllowedScript(string $scriptName): bool {
    $p = str_replace('\\', '/', $scriptName);
    foreach (['/modules/settings/profile.php', '/logout.php'] as $allowed) {
        $len = strlen($allowed);
        if (strlen($p) >= $len && substr($p, -$len) === $allowed) { return true; }
    }
    return false;
}
