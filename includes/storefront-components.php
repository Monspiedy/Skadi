<?php

declare(strict_types=1);

function renderStorefrontArtwork(array $game, string $className = '', bool $eager = false): void
{
    $coverUrl = safeGameMediaUrl($game['cover_path'] ?? null);
    if ($coverUrl !== null) {
        ?>
        <img class="game-artwork <?= escape($className) ?>" src="<?= escape($coverUrl) ?>"
            alt="<?= escape(($game['cover_alt'] ?? '') ?: $game['title'] . ' cover') ?>"
            loading="<?= $eager ? 'eager' : 'lazy' ?>" decoding="async">
        <?php
        return;
    }
    ?>
    <div class="game-artwork game-artwork-placeholder <?= escape($className) ?>" role="img"
        aria-label="Original placeholder cover for <?= escape($game['title']) ?>">
        <span aria-hidden="true"><?= escape(mb_strtoupper(mb_substr($game['title'], 0, 2))) ?></span>
        <small><?= escape($game['title']) ?></small>
    </div>
    <?php
}

function renderStorefrontPrice(array $game, string $className = ''): void
{
    $discountPercent = (int) ($game['discount_percent'] ?? 0);
    $isFree = moneyToCents((string) $game['current_price']) === 0;
    ?>
    <div class="store-price <?= escape($className) ?>">
        <?php if ($discountPercent > 0): ?>
            <span class="discount-badge"><?= escape($discountPercent) ?>% off</span>
            <span class="base-price"><?= escape(commercePriceLabel((string) $game['base_price'])) ?></span>
        <?php endif; ?>
        <strong class="current-price<?= $isFree ? ' price-free' : '' ?>">
            <?= escape(commercePriceLabel((string) $game['current_price'])) ?>
        </strong>
    </div>
    <?php
}

function renderStorefrontStateBadges(array $game): void
{
    $badges = [];
    if (!empty($game['is_owned'])) {
        $badges[] = 'Owned';
    }
    if (!empty($game['is_in_cart'])) {
        $badges[] = 'In cart';
    }
    if (!empty($game['is_wishlisted'])) {
        $badges[] = 'Wishlisted';
    }

    if ($badges === []) {
        return;
    }
    ?>
    <ul class="game-state-badges" aria-label="Your game status">
        <?php foreach ($badges as $badge): ?>
            <li><?= escape($badge) ?></li>
        <?php endforeach; ?>
    </ul>
    <?php
}

function renderStorefrontActions(array $game): void
{
    if (!isAuthenticated() || !empty(currentUser()['must_change_password'])) {
        ?>
        <a class="btn btn-sm btn-outline-secondary" href="<?= escape(baseUrl('login.php')) ?>">
            Sign in to add this game
        </a>
        <?php
        return;
    }

    if (!empty($game['is_owned'])) {
        ?>
        <a class="btn btn-sm btn-outline-secondary" href="<?= escape(baseUrl('library.php')) ?>">View in Library</a>
        <?php
        return;
    }
    ?>
    <div class="game-purchase-actions">
        <?php if (!empty($game['is_in_cart'])): ?>
            <a class="btn btn-sm btn-primary" href="<?= escape(baseUrl('cart.php')) ?>">View cart</a>
        <?php else: ?>
            <form method="post" action="<?= escape(baseUrl('actions/cart-add.php')) ?>">
                <?= csrfInput() ?>
                <input type="hidden" name="game_id" value="<?= escape((int) $game['id']) ?>">
                <button class="btn btn-sm btn-primary" type="submit">Add to cart</button>
            </form>
        <?php endif; ?>

        <?php if (!empty($game['is_wishlisted'])): ?>
            <a class="btn btn-sm btn-outline-secondary" href="<?= escape(baseUrl('wishlist.php')) ?>">View wishlist</a>
        <?php else: ?>
            <form method="post" action="<?= escape(baseUrl('actions/wishlist-add.php')) ?>">
                <?= csrfInput() ?>
                <input type="hidden" name="game_id" value="<?= escape((int) $game['id']) ?>">
                <button class="btn btn-sm btn-outline-secondary" type="submit">Wishlist</button>
            </form>
        <?php endif; ?>
    </div>
    <?php
}

function renderStorefrontCard(array $game, string $variant = 'catalog'): void
{
    $gameUrl = baseUrl('game.php?id=' . (int) $game['id']);
    ?>
    <article class="game-card game-card-<?= escape($variant) ?>">
        <div class="game-card-media">
            <a class="game-card-art" href="<?= escape($gameUrl) ?>"
                aria-label="View <?= escape($game['title']) ?> game details">
                <?php renderStorefrontArtwork($game, '', $variant === 'compact'); ?>
            </a>
            <?php renderStorefrontStateBadges($game); ?>
        </div>
        <div class="game-card-body">
            <div class="game-card-heading">
                <h3><a href="<?= escape($gameUrl) ?>"><?= escape($game['title']) ?></a></h3>
                <?php renderStorefrontPrice($game); ?>
            </div>
            <p class="game-card-description"><?= escape($game['short_description']) ?></p>
            <?php if (($game['genres'] ?? []) !== []): ?>
                <ul class="genre-list" aria-label="<?= escape($game['title']) ?> genres">
                    <?php foreach ($game['genres'] as $genre): ?>
                        <li><a href="<?= escape(baseUrl('store.php?genre=' . rawurlencode($genre['slug']))) ?>">
                            <?= escape($genre['name']) ?>
                        </a></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <p class="game-card-meta"><?= escape(gameRatingLabel($game)) ?></p>
            <div class="game-card-actions">
                <?php if ($variant !== 'compact'): ?>
                    <?php renderStorefrontActions($game); ?>
                <?php endif; ?>
                <a class="game-details-link" href="<?= escape($gameUrl) ?>">Game details <span aria-hidden="true">&rarr;</span></a>
            </div>
        </div>
    </article>
    <?php
}

function renderHomeStorefrontShelf(
    string $headingId,
    string $eyebrow,
    string $title,
    string $description,
    array $games,
    string $storeUrl
): void {
    if ($games === []) {
        return;
    }
    ?>
    <section class="home-shelf" aria-labelledby="<?= escape($headingId) ?>">
        <div class="home-shelf-heading">
            <div>
                <p class="eyebrow"><?= escape($eyebrow) ?></p>
                <h2 id="<?= escape($headingId) ?>"><?= escape($title) ?></h2>
            </div>
            <a href="<?= escape($storeUrl) ?>">View in Store <span aria-hidden="true">&rarr;</span></a>
        </div>
        <p class="home-shelf-description"><?= escape($description) ?></p>
        <div class="home-shelf-games">
            <?php foreach ($games as $game): ?>
                <?php renderStorefrontCard($game, 'compact'); ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
}
