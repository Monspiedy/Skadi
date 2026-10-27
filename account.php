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
        <a href="<?= escape(baseUrl('change-password.php')) ?>">Change password</a>
    </div>
</section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
