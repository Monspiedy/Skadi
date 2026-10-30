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
        'wallet_balance' => (string) ($user['wallet_balance'] ?? '0.00'),
        'cart_game_count' => (int) ($user['cart_game_count'] ?? 0),
        'unread_chat_count' => (int) ($user['unread_chat_count'] ?? 0),
        'pending_friend_count' => (int) ($user['pending_friend_count'] ?? 0),
        'role' => (string) $user['role'],
        'status' => (string) $user['status'],
        'must_change_password' => (bool) $user['must_change_password'],
        'theme' => normalizeTheme($user['theme'] ?? 'skadi'),
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

    // The database-backed session refresh derives both private navbar totals in this one
    // prepared request query, avoiding N+1 reads while excluding read/hidden/outgoing,
    // inactive, non-accepted and blocked rows.
    $statement = db()->prepare(
        'SELECT u.id, u.display_name, u.skadi_id, u.wallet_balance, '
        . '(SELECT COUNT(*) FROM cart_items ci WHERE ci.user_id = u.id) AS cart_game_count, '
        . '(SELECT COUNT(*) FROM message_user_states mus '
        . 'INNER JOIN messages m ON m.id = mus.message_id '
        . 'INNER JOIN conversations c ON c.id = m.conversation_id '
        . 'INNER JOIN friendships f ON f.user_low_id = c.user_low_id AND f.user_high_id = c.user_high_id '
        . 'INNER JOIN users chat_friend ON chat_friend.id = CASE '
        . 'WHEN c.user_low_id = u.id THEN c.user_high_id ELSE c.user_low_id END '
        . 'LEFT JOIN user_blocks chat_block ON chat_block.user_low_id = c.user_low_id '
        . 'AND chat_block.user_high_id = c.user_high_id '
        . 'WHERE mus.user_id = u.id AND m.sender_user_id <> u.id '
        . 'AND (c.user_low_id = u.id OR c.user_high_id = u.id) '
        . 'AND mus.read_at IS NULL AND mus.hidden_at IS NULL '
        . "AND f.status = 'accepted' AND chat_block.id IS NULL AND chat_friend.status = 'active') "
        . 'AS unread_chat_count, '
        . '(SELECT COUNT(*) FROM friendships incoming_friendship '
        . 'INNER JOIN users requester ON requester.id = incoming_friendship.requested_by_user_id '
        . 'LEFT JOIN user_blocks request_block ON request_block.user_low_id = incoming_friendship.user_low_id '
        . 'AND request_block.user_high_id = incoming_friendship.user_high_id '
        . 'WHERE (incoming_friendship.user_low_id = u.id OR incoming_friendship.user_high_id = u.id) '
        . "AND incoming_friendship.status = 'pending' AND incoming_friendship.requested_by_user_id <> u.id "
        . "AND requester.status = 'active' AND request_block.id IS NULL) AS pending_friend_count, "
        . 'u.status, u.must_change_password, r.name AS role, '
        . 'COALESCE(up.theme, :default_theme) AS theme FROM users u '
        . 'INNER JOIN roles r ON r.id = u.role_id '
        . 'LEFT JOIN user_profiles up ON up.user_id = u.id WHERE u.id = :id LIMIT 1'
    );
    $statement->execute([
        'default_theme' => 'skadi',
        'id' => (int) $sessionUser['id'],
    ]);
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
        'wallet_balance' => (string) $databaseUser['wallet_balance'],
        'cart_game_count' => (int) $databaseUser['cart_game_count'],
        'unread_chat_count' => (int) $databaseUser['unread_chat_count'],
        'pending_friend_count' => (int) $databaseUser['pending_friend_count'],
        'role' => (string) $databaseUser['role'],
        'status' => (string) $databaseUser['status'],
        'must_change_password' => (bool) $databaseUser['must_change_password'],
        'theme' => normalizeTheme($databaseUser['theme'] ?? 'skadi'),
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
