<?php

declare(strict_types=1);

$navigationUser = currentUser();
$navigationCartCount = (int) ($navigationUser['cart_game_count'] ?? 0);
$navigationUnreadChatCount = (int) ($navigationUser['unread_chat_count'] ?? 0);
$navigationPendingFriendCount = (int) ($navigationUser['pending_friend_count'] ?? 0);
$currentScript = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));

function navigationClass(bool $isActive): string
{
    return 'nav-link' . ($isActive ? ' active' : '');
}
?>
<header class="site-header sticky-top">
    <nav class="navbar navbar-expand-lg" aria-label="Main navigation">
        <div class="container">
            <a class="navbar-brand brand" href="<?= escape(baseUrl()) ?>">
                <span class="brand-mark" aria-hidden="true">S</span>
                <span>Skadi</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#primary-navigation" aria-controls="primary-navigation"
                aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="primary-navigation">
                <ul class="navbar-nav me-auto mb-3 mb-lg-0">
                    <li class="nav-item">
                        <a class="<?= navigationClass(in_array($currentScript, ['store.php', 'game.php'], true)) ?>"
                            href="<?= escape(baseUrl('store.php')) ?>"
                            <?= in_array($currentScript, ['store.php', 'game.php'], true) ? 'aria-current="page"' : '' ?>>Store</a>
                    </li>
                    <li class="nav-item">
                        <a class="<?= navigationClass($currentScript === 'library.php') ?>"
                            href="<?= escape(baseUrl('library.php')) ?>"
                            <?= $currentScript === 'library.php' ? 'aria-current="page"' : '' ?>>Library</a>
                    </li>
                    <li class="nav-item">
                        <a class="<?= navigationClass(in_array($currentScript, ['community.php', 'game-community.php', 'discussions.php', 'discussion.php', 'topic-edit.php', 'posts.php', 'community-post.php', 'post-edit.php', 'guides.php', 'guide.php', 'guide-edit.php', 'people.php', 'profile.php', 'friends.php', 'chat.php'], true)) ?>"
                            href="<?= escape(baseUrl('community.php')) ?>"
                            <?= in_array($currentScript, ['community.php', 'game-community.php', 'discussions.php', 'discussion.php', 'topic-edit.php', 'posts.php', 'community-post.php', 'post-edit.php', 'guides.php', 'guide.php', 'guide-edit.php', 'people.php', 'profile.php', 'friends.php', 'chat.php'], true) ? 'aria-current="page"' : '' ?>>Community</a>
                    </li>
                    <?php if ($navigationUser !== null && empty($navigationUser['must_change_password'])): ?>
                        <li class="nav-item">
                            <a class="<?= navigationClass($currentScript === 'cart.php') ?> nav-summary-link"
                                href="<?= escape(baseUrl('cart.php')) ?>"
                                <?= $currentScript === 'cart.php' ? 'aria-current="page"' : '' ?>>
                                <span>Cart</span>
                                <span class="nav-summary-value nav-cart-count">
                                    <?= escape($navigationCartCount . ' ' . ($navigationCartCount === 1 ? 'game' : 'games')) ?>
                                </span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="<?= navigationClass($currentScript === 'wishlist.php') ?>"
                                href="<?= escape(baseUrl('wishlist.php')) ?>"
                                <?= $currentScript === 'wishlist.php' ? 'aria-current="page"' : '' ?>>Wishlist</a>
                        </li>
                        <li class="nav-item">
                            <a class="<?= navigationClass($currentScript === 'friends.php') ?> nav-count-link"
                                href="<?= escape(baseUrl('friends.php')) ?>"
                                <?= $currentScript === 'friends.php' ? 'aria-current="page"' : '' ?>>
                                <span>Friends</span>
                                <?php if ($navigationPendingFriendCount > 0): ?>
                                    <span class="nav-notification-count" aria-label="<?= escape(
                                        $navigationPendingFriendCount . ' pending incoming friend '
                                        . ($navigationPendingFriendCount === 1 ? 'request' : 'requests')
                                    ) ?>"><?= escape($navigationPendingFriendCount) ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="<?= navigationClass($currentScript === 'chat.php') ?> nav-count-link"
                                href="<?= escape(baseUrl('chat.php')) ?>"
                                <?= $currentScript === 'chat.php' ? 'aria-current="page"' : '' ?>>
                                <span>Chat</span>
                                <?php if ($navigationUnreadChatCount > 0): ?>
                                    <span class="nav-notification-count" aria-label="<?= escape(
                                        $navigationUnreadChatCount . ' unread chat '
                                        . ($navigationUnreadChatCount === 1 ? 'message' : 'messages')
                                    ) ?>"><?= escape($navigationUnreadChatCount) ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>

                <div class="nav-utilities">
                    <?php if ($navigationUser === null): ?>
                        <a class="nav-link" href="<?= escape(baseUrl('login.php')) ?>">Sign in</a>
                        <a class="btn btn-sm btn-primary" href="<?= escape(baseUrl('register.php')) ?>">Create account</a>
                    <?php else: ?>
                        <?php if (empty($navigationUser['must_change_password'])): ?>
                            <a class="nav-link nav-summary-link" href="<?= escape(baseUrl('wallet.php')) ?>">
                                <span>Wallet</span>
                                <span class="nav-summary-value nav-wallet-balance">
                                    <?= escape('€' . centsToMoney(moneyToCents($navigationUser['wallet_balance']))) ?>
                                </span>
                            </a>
                            <a class="nav-link" href="<?= escape(baseUrl('orders.php')) ?>">Orders</a>
                        <?php endif; ?>
                        <a class="nav-account" href="<?= escape(baseUrl(authenticationDestination())) ?>">
                            <span class="avatar-placeholder" aria-hidden="true"><?= escape(mb_strtoupper(mb_substr($navigationUser['display_name'], 0, 1))) ?></span>
                            <span><?= escape($navigationUser['display_name']) ?></span>
                        </a>
                        <?php if ($navigationUser['role'] === 'admin' && empty($navigationUser['must_change_password'])): ?>
                            <a class="nav-link" href="<?= escape(baseUrl('admin/')) ?>">Admin</a>
                        <?php endif; ?>
                        <form class="nav-form" method="post" action="<?= escape(baseUrl('actions/logout.php')) ?>">
                            <?= csrfInput() ?>
                            <button class="btn btn-sm btn-outline-secondary" type="submit">Sign out</button>
                        </form>
                    <?php endif; ?>

                    <div class="theme-control">
                        <label for="theme-selector">Theme</label>
                        <select id="theme-selector" class="form-select form-select-sm" data-theme-selector
                            data-save-url="<?= escape(baseUrl('actions/theme.php')) ?>">
                            <option value="skadi" <?= $activeTheme === 'skadi' ? 'selected' : '' ?>>Skadi</option>
                            <option value="dark" <?= $activeTheme === 'dark' ? 'selected' : '' ?>>Dark</option>
                            <option value="light" <?= $activeTheme === 'light' ? 'selected' : '' ?>>Light</option>
                        </select>
                        <span class="visually-hidden" data-theme-status aria-live="polite"></span>
                    </div>
                </div>
                <noscript><p class="navigation-note">Theme switching requires JavaScript. The default theme remains readable.</p></noscript>
            </div>
        </div>
    </nav>
</header>
