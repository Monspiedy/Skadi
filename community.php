<?php

declare(strict_types=1);
require __DIR__ . '/config/bootstrap.php';
$gameId = communityPositiveInt($_GET['game'] ?? null);
$requestedPage = communityPositiveInt($_GET['page'] ?? 1) ?? 1;
$games = loadCommunityGames(db());
$center = loadCommunityCenter(db(), $gameId, $requestedPage);
$page = (int) $center['page'];
$pageTitle = 'Community Center';
require SKADI_ROOT . '/includes/header.php';
?>
<section class="community-center" aria-labelledby="community-heading">
    <header class="section-heading"><div><p class="eyebrow">Public community</p><h1 id="community-heading">Community Center</h1>
        <p>Discover public discussions, guides, screenshots and player posts. Private chats never appear here.</p></div>
        <?php if (isAuthenticated() && empty(currentUser()['must_change_password'])): ?><div class="community-actions"><a class="btn btn-primary" href="<?= escape(baseUrl('topic-edit.php')) ?>">Start discussion</a><a class="btn btn-outline-secondary" href="<?= escape(baseUrl('post-edit.php')) ?>">Create post</a></div><?php endif; ?>
    </header>
    <form class="community-filter" method="get"><label for="community-game">Game</label><select class="form-select" id="community-game" name="game"><option value="">All games</option><?php foreach ($games as $game): ?><option value="<?= (int) $game['id'] ?>" <?= $gameId === (int) $game['id'] ? 'selected' : '' ?>><?= escape($game['title']) ?></option><?php endforeach; ?></select><button class="btn btn-primary" type="submit">Filter</button></form>
    <div class="community-dashboard">
        <section class="community-panel"><div class="panel-heading"><h2>Popular discussions</h2><a href="<?= escape(baseUrl('discussions.php' . ($gameId ? '?game=' . $gameId : ''))) ?>">View all</a></div><?php if ($center['topics'] === []): ?><p>No active discussions.</p><?php endif; ?><?php foreach ($center['topics'] as $topic): ?><article><p class="eyebrow"><?= escape($topic['game_title']) ?> · <?= escape($topic['category_name']) ?></p><h3><a href="<?= escape(baseUrl('discussion.php?id=' . (int) $topic['id'])) ?>"><?= escape($topic['title']) ?></a></h3><small><?= (int) $topic['reply_count'] ?> replies<?= !empty($topic['is_locked']) ? ' · Locked' : '' ?></small></article><?php endforeach; ?></section>
        <section class="community-panel"><div class="panel-heading"><h2>Popular guides</h2><a href="<?= escape(baseUrl('guides.php' . ($gameId ? '?game=' . $gameId : ''))) ?>">View all</a></div><?php if ($center['guides']['items'] === []): ?><p>No published guides.</p><?php endif; ?><?php foreach ($center['guides']['items'] as $guide): ?><article><p class="eyebrow"><?= escape($guide['game_title']) ?></p><h3><a href="<?= escape(baseUrl('guide.php?id=' . (int) $guide['id'])) ?>"><?= escape($guide['title']) ?></a></h3><small>Score <?= (int) $guide['helpful_score'] ?> · <?= (int) $guide['comment_count'] ?> comments</small></article><?php endforeach; ?></section>
        <section class="community-panel"><div class="panel-heading"><h2>New screenshots</h2></div><div class="community-media-grid"><?php if ($center['screenshots'] === []): ?><p>No screenshots.</p><?php endif; ?><?php foreach ($center['screenshots'] as $shot): ?><?php $shotUrl = safeGameMediaUrl($shot['storage_path']); ?><?php if ($shotUrl !== null): ?><a href="<?= escape(baseUrl('game-community.php?id=' . (int) $shot['game_id'] . '&tab=screenshots')) ?>"><img src="<?= escape($shotUrl) ?>" alt="<?= escape($shot['alt_text']) ?>"><span><?= escape($shot['game_title']) ?></span></a><?php endif; ?><?php endforeach; ?></div></section>
        <section class="community-panel"><div class="panel-heading"><h2>New community posts</h2><a href="<?= escape(baseUrl('posts.php' . ($gameId ? '?game=' . $gameId : ''))) ?>">View all</a></div><?php if ($center['posts'] === []): ?><p>No community posts.</p><?php endif; ?><?php foreach ($center['posts'] as $post): ?><article><p class="eyebrow"><?= escape($post['game_title']) ?></p><p><?= nl2br(escape(mb_strimwidth($post['body'], 0, 180, '…'))) ?></p><a href="<?= escape(baseUrl('community-post.php?id=' . (int) $post['id'])) ?>"><?= (int) $post['like_count'] ?> likes · <?= (int) $post['comment_count'] ?> comments</a></article><?php endforeach; ?></section>
    </div>
    <nav class="catalog-pagination" aria-label="Community pages"><?php if ($page > 1): ?><a class="btn btn-outline-secondary" href="?<?= escape(http_build_query(['game' => $gameId, 'page' => $page - 1])) ?>">Previous</a><?php endif; ?><span>Page <?= $page ?> of <?= $center['pages'] ?></span><?php if ($page < $center['pages']): ?><a class="btn btn-outline-secondary" href="?<?= escape(http_build_query(['game' => $gameId, 'page' => $page + 1])) ?>">Next</a><?php endif; ?></nav>
</section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
