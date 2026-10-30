<?php

declare(strict_types=1);

const MAX_ACCEPTED_FRIENDS = 100;

class SocialException extends RuntimeException
{
}

function canonicalUserPair(int $firstUserId, int $secondUserId): array
{
    if ($firstUserId < 1 || $secondUserId < 1 || $firstUserId === $secondUserId) {
        throw new SocialException('Choose another Skadi user.');
    }

    return $firstUserId < $secondUserId
        ? [$firstUserId, $secondUserId]
        : [$secondUserId, $firstUserId];
}

function normalizedProfileInput(array $input): array
{
    return [
        'display_name' => trim((string) ($input['display_name'] ?? '')),
        'email' => mb_strtolower(trim((string) ($input['email'] ?? ''))),
        'biography' => trim((string) ($input['biography'] ?? '')),
        'theme' => strtolower(trim((string) ($input['theme'] ?? ''))),
        'profile_visibility' => strtolower(trim((string) ($input['profile_visibility'] ?? ''))),
        'friend_request_setting' => strtolower(trim((string) ($input['friend_request_setting'] ?? ''))),
        'show_online_status' => ($input['show_online_status'] ?? null) === '1',
        'remove_avatar' => ($input['remove_avatar'] ?? null) === '1',
    ];
}

function profileInputErrors(array $input): array
{
    $errors = [];
    if (($error = displayNameError($input['display_name'])) !== null) {
        $errors['display_name'] = $error;
    }
    if (strlen($input['email']) > 254 || ($error = emailError($input['email'])) !== null) {
        $errors['email'] = 'Email must be a valid address no longer than 254 characters.';
    }
    $biographyWithoutWhitespace = str_replace(["\r", "\n", "\t"], '', $input['biography']);
    if (mb_strlen($input['biography']) > 1000 || preg_match('/\p{C}/u', $biographyWithoutWhitespace) === 1) {
        $errors['biography'] = 'Biography must be at most 1000 readable characters.';
    }
    if (!in_array($input['theme'], allowedThemes(), true)) {
        $errors['theme'] = 'Choose an available theme.';
    }
    if (!in_array($input['profile_visibility'], ['public', 'friends_only', 'private'], true)) {
        $errors['profile_visibility'] = 'Choose a valid profile visibility.';
    }
    if (!in_array($input['friend_request_setting'], ['everyone', 'friends_of_friends', 'nobody'], true)) {
        $errors['friend_request_setting'] = 'Choose a valid friend-request preference.';
    }

    return $errors;
}

function loadOwnProfile(PDO $connection, int $userId): ?array
{
    $statement = $connection->prepare(
        'SELECT u.id, u.email, u.skadi_id, u.display_name, u.created_at, '
        . 'up.biography, up.avatar_path, up.theme, up.profile_visibility, '
        . 'up.friend_request_setting, up.show_online_status '
        . 'FROM users u INNER JOIN user_profiles up ON up.user_id = u.id '
        . 'WHERE u.id = :user_id AND u.status = :status LIMIT 1'
    );
    $statement->execute(['user_id' => $userId, 'status' => 'active']);
    $profile = $statement->fetch();

    return is_array($profile) ? $profile : null;
}

function updateProfileSettings(PDO $connection, int $userId, array $input, ?string $newAvatarPath): ?string
{
    $connection->beginTransaction();
    try {
        $lock = $connection->prepare(
            'SELECT u.id, u.email, up.avatar_path FROM users u '
            . 'INNER JOIN user_profiles up ON up.user_id = u.id '
            . 'WHERE u.id = :user_id AND u.status = :status FOR UPDATE'
        );
        $lock->execute(['user_id' => $userId, 'status' => 'active']);
        $current = $lock->fetch();
        if (!is_array($current)) {
            throw new SocialException('The profile is no longer available.');
        }

        $emailCheck = $connection->prepare('SELECT 1 FROM users WHERE email = :email AND id <> :user_id LIMIT 1');
        $emailCheck->execute(['email' => $input['email'], 'user_id' => $userId]);
        if ($emailCheck->fetchColumn() !== false) {
            throw new SocialException('This email cannot be used for the profile.');
        }

        $userUpdate = $connection->prepare(
            'UPDATE users SET display_name = :display_name, email = :email WHERE id = :user_id'
        );
        $userUpdate->execute([
            'display_name' => $input['display_name'],
            'email' => $input['email'],
            'user_id' => $userId,
        ]);

        $avatarPath = $newAvatarPath;
        if ($avatarPath === null && !$input['remove_avatar']) {
            $avatarPath = $current['avatar_path'];
        }
        $profileUpdate = $connection->prepare(
            'UPDATE user_profiles SET biography = :biography, avatar_path = :avatar_path, theme = :theme, '
            . 'profile_visibility = :profile_visibility, friend_request_setting = :friend_request_setting, '
            . 'show_online_status = :show_online_status WHERE user_id = :user_id'
        );
        $profileUpdate->execute([
            'biography' => $input['biography'] === '' ? null : $input['biography'],
            'avatar_path' => $avatarPath,
            'theme' => $input['theme'],
            'profile_visibility' => $input['profile_visibility'],
            'friend_request_setting' => $input['friend_request_setting'],
            'show_online_status' => $input['show_online_status'] ? 1 : 0,
            'user_id' => $userId,
        ]);
        $connection->commit();

        $sessionUser = currentUser();
        if ($sessionUser !== null && (int) $sessionUser['id'] === $userId) {
            $_SESSION['authenticated_user']['display_name'] = $input['display_name'];
            $_SESSION['authenticated_user']['theme'] = $input['theme'];
        }

        return is_string($current['avatar_path']) ? $current['avatar_path'] : null;
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    }
}

function loadPublicProfileBySkadiId(PDO $connection, string $skadiId): ?array
{
    $statement = $connection->prepare(
        'SELECT u.id, u.skadi_id, u.display_name, u.last_activity_at, u.created_at, '
        . 'up.biography, up.avatar_path, up.profile_visibility, up.show_online_status '
        . 'FROM users u INNER JOIN user_profiles up ON up.user_id = u.id '
        . 'WHERE u.skadi_id = :skadi_id AND u.status = :status LIMIT 1'
    );
    $statement->execute(['skadi_id' => strtoupper(trim($skadiId)), 'status' => 'active']);
    $profile = $statement->fetch();

    return is_array($profile) ? $profile : null;
}

function isUserPairBlocked(PDO $connection, int $firstUserId, int $secondUserId): bool
{
    [$lowId, $highId] = canonicalUserPair($firstUserId, $secondUserId);
    $statement = $connection->prepare(
        'SELECT 1 FROM user_blocks WHERE user_low_id = :low_id AND user_high_id = :high_id LIMIT 1'
    );
    $statement->execute(['low_id' => $lowId, 'high_id' => $highId]);

    return $statement->fetchColumn() !== false;
}

function areAcceptedFriends(PDO $connection, int $firstUserId, int $secondUserId): bool
{
    [$lowId, $highId] = canonicalUserPair($firstUserId, $secondUserId);
    $statement = $connection->prepare(
        'SELECT 1 FROM friendships WHERE user_low_id = :low_id AND user_high_id = :high_id '
        . 'AND status = :status LIMIT 1'
    );
    $statement->execute(['low_id' => $lowId, 'high_id' => $highId, 'status' => 'accepted']);

    return $statement->fetchColumn() !== false;
}

/**
 * One central decision protects library, review, activity and friend-list sections.
 */
function profilePrivacyAccess(PDO $connection, array $profile, ?int $viewerUserId): array
{
    $ownerId = (int) $profile['id'];
    if ($viewerUserId === $ownerId) {
        return ['can_view_details' => true, 'is_owner' => true, 'is_friend' => false, 'is_blocked' => false];
    }

    $blocked = $viewerUserId !== null && isUserPairBlocked($connection, $ownerId, $viewerUserId);
    $friends = !$blocked && $viewerUserId !== null && areAcceptedFriends($connection, $ownerId, $viewerUserId);
    $visibility = (string) $profile['profile_visibility'];

    return [
        'can_view_details' => !$blocked && ($visibility === 'public' || ($visibility === 'friends_only' && $friends)),
        'is_owner' => false,
        'is_friend' => $friends,
        'is_blocked' => $blocked,
    ];
}

function publicPresenceLabel(array $profile, bool $isOwner = false, ?DateTimeImmutable $now = null): ?string
{
    if (!(bool) $profile['show_online_status']) {
        return $isOwner ? 'Hidden' : null;
    }
    if (empty($profile['last_activity_at'])) {
        return 'Offline';
    }

    $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $lastActivity = new DateTimeImmutable((string) $profile['last_activity_at'], new DateTimeZone('UTC'));
    $seconds = max(0, $now->getTimestamp() - $lastActivity->getTimestamp());
    if ($seconds <= 300) {
        return 'Online';
    }
    if ($seconds <= 86400) {
        return 'Recently online';
    }

    return 'Offline';
}

function loadProfileLibrary(PDO $connection, int $userId, int $limit = 12): array
{
    $statement = $connection->prepare(
        "SELECT g.id, g.title, li.acquired_at,
                cover.storage_path AS cover_path, cover.alt_text AS cover_alt
         FROM library_items li INNER JOIN games g ON g.id = li.game_id
         LEFT JOIN game_media cover
            ON cover.game_id = g.id AND cover.media_type = 'cover' AND cover.sort_order = 0
         WHERE li.user_id = :user_id AND li.removed_at IS NULL AND li.hidden_at IS NULL
         ORDER BY li.acquired_at DESC LIMIT " . max(1, min(50, $limit))
    );
    $statement->execute(['user_id' => $userId]);

    return $statement->fetchAll();
}

function loadProfileReviews(PDO $connection, int $userId, int $limit = 12): array
{
    $statement = $connection->prepare(
        "SELECT r.game_id, g.title, r.rating, r.body, r.created_at, r.updated_at,
                cover.storage_path AS cover_path, cover.alt_text AS cover_alt
         FROM reviews r INNER JOIN games g ON g.id = r.game_id
         LEFT JOIN game_media cover
            ON cover.game_id = g.id AND cover.media_type = 'cover' AND cover.sort_order = 0
         WHERE r.user_id = :user_id AND r.status = 'active'
         ORDER BY r.updated_at DESC LIMIT " . max(1, min(50, $limit))
    );
    $statement->execute(['user_id' => $userId]);

    return $statement->fetchAll();
}

/**
 * Call only after profilePrivacyAccess() grants detail visibility. These counts use
 * the same visible rows as the profile sections, so private data cannot leak in totals.
 */
function loadProfileStatistics(PDO $connection, int $userId): array
{
    $statement = $connection->prepare(
        "SELECT
            (SELECT COUNT(*) FROM library_items
             WHERE user_id = :library_user_id AND removed_at IS NULL AND hidden_at IS NULL) AS game_count,
            (SELECT COUNT(*) FROM reviews
             WHERE user_id = :review_user_id AND status = 'active') AS review_count,
            (SELECT COUNT(*) FROM friendships f
             INNER JOIN users friend_user ON friend_user.id = CASE
                WHEN f.user_low_id = :friend_select_id THEN f.user_high_id ELSE f.user_low_id END
             WHERE (f.user_low_id = :friend_low_id OR f.user_high_id = :friend_high_id)
               AND f.status = 'accepted' AND friend_user.status = 'active') AS friend_count"
    );
    $statement->execute([
        'library_user_id' => $userId,
        'review_user_id' => $userId,
        'friend_select_id' => $userId,
        'friend_low_id' => $userId,
        'friend_high_id' => $userId,
    ]);
    $statistics = $statement->fetch() ?: [];

    return [
        'game_count' => (int) ($statistics['game_count'] ?? 0),
        'review_count' => (int) ($statistics['review_count'] ?? 0),
        'friend_count' => (int) ($statistics['friend_count'] ?? 0),
    ];
}

function loadAcceptedFriends(PDO $connection, int $userId, int $limit = 100): array
{
    $statement = $connection->prepare(
        "SELECT u.id, u.skadi_id, u.display_name, u.last_activity_at, up.avatar_path, up.show_online_status
         FROM friendships f
         INNER JOIN users u ON u.id = CASE WHEN f.user_low_id = :user_id_select
             THEN f.user_high_id ELSE f.user_low_id END
         INNER JOIN user_profiles up ON up.user_id = u.id
         WHERE (f.user_low_id = :user_id_low OR f.user_high_id = :user_id_high)
           AND f.status = 'accepted' AND u.status = 'active'
         ORDER BY u.display_name, u.skadi_id LIMIT " . max(1, min(MAX_ACCEPTED_FRIENDS, $limit))
    );
    $statement->execute([
        'user_id_select' => $userId,
        'user_id_low' => $userId,
        'user_id_high' => $userId,
    ]);

    return $statement->fetchAll();
}

function buildProfileActivity(array $library, array $reviews, int $limit = 12): array
{
    $activity = [];
    foreach ($library as $item) {
        $activity[] = [
            'type' => 'library',
            'game_id' => (int) $item['id'],
            'game_title' => (string) $item['title'],
            'cover_path' => $item['cover_path'] ?? null,
            'cover_alt' => $item['cover_alt'] ?? null,
            'occurred_at' => (string) $item['acquired_at'],
        ];
    }
    foreach ($reviews as $review) {
        $activity[] = [
            'type' => 'review',
            'game_id' => (int) $review['game_id'],
            'game_title' => (string) $review['title'],
            'cover_path' => $review['cover_path'] ?? null,
            'cover_alt' => $review['cover_alt'] ?? null,
            'occurred_at' => (string) $review['updated_at'],
        ];
    }
    usort($activity, static fn (array $left, array $right): int => strcmp($right['occurred_at'], $left['occurred_at']));

    return array_slice($activity, 0, max(1, $limit));
}

function escapeLikeSearch(string $value): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
}

function searchPublicProfiles(PDO $connection, string $query, int $limit = 30): array
{
    $query = trim($query);
    if ($query === '' || mb_strlen($query) > 80) {
        return [];
    }

    $statement = $connection->prepare(
        "SELECT u.id, u.skadi_id, u.display_name, u.last_activity_at, up.avatar_path, up.show_online_status
         FROM users u INNER JOIN user_profiles up ON up.user_id = u.id
         WHERE u.status = 'active' AND (u.skadi_id = :skadi_id OR u.display_name LIKE :display_name ESCAPE '\\\\')
         ORDER BY (u.skadi_id = :exact_order) DESC, u.display_name, u.skadi_id
         LIMIT " . max(1, min(50, $limit))
    );
    $statement->execute([
        'skadi_id' => strtoupper($query),
        'display_name' => '%' . escapeLikeSearch($query) . '%',
        'exact_order' => strtoupper($query),
    ]);

    return $statement->fetchAll();
}

function relationshipForUsers(PDO $connection, int $viewerUserId, int $otherUserId): array
{
    [$lowId, $highId] = canonicalUserPair($viewerUserId, $otherUserId);
    $statement = $connection->prepare(
        'SELECT f.status, f.requested_by_user_id, ub.blocked_by_user_id '
        . 'FROM (SELECT :low_id AS low_id, :high_id AS high_id) pair_row '
        . 'LEFT JOIN friendships f ON f.user_low_id = pair_row.low_id AND f.user_high_id = pair_row.high_id '
        . 'LEFT JOIN user_blocks ub ON ub.user_low_id = pair_row.low_id AND ub.user_high_id = pair_row.high_id'
    );
    $statement->execute(['low_id' => $lowId, 'high_id' => $highId]);
    $row = $statement->fetch() ?: [];

    return [
        'status' => $row['status'] ?? null,
        'requested_by_user_id' => isset($row['requested_by_user_id']) ? (int) $row['requested_by_user_id'] : null,
        'blocked_by_user_id' => isset($row['blocked_by_user_id']) ? (int) $row['blocked_by_user_id'] : null,
    ];
}

function lockSocialUsers(PDO $connection, int $firstUserId, int $secondUserId): array
{
    [$lowId, $highId] = canonicalUserPair($firstUserId, $secondUserId);
    $statement = $connection->prepare(
        'SELECT id, status FROM users WHERE id IN (?, ?) ORDER BY id FOR UPDATE'
    );
    $statement->execute([$lowId, $highId]);
    $users = $statement->fetchAll();
    if (count($users) !== 2) {
        throw new SocialException('The selected user no longer exists.');
    }
    foreach ($users as $user) {
        if ($user['status'] !== 'active') {
            throw new SocialException('The selected account is not available.');
        }
    }

    return [$lowId, $highId];
}

function lockFriendship(PDO $connection, int $lowId, int $highId): ?array
{
    $statement = $connection->prepare(
        'SELECT id, requested_by_user_id, status FROM friendships '
        . 'WHERE user_low_id = :low_id AND user_high_id = :high_id FOR UPDATE'
    );
    $statement->execute(['low_id' => $lowId, 'high_id' => $highId]);
    $row = $statement->fetch();

    return is_array($row) ? $row : null;
}

function lockedPairBlock(PDO $connection, int $lowId, int $highId): ?array
{
    $statement = $connection->prepare(
        'SELECT id, blocked_by_user_id FROM user_blocks '
        . 'WHERE user_low_id = :low_id AND user_high_id = :high_id FOR UPDATE'
    );
    $statement->execute(['low_id' => $lowId, 'high_id' => $highId]);
    $row = $statement->fetch();

    return is_array($row) ? $row : null;
}

function acceptedFriendCount(PDO $connection, int $userId): int
{
    $statement = $connection->prepare(
        "SELECT COUNT(*) FROM friendships
         WHERE (user_low_id = :low_id OR user_high_id = :high_id) AND status = 'accepted'"
    );
    $statement->execute(['low_id' => $userId, 'high_id' => $userId]);

    return (int) $statement->fetchColumn();
}

function hasMutualFriend(PDO $connection, int $firstUserId, int $secondUserId): bool
{
    $firstFriends = loadAcceptedFriends($connection, $firstUserId);
    $firstIds = array_column($firstFriends, 'id');
    foreach (loadAcceptedFriends($connection, $secondUserId) as $friend) {
        if (in_array($friend['id'], $firstIds, false)) {
            return true;
        }
    }

    return false;
}

function sendFriendRequest(PDO $connection, int $requesterId, int $targetId): void
{
    canonicalUserPair($requesterId, $targetId);
    $connection->beginTransaction();
    try {
        [$lowId, $highId] = lockSocialUsers($connection, $requesterId, $targetId);
        if (lockedPairBlock($connection, $lowId, $highId) !== null) {
            throw new SocialException('A friend request is not available for this profile.');
        }
        $profile = $connection->prepare(
            'SELECT friend_request_setting FROM user_profiles WHERE user_id = :user_id FOR UPDATE'
        );
        $profile->execute(['user_id' => $targetId]);
        $setting = $profile->fetchColumn();
        if ($setting === 'nobody') {
            throw new SocialException('This user is not accepting friend requests.');
        }
        if ($setting === 'friends_of_friends' && !hasMutualFriend($connection, $requesterId, $targetId)) {
            throw new SocialException('This user accepts requests only from friends of friends.');
        }

        $friendship = lockFriendship($connection, $lowId, $highId);
        if ($friendship !== null && $friendship['status'] === 'accepted') {
            throw new SocialException('You are already friends.');
        }
        if ($friendship !== null && $friendship['status'] === 'pending') {
            throw new SocialException('A friend request is already pending between these accounts.');
        }

        if ($friendship === null) {
            $statement = $connection->prepare(
                "INSERT INTO friendships (user_low_id, user_high_id, requested_by_user_id, status)
                 VALUES (:low_id, :high_id, :requester_id, 'pending')"
            );
        } else {
            $statement = $connection->prepare(
                "UPDATE friendships SET requested_by_user_id = :requester_id, status = 'pending',
                 created_at = CURRENT_TIMESTAMP, responded_at = NULL
                 WHERE user_low_id = :low_id AND user_high_id = :high_id"
            );
        }
        $statement->execute(['low_id' => $lowId, 'high_id' => $highId, 'requester_id' => $requesterId]);
        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    }
}

function respondToFriendRequest(PDO $connection, int $actorId, int $otherUserId, bool $accept): void
{
    $connection->beginTransaction();
    try {
        [$lowId, $highId] = lockSocialUsers($connection, $actorId, $otherUserId);
        if (lockedPairBlock($connection, $lowId, $highId) !== null) {
            throw new SocialException('This request is no longer available.');
        }
        $friendship = lockFriendship($connection, $lowId, $highId);
        if ($friendship === null || $friendship['status'] !== 'pending'
            || (int) $friendship['requested_by_user_id'] === $actorId) {
            throw new SocialException('This incoming friend request is no longer available.');
        }
        if ($accept && (acceptedFriendCount($connection, $actorId) >= MAX_ACCEPTED_FRIENDS
            || acceptedFriendCount($connection, $otherUserId) >= MAX_ACCEPTED_FRIENDS)) {
            throw new SocialException('The 100-friend limit prevents accepting this request.');
        }

        $statement = $connection->prepare(
            'UPDATE friendships SET status = :status, responded_at = UTC_TIMESTAMP() WHERE id = :id'
        );
        $statement->execute(['status' => $accept ? 'accepted' : 'declined', 'id' => $friendship['id']]);
        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    }
}

function cancelFriendRequest(PDO $connection, int $actorId, int $otherUserId): void
{
    changeFriendshipState($connection, $actorId, $otherUserId, 'pending', 'cancelled', true);
}

function removeFriend(PDO $connection, int $actorId, int $otherUserId): void
{
    changeFriendshipState($connection, $actorId, $otherUserId, 'accepted', 'removed', false);
}

function changeFriendshipState(
    PDO $connection,
    int $actorId,
    int $otherUserId,
    string $requiredStatus,
    string $newStatus,
    bool $requesterOnly
): void {
    $connection->beginTransaction();
    try {
        [$lowId, $highId] = lockSocialUsers($connection, $actorId, $otherUserId);
        $friendship = lockFriendship($connection, $lowId, $highId);
        if ($friendship === null || $friendship['status'] !== $requiredStatus
            || ($requesterOnly && (int) $friendship['requested_by_user_id'] !== $actorId)) {
            throw new SocialException('This friendship action is no longer available.');
        }
        $statement = $connection->prepare(
            'UPDATE friendships SET status = :status, responded_at = UTC_TIMESTAMP() WHERE id = :id'
        );
        $statement->execute(['status' => $newStatus, 'id' => $friendship['id']]);
        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    }
}

function blockUser(PDO $connection, int $actorId, int $otherUserId): void
{
    $connection->beginTransaction();
    try {
        [$lowId, $highId] = lockSocialUsers($connection, $actorId, $otherUserId);
        if (lockedPairBlock($connection, $lowId, $highId) !== null) {
            throw new SocialException('These accounts are already blocked from interacting.');
        }
        $statement = $connection->prepare(
            'INSERT INTO user_blocks (user_low_id, user_high_id, blocked_by_user_id) '
            . 'VALUES (:low_id, :high_id, :actor_id)'
        );
        $statement->execute(['low_id' => $lowId, 'high_id' => $highId, 'actor_id' => $actorId]);
        $friendship = lockFriendship($connection, $lowId, $highId);
        if ($friendship !== null) {
            $remove = $connection->prepare(
                "UPDATE friendships SET status = 'removed', responded_at = UTC_TIMESTAMP() WHERE id = :id"
            );
            $remove->execute(['id' => $friendship['id']]);
        }
        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    }
}

function unblockUser(PDO $connection, int $actorId, int $otherUserId): void
{
    $connection->beginTransaction();
    try {
        [$lowId, $highId] = lockSocialUsers($connection, $actorId, $otherUserId);
        $block = lockedPairBlock($connection, $lowId, $highId);
        if ($block === null || (int) $block['blocked_by_user_id'] !== $actorId) {
            throw new SocialException('You do not have an active block to remove.');
        }
        $statement = $connection->prepare('DELETE FROM user_blocks WHERE id = :id');
        $statement->execute(['id' => $block['id']]);
        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    }
}

function loadSocialDashboard(PDO $connection, int $userId): array
{
    $accepted = loadAcceptedFriends($connection, $userId, MAX_ACCEPTED_FRIENDS);
    $statement = $connection->prepare(
        "SELECT f.status, f.requested_by_user_id, u.id, u.skadi_id, u.display_name, up.avatar_path
         FROM friendships f
         INNER JOIN users u ON u.id = CASE WHEN f.user_low_id = :select_id THEN f.user_high_id ELSE f.user_low_id END
         INNER JOIN user_profiles up ON up.user_id = u.id
         WHERE (f.user_low_id = :low_id OR f.user_high_id = :high_id) AND f.status = 'pending'
         ORDER BY f.created_at DESC"
    );
    $statement->execute(['select_id' => $userId, 'low_id' => $userId, 'high_id' => $userId]);
    $incoming = [];
    $outgoing = [];
    foreach ($statement->fetchAll() as $row) {
        if ((int) $row['requested_by_user_id'] === $userId) {
            $outgoing[] = $row;
        } else {
            $incoming[] = $row;
        }
    }

    $blocks = $connection->prepare(
        'SELECT u.id, u.skadi_id, u.display_name, up.avatar_path '
        . 'FROM user_blocks ub '
        . 'INNER JOIN users u ON u.id = CASE WHEN ub.user_low_id = :select_id '
        . 'THEN ub.user_high_id ELSE ub.user_low_id END '
        . 'INNER JOIN user_profiles up ON up.user_id = u.id '
        . 'WHERE ub.blocked_by_user_id = :blocker_id ORDER BY ub.created_at DESC'
    );
    $blocks->execute(['select_id' => $userId, 'blocker_id' => $userId]);

    return [
        'accepted' => $accepted,
        'incoming' => $incoming,
        'outgoing' => $outgoing,
        'blocked' => $blocks->fetchAll(),
    ];
}
