<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

requireAdmin();

$pageTitle = 'Administration';
require SKADI_ROOT . '/includes/header.php';
?>
<section class="admin-shell" aria-labelledby="admin-heading">
    <p class="eyebrow">Catalog administration</p>
    <h1 id="admin-heading">Game store management</h1>
    <p>Manage the Phase 06 catalog slice. User, wallet and moderation tools remain outside this phase.</p>
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
    </div>
</section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
