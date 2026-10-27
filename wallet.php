<?php

declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';

requireAuth();
$userId = (int) currentUser()['id'];
$balance = loadWalletBalance(db(), $userId);
$transactions = loadWalletTransactions(db(), $userId);

$pageTitle = 'Wallet history';
require SKADI_ROOT . '/includes/header.php';
?>
<section class="commerce-page" aria-labelledby="wallet-heading">
    <div class="section-heading">
        <div><p class="eyebrow">Demo currency</p><h1 id="wallet-heading">Wallet history</h1></div>
        <strong class="wallet-balance"><?= escape(commercePriceLabel($balance)) ?></strong>
    </div>
    <p>Skadi uses demonstration money only. Users cannot add funds.</p>
    <?php if ($transactions === []): ?>
        <?php renderStatePanel('empty', 'No wallet entries', 'Wallet credits and paid purchases will appear here.'); ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle commerce-table">
                <thead><tr><th>Date</th><th>Type</th><th>Reason</th><th>Amount</th><th>Balance after</th></tr></thead>
                <tbody>
                <?php foreach ($transactions as $transaction): ?>
                    <?php $amount = (string) $transaction['amount']; ?>
                    <tr>
                        <td><time datetime="<?= escape($transaction['created_at']) ?>"><?= escape($transaction['created_at']) ?> UTC</time></td>
                        <td><?= escape(ucwords(str_replace('_', ' ', $transaction['transaction_type']))) ?></td>
                        <td><?= escape($transaction['reason']) ?></td>
                        <td class="<?= str_starts_with($amount, '-') ? 'amount-debit' : 'amount-credit' ?>">
                            <?= escape(str_starts_with($amount, '-') ? '-€' . centsToMoney(abs(moneyToCents(substr($amount, 1)))) : '+€' . centsToMoney(moneyToCents($amount))) ?>
                        </td>
                        <td><?= escape(commercePriceLabel($transaction['balance_after'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
