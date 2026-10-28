<?php

declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';

$filters = parseGuideFilters($_GET);
$result = loadGuideList(db(), $filters);
$games = loadPublishedGamesForGuides(db());
$myGuides = isAuthenticated() && empty(currentUser()['must_change_password'])
    ? loadAuthorGuides(db(), (int) currentUser()['id']) : [];
$pageTitle = 'Player guides';
require SKADI_ROOT . '/includes/header.php';
?>
<section class="guides-page" aria-labelledby="guides-heading">
    <header class="section-heading">
        <div>
            <p class="eyebrow">Community knowledge</p>
            <h1 id="guides-heading">Player guides</h1>
            <p>Walkthroughs, strategies, builds and tips written by Skadi players.</p>
        </div>
        <?php if (isAuthenticated() && empty(currentUser()['must_change_password'])): ?>
            <a class="btn btn-primary" href="<?= escape(baseUrl('guide-edit.php')) ?>">Write a guide</a>
        <?php endif; ?>
    </header>

    <form class="guide-filters" method="get">
        <div class="filter-field">
            <label for="guide-game">Game</label>
            <select class="form-select" id="guide-game" name="game">
                <option value="">All games</option>
                <?php foreach ($games as $game): ?>
                    <option value="<?= (int) $game['id'] ?>" <?= $filters['game_id'] === (int) $game['id'] ? 'selected' : '' ?>>
                        <?= escape($game['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-field">
            <label for="guide-sort">Sort</label>
            <select class="form-select" id="guide-sort" name="sort">
                <option value="newest" <?= $filters['sort'] === 'newest' ? 'selected' : '' ?>>Newest</option>
                <option value="popular" <?= $filters['sort'] === 'popular' ? 'selected' : '' ?>>Popularity</option>
                <option value="helpful" <?= $filters['sort'] === 'helpful' ? 'selected' : '' ?>>Helpful score</option>
            </select>
        </div>
        <button class="btn btn-outline-secondary" type="submit">Apply</button>
    </form>

    <?php if ($myGuides !== []): ?>
        <details class="my-guides-panel">
            <summary>My drafts and guides (<?= count($myGuides) ?>)</summary>
            <ul>
                <?php foreach ($myGuides as $myGuide): ?>
                    <li><a href="<?= escape(baseUrl('guide.php?id=' . (int) $myGuide['id'])) ?>"><?= escape($myGuide['title']) ?></a>
                        <span class="badge text-bg-secondary"><?= escape(ucfirst($myGuide['status'])) ?></span>
                        <small><?= escape($myGuide['game_title']) ?></small></li>
                <?php endforeach; ?>
            </ul>
        </details>
    <?php endif; ?>

    <?php if ($result['items'] === []): ?>
        <?php renderStatePanel('empty', 'No published guides', 'Try another game or write the first guide.'); ?>
    <?php else: ?>
        <div class="guide-grid">
            <?php foreach ($result['items'] as $guide): ?>
                <article class="guide-card">
                    <?php $thumbnailUrl = safeGuideThumbnailUrl($guide['thumbnail_path']); ?>
                    <?php if ($thumbnailUrl !== null): ?>
                        <img src="<?= escape($thumbnailUrl) ?>" alt="" class="guide-thumbnail">
                    <?php endif; ?>
                    <div class="guide-card-body">
                        <p class="eyebrow"><?= escape($guide['game_title']) ?> · <?= escape(guideCategories()[$guide['category']]) ?></p>
                        <h2><a href="<?= escape(baseUrl('guide.php?id=' . (int) $guide['id'])) ?>"><?= escape($guide['title']) ?></a></h2>
                        <p><?= escape($guide['summary']) ?></p>
                        <p class="guide-byline">By <a href="<?= escape(baseUrl('profile.php?id=' . rawurlencode($guide['skadi_id']))) ?>"><?= escape($guide['display_name']) ?></a>
                            <?php if (!empty($guide['is_owner'])): ?><span class="badge text-bg-info">Owner</span><?php endif; ?>
                        </p>
                        <ul class="guide-stats" aria-label="Guide statistics">
                            <li>Score <?= (int) $guide['helpful_score'] ?></li>
                            <li><?= (int) $guide['favorite_count'] ?> favorites</li>
                            <li><?= (int) $guide['comment_count'] ?> comments</li>
                        </ul>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?php if ($result['pages'] > 1): ?>
            <nav class="catalog-pagination" aria-label="Guide pages">
                <?php if ($result['page'] > 1): ?>
                    <a class="btn btn-outline-secondary" href="<?= escape(baseUrl('guides.php?' . http_build_query(['game' => $filters['game_id'], 'sort' => $filters['sort'], 'page' => $result['page'] - 1]))) ?>">Previous</a>
                <?php endif; ?>
                <span>Page <?= (int) $result['page'] ?> of <?= (int) $result['pages'] ?></span>
                <?php if ($result['page'] < $result['pages']): ?>
                    <a class="btn btn-outline-secondary" href="<?= escape(baseUrl('guides.php?' . http_build_query(['game' => $filters['game_id'], 'sort' => $filters['sort'], 'page' => $result['page'] + 1]))) ?>">Next</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
