<?php

declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';

requireAuth();
$orders = loadOrderHistory(db(), (int) currentUser()['id']);

$pageTitle = 'Order history';
require SKADI_ROOT . '/includes/header.php';
?>
<section class="commerce-page" aria-labelledby="orders-heading">
    <div class="section-heading">
        <div><p class="eyebrow">Historical price snapshots</p><h1 id="orders-heading">Order history</h1></div>
        <a class="btn btn-outline-secondary" href="<?= escape(baseUrl('wallet.php')) ?>">Wallet history</a>
    </div>
    <?php if ($orders === []): ?>
        <?php renderStatePanel('empty', 'No orders yet', 'Completed demo purchases will keep their paid prices here.'); ?>
    <?php else: ?>
        <div class="order-history">
            <?php foreach ($orders as $order): ?>
                <article class="order-card">
                    <header>
                        <div><p class="eyebrow">Completed order</p><h2><?= escape($order['order_number']) ?></h2></div>
                        <div><time datetime="<?= escape($order['created_at']) ?>"><?= escape($order['created_at']) ?> UTC</time><strong><?= escape(commercePriceLabel($order['total'])) ?></strong></div>
                    </header>
                    <ul>
                        <?php foreach ($order['items'] as $item): ?>
                            <li>
                                <a href="<?= escape(baseUrl('game.php?id=' . $item['game_id'])) ?>"><?= escape($item['game_title']) ?></a>
                                <span>
                                    <?= $item['discount_percent'] > 0 ? escape($item['discount_percent'] . '% off · ') : '' ?>
                                    paid <?= escape(commercePriceLabel($item['paid_price'])) ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if (moneyToCents($order['discount_total']) > 0): ?>
                        <p>Base subtotal <?= escape(commercePriceLabel($order['subtotal'])) ?> · saved <?= escape(commercePriceLabel($order['discount_total'])) ?></p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
