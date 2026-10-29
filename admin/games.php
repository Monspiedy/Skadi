<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

requireAdmin();

$games = loadAdminGames(db());
$pageTitle = 'Manage games';
$pageScripts = ['assets/js/admin-media.js'];
require SKADI_ROOT . '/includes/header.php';
?>
<section class="admin-shell" aria-labelledby="games-heading">
    <div class="admin-heading-row">
        <div>
            <p class="eyebrow">Catalog administration</p>
            <h1 id="games-heading">Games</h1>
        </div>
        <a class="btn btn-primary" href="<?= escape(baseUrl('admin/game-form.php')) ?>">Add game</a>
    </div>
    <?php renderAdminNav('games'); ?>

    <?php if ($games === []): ?>
        <?php renderStatePanel('empty', 'No games yet', 'Create the first catalog game to begin.'); ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th scope="col">Game</th>
                        <th scope="col">Status</th>
                        <th scope="col">Price</th>
                        <th scope="col">Media</th>
                        <th scope="col">Featured</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($games as $game): ?>
                        <tr>
                            <th scope="row">
                                <?= escape($game['title']) ?>
                                <small><?= escape($game['slug']) ?> · <?= (int) $game['genre_count'] ?> genres</small>
                            </th>
                            <td><span class="status-badge"><?= escape(ucfirst($game['status'])) ?></span></td>
                            <td><?= escape(gamePriceLabel($game['base_price'])) ?></td>
                            <td><?= $game['cover_path'] ? 'Cover' : 'No cover' ?> · <?= (int) $game['screenshot_count'] ?> shots</td>
                            <td><?= $game['featured_position'] === null ? '—' : '#' . (int) $game['featured_position'] ?></td>
                            <td class="admin-actions">
                                <a class="btn btn-sm btn-outline-secondary"
                                    href="<?= escape(baseUrl('admin/game-form.php?id=' . (int) $game['id'])) ?>">Edit</a>
                                <form method="post" action="<?= escape(baseUrl('actions/admin/game-delete.php')) ?>"
                                    data-confirm-form="Delete this game? Unreferenced media, genre links and featured placement will also be removed.">
                                    <?= csrfInput() ?>
                                    <input type="hidden" name="game_id" value="<?= (int) $game['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="form-note">Deletion is refused when sales, carts, orders, libraries, wishlists, reviews or community records reference a game.</p>
    <?php endif; ?>
</section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
