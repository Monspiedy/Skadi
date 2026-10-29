<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';
requireAdmin();

$search = is_string($_GET['search'] ?? null) ? $_GET['search'] : '';
$users = loadAdminUsers(db(), $search, adminPositivePage($_GET['page'] ?? 1));
$pageTitle = 'User administration';
require SKADI_ROOT . '/includes/header.php';
?>
<section class="admin-shell" aria-labelledby="users-heading">
    <p class="eyebrow">Administration</p>
    <h1 id="users-heading">Users and wallets</h1>
    <p>Login names and password data are deliberately excluded. Every change is recorded with the acting administrator.</p>
    <?php renderAdminNav('users'); ?>

    <form class="filter-form" method="get" action="<?= escape(baseUrl('admin/users.php')) ?>">
        <label for="user-search">Display name, exact Skadi ID or email</label>
        <div class="input-group">
            <input class="form-control" id="user-search" name="search" maxlength="100" value="<?= escape($users['search']) ?>">
            <button class="btn btn-primary" type="submit">Search</button>
        </div>
    </form>

    <?php if ($users['items'] === []): ?>
        <?php renderStatePanel('empty', 'No users found', 'Try a different safe account search.'); ?>
    <?php else: ?>
        <div class="admin-user-grid">
            <?php foreach ($users['items'] as $user): ?>
                <article class="admin-panel">
                    <h2><?= escape($user['display_name']) ?></h2>
                    <p><strong><?= escape($user['skadi_id']) ?></strong><br><?= escape($user['email']) ?></p>
                    <p>Wallet: <?= escape(gamePriceLabel($user['wallet_balance'])) ?> · Joined <?= escape($user['created_at']) ?></p>
                    <form class="admin-form" method="post" action="<?= escape(baseUrl('actions/admin/user-update.php')) ?>">
                        <?= csrfInput() ?>
                        <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                        <div class="admin-form-grid">
                            <label>Role
                                <select name="role" required>
                                    <option value="user" <?= $user['role_name'] === 'user' ? 'selected' : '' ?>>User</option>
                                    <option value="admin" <?= $user['role_name'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                </select>
                            </label>
                            <label>Status
                                <select name="status" required>
                                    <?php foreach (['active', 'suspended', 'blocked'] as $status): ?>
                                        <option value="<?= $status ?>" <?= $user['status'] === $status ? 'selected' : '' ?>><?= escape(ucfirst($status)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>
                        <button class="btn btn-sm btn-outline-primary" type="submit">Save access</button>
                    </form>
                    <form class="admin-form" method="post" action="<?= escape(baseUrl('actions/admin/wallet-adjust.php')) ?>">
                        <?= csrfInput() ?>
                        <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                        <div class="admin-form-grid">
                            <label>Adjustment
                                <input name="amount" inputmode="decimal" placeholder="10.00 or -5.00" required>
                            </label>
                            <label>Mandatory reason
                                <input name="reason" minlength="3" maxlength="500" required>
                            </label>
                        </div>
                        <button class="btn btn-sm btn-outline-warning" type="submit">Adjust wallet</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
        <?php renderAdminPagination('admin/users.php', $users, ['search' => $users['search']]); ?>
    <?php endif; ?>
</section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
