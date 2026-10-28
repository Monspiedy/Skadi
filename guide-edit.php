<?php

declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';
requireAuth();
$userId = (int) currentUser()['id'];
$guideId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
$guide = $guideId !== null ? loadGuide(db(), $guideId, $userId) : null;
if ($guideId !== null && ($guide === null || (int) $guide['author_user_id'] !== $userId || in_array($guide['status'], ['hidden', 'deleted'], true))) {
    http_response_code(404);
    $pageTitle = 'Guide not found';
    require SKADI_ROOT . '/includes/header.php';
    renderStatePanel('error', 'Guide not found', 'You cannot edit this guide.');
    require SKADI_ROOT . '/includes/footer.php';
    exit;
}
$games = loadPublishedGamesForGuides(db());
$pageTitle = $guide ? 'Edit guide' : 'Write a guide';
$pageScripts = ['assets/js/guide-editor.js'];
require SKADI_ROOT . '/includes/header.php';
?>
<section class="guide-editor-page" data-guide-editor>
    <header><p class="eyebrow">Player guides</p><h1><?= $guide ? 'Edit guide' : 'Write a guide' ?></h1></header>
    <form class="guide-editor-form" method="post" enctype="multipart/form-data" action="<?= escape(baseUrl('actions/guide-save.php')) ?>">
        <?= csrfInput() ?>
        <?php if ($guide): ?><input type="hidden" name="guide_id" value="<?= (int) $guide['id'] ?>"><?php endif; ?>
        <div class="guide-editor-fields">
            <label for="guide-game">Game</label>
            <select class="form-select" id="guide-game" name="game_id" required>
                <?php foreach ($games as $game): ?><option value="<?= (int) $game['id'] ?>" <?= (int) ($guide['game_id'] ?? ($_GET['game'] ?? 0)) === (int) $game['id'] ? 'selected' : '' ?>><?= escape($game['title']) ?></option><?php endforeach; ?>
            </select>
            <label for="guide-title">Title</label><input class="form-control" id="guide-title" name="title" maxlength="180" required value="<?= escape($guide['title'] ?? '') ?>">
            <label for="guide-summary">Summary</label><textarea class="form-control" id="guide-summary" name="summary" rows="3" maxlength="500" required><?= escape($guide['summary'] ?? '') ?></textarea>
            <label for="guide-category">Category</label>
            <select class="form-select" id="guide-category" name="category"><?php foreach (guideCategories() as $value => $label): ?><option value="<?= escape($value) ?>" <?= ($guide['category'] ?? 'general') === $value ? 'selected' : '' ?>><?= escape($label) ?></option><?php endforeach; ?></select>
            <label for="guide-thumbnail">Thumbnail <span class="form-note">JPG, PNG or WebP; optional</span></label><input class="form-control" type="file" id="guide-thumbnail" name="thumbnail" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
            <div class="format-toolbar" role="toolbar" aria-label="Guide formatting">
                <button type="button" data-format-open="[h2]" data-format-close="[/h2]">Heading</button>
                <button type="button" data-format-open="[b]" data-format-close="[/b]">Bold</button>
                <button type="button" data-format-open="[i]" data-format-close="[/i]">Italic</button>
                <button type="button" data-format-open="[list]&#10;[*]" data-format-close="&#10;[/list]">List</button>
                <button type="button" data-format-open="[url=https://example.com]" data-format-close="[/url]">Link</button>
                <button type="button" data-format-open="[code]" data-format-close="[/code]">Code</button>
            </div>
            <p class="form-note">Allowed BBCode: [h1]-[h3], [b], [i], [list] with [*], [url=https://…], and [code]. Raw HTML is displayed as text.</p>
            <label for="guide-body">Guide body</label><textarea class="form-control" id="guide-body" name="body" rows="18" maxlength="50000" required data-guide-source><?= escape($guide['body_source'] ?? '') ?></textarea>
            <fieldset><legend>Save as</legend><label><input type="radio" name="status" value="draft" <?= ($guide['status'] ?? 'draft') === 'draft' ? 'checked' : '' ?>> Draft</label> <label><input type="radio" name="status" value="published" <?= ($guide['status'] ?? '') === 'published' ? 'checked' : '' ?>> Published</label></fieldset>
            <button class="btn btn-primary" type="submit">Save guide</button>
        </div>
        <aside class="guide-preview-panel"><h2>Safe preview</h2><div class="guide-body" data-guide-preview></div></aside>
    </form>
</section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
