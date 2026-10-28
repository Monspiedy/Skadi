<?php

declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';

requireAuth();
$userId = (int) currentUser()['id'];
$dashboard = loadSocialDashboard(db(), $userId);
$pageTitle = 'Friends';
require SKADI_ROOT . '/includes/header.php';

function socialDashboardCard(array $person, string $section): void
{
    $profileUrl = baseUrl('profile.php?id=' . rawurlencode((string) $person['skadi_id']));
    ?>
    <article class="person-card">
        <?php if (is_string($person['avatar_path']) && isManagedAvatarUploadPath($person['avatar_path'])): ?>
            <img class="profile-avatar" src="<?= escape(baseUrl($person['avatar_path'])) ?>" alt="">
        <?php else: ?>
            <span class="profile-avatar profile-avatar-placeholder" aria-hidden="true"><?= escape(mb_strtoupper(mb_substr($person['display_name'], 0, 1))) ?></span>
        <?php endif; ?>
        <div class="person-card-body">
            <div><h3><a href="<?= escape($profileUrl) ?>"><?= escape($person['display_name']) ?></a></h3><small><?= escape($person['skadi_id']) ?></small></div>
            <form class="social-actions" method="post" action="<?= escape(baseUrl('actions/social-action.php')) ?>">
                <?= csrfInput() ?><input type="hidden" name="target_user_id" value="<?= (int) $person['id'] ?>">
                <input type="hidden" name="return_to" value="friends">
                <?php if ($section === 'incoming'): ?>
                    <button class="btn btn-sm btn-primary" name="social_action" value="accept" type="submit">Accept</button>
                    <button class="btn btn-sm btn-outline-secondary" name="social_action" value="decline" type="submit">Decline</button>
                <?php elseif ($section === 'outgoing'): ?>
                    <button class="btn btn-sm btn-outline-secondary" name="social_action" value="cancel" type="submit">Cancel request</button>
                <?php elseif ($section === 'accepted'): ?>
                    <a class="btn btn-sm btn-primary" href="<?= escape(baseUrl('chat.php?user_id=' . (int) $person['id'])) ?>">Chat</a>
                    <button class="btn btn-sm btn-outline-danger" name="social_action" value="remove" type="submit">Remove friend</button>
                <?php else: ?>
                    <button class="btn btn-sm btn-outline-secondary" name="social_action" value="unblock" type="submit">Unblock</button>
                <?php endif; ?>
            </form>
        </div>
    </article>
    <?php
}
?>
<section class="social-page" aria-labelledby="friends-heading">
    <div class="section-heading">
        <div><p class="eyebrow">Social</p><h1 id="friends-heading">Friends</h1></div>
        <a class="btn btn-primary" href="<?= escape(baseUrl('people.php')) ?>">Find people</a>
    </div>
    <p><?= count($dashboard['accepted']) ?> / <?= MAX_ACCEPTED_FRIENDS ?> accepted friends</p>

    <?php if ($dashboard['incoming'] !== []): ?>
        <section><h2>Incoming requests</h2><div class="people-grid">
            <?php foreach ($dashboard['incoming'] as $person) { socialDashboardCard($person, 'incoming'); } ?>
        </div></section>
    <?php endif; ?>
    <?php if ($dashboard['outgoing'] !== []): ?>
        <section><h2>Sent requests</h2><div class="people-grid">
            <?php foreach ($dashboard['outgoing'] as $person) { socialDashboardCard($person, 'outgoing'); } ?>
        </div></section>
    <?php endif; ?>
    <section><h2>Accepted friends</h2>
        <?php if ($dashboard['accepted'] === []): ?>
            <?php renderEmptyState('No friends yet', 'Search for someone by display name or exact Skadi ID.'); ?>
        <?php else: ?><div class="people-grid">
            <?php foreach ($dashboard['accepted'] as $person) { socialDashboardCard($person, 'accepted'); } ?>
        </div><?php endif; ?>
    </section>
    <?php if ($dashboard['blocked'] !== []): ?>
        <section><h2>Blocked users</h2><p>Only blocks you created are shown here.</p><div class="people-grid">
            <?php foreach ($dashboard['blocked'] as $person) { socialDashboardCard($person, 'blocked'); } ?>
        </div></section>
    <?php endif; ?>
</section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
