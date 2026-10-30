<?php

declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';

$connection = db();
$viewerUserId = isAuthenticated() ? (int) currentUser()['id'] : null;
$genres = loadCatalogGenres($connection);
$filters = parseCatalogFilters($_GET, $genres);
$totalGames = countCatalogGames($connection, $filters);
$totalPages = max(1, (int) ceil($totalGames / CATALOG_PAGE_SIZE));

if ($filters['page'] > $totalPages) {
    $filters['page'] = $totalPages;
    $filters['warnings'][] = 'That page was outside the catalog, so the last available page is shown.';
}

$featuredGames = loadFeaturedGames($connection, $viewerUserId);
$catalogGames = $totalGames > 0
    ? loadCatalogGames($connection, $filters, $filters['page'], $viewerUserId)
    : [];
$priceRows = loadCurrentPriceRows(
    $connection,
    array_merge(array_column($featuredGames, 'id'), array_column($catalogGames, 'id'))
);
foreach ($featuredGames as &$featuredGame) {
    $featuredGame = array_merge($featuredGame, $priceRows[(int) $featuredGame['id']] ?? [
        'current_price' => $featuredGame['base_price'], 'discount_percent' => 0,
    ]);
}
unset($featuredGame);
foreach ($catalogGames as &$catalogGame) {
    $catalogGame = array_merge($catalogGame, $priceRows[(int) $catalogGame['id']] ?? [
        'current_price' => $catalogGame['base_price'], 'discount_percent' => 0,
    ]);
}
unset($catalogGame);
$firstResult = $totalGames === 0 ? 0 : (($filters['page'] - 1) * CATALOG_PAGE_SIZE) + 1;
$lastResult = min($filters['page'] * CATALOG_PAGE_SIZE, $totalGames);
$saleCountdown = weekendSaleCountdownState();

$pageTitle = 'Store';
$pageScripts = ['assets/js/sale-countdown.js', 'assets/js/catalog-search.js'];
require SKADI_ROOT . '/includes/header.php';
?>
<section class="store-intro" aria-labelledby="store-heading">
    <p class="eyebrow">Browse the collection</p>
    <h1 id="store-heading">Store</h1>
    <p>Explore twelve demonstration games using server-rendered search, filters and sorting.</p>
</section>

<aside class="sale-banner<?= $saleCountdown['is_active'] ? ' sale-banner-active' : '' ?>"
    aria-label="Weekend sale timing" data-sale-countdown data-target-time="<?= escape($saleCountdown['target_iso']) ?>"
    data-sale-active="<?= $saleCountdown['is_active'] ? 'true' : 'false' ?>">
    <div>
        <p class="eyebrow"><?= $saleCountdown['is_active'] ? 'Weekend sale is live' : 'Weekend sale' ?></p>
        <strong data-sale-countdown-label><?= escape($saleCountdown['fallback']) ?></strong>
    </div>
    <p>The server decides every price. This countdown is display-only.</p>
</aside>

<?php if ($featuredGames !== []): ?>
    <section class="catalog-section" aria-labelledby="featured-heading">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Selected by Skadi</p>
                <h2 id="featured-heading">Featured games</h2>
            </div>
            <p><?= escape(count($featuredGames)) ?> of 5 featured slots</p>
        </div>
        <div class="featured-grid">
            <?php foreach ($featuredGames as $game): ?>
                <?php renderStorefrontCard($game, 'featured'); ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<section class="catalog-section" aria-labelledby="catalog-heading">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Full catalog</p>
            <h2 id="catalog-heading">Find your next game</h2>
        </div>
        <p aria-live="polite">
            <?= $totalGames === 0 ? 'No games found' : escape($firstResult . '–' . $lastResult . ' of ' . $totalGames . ' games') ?>
        </p>
    </div>

    <form class="catalog-filters" method="get" action="<?= escape(baseUrl('store.php')) ?>">
        <div class="filter-field filter-search catalog-search-control" data-catalog-search
            data-suggestions-url="<?= escape(baseUrl('api/catalog-suggestions.php')) ?>"
            data-game-url="<?= escape(baseUrl('game.php')) ?>">
            <label for="catalog-search">Title</label>
            <input class="form-control" id="catalog-search" name="q" type="search" maxlength="80"
                value="<?= escape($filters['search']) ?>" placeholder="Search game titles" autocomplete="off"
                role="combobox" aria-autocomplete="list" aria-controls="catalog-suggestions"
                aria-expanded="false" data-catalog-search-input>
            <ul class="catalog-suggestions" id="catalog-suggestions" role="listbox"
                aria-label="Game suggestions" data-catalog-suggestions hidden></ul>
            <span class="visually-hidden" aria-live="polite" data-catalog-search-status></span>
        </div>
        <div class="filter-field">
            <label for="catalog-genre">Genre</label>
            <select class="form-select" id="catalog-genre" name="genre">
                <option value="">All genres</option>
                <?php foreach ($genres as $genre): ?>
                    <option value="<?= escape($genre['slug']) ?>" <?= $filters['genre'] === $genre['slug'] ? 'selected' : '' ?>>
                        <?= escape($genre['name']) ?> (<?= escape((int) $genre['game_count']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-field">
            <label for="catalog-price">Price</label>
            <select class="form-select" id="catalog-price" name="price">
                <?php foreach (catalogPriceOptions() as $value => $label): ?>
                    <option value="<?= escape($value) ?>" <?= $filters['price'] === $value ? 'selected' : '' ?>><?= escape($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-field">
            <label for="catalog-sort">Sort by</label>
            <select class="form-select" id="catalog-sort" name="sort">
                <?php foreach (catalogSortOptions() as $value => $label): ?>
                    <option value="<?= escape($value) ?>" <?= $filters['sort'] === $value ? 'selected' : '' ?>><?= escape($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-actions">
            <button class="btn btn-primary" type="submit">Apply filters</button>
            <a class="btn btn-outline-secondary" href="<?= escape(baseUrl('store.php')) ?>">Reset</a>
        </div>
    </form>

    <?php if ($filters['warnings'] !== []): ?>
        <div class="catalog-notice" role="status">
            <?php foreach ($filters['warnings'] as $warning): ?>
                <p><?= escape($warning) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($catalogGames === []): ?>
        <?php renderStatePanel('empty', 'No matching games', 'Try a broader title, another genre or a different price range.'); ?>
    <?php else: ?>
        <div class="catalog-grid">
            <?php foreach ($catalogGames as $game): ?>
                <?php renderStorefrontCard($game); ?>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="catalog-pagination" aria-label="Catalog pages">
                <a class="btn btn-outline-secondary<?= $filters['page'] === 1 ? ' disabled' : '' ?>"
                    href="<?= escape(catalogQueryUrl($filters, max(1, $filters['page'] - 1))) ?>"
                    <?= $filters['page'] === 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Previous</a>
                <span>Page <?= escape($filters['page']) ?> of <?= escape($totalPages) ?></span>
                <a class="btn btn-outline-secondary<?= $filters['page'] === $totalPages ? ' disabled' : '' ?>"
                    href="<?= escape(catalogQueryUrl($filters, min($totalPages, $filters['page'] + 1))) ?>"
                    <?= $filters['page'] === $totalPages ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Next</a>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
