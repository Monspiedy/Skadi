<?php

declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';

requireAuth();
$userId = (int) currentUser()['id'];
$filters = parseLibraryFilters($_GET);
$items = loadLibraryItems(db(), $userId, $filters);
$pageTitle = 'Library';
$pageScripts = ['assets/js/library.js'];
require SKADI_ROOT . '/includes/header.php';
?>
<section class="library-page" aria-labelledby="library-heading">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Your games</p>
            <h1 id="library-heading">Library</h1>
        </div>
        <a class="btn btn-outline-secondary" href="<?= escape(baseUrl('wishlist.php')) ?>">View wishlist</a>
    </div>
    <form class="library-filters" method="get" action="<?= escape(baseUrl('library.php')) ?>">
        <div>
            <label for="library-search">Title</label>
            <input class="form-control" id="library-search" name="q" type="search" maxlength="80"
                value="<?= escape($filters['search']) ?>" placeholder="Search owned games">
        </div>
        <div>
            <label for="library-visibility">Visibility</label>
            <select class="form-select" id="library-visibility" name="visibility">
                <option value="visible" <?= $filters['visibility'] === 'visible' ? 'selected' : '' ?>>Visible</option>
                <option value="hidden" <?= $filters['visibility'] === 'hidden' ? 'selected' : '' ?>>Hidden</option>
                <option value="all" <?= $filters['visibility'] === 'all' ? 'selected' : '' ?>>All owned games</option>
            </select>
        </div>
        <div class="filter-actions">
            <button class="btn btn-primary" type="submit">Apply filters</button>
            <a class="btn btn-outline-secondary" href="<?= escape(baseUrl('library.php')) ?>">Reset</a>
        </div>
    </form>
    <?php foreach ($filters['warnings'] as $warning): ?>
        <p class="catalog-notice" role="status"><?= escape($warning) ?></p>
    <?php endforeach; ?>
    <?php if ($items === []): ?>
        <?php renderStatePanel('empty', 'No library games found', 'Try another search or visibility filter, or acquire a game from the store.'); ?>
    <?php else: ?>
        <div class="library-grid">
            <?php foreach ($items as $item): ?>
                <?php $coverUrl = safeGameMediaUrl($item['cover_path']); ?>
                <article class="library-card">
                    <?php if ($coverUrl !== null): ?>
                        <img class="library-cover" src="<?= escape($coverUrl) ?>"
                            alt="<?= escape($item['cover_alt'] ?: $item['title'] . ' cover') ?>">
                    <?php else: ?>
                        <div class="library-cover game-artwork-placeholder" role="img"
                            aria-label="Original placeholder cover for <?= escape($item['title']) ?>">
                            <span aria-hidden="true"><?= escape(mb_strtoupper(mb_substr($item['title'], 0, 2))) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="library-card-body">
                        <div>
                            <h2><a href="<?= escape(baseUrl('game.php?id=' . (int) $item['game_id'])) ?>"><?= escape($item['title']) ?></a></h2>
                            <p><?= escape($item['short_description']) ?></p>
                            <small>Acquired <?= escape(formatUtcDateTime($item['acquired_at'])) ?></small>
                            <?php if ($item['hidden_at'] !== null): ?>
                                <span class="badge text-bg-secondary">Hidden</span>
                            <?php endif; ?>
                        </div>
                        <div class="library-actions">
                            <form method="post" action="<?= escape(baseUrl('actions/library-visibility.php')) ?>">
                                <?= csrfInput() ?>
                                <input type="hidden" name="game_id" value="<?= escape((int) $item['game_id']) ?>">
                                <input type="hidden" name="visibility" value="<?= $item['hidden_at'] === null ? 'hidden' : 'visible' ?>">
                                <button class="btn btn-sm btn-outline-secondary" type="submit">
                                    <?= $item['hidden_at'] === null ? 'Hide' : 'Unhide' ?>
                                </button>
                            </form>
                            <details class="remove-access-panel">
                                <summary>Remove access permanently</summary>
                                <p>This removes access without a refund. Your order and wallet history remain unchanged.</p>
                                <form method="post" action="<?= escape(baseUrl('actions/library-remove.php')) ?>"
                                    data-confirm-form="Remove this game from your library without a refund?">
                                    <?= csrfInput() ?>
                                    <input type="hidden" name="game_id" value="<?= escape((int) $item['game_id']) ?>">
                                    <div class="form-check">
                                        <input class="form-check-input" id="confirm-remove-<?= escape((int) $item['game_id']) ?>"
                                            name="confirm_remove" type="checkbox" value="yes" required>
                                        <label class="form-check-label" for="confirm-remove-<?= escape((int) $item['game_id']) ?>">
                                            I understand there is no refund.
                                        </label>
                                    </div>
                                    <button class="btn btn-sm btn-danger" type="submit">Permanently remove access</button>
                                </form>
                            </details>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
