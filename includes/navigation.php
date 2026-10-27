<?php

declare(strict_types=1);

$navigationUser = currentUser();
?>
<header class="site-header">
    <nav class="site-nav" aria-label="Main navigation">
        <a class="brand" href="<?= escape(baseUrl()) ?>">Skadi</a>
        <div class="nav-links">
            <a href="<?= escape(baseUrl()) ?>">Home</a>
            <a href="<?= escape(baseUrl('health.php')) ?>">Health check</a>
            <?php if ($navigationUser === null): ?>
                <a href="<?= escape(baseUrl('register.php')) ?>">Register</a>
                <a href="<?= escape(baseUrl('login.php')) ?>">Sign in</a>
            <?php else: ?>
                <?php if (empty($navigationUser['must_change_password'])): ?>
                    <a href="<?= escape(baseUrl('account.php')) ?>">Account</a>
                <?php else: ?>
                    <a href="<?= escape(baseUrl('change-password.php')) ?>">Change password</a>
                <?php endif; ?>
                <?php if ($navigationUser['role'] === 'admin' && empty($navigationUser['must_change_password'])): ?>
                    <a href="<?= escape(baseUrl('admin/')) ?>">Admin</a>
                <?php endif; ?>
                <form class="nav-form" method="post" action="<?= escape(baseUrl('actions/logout.php')) ?>">
                    <?= csrfInput() ?>
                    <button type="submit">Sign out</button>
                </form>
            <?php endif; ?>
        </div>
    </nav>
</header>
