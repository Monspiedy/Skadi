<?php

declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';

$rawSkadiId = is_string($_GET['id'] ?? null) ? trim($_GET['id']) : '';
if ($rawSkadiId === '' || strlen($rawSkadiId) > 30) {
    renderErrorPage(404, 'Profile not found', 'The profile identifier is invalid.');
}
$profile = loadPublicProfileBySkadiId(db(), $rawSkadiId);
if ($profile === null) {
    renderErrorPage(404, 'Profile not found', 'No active profile matches that Skadi ID.');
}
$viewer = isAuthenticated() && empty(currentUser()['must_change_password']) ? currentUser() : null;
$viewerId = $viewer === null ? null : (int) $viewer['id'];
$privacy = profilePrivacyAccess(db(), $profile, $viewerId);
$relationship = null;
if ($viewerId !== null && $viewerId !== (int) $profile['id']) {
    $relationship = relationshipForUsers(db(), $viewerId, (int) $profile['id']);
}
$library = $privacy['can_view_details'] ? loadProfileLibrary(db(), (int) $profile['id']) : [];
$reviews = $privacy['can_view_details'] ? loadProfileReviews(db(), (int) $profile['id']) : [];
$friends = $privacy['can_view_details'] ? loadAcceptedFriends(db(), (int) $profile['id']) : [];
$activity = $privacy['can_view_details'] ? buildProfileActivity($library, $reviews) : [];
$presence = $privacy['is_blocked'] ? null : publicPresenceLabel($profile, $privacy['is_owner']);
$pageTitle = (string) $profile['display_name'];
require SKADI_ROOT . '/includes/header.php';
?>
<section class="social-page public-profile" aria-labelledby="profile-heading">
    <header class="profile-hero">
        <?php if (is_string($profile['avatar_path']) && isManagedAvatarUploadPath($profile['avatar_path'])): ?>
            <img class="profile-avatar profile-avatar-large" src="<?= escape(baseUrl($profile['avatar_path'])) ?>" alt="<?= escape($profile['display_name']) ?> avatar">
        <?php else: ?>
            <span class="profile-avatar profile-avatar-large profile-avatar-placeholder" aria-hidden="true"><?= escape(mb_strtoupper(mb_substr($profile['display_name'], 0, 1))) ?></span>
        <?php endif; ?>
        <div>
            <p class="eyebrow">Skadi profile</p>
            <h1 id="profile-heading"><?= escape($profile['display_name']) ?></h1>
            <p class="subtle-skadi-id"><?= escape($profile['skadi_id']) ?></p>
            <?php if ($presence !== null): ?><span class="presence presence-<?= escape(strtolower(str_replace(' ', '-', $presence))) ?>"><?= escape($presence) ?></span><?php endif; ?>
        </div>
        <?php if ($privacy['is_owner']): ?>
            <a class="btn btn-primary" href="<?= escape(baseUrl('profile-settings.php')) ?>">Edit profile</a>
        <?php elseif ($viewerId !== null && $relationship !== null): ?>
            <form class="social-actions" method="post" action="<?= escape(baseUrl('actions/social-action.php')) ?>">
                <?= csrfInput() ?>
                <input type="hidden" name="target_user_id" value="<?= (int) $profile['id'] ?>">
                <input type="hidden" name="return_to" value="profile">
                <?php if ($relationship['blocked_by_user_id'] === $viewerId): ?>
                    <button class="btn btn-outline-secondary" name="social_action" value="unblock" type="submit">Unblock</button>
                <?php elseif ($relationship['blocked_by_user_id'] === null): ?>
                    <?php if ($relationship['status'] === 'pending' && $relationship['requested_by_user_id'] === $viewerId): ?>
                        <button class="btn btn-outline-secondary" name="social_action" value="cancel" type="submit">Cancel request</button>
                    <?php elseif ($relationship['status'] === 'pending'): ?>
                        <button class="btn btn-primary" name="social_action" value="accept" type="submit">Accept request</button>
                        <button class="btn btn-outline-secondary" name="social_action" value="decline" type="submit">Decline</button>
                    <?php elseif ($relationship['status'] === 'accepted'): ?>
                        <button class="btn btn-outline-secondary" name="social_action" value="remove" type="submit">Remove friend</button>
                    <?php else: ?>
                        <button class="btn btn-primary" name="social_action" value="send" type="submit">Add friend</button>
                    <?php endif; ?>
                    <button class="btn btn-outline-danger" name="social_action" value="block" type="submit">Block</button>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </header>

    <?php if ($privacy['can_view_details']): ?>
        <?php if (!empty($profile['biography'])): ?><section class="profile-panel"><h2>About</h2><p class="profile-biography"><?= nl2br(escape($profile['biography'])) ?></p></section><?php endif; ?>
        <div class="profile-section-grid">
            <section class="profile-panel"><h2>Library</h2>
                <?php if ($library === []): ?><p>No visible games.</p><?php else: ?><ul class="profile-list">
                    <?php foreach ($library as $game): ?><li><div class="game-reference"><?php renderGameThumbnail($game); ?><a class="game-reference-body" href="<?= escape(baseUrl('game.php?id=' . $game['id'])) ?>"><?= escape($game['title']) ?></a></div></li><?php endforeach; ?>
                </ul><?php endif; ?>
            </section>
            <section class="profile-panel"><h2>Reviews</h2>
                <?php if ($reviews === []): ?><p>No active reviews.</p><?php else: ?><ul class="profile-list">
                    <?php foreach ($reviews as $review): ?><li><div class="game-reference"><?php renderGameThumbnail($review); ?><span class="game-reference-body"><a href="<?= escape(baseUrl('game.php?id=' . $review['game_id'] . '#reviews')) ?>"><?= escape($review['title']) ?></a> <strong><?= (int) $review['rating'] ?>/10</strong></span></div></li><?php endforeach; ?>
                </ul><?php endif; ?>
            </section>
            <section class="profile-panel"><h2>Recent activity</h2>
                <?php if ($activity === []): ?><p>No visible activity.</p><?php else: ?><ul class="profile-list">
                    <?php foreach ($activity as $item): ?><li><div class="game-reference"><?php renderGameThumbnail($item); ?><span class="game-reference-body"><?= $item['type'] === 'review' ? 'Reviewed' : 'Added' ?> <a href="<?= escape(baseUrl('game.php?id=' . $item['game_id'])) ?>"><?= escape($item['game_title']) ?></a></span></div></li><?php endforeach; ?>
                </ul><?php endif; ?>
            </section>
            <section class="profile-panel"><h2>Friends</h2>
                <?php if ($friends === []): ?><p>No visible friends.</p><?php else: ?><ul class="profile-list">
                    <?php foreach ($friends as $friend): ?><li><a href="<?= escape(baseUrl('profile.php?id=' . rawurlencode($friend['skadi_id']))) ?>"><?= escape($friend['display_name']) ?></a> <small><?= escape($friend['skadi_id']) ?></small></li><?php endforeach; ?>
                </ul><?php endif; ?>
            </section>
        </div>
    <?php else: ?>
        <div class="profile-panel privacy-notice">
            <h2>Profile details are private</h2>
            <p><?= $privacy['is_blocked'] ? 'Blocking prevents access to profile details and social contact.' : 'This user shares their library, reviews, activity and friends only with the audience they selected.' ?></p>
        </div>
    <?php endif; ?>
</section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
