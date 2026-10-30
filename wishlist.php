<?php

declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';

requireAuth();
$wishlistSort = parseWishlistSort($_GET);
$items = sortWishlistItems(loadWishlist(db(), (int) currentUser()['id']), $wishlistSort['sort']);
$pageTitle = 'Wishlist';
require SKADI_ROOT . '/includes/header.php';
?>
<section class="library-page" aria-labelledby="wishlist-heading">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Saved for later</p>
            <h1 id="wishlist-heading">Wishlist</h1>
        </div>
        <a class="btn btn-outline-secondary" href="<?= escape(baseUrl('library.php')) ?>">Back to library</a>
    </div>
    <div class="wishlist-toolbar">
        <p><strong><?= count($items) ?></strong> <?= count($items) === 1 ? 'saved game' : 'saved games' ?></p>
        <form method="get">
            <label for="wishlist-sort">Sort by</label>
            <select class="form-select" id="wishlist-sort" name="sort">
                <option value="newest" <?= $wishlistSort['sort'] === 'newest' ? 'selected' : '' ?>>Newest added</option>
                <option value="discount" <?= $wishlistSort['sort'] === 'discount' ? 'selected' : '' ?>>Largest discount</option>
                <option value="price" <?= $wishlistSort['sort'] === 'price' ? 'selected' : '' ?>>Current price</option>
            </select>
            <button class="btn btn-outline-secondary" type="submit">Apply</button>
        </form>
    </div>
    <?php if ($wishlistSort['warning'] !== null): ?>
        <div class="catalog-notice" role="status"><p><?= escape($wishlistSort['warning']) ?></p></div>
    <?php endif; ?>
    <?php if ($items === []): ?>
        <?php renderStatePanel('empty', 'Your wishlist is empty', 'Save a published game from the store or a game page.'); ?>
    <?php else: ?>
        <div class="wishlist-list">
            <?php foreach ($items as $item): ?>
                <article class="wishlist-item">
                    <div class="game-reference">
                        <a href="<?= escape(baseUrl('game.php?id=' . (int) $item['game_id'])) ?>" aria-label="View <?= escape($item['title']) ?>">
                            <?php renderGameThumbnail($item, 'large'); ?>
                        </a>
                        <div class="game-reference-body">
                            <div class="wishlist-status-row">
                                <?php if ((int) $item['is_owned'] === 1): ?><span class="game-state-pill game-state-owned">Owned</span><?php endif; ?>
                                <?php if ((int) $item['is_in_cart'] === 1): ?><span class="game-state-pill">In cart</span><?php endif; ?>
                                <?php if ((int) $item['discount_percent'] > 0): ?><span class="discount-badge">Weekend Deal</span><?php endif; ?>
                            </div>
                            <h2><a href="<?= escape(baseUrl('game.php?id=' . (int) $item['game_id'])) ?>"><?= escape($item['title']) ?></a></h2>
                            <p><?= escape($item['short_description']) ?></p>
                            <small>Added <?= escape(formatUtcDateTime($item['created_at'])) ?></small>
                        </div>
                    </div>
                    <div class="wishlist-actions">
                        <div class="wishlist-price">
                            <?php if ((int) $item['discount_percent'] > 0): ?>
                                <span class="discount-badge"><?= (int) $item['discount_percent'] ?>% off</span>
                                <s><?= escape(commercePriceLabel($item['base_price'])) ?></s>
                            <?php endif; ?>
                            <strong><?= escape(commercePriceLabel($item['current_price'])) ?></strong>
                        </div>
                        <?php if ((int) $item['is_owned'] === 1): ?>
                            <a class="btn btn-sm btn-primary" href="<?= escape(baseUrl('library.php')) ?>">View in library</a>
                        <?php elseif ((int) $item['is_in_cart'] === 1): ?>
                            <a class="btn btn-sm btn-primary" href="<?= escape(baseUrl('cart.php')) ?>">View cart</a>
                        <?php else: ?>
                            <form method="post" action="<?= escape(baseUrl('actions/wishlist-move-to-cart.php')) ?>">
                                <?= csrfInput() ?>
                                <input type="hidden" name="game_id" value="<?= escape((int) $item['game_id']) ?>">
                                <button class="btn btn-sm btn-primary" type="submit">Move to cart</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" action="<?= escape(baseUrl('actions/wishlist-remove.php')) ?>">
                            <?= csrfInput() ?>
                            <input type="hidden" name="game_id" value="<?= escape((int) $item['game_id']) ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit">Remove</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
