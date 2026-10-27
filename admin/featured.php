<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

requireAdmin();

$games = loadFeaturedAdminGames(db());
$selected = [];
foreach ($games as $game) {
    if ($game['position'] !== null) {
        $selected[(int) $game['position']] = (int) $game['id'];
    }
}
ksort($selected);
$selected = array_values($selected);
$pageTitle = 'Featured games';
$pageScripts = ['assets/js/admin-media.js'];
require SKADI_ROOT . '/includes/header.php';
?>
<section class="admin-shell" aria-labelledby="featured-heading">
    <p class="eyebrow">Catalog administration</p>
    <h1 id="featured-heading">Featured games</h1>
    <nav class="admin-subnav" aria-label="Administration sections">
        <a href="<?= escape(baseUrl('admin/games.php')) ?>">Games</a>
        <a href="<?= escape(baseUrl('admin/genres.php')) ?>">Genres</a>
        <a aria-current="page" href="<?= escape(baseUrl('admin/featured.php')) ?>">Featured</a>
    </nav>
    <p>Choose up to five published games. Their slot number controls storefront order.</p>
    <form class="admin-form featured-form" method="post" action="<?= escape(baseUrl('actions/admin/featured-save.php')) ?>" data-featured-form>
        <?= csrfInput() ?>
        <?php for ($position = 0; $position < MAX_FEATURED_GAMES; $position++): ?>
            <div class="form-field">
                <label for="featured_<?= $position ?>">Position <?= $position + 1 ?></label>
                <select id="featured_<?= $position ?>" name="game_ids[]">
                    <option value="">Not used</option>
                    <?php foreach ($games as $game): ?>
                        <?php if ($game['status'] === 'published'): ?>
                            <option value="<?= (int) $game['id'] ?>" <?= ($selected[$position] ?? null) === (int) $game['id'] ? 'selected' : '' ?>><?= escape($game['title']) ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endfor; ?>
        <p class="field-error" data-featured-error hidden></p>
        <button class="btn btn-primary" type="submit">Save featured order</button>
    </form>
</section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
