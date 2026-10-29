<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

requireAdmin();

$counts = loadAdminDashboardCounts(db());

$pageTitle = 'Administration';
require SKADI_ROOT . '/includes/header.php';
?>
<section class="admin-shell" aria-labelledby="admin-heading">
    <p class="eyebrow">Administration</p>
    <h1 id="admin-heading">Skadi control panel</h1>
    <p>Manage the store, accounts, commerce audit trails, public content and weekend sale.</p>
    <?php renderAdminNav('dashboard'); ?>
    <div class="admin-stat-grid" aria-label="Administration statistics">
        <div><strong><?= $counts['users'] ?></strong><span>Users (<?= $counts['active_users'] ?> active)</span></div>
        <div><strong><?= $counts['published_games'] ?></strong><span>Published games</span></div>
        <div><strong><?= $counts['completed_orders'] ?></strong><span>Completed orders</span></div>
        <div><strong><?= $counts['open_reports'] ?></strong><span>Open reports</span></div>
        <div><strong><?= $counts['hidden_content'] ?></strong><span>Hidden content items</span></div>
    </div>
    <div class="admin-card-grid">
        <a class="admin-card" href="<?= escape(baseUrl('admin/games.php')) ?>">
            <strong>Games and media</strong>
            <span>Create and edit catalog facts, genres, covers and screenshots.</span>
        </a>
        <a class="admin-card" href="<?= escape(baseUrl('admin/genres.php')) ?>">
            <strong>Genres</strong>
            <span>Create, edit and safely remove unused genres.</span>
        </a>
        <a class="admin-card" href="<?= escape(baseUrl('admin/featured.php')) ?>">
            <strong>Featured games</strong>
            <span>Choose and order up to five published games.</span>
        </a>
        <a class="admin-card" href="<?= escape(baseUrl('admin/weekend-sale.php')) ?>">
            <strong>Weekend sale</strong>
            <span>Inspect persisted generations and safely regenerate the active demonstration sale.</span>
        </a>
        <a class="admin-card" href="<?= escape(baseUrl('admin/users.php')) ?>">
            <strong>Users and wallets</strong>
            <span>Search safe account fields, control access and make audited balance adjustments.</span>
        </a>
        <a class="admin-card" href="<?= escape(baseUrl('admin/commerce.php')) ?>">
            <strong>Commerce history</strong>
            <span>Inspect read-only order and wallet histories.</span>
        </a>
        <a class="admin-card" href="<?= escape(baseUrl('admin/moderation.php')) ?>">
            <strong>Content moderation</strong>
            <span>Hide, restore or delete public content and control discussion topics.</span>
        </a>
        <a class="admin-card" href="<?= escape(baseUrl('admin/reports.php')) ?>">
            <strong>Reports</strong>
            <span>Review and resolve the public-content report queue with notes.</span>
        </a>
    </div>
</section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
