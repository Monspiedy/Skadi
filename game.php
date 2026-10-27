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

$coverUrl = safeGameMediaUrl($game['cover']['storage_path'] ?? null);
$pageTitle = $game['title'];
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
            <p class="eyebrow">Game details</p>
            <h1><?= escape($game['title']) ?></h1>
            <p class="game-lead"><?= escape($game['short_description']) ?></p>
            <ul class="genre-list" aria-label="Genres">
                <?php foreach ($game['genres'] as $genre): ?>
                    <li><a href="<?= escape(baseUrl('store.php?genre=' . rawurlencode($genre['slug']))) ?>"><?= escape($genre['name']) ?></a></li>
                <?php endforeach; ?>
            </ul>
            <div class="price-panel">
                <div>
                    <span>Base price</span>
                    <strong><?= escape(gamePriceLabel($game['base_price'])) ?></strong>
                </div>
                <div>
                    <span>Current price</span>
                    <strong class="<?= (float) $game['base_price'] === 0.0 ? 'price-free' : '' ?>">
                        <?= escape(gamePriceLabel($game['base_price'])) ?>
                    </strong>
                </div>
                <small><?= (float) $game['base_price'] === 0.0
                    ? 'This game is free to play.'
                    : 'Sale pricing will be calculated in the weekend sale phase.' ?></small>
            </div>
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
        </div>

        <aside class="game-side-panel" aria-label="Game community and reviews">
            <section id="reviews">
                <p class="eyebrow">Player rating</p>
                <h2><?= escape(gameRatingLabel($game)) ?></h2>
                <p>Review reading and writing will become available in Phase 08.</p>
            </section>
            <section>
                <p class="eyebrow">Community</p>
                <h2>Community Hub</h2>
                <p>Guides, discussions and screenshots will appear in the game’s hub in later phases.</p>
                <a class="btn btn-outline-secondary" href="<?= escape(baseUrl('community.php?game=' . (int) $game['id'])) ?>">Community Hub placeholder</a>
            </section>
        </aside>
    </div>
</article>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
