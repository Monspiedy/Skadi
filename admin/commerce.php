<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';
requireAdmin();

$orders = loadAdminOrders(db(), adminPositivePage($_GET['order_page'] ?? 1));
$wallet = loadAdminWalletHistory(db(), adminPositivePage($_GET['wallet_page'] ?? 1));
$pageTitle = 'Commerce administration';
require SKADI_ROOT . '/includes/header.php';
?>
<section class="admin-shell" aria-labelledby="commerce-heading">
    <p class="eyebrow">Administration</p>
    <h1 id="commerce-heading">Commerce history</h1>
    <p>These order and wallet ledgers are read-only. Historical prices and balances are never recalculated here.</p>
    <?php renderAdminNav('commerce'); ?>

    <section class="admin-panel" aria-labelledby="orders-heading">
        <h2 id="orders-heading">Orders</h2>
        <div class="table-responsive"><table class="admin-table">
            <thead><tr><th>Order</th><th>User</th><th>Status</th><th>Items</th><th>Total</th><th>Created</th></tr></thead>
            <tbody><?php foreach ($orders['items'] as $order): ?><tr>
                <th><?= escape($order['order_number']) ?></th>
                <td><?= escape($order['display_name']) ?><small><?= escape($order['skadi_id']) ?></small></td>
                <td><?= escape(ucfirst($order['status'])) ?></td><td><?= (int) $order['item_count'] ?></td>
                <td><?= escape(gamePriceLabel($order['total'])) ?></td><td><?= escape($order['created_at']) ?></td>
            </tr><?php endforeach; ?></tbody>
        </table></div>
        <?php if ($orders['pages'] > 1): ?><p>Order page <?= $orders['page'] ?> of <?= $orders['pages'] ?>.
            <?php if ($orders['page'] > 1): ?><a href="?order_page=<?= $orders['page'] - 1 ?>&amp;wallet_page=<?= $wallet['page'] ?>">Previous</a><?php endif; ?>
            <?php if ($orders['page'] < $orders['pages']): ?><a href="?order_page=<?= $orders['page'] + 1 ?>&amp;wallet_page=<?= $wallet['page'] ?>">Next</a><?php endif; ?></p><?php endif; ?>
    </section>

    <section class="admin-panel" aria-labelledby="wallet-heading">
        <h2 id="wallet-heading">Wallet ledger</h2>
        <div class="table-responsive"><table class="admin-table">
            <thead><tr><th>User</th><th>Type</th><th>Amount</th><th>Balance after</th><th>Actor/order</th><th>Reason</th><th>Created</th></tr></thead>
            <tbody><?php foreach ($wallet['items'] as $entry): ?><tr>
                <th><?= escape($entry['display_name']) ?><small><?= escape($entry['skadi_id']) ?></small></th>
                <td><?= escape(str_replace('_', ' ', ucfirst($entry['transaction_type']))) ?></td>
                <td><?= escape($entry['amount']) ?> €</td><td><?= escape($entry['balance_after']) ?> €</td>
                <td><?= escape($entry['actor_name'] ?? $entry['order_number'] ?? 'System') ?></td>
                <td><?= escape($entry['reason']) ?></td><td><?= escape($entry['created_at']) ?></td>
            </tr><?php endforeach; ?></tbody>
        </table></div>
        <?php if ($wallet['pages'] > 1): ?><p>Wallet page <?= $wallet['page'] ?> of <?= $wallet['pages'] ?>.
            <?php if ($wallet['page'] > 1): ?><a href="?order_page=<?= $orders['page'] ?>&amp;wallet_page=<?= $wallet['page'] - 1 ?>">Previous</a><?php endif; ?>
            <?php if ($wallet['page'] < $wallet['pages']): ?><a href="?order_page=<?= $orders['page'] ?>&amp;wallet_page=<?= $wallet['page'] + 1 ?>">Next</a><?php endif; ?></p><?php endif; ?>
    </section>
</section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
