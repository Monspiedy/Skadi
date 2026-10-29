<?php

declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';

requireAuth();
$userId = (int) currentUser()['id'];
$cart = loadCart(db(), $userId);
$balance = loadWalletBalance(db(), $userId);
$canAfford = moneyToCents($balance) >= moneyToCents($cart['total']);

$pageTitle = 'Cart';
require SKADI_ROOT . '/includes/header.php';
?>
<section class="commerce-page" aria-labelledby="cart-heading">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Persistent cart</p>
            <h1 id="cart-heading">Your cart</h1>
        </div>
        <a class="btn btn-outline-secondary" href="<?= escape(baseUrl('store.php')) ?>">Continue shopping</a>
    </div>

    <?php if ($cart['items'] === []): ?>
        <?php renderStatePanel('empty', 'Your cart is empty', 'Add a published game from the store to begin a demo purchase.'); ?>
    <?php else: ?>
        <div class="cart-layout">
            <div class="cart-list">
                <?php foreach ($cart['items'] as $item): ?>
                    <article class="cart-item">
                        <div class="game-reference">
                            <?php renderGameThumbnail($item, 'medium'); ?>
                            <div class="game-reference-body">
                                <h2><a href="<?= escape(baseUrl('game.php?id=' . (int) $item['game_id'])) ?>"><?= escape($item['title']) ?></a></h2>
                                <?php if ($item['discount_percent'] > 0): ?>
                                    <p><span class="badge text-bg-info"><?= escape($item['discount_percent']) ?>% off</span>
                                        <span class="text-decoration-line-through"><?= escape(commercePriceLabel($item['base_price'])) ?></span></p>
                                <?php endif; ?>
                                <?php if ($item['price_changed']): ?>
                                    <p class="price-change-notice" role="status">
                                        Price changed from <?= escape(commercePriceLabel($item['price_when_added'])) ?>
                                        to <?= escape(commercePriceLabel($item['current_price'])) ?> since it was added.
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="cart-item-actions">
                            <strong><?= escape(commercePriceLabel($item['current_price'])) ?></strong>
                            <form method="post" action="<?= escape(baseUrl('actions/cart-remove.php')) ?>">
                                <?= csrfInput() ?>
                                <input type="hidden" name="game_id" value="<?= escape((int) $item['game_id']) ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit">Remove</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <aside class="checkout-summary" aria-label="Checkout summary">
                <h2>Order summary</h2>
                <dl>
                    <div><dt>Current total</dt><dd><?= escape(commercePriceLabel($cart['total'])) ?></dd></div>
                    <div><dt>Wallet balance</dt><dd><?= escape(commercePriceLabel($balance)) ?></dd></div>
                </dl>
                <p>Prices and ownership are calculated again inside one database transaction.</p>
                <?php if (!$canAfford): ?>
                    <p class="price-change-notice">Your wallet balance is too low for this cart.</p>
                <?php endif; ?>
                <form method="post" action="<?= escape(baseUrl('actions/checkout.php')) ?>">
                    <?= csrfInput() ?>
                    <button class="btn btn-primary w-100" type="submit" <?= !$canAfford ? 'disabled' : '' ?>>Complete demo purchase</button>
                </form>
            </aside>
        </div>
    <?php endif; ?>
</section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
