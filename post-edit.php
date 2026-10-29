<?php

declare(strict_types=1);
require __DIR__ . '/config/bootstrap.php';
requireAuth();
$games = loadCommunityGames(db());
$selectedGame = communityPositiveInt($_GET['game'] ?? null);
$pageTitle = 'Create community post';
require SKADI_ROOT . '/includes/header.php';
?>
<section class="community-page"><p class="eyebrow">Public community</p><h1>Create a post</h1><form class="community-editor" method="post" enctype="multipart/form-data" action="<?= escape(baseUrl('actions/post-save.php')) ?>"><?= csrfInput() ?><label>Game<select class="form-select" name="game_id" required><?php foreach ($games as $game): ?><option value="<?= (int) $game['id'] ?>" <?= $selectedGame === (int) $game['id'] ? 'selected' : '' ?>><?= escape($game['title']) ?></option><?php endforeach; ?></select></label><label>Text<textarea class="form-control" name="body" maxlength="5000" rows="7"></textarea></label><label>Optional image<input class="form-control" type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"></label><p class="form-note">JPG, PNG or WebP; maximum 5 MB. Add text, an image, or both.</p><button class="btn btn-primary" type="submit">Publish post</button></form></section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
