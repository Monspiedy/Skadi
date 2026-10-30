<?php

declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';

$rawGameId = $_GET['id'] ?? '';
$gameId = is_string($rawGameId) && ctype_digit($rawGameId) ? (int) $rawGameId : 0;
$game = $gameId > 0 ? loadGameDetail(db(), $gameId) : null;

if ($game === null) {
    http_response_code(404);
    $pageTitle = 'Game not found';
    require SKADI_ROOT . '/includes/header.php';
    ?>
    <section class="status-page" aria-labelledby="missing-game-heading">
        <p class="status-code">404</p>
        <h1 id="missing-game-heading">Game not found</h1>
        <p>The game ID is missing, invalid or no longer available in the public catalog.</p>
        <a class="button-link" href="<?= escape(baseUrl('store.php')) ?>">Return to the store</a>
    </section>
    <?php
    require SKADI_ROOT . '/includes/footer.php';
    exit;
}

$currentPrice = loadCurrentPriceRows(db(), [$gameId])[$gameId] ?? [
    'current_price' => $game['base_price'],
    'discount_percent' => 0,
];
$reviews = loadGameReviews(db(), $gameId);
$ownsGame = false;
$viewerGameState = ['is_owned' => false, 'is_in_cart' => false, 'is_wishlisted' => false];
$userReview = null;
if (isAuthenticated() && empty(currentUser()['must_change_password'])) {
    $currentUserId = (int) currentUser()['id'];
    $viewerGameState = loadViewerGameState(db(), $currentUserId, $gameId);
    $ownsGame = $viewerGameState['is_owned'];
    $userReview = loadUserReview(db(), $currentUserId, $gameId);
}

$coverUrl = safeGameMediaUrl($game['cover']['storage_path'] ?? null);
$pageTitle = $game['title'];
$pageScripts = ['assets/js/library.js'];
require SKADI_ROOT . '/includes/header.php';
?>
<nav class="breadcrumb-nav" aria-label="Breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= escape(baseUrl('store.php')) ?>">Store</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= escape($game['title']) ?></li>
    </ol>
</nav>

<article class="game-detail">
    <header class="game-detail-hero">
        <div class="game-detail-cover">
            <?php if ($coverUrl !== null): ?>
                <img class="game-artwork" src="<?= escape($coverUrl) ?>"
                    alt="<?= escape($game['cover']['alt_text'] ?: $game['title'] . ' cover') ?>">
            <?php else: ?>
                <div class="game-artwork game-artwork-placeholder" role="img"
                    aria-label="Original placeholder cover for <?= escape($game['title']) ?>">
                    <span aria-hidden="true"><?= escape(mb_strtoupper(mb_substr($game['title'], 0, 2))) ?></span>
                    <small><?= escape($game['title']) ?></small>
                </div>
            <?php endif; ?>
        </div>
        <div class="game-detail-summary">
            <div class="game-detail-kicker">
                <p class="eyebrow">Store / Game</p>
                <?php if ($currentPrice['discount_percent'] > 0): ?>
                    <span class="discount-badge">Weekend Deal · <?= (int) $currentPrice['discount_percent'] ?>% off</span>
                <?php elseif (moneyToCents($game['base_price']) === 0): ?>
                    <span class="game-state-pill">Free to play</span>
                <?php endif; ?>
            </div>
            <h1><?= escape($game['title']) ?></h1>
            <p class="game-lead"><?= escape($game['short_description']) ?></p>
            <ul class="genre-list" aria-label="Genres">
                <?php foreach ($game['genres'] as $genre): ?>
                    <li><a href="<?= escape(baseUrl('store.php?genre=' . rawurlencode($genre['slug']))) ?>"><?= escape($genre['name']) ?></a></li>
                <?php endforeach; ?>
            </ul>
            <div class="price-panel <?= $currentPrice['discount_percent'] > 0 ? 'price-panel-sale' : '' ?>">
                <div class="game-current-price">
                    <span><?= $currentPrice['discount_percent'] > 0 ? 'Weekend price' : 'Current price' ?></span>
                    <strong class="<?= moneyToCents($currentPrice['current_price']) === 0 ? 'price-free' : '' ?>">
                        <?= escape(commercePriceLabel($currentPrice['current_price'])) ?>
                    </strong>
                </div>
                <?php if ($currentPrice['discount_percent'] > 0): ?>
                    <div class="game-base-price">
                        <span>Regular price</span>
                        <strong><s><?= escape(gamePriceLabel($game['base_price'])) ?></s></strong>
                    </div>
                <?php else: ?>
                    <div class="game-base-price"><span>Price status</span><strong><?= moneyToCents($game['base_price']) === 0 ? 'Always free' : 'Regular price' ?></strong></div>
                <?php endif; ?>
                <small>Price and discount are calculated by Skadi on the server.</small>
            </div>
            <?php if (isAuthenticated() && empty(currentUser()['must_change_password'])): ?>
                <div class="game-account-state" aria-label="Your game status">
                    <?php if ($viewerGameState['is_owned']): ?>
                        <span class="game-state-pill game-state-owned">Owned</span>
                        <a class="btn btn-primary" href="<?= escape(baseUrl('library.php')) ?>">View in library</a>
                    <?php elseif ($viewerGameState['is_in_cart']): ?>
                        <span class="game-state-pill">In cart</span>
                        <a class="btn btn-primary" href="<?= escape(baseUrl('cart.php')) ?>">View cart</a>
                        <?php if ($viewerGameState['is_wishlisted']): ?><span class="game-state-pill">Wishlisted</span><?php endif; ?>
                    <?php else: ?>
                        <?php if ($viewerGameState['is_wishlisted']): ?>
                            <span class="game-state-pill">Wishlisted</span>
                            <a class="btn btn-outline-secondary" href="<?= escape(baseUrl('wishlist.php')) ?>">View wishlist</a>
                        <?php endif; ?>
                        <form method="post" action="<?= escape(baseUrl('actions/cart-add.php')) ?>">
                            <?= csrfInput() ?>
                            <input type="hidden" name="game_id" value="<?= escape($gameId) ?>">
                            <button class="btn btn-primary" type="submit">Add to cart</button>
                        </form>
                        <?php if (!$viewerGameState['is_wishlisted']): ?>
                            <form method="post" action="<?= escape(baseUrl('actions/wishlist-add.php')) ?>">
                                <?= csrfInput() ?>
                                <input type="hidden" name="game_id" value="<?= escape($gameId) ?>">
                                <button class="btn btn-outline-secondary" type="submit">Add to wishlist</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="game-account-state">
                    <a class="btn btn-primary" href="<?= escape(baseUrl('login.php')) ?>">Sign in to add this game</a>
                    <span>Keep purchases, reviews and wishlists tied to your account.</span>
                </div>
            <?php endif; ?>
            <dl class="game-facts">
                <div><dt>Developer</dt><dd><?= escape($game['developer']) ?></dd></div>
                <div><dt>Publisher</dt><dd><?= escape($game['publisher']) ?></dd></div>
                <div><dt>Release date</dt><dd><time datetime="<?= escape($game['release_date']) ?>"><?= escape(date('F j, Y', strtotime($game['release_date']))) ?></time></dd></div>
                <div><dt>Rating</dt><dd><?= escape(gameRatingLabel($game)) ?></dd></div>
            </dl>
        </div>
    </header>

    <div class="game-detail-grid">
        <div class="game-main-content">
            <section aria-labelledby="about-heading">
                <h2 id="about-heading">About this game</h2>
                <p><?= nl2br(escape($game['description'])) ?></p>
            </section>

            <section aria-labelledby="gallery-heading">
                <div class="section-heading">
                    <h2 id="gallery-heading">Screenshots</h2>
                    <p><?= escape(count($game['screenshots'])) ?> available</p>
                </div>
                <?php if ($game['screenshots'] === []): ?>
                    <?php renderStatePanel('empty', 'Gallery awaiting media', 'Original screenshots can be added through the approved upload phase.'); ?>
                <?php else: ?>
                    <div class="screenshot-grid">
                        <?php foreach ($game['screenshots'] as $screenshot): ?>
                            <?php $screenshotUrl = safeGameMediaUrl($screenshot['storage_path']); ?>
                            <?php if ($screenshotUrl !== null): ?>
                                <a href="<?= escape($screenshotUrl) ?>" target="_blank" rel="noopener">
                                    <img src="<?= escape($screenshotUrl) ?>" alt="<?= escape($screenshot['alt_text']) ?>">
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section aria-labelledby="requirements-heading">
                <h2 id="requirements-heading">System requirements</h2>
                <p><?= nl2br(escape($game['system_requirements'])) ?></p>
            </section>

            <section class="reviews-section" id="reviews" aria-labelledby="reviews-heading">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">Owner feedback</p>
                        <h2 id="reviews-heading">Reviews</h2>
                    </div>
                    <strong><?= escape(gameRatingLabel($game)) ?></strong>
                </div>

                <?php if ($ownsGame && ($userReview === null || $userReview['status'] !== 'hidden')): ?>
                    <form class="review-form" method="post" action="<?= escape(baseUrl('actions/review-save.php')) ?>">
                        <?= csrfInput() ?>
                        <input type="hidden" name="game_id" value="<?= escape($gameId) ?>">
                        <div>
                            <label for="review-rating">Rating</label>
                            <select class="form-select" id="review-rating" name="rating" required>
                                <?php for ($rating = 10; $rating >= 1; $rating--): ?>
                                    <option value="<?= $rating ?>" <?= (int) ($userReview['rating'] ?? 10) === $rating ? 'selected' : '' ?>>
                                        <?= $rating ?>/10
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div>
                            <label for="review-body">Review text <span class="form-note">(optional)</span></label>
                            <textarea class="form-control" id="review-body" name="body" rows="5" maxlength="4000"><?= escape((string) ($userReview['body'] ?? '')) ?></textarea>
                        </div>
                        <div class="review-form-actions">
                            <button class="btn btn-primary" type="submit">
                                <?= $userReview !== null && $userReview['status'] === 'active' ? 'Update review' : 'Publish review' ?>
                            </button>
                        </div>
                    </form>
                <?php elseif ($userReview !== null && $userReview['status'] === 'hidden'): ?>
                    <p class="catalog-notice">Your review is hidden by moderation and cannot be edited. You may still delete it.</p>
                <?php elseif (isAuthenticated()): ?>
                    <p>Only current owners can publish a rating and review.</p>
                <?php else: ?>
                    <p><a href="<?= escape(baseUrl('login.php')) ?>">Sign in</a> to review a game you own.</p>
                <?php endif; ?>

                <?php if ($userReview !== null && in_array($userReview['status'], ['active', 'hidden'], true)): ?>
                    <form method="post" action="<?= escape(baseUrl('actions/review-delete.php')) ?>"
                        data-confirm-form="Delete your review and remove it from the game average?">
                        <?= csrfInput() ?>
                        <input type="hidden" name="game_id" value="<?= escape($gameId) ?>">
                        <button class="btn btn-sm btn-outline-danger" type="submit">Delete my review</button>
                    </form>
                <?php endif; ?>

                <?php if ($reviews === []): ?>
                    <?php renderStatePanel('empty', 'No active reviews yet', 'Current owners can be the first to rate this game.'); ?>
                <?php else: ?>
                    <div class="review-list">
                        <?php foreach ($reviews as $review): ?>
                            <article class="review-card">
                                <header>
                                    <div>
                                        <h3><?= escape($review['display_name']) ?></h3>
                                        <span class="badge text-bg-info"><?= escape((int) $review['rating']) ?>/10</span>
                                    </div>
                                    <small>
                                        <?= $review['updated_at'] !== $review['created_at'] ? 'Updated' : 'Published' ?>
                                        <?= escape(formatUtcDateTime($review['updated_at'])) ?>
                                    </small>
                                </header>
                                <?php if ($review['body'] !== null && trim($review['body']) !== ''): ?>
                                    <p><?= nl2br(escape($review['body'])) ?></p>
                                <?php else: ?>
                                    <p class="form-note">Rating only.</p>
                                <?php endif; ?>
                                <?php renderReportForm('review', (int) $review['id'], 'game.php?id=' . $gameId . '#reviews'); ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <aside class="game-side-panel" aria-label="Game community and reviews">
            <nav class="game-section-nav" aria-label="Explore this game">
                <p class="eyebrow">Explore</p>
                <a href="#about-heading">About</a>
                <a href="#gallery-heading">Screenshots</a>
                <a href="#requirements-heading">Requirements</a>
                <a href="#reviews-heading">Reviews</a>
            </nav>
            <section>
                <p class="eyebrow">Player rating</p>
                <h2><?= escape(gameRatingLabel($game)) ?></h2>
                <p>Average and count include active reviews only.</p>
                <a class="btn btn-outline-secondary" href="#reviews-heading">Read reviews</a>
            </section>
            <section>
                <p class="eyebrow">Community</p>
                <h2>Community Hub</h2>
                <p>Browse this game's discussions, guides, screenshots and reviews.</p>
                <a class="btn btn-outline-secondary" href="<?= escape(baseUrl('game-community.php?id=' . (int) $game['id'])) ?>">Open Community Hub</a>
                <a class="btn btn-outline-secondary" href="<?= escape(baseUrl('game-community.php?id=' . (int) $game['id'] . '&tab=guides')) ?>">Browse guides</a>
                <a class="btn btn-outline-secondary" href="<?= escape(baseUrl('game-community.php?id=' . (int) $game['id'] . '&tab=discussions')) ?>">Browse discussions</a>
                <?php if (isAuthenticated() && empty(currentUser()['must_change_password'])): ?>
                    <a class="btn btn-outline-secondary" href="<?= escape(baseUrl('guide-edit.php?game=' . (int) $game['id'])) ?>">Write a guide</a>
                <?php endif; ?>
            </section>
        </aside>
    </div>
</article>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
