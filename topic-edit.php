<?php

declare(strict_types=1);
require __DIR__ . '/config/bootstrap.php';
requireAuth();
$topicId = communityPositiveInt($_GET['id'] ?? null);
$topic = null;
if ($topicId !== null) {
    $statement = db()->prepare("SELECT * FROM discussion_topics WHERE id = :id AND author_user_id = :user AND status = 'active'");
    $statement->execute(['id' => $topicId, 'user' => (int) currentUser()['id']]);
    $topic = $statement->fetch() ?: null;
    if ($topic === null) { renderErrorPage(404, 'Topic not found', 'This topic cannot be edited.'); }
}
$games = loadCommunityGames(db());
$categories = discussionCategories(db());
$selectedGame = $topic['game_id'] ?? communityPositiveInt($_GET['game'] ?? null);
$pageTitle = $topic ? 'Edit discussion' : 'Start discussion';
require SKADI_ROOT . '/includes/header.php';
?>
<section class="community-page"><p class="eyebrow">Public discussion</p><h1><?= escape($pageTitle) ?></h1><form class="community-editor" method="post" action="<?= escape(baseUrl('actions/topic-save.php')) ?>"><?= csrfInput() ?><?php if ($topic): ?><input type="hidden" name="topic_id" value="<?= (int) $topic['id'] ?>"><?php endif; ?><label>Game<select class="form-select" name="game_id" required><?php foreach ($games as $game): ?><option value="<?= (int) $game['id'] ?>" <?= (int) $selectedGame === (int) $game['id'] ? 'selected' : '' ?>><?= escape($game['title']) ?></option><?php endforeach; ?></select></label><label>Category<select class="form-select" name="category_id" required><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= (int) ($topic['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>><?= escape($category['name']) ?></option><?php endforeach; ?></select></label><label>Title<input class="form-control" name="title" minlength="3" maxlength="180" required value="<?= escape($topic['title'] ?? '') ?>"></label><label>Message<textarea class="form-control" name="body" minlength="5" maxlength="30000" rows="10" required><?= escape($topic['body'] ?? '') ?></textarea></label><p class="form-note">Posts are plain text and safely escaped. Pinning and locking are administrator controls.</p><button class="btn btn-primary" type="submit">Save topic</button></form></section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
