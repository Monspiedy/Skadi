<?php

declare(strict_types=1);
require __DIR__ . '/config/bootstrap.php';
$filters = parseDiscussionFilters($_GET);
$result = loadDiscussionTopics(db(), $filters);
$games = loadCommunityGames(db());
$categories = discussionCategories(db());
$pageTitle = 'Discussions';
require SKADI_ROOT . '/includes/header.php';
?>
<section class="community-page"><header class="section-heading"><div><p class="eyebrow">Community</p><h1>Discussions</h1></div><?php if (isAuthenticated() && empty(currentUser()['must_change_password'])): ?><a class="btn btn-primary" href="<?= escape(baseUrl('topic-edit.php' . ($filters['game_id'] ? '?game=' . $filters['game_id'] : ''))) ?>">Start topic</a><?php endif; ?></header>
<form class="discussion-filters" method="get"><label>Search<input class="form-control" type="search" name="search" value="<?= escape($filters['search']) ?>" maxlength="100"></label><label>Game<select class="form-select" name="game"><option value="">All games</option><?php foreach ($games as $game): ?><option value="<?= (int) $game['id'] ?>" <?= $filters['game_id'] === (int) $game['id'] ? 'selected' : '' ?>><?= escape($game['title']) ?></option><?php endforeach; ?></select></label><label>Category<select class="form-select" name="category"><option value="">All categories</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= $filters['category_id'] === (int) $category['id'] ? 'selected' : '' ?>><?= escape($category['name']) ?></option><?php endforeach; ?></select></label><button class="btn btn-primary" type="submit">Apply</button></form>
<div class="discussion-list"><?php if ($result['items'] === []): ?><?php renderStatePanel('empty', 'No discussions found', 'Try another search or start a topic.'); ?><?php endif; ?><?php foreach ($result['items'] as $topic): ?><article class="discussion-card"><div><p class="eyebrow"><?= escape($topic['game_title']) ?> · <?= escape($topic['category_name']) ?></p><h2><a href="<?= escape(baseUrl('discussion.php?id=' . (int) $topic['id'])) ?>"><?= escape($topic['title']) ?></a></h2><small>By <?= escape($topic['display_name']) ?> · <?= (int) $topic['reply_count'] ?> replies</small></div><div><?php if ($topic['is_pinned']): ?><span class="badge text-bg-info">Pinned</span><?php endif; ?> <?php if ($topic['is_locked']): ?><span class="badge text-bg-secondary">Locked</span><?php endif; ?></div></article><?php endforeach; ?></div>
<nav class="catalog-pagination" aria-label="Discussion pages"><?php if ($result['page'] > 1): ?><a class="btn btn-outline-secondary" href="?<?= escape(http_build_query(array_merge($filters, ['page' => $result['page'] - 1]))) ?>">Previous</a><?php endif; ?><span>Page <?= $result['page'] ?> of <?= $result['pages'] ?></span><?php if ($result['page'] < $result['pages']): ?><a class="btn btn-outline-secondary" href="?<?= escape(http_build_query(array_merge($filters, ['page' => $result['page'] + 1]))) ?>">Next</a><?php endif; ?></nav></section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
