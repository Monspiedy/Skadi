<?php

declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';

$query = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$queryError = null;
if (mb_strlen($query) > 80) {
    $queryError = 'Search must be at most 80 characters.';
    $results = [];
} else {
    $results = $query === '' ? [] : searchPublicProfiles(db(), $query);
}
$viewerId = isAuthenticated() && empty(currentUser()['must_change_password']) ? (int) currentUser()['id'] : null;
$pageTitle = 'Find people';
require SKADI_ROOT . '/includes/header.php';
?>
<section class="social-page" aria-labelledby="people-heading">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Skadi community</p>
            <h1 id="people-heading">Find people</h1>
        </div>
        <?php if ($viewerId !== null): ?>
            <a class="btn btn-outline-secondary" href="<?= escape(baseUrl('friends.php')) ?>">Friends and requests</a>
        <?php endif; ?>
    </div>
    <form class="people-search" method="get" action="<?= escape(baseUrl('people.php')) ?>">
        <label for="people-query">Partial display name or exact Skadi ID</label>
        <div>
            <input id="people-query" name="q" type="search" maxlength="80" value="<?= escape($query) ?>"
                placeholder="e.g. Demo or SKD-DEMO-0001">
            <button class="btn btn-primary" type="submit">Search</button>
        </div>
        <?php if ($queryError !== null): ?><span class="field-error"><?= escape($queryError) ?></span><?php endif; ?>
    </form>

    <?php if ($query !== '' && $queryError === null): ?>
        <h2><?= count($results) ?> result<?= count($results) === 1 ? '' : 's' ?></h2>
        <?php if ($results === []): ?>
            <?php renderEmptyState('No people found', 'Try another display-name fragment or a complete Skadi ID.'); ?>
        <?php else: ?>
            <div class="people-grid">
                <?php foreach ($results as $person): ?>
                    <?php
                    $blocked = $viewerId !== null && $viewerId !== (int) $person['id']
                        ? isUserPairBlocked(db(), $viewerId, (int) $person['id']) : false;
                    ?>
                    <article class="person-card">
                        <?php if (is_string($person['avatar_path']) && isManagedAvatarUploadPath($person['avatar_path'])): ?>
                            <img class="profile-avatar" src="<?= escape(baseUrl($person['avatar_path'])) ?>" alt="">
                        <?php else: ?>
                            <span class="profile-avatar profile-avatar-placeholder" aria-hidden="true"><?= escape(mb_strtoupper(mb_substr($person['display_name'], 0, 1))) ?></span>
                        <?php endif; ?>
                        <div>
                            <h2><a href="<?= escape(baseUrl('profile.php?id=' . rawurlencode($person['skadi_id']))) ?>"><?= escape($person['display_name']) ?></a></h2>
                            <small><?= escape($person['skadi_id']) ?></small>
                            <?php if (!$blocked && ($presence = publicPresenceLabel($person)) !== null): ?>
                                <span class="presence presence-<?= escape(strtolower(str_replace(' ', '-', $presence))) ?>"><?= escape($presence) ?></span>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
