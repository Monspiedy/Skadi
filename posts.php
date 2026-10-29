<?php

declare(strict_types=1);
require __DIR__ . '/config/bootstrap.php';
$gameId = communityPositiveInt($_GET['game'] ?? null);
$page = communityPositiveInt($_GET['page'] ?? 1) ?? 1;
$viewerId = isAuthenticated() ? (int) currentUser()['id'] : null;
$posts = loadCommunityPosts(db(), $gameId, $page, $viewerId);
$games = loadCommunityGames(db());
$pageTitle = 'Community posts';
require SKADI_ROOT . '/includes/header.php';
?>
<section class="community-page"><header class="section-heading"><div><p class="eyebrow">Community</p><h1>Community posts</h1></div><?php if ($viewerId): ?><a class="btn btn-primary" href="<?= escape(baseUrl('post-edit.php' . ($gameId ? '?game=' . $gameId : ''))) ?>">Create post</a><?php endif; ?></header><form class="community-filter" method="get"><label>Game<select class="form-select" name="game"><option value="">All games</option><?php foreach ($games as $game): ?><option value="<?= (int) $game['id'] ?>" <?= $gameId === (int) $game['id'] ? 'selected' : '' ?>><?= escape($game['title']) ?></option><?php endforeach; ?></select></label><button class="btn btn-primary" type="submit">Filter</button></form><div class="post-grid"><?php if ($posts['items'] === []): ?><?php renderStatePanel('empty', 'No posts', 'Create the first public community post.'); ?><?php endif; ?><?php foreach ($posts['items'] as $post): ?><article class="post-card"><p class="eyebrow"><?= escape($post['game_title']) ?></p><?php $imageUrl = safeCommunityImageUrl($post['image_path']); ?><?php if ($imageUrl): ?><img src="<?= escape($imageUrl) ?>" alt="Community image by <?= escape($post['display_name']) ?>"><?php endif; ?><p><?= nl2br(escape($post['body'])) ?></p><p><a href="<?= escape(baseUrl('community-post.php?id=' . (int) $post['id'])) ?>"><?= (int) $post['like_count'] ?> likes · <?= (int) $post['comment_count'] ?> comments</a></p></article><?php endforeach; ?></div><nav class="catalog-pagination"><?php if ($posts['page'] > 1): ?><a href="?<?= escape(http_build_query(['game' => $gameId, 'page' => $posts['page'] - 1])) ?>">Previous</a><?php endif; ?><span>Page <?= $posts['page'] ?> of <?= $posts['pages'] ?></span><?php if ($posts['page'] < $posts['pages']): ?><a href="?<?= escape(http_build_query(['game' => $gameId, 'page' => $posts['page'] + 1])) ?>">Next</a><?php endif; ?></nav></section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
