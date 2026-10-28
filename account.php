<?php

declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';

requireAuth();

$user = currentUser();
$pageTitle = 'Account';
require SKADI_ROOT . '/includes/header.php';
?>
<section aria-labelledby="account-heading">
    <p class="eyebrow">Protected page</p>
    <h1 id="account-heading">Welcome, <?= escape($user['display_name']) ?></h1>
    <div class="card account-summary">
        <h2>Account session</h2>
        <dl>
            <dt>Skadi ID</dt>
            <dd><?= escape($user['skadi_id']) ?></dd>
            <dt>Role</dt>
            <dd><?= escape(ucfirst($user['role'])) ?></dd>
        </dl>
        <p>Your login name remains private and is not displayed here.</p>
        <div class="admin-actions">
            <a href="<?= escape(baseUrl('profile.php?id=' . rawurlencode($user['skadi_id']))) ?>">Public profile</a>
            <a href="<?= escape(baseUrl('profile-settings.php')) ?>">Profile settings</a>
            <a href="<?= escape(baseUrl('friends.php')) ?>">Friends and requests</a>
            <a href="<?= escape(baseUrl('change-password.php')) ?>">Change password</a>
            <a href="<?= escape(baseUrl('wallet.php')) ?>">Wallet history</a>
            <a href="<?= escape(baseUrl('orders.php')) ?>">Order history</a>
            <a href="<?= escape(baseUrl('library.php')) ?>">Game library</a>
            <a href="<?= escape(baseUrl('wishlist.php')) ?>">Wishlist</a>
        </div>
    </div>
</section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
