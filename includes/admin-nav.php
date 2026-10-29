<?php

declare(strict_types=1);

function renderAdminNav(string $current): void
{
    $links = [
        'dashboard' => ['admin/', 'Dashboard'],
        'games' => ['admin/games.php', 'Games'],
        'genres' => ['admin/genres.php', 'Genres'],
        'featured' => ['admin/featured.php', 'Featured'],
        'users' => ['admin/users.php', 'Users'],
        'commerce' => ['admin/commerce.php', 'Commerce'],
        'moderation' => ['admin/moderation.php', 'Moderation'],
        'reports' => ['admin/reports.php', 'Reports'],
        'sale' => ['admin/weekend-sale.php', 'Weekend sale'],
    ];
    ?>
    <nav class="admin-subnav" aria-label="Administration sections">
        <?php foreach ($links as $key => [$path, $label]): ?>
            <a <?= $key === $current ? 'aria-current="page"' : '' ?> href="<?= escape(baseUrl($path)) ?>"><?= escape($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <?php
}

function renderAdminPagination(string $path, array $data, array $query = []): void
{
    if ((int) $data['pages'] <= 1) {
        return;
    }
    $page = (int) $data['page'];
    $pages = (int) $data['pages'];
    ?>
    <nav class="pagination-nav" aria-label="Pagination">
        <?php if ($page > 1): ?>
            <a class="btn btn-sm btn-outline-secondary" href="<?= escape(baseUrl($path . '?' . http_build_query($query + ['page' => $page - 1]))) ?>">Previous</a>
        <?php endif; ?>
        <span>Page <?= $page ?> of <?= $pages ?></span>
        <?php if ($page < $pages): ?>
            <a class="btn btn-sm btn-outline-secondary" href="<?= escape(baseUrl($path . '?' . http_build_query($query + ['page' => $page + 1]))) ?>">Next</a>
        <?php endif; ?>
    </nav>
    <?php
}
