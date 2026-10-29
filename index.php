<?php

declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';

$connection = db();
$viewerUserId = isAuthenticated() ? (int) currentUser()['id'] : null;
$storefrontGames = loadHomeStorefrontGames($connection, $viewerUserId);
$priceRows = loadCurrentPriceRows($connection, array_column($storefrontGames, 'id'));

foreach ($storefrontGames as &$storefrontGame) {
    $storefrontGame = array_merge($storefrontGame, $priceRows[(int) $storefrontGame['id']] ?? [
        'current_price' => $storefrontGame['base_price'],
        'discount_percent' => 0,
    ]);
}
unset($storefrontGame);

$featuredGame = $storefrontGames[0] ?? null;
if ($featuredGame !== null) {
    foreach ($storefrontGames as $candidate) {
        if ($candidate['featured_position'] !== null) {
            $featuredGame = $candidate;
            break;
        }
    }
}

$usedGameIds = $featuredGame === null ? [] : [(int) $featuredGame['id'] => true];
$paidGames = array_values(array_filter(
    $storefrontGames,
    static fn (array $game): bool => moneyToCents((string) $game['base_price']) > 0
));
$discountedGames = array_values(array_filter(
    $paidGames,
    static fn (array $game): bool => (int) $game['discount_percent'] > 0
));
usort($discountedGames, static function (array $left, array $right): int {
    return ((int) $right['discount_percent'] <=> (int) $left['discount_percent'])
        ?: (moneyToCents((string) $left['current_price']) <=> moneyToCents((string) $right['current_price']));
});
usort($paidGames, static function (array $left, array $right): int {
    return (($left['featured_position'] === null) <=> ($right['featured_position'] === null))
        ?: ((int) ($left['featured_position'] ?? PHP_INT_MAX) <=> (int) ($right['featured_position'] ?? PHP_INT_MAX));
});

$saleCountdown = weekendSaleCountdownState();
$weekendCandidates = $saleCountdown['is_active'] ? $discountedGames : $paidGames;
$weekendGames = takeUniqueStorefrontGames($weekendCandidates, $usedGameIds, 2);

// Reserve free games before ranking the other shelves so each home section stays distinct.
$freeCandidates = array_values(array_filter(
    $storefrontGames,
    static fn (array $game): bool => moneyToCents((string) $game['base_price']) === 0
));
$freeGames = takeUniqueStorefrontGames($freeCandidates, $usedGameIds, 2);

$popularCandidates = $storefrontGames;
usort($popularCandidates, static function (array $left, array $right): int {
    return ((int) $right['owner_count'] <=> (int) $left['owner_count'])
        ?: ((int) $right['review_count'] <=> (int) $left['review_count'])
        ?: ((float) ($right['average_rating'] ?? 0) <=> (float) ($left['average_rating'] ?? 0))
        ?: strcasecmp((string) $left['title'], (string) $right['title']);
});
$popularGames = takeUniqueStorefrontGames($popularCandidates, $usedGameIds, 2);

$newCandidates = $storefrontGames;
usort($newCandidates, static function (array $left, array $right): int {
    return strcmp((string) $right['release_date'], (string) $left['release_date'])
        ?: strcasecmp((string) $left['title'], (string) $right['title']);
});
$newGames = takeUniqueStorefrontGames($newCandidates, $usedGameIds, 2);

$pageTitle = 'Storefront';
$pageScripts = ['assets/js/sale-countdown.js'];
require SKADI_ROOT . '/includes/header.php';
?>
<?php if ($featuredGame !== null): ?>
    <section class="home-featured" aria-labelledby="featured-game-heading">
        <a class="home-featured-art" href="<?= escape(baseUrl('game.php?id=' . (int) $featuredGame['id'])) ?>"
            aria-label="View <?= escape($featuredGame['title']) ?> game details">
            <?php renderStorefrontArtwork($featuredGame, 'home-featured-cover', true); ?>
        </a>
        <div class="home-featured-content">
            <p class="eyebrow">Featured game</p>
            <h1 id="featured-game-heading"><?= escape($featuredGame['title']) ?></h1>
            <p class="home-featured-lead"><?= escape($featuredGame['short_description']) ?></p>
            <?php if ($featuredGame['genres'] !== []): ?>
                <ul class="genre-list" aria-label="<?= escape($featuredGame['title']) ?> genres">
                    <?php foreach ($featuredGame['genres'] as $genre): ?>
                        <li><a href="<?= escape(baseUrl('store.php?genre=' . rawurlencode($genre['slug']))) ?>">
                            <?= escape($genre['name']) ?>
                        </a></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <div class="home-featured-meta">
                <?php renderStorefrontPrice($featuredGame, 'store-price-hero'); ?>
                <span><?= escape(gameRatingLabel($featuredGame)) ?></span>
            </div>
            <?php renderStorefrontStateBadges($featuredGame); ?>
            <div class="home-featured-actions">
                <a class="btn btn-primary" href="<?= escape(baseUrl('game.php?id=' . (int) $featuredGame['id'])) ?>">
                    View <?= escape($featuredGame['title']) ?>
                </a>
                <a class="btn btn-outline-secondary" href="<?= escape(baseUrl('store.php')) ?>">Browse the Store</a>
            </div>
        </div>
    </section>
<?php else: ?>
    <?php renderStatePanel('empty', 'The storefront is being prepared', 'Published games will appear here when the catalog is available.'); ?>
<?php endif; ?>

<aside class="sale-banner home-sale-banner<?= $saleCountdown['is_active'] ? ' sale-banner-active' : '' ?>"
    aria-label="Weekend sale timing" data-sale-countdown data-target-time="<?= escape($saleCountdown['target_iso']) ?>"
    data-sale-active="<?= $saleCountdown['is_active'] ? 'true' : 'false' ?>">
    <div>
        <p class="eyebrow"><?= $saleCountdown['is_active'] ? 'Weekend sale is live' : 'Next weekend sale' ?></p>
        <strong data-sale-countdown-label><?= escape($saleCountdown['fallback']) ?></strong>
    </div>
    <a href="<?= escape(baseUrl('store.php')) ?>">See all games <span aria-hidden="true">&rarr;</span></a>
</aside>

<div class="home-discovery-grid">
    <?php renderHomeStorefrontShelf(
        'weekend-deals-heading',
        $saleCountdown['is_active'] ? 'Discounts active now' : 'Before the frost falls',
        $saleCountdown['is_active'] ? 'Weekend Deals' : 'Weekend preview',
        $saleCountdown['is_active']
            ? 'Persisted discounts shared by every visitor, with prices calculated on the server.'
            : 'No discounts are active yet. These paid games are shown at their current base prices.',
        $weekendGames,
        baseUrl('store.php?price=all&sort=price')
    ); ?>

    <?php renderHomeStorefrontShelf(
        'popular-games-heading',
        'Player activity',
        'Popular Games',
        'Ranked from current ownership and review activity, with a stable title tie-breaker.',
        $popularGames,
        baseUrl('store.php?sort=popularity')
    ); ?>

    <?php renderHomeStorefrontShelf(
        'free-games-heading',
        'Start playing',
        'Free to Play',
        'Published games with a current server price of zero—no demo-wallet charge required.',
        $freeGames,
        baseUrl('store.php?price=free')
    ); ?>

    <?php renderHomeStorefrontShelf(
        'new-releases-heading',
        'Latest by release date',
        'New Releases',
        'The most recently released catalog games not already highlighted above.',
        $newGames,
        baseUrl('store.php?sort=newest')
    ); ?>
</div>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
