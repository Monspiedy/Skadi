<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    renderErrorPage(405, 'Method not allowed', 'Social changes must be submitted from a Skadi form.');
}
requireValidCsrfToken($_POST['csrf_token'] ?? null);

$rawTargetId = $_POST['target_user_id'] ?? '';
if (!is_string($rawTargetId) || !ctype_digit($rawTargetId) || (int) $rawTargetId < 1) {
    renderErrorPage(404, 'Profile not found', 'The submitted profile identifier is invalid.');
}
$targetId = (int) $rawTargetId;
$actorId = (int) currentUser()['id'];
$action = is_string($_POST['social_action'] ?? null) ? $_POST['social_action'] : '';
$returnTo = ($_POST['return_to'] ?? '') === 'friends' ? 'friends' : 'profile';

try {
    match ($action) {
        'send' => sendFriendRequest(db(), $actorId, $targetId),
        'accept' => respondToFriendRequest(db(), $actorId, $targetId, true),
        'decline' => respondToFriendRequest(db(), $actorId, $targetId, false),
        'cancel' => cancelFriendRequest(db(), $actorId, $targetId),
        'remove' => removeFriend(db(), $actorId, $targetId),
        'block' => blockUser(db(), $actorId, $targetId),
        'unblock' => unblockUser(db(), $actorId, $targetId),
        default => throw new SocialException('Choose a valid social action.'),
    };
    $messages = [
        'send' => 'Friend request sent.', 'accept' => 'Friend request accepted.',
        'decline' => 'Friend request declined.', 'cancel' => 'Friend request cancelled.',
        'remove' => 'Friend removed.', 'block' => 'User blocked and any friendship removed.',
        'unblock' => 'User unblocked.',
    ];
    addFlashMessage('success', $messages[$action]);
} catch (SocialException $exception) {
    addFlashMessage('warning', $exception->getMessage());
}

if ($returnTo === 'friends') {
    redirectTo('friends.php');
}
$target = $targetId === $actorId ? null : loadOwnProfile(db(), $targetId);
redirectTo($target === null ? 'people.php' : 'profile.php?id=' . rawurlencode((string) $target['skadi_id']));
