<?php

declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';

requireAuth();
$items = loadWishlist(db(), (int) currentUser()['id']);
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
    <?php if ($items === []): ?>
        <?php renderStatePanel('empty', 'Your wishlist is empty', 'Save a published game from the store or a game page.'); ?>
    <?php else: ?>
        <div class="wishlist-list">
            <?php foreach ($items as $item): ?>
                <article class="wishlist-item">
                    <div class="game-reference">
                        <?php renderGameThumbnail($item, 'medium'); ?>
                        <div class="game-reference-body">
                            <h2><a href="<?= escape(baseUrl('game.php?id=' . (int) $item['game_id'])) ?>"><?= escape($item['title']) ?></a></h2>
                            <p><?= escape($item['short_description']) ?></p>
                            <?php if ((int) $item['discount_percent'] > 0): ?>
                                <p><span class="badge text-bg-info"><?= escape((int) $item['discount_percent']) ?>% off</span>
                                    <span class="text-decoration-line-through"><?= escape(commercePriceLabel($item['base_price'])) ?></span></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="wishlist-actions">
                        <strong><?= escape(commercePriceLabel($item['current_price'])) ?></strong>
                        <form method="post" action="<?= escape(baseUrl('actions/wishlist-move-to-cart.php')) ?>">
                            <?= csrfInput() ?>
                            <input type="hidden" name="game_id" value="<?= escape((int) $item['game_id']) ?>">
                            <button class="btn btn-sm btn-primary" type="submit">Move to cart</button>
                        </form>
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
