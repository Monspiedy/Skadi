<?php

declare(strict_types=1);

function currentUser(): ?array
{
    $user = $_SESSION['authenticated_user'] ?? null;

    return is_array($user) ? $user : null;
}

function isAuthenticated(): bool
{
    return currentUser() !== null;
}

function authenticationDestination(): string
{
    return !empty(currentUser()['must_change_password'])
        ? 'change-password.php'
        : 'account.php';
}

function setAuthenticatedUser(array $user): void
{
    session_regenerate_id(true);
    unset($_SESSION['csrf_token']);

    $_SESSION['authenticated_user'] = [
        'id' => (int) $user['id'],
        'display_name' => (string) $user['display_name'],
        'skadi_id' => (string) $user['skadi_id'],
        'role' => (string) $user['role'],
        'status' => (string) $user['status'],
        'must_change_password' => (bool) $user['must_change_password'],
    ];
    $_SESSION['last_seen_at'] = time();
    $_SESSION['activity_recorded_at'] = time();
}

function clearAuthenticatedUser(bool $regenerateSession = true): void
{
    unset(
        $_SESSION['authenticated_user'],
        $_SESSION['last_seen_at'],
        $_SESSION['activity_recorded_at'],
        $_SESSION['csrf_token']
    );

    if ($regenerateSession && session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
        session_regenerate_id(true);
    }
}

function clearEntireSession(): void
{
    // Keep the session active for the post-logout flash message, but discard all prior user state.
    $_SESSION = [];

    if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
        session_regenerate_id(true);
    }
}

function synchronizeAuthenticatedSession(): void
{
    $sessionUser = currentUser();

    if ($sessionUser === null) {
        return;
    }

    $now = time();
    $lastSeenAt = (int) ($_SESSION['last_seen_at'] ?? $now);
    $idleTimeout = (int) appConfig('session_idle_timeout', 1800);

    if ($now - $lastSeenAt > $idleTimeout) {
        clearAuthenticatedUser();
        addFlashMessage('warning', 'Your session expired because it was inactive. Please sign in again.');
        return;
    }

    $statement = db()->prepare(
        'SELECT u.id, u.display_name, u.skadi_id, u.status, u.must_change_password, r.name AS role '
        . 'FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.id = :id LIMIT 1'
    );
    $statement->execute(['id' => (int) $sessionUser['id']]);
    $databaseUser = $statement->fetch();

    if (!is_array($databaseUser) || $databaseUser['status'] !== 'active') {
        clearAuthenticatedUser();
        addFlashMessage('warning', 'This account is not available.');
        return;
    }

    $_SESSION['authenticated_user'] = [
        'id' => (int) $databaseUser['id'],
        'display_name' => (string) $databaseUser['display_name'],
        'skadi_id' => (string) $databaseUser['skadi_id'],
        'role' => (string) $databaseUser['role'],
        'status' => (string) $databaseUser['status'],
        'must_change_password' => (bool) $databaseUser['must_change_password'],
    ];
    $_SESSION['last_seen_at'] = $now;

    $recordedAt = (int) ($_SESSION['activity_recorded_at'] ?? 0);
    $updateInterval = (int) appConfig('activity_update_interval', 60);

    if ($now - $recordedAt >= $updateInterval) {
        $update = db()->prepare('UPDATE users SET last_activity_at = UTC_TIMESTAMP() WHERE id = :id');
        $update->execute(['id' => (int) $databaseUser['id']]);
        $_SESSION['activity_recorded_at'] = $now;
    }
}

function userHasRole(string $role): bool
{
    return isAuthenticated() && (currentUser()['role'] ?? null) === $role;
}

function requireGuest(): void
{
    if (isAuthenticated()) {
        redirectTo(authenticationDestination());
    }
}

function requireAuth(bool $allowPasswordChange = false): void
{
    if (!isAuthenticated()) {
        addFlashMessage('warning', 'Please sign in to continue.');
        redirectTo('login.php');
    }

    if (!$allowPasswordChange && !empty(currentUser()['must_change_password'])) {
        addFlashMessage('warning', 'Change the temporary password before continuing.');
        redirectTo('change-password.php');
    }
}

function requireAdmin(): void
{
    requireAuth();

    if (!userHasRole('admin')) {
        renderErrorPage(403, 'Access denied', 'Your account does not have permission to view this page.');
    }
}

function requireAuthentication(): void
{
    requireAuth();
}

function requireRole(string $role): void
{
    if ($role === 'admin') {
        requireAdmin();
        return;
    }

    requireAuth();

    if (!userHasRole($role)) {
        renderErrorPage(403, 'Access denied', 'Your account does not have permission to view this page.');
    }
}
