<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

requireAdmin();

$rawGameId = $_GET['id'] ?? null;
$gameId = is_string($rawGameId) && ctype_digit($rawGameId) && (int) $rawGameId > 0 ? (int) $rawGameId : null;
if ($rawGameId !== null && $gameId === null) {
    renderErrorPage(404, 'Game not found', 'The requested game identifier is invalid.');
}

$databaseGame = $gameId === null ? null : loadAdminGame(db(), $gameId);
if ($gameId !== null && $databaseGame === null) {
    renderErrorPage(404, 'Game not found', 'The requested game does not exist.');
}

$formState = $_SESSION['admin_game_form'] ?? null;
$errors = $_SESSION['admin_game_errors'] ?? [];
$formGameId = $_SESSION['admin_game_form_id'] ?? null;
unset($_SESSION['admin_game_form'], $_SESSION['admin_game_errors'], $_SESSION['admin_game_form_id']);
if (!is_array($formState) || $formGameId !== $gameId) {
    $formState = $databaseGame ?? [
        'title' => '', 'slug' => '', 'short_description' => '', 'description' => '',
        'developer' => '', 'publisher' => '', 'release_date' => '', 'base_price' => '0.00',
        'system_requirements' => '', 'status' => 'draft', 'genre_ids' => [],
    ];
}
$errors = is_array($errors) && $formGameId === $gameId ? $errors : [];
$genres = loadAllGenres(db());
$selectedGenreIds = array_map('intval', $formState['genre_ids'] ?? []);
$pageTitle = $gameId === null ? 'Add game' : 'Edit ' . $databaseGame['title'];
$pageScripts = ['assets/js/admin-media.js'];
require SKADI_ROOT . '/includes/header.php';
?>
<section class="admin-shell" aria-labelledby="game-form-heading">
    <p class="eyebrow">Catalog administration</p>
    <h1 id="game-form-heading"><?= $gameId === null ? 'Add game' : 'Edit game' ?></h1>
    <?php renderAdminNav('games'); ?>
    <p><a href="<?= escape(baseUrl('admin/games.php')) ?>">← Back to games</a></p>

    <form class="admin-form" method="post" action="<?= escape(baseUrl('actions/admin/game-save.php')) ?>" novalidate>
        <?= csrfInput() ?>
        <?php if ($gameId !== null): ?>
            <input type="hidden" name="game_id" value="<?= $gameId ?>">
        <?php endif; ?>
        <div class="admin-form-grid">
            <div class="form-field">
                <label for="title">Title</label>
                <input id="title" name="title" maxlength="160" required value="<?= escape($formState['title']) ?>">
                <?php if (($error = fieldError($errors, 'title')) !== null): ?><span class="field-error"><?= escape($error) ?></span><?php endif; ?>
            </div>
            <div class="form-field">
                <label for="slug">Slug</label>
                <input id="slug" name="slug" maxlength="160" required value="<?= escape($formState['slug']) ?>">
                <?php if (($error = fieldError($errors, 'slug')) !== null): ?><span class="field-error"><?= escape($error) ?></span><?php endif; ?>
            </div>
            <div class="form-field">
                <label for="developer">Developer</label>
                <input id="developer" name="developer" maxlength="160" required value="<?= escape($formState['developer']) ?>">
                <?php if (($error = fieldError($errors, 'developer')) !== null): ?><span class="field-error"><?= escape($error) ?></span><?php endif; ?>
            </div>
            <div class="form-field">
                <label for="publisher">Publisher</label>
                <input id="publisher" name="publisher" maxlength="160" required value="<?= escape($formState['publisher']) ?>">
                <?php if (($error = fieldError($errors, 'publisher')) !== null): ?><span class="field-error"><?= escape($error) ?></span><?php endif; ?>
            </div>
            <div class="form-field">
                <label for="release_date">Release date</label>
                <input id="release_date" name="release_date" type="date" required value="<?= escape($formState['release_date']) ?>">
                <?php if (($error = fieldError($errors, 'release_date')) !== null): ?><span class="field-error"><?= escape($error) ?></span><?php endif; ?>
            </div>
            <div class="form-field">
                <label for="base_price">Base price (EUR)</label>
                <input id="base_price" name="base_price" inputmode="decimal" required value="<?= escape($formState['base_price']) ?>">
                <?php if (($error = fieldError($errors, 'base_price')) !== null): ?><span class="field-error"><?= escape($error) ?></span><?php endif; ?>
            </div>
            <div class="form-field">
                <label for="status">Status</label>
                <select id="status" name="status" required>
                    <?php foreach (['draft' => 'Draft', 'published' => 'Published', 'hidden' => 'Hidden'] as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $formState['status'] === $value ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (($error = fieldError($errors, 'status')) !== null): ?><span class="field-error"><?= escape($error) ?></span><?php endif; ?>
            </div>
        </div>
        <div class="form-field">
            <label for="short_description">Short description</label>
            <textarea id="short_description" name="short_description" maxlength="500" rows="3" required><?= escape($formState['short_description']) ?></textarea>
            <?php if (($error = fieldError($errors, 'short_description')) !== null): ?><span class="field-error"><?= escape($error) ?></span><?php endif; ?>
        </div>
        <div class="form-field">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="8" required><?= escape($formState['description']) ?></textarea>
            <?php if (($error = fieldError($errors, 'description')) !== null): ?><span class="field-error"><?= escape($error) ?></span><?php endif; ?>
        </div>
        <div class="form-field">
            <label for="system_requirements">System requirements</label>
            <textarea id="system_requirements" name="system_requirements" rows="6" required><?= escape($formState['system_requirements']) ?></textarea>
            <?php if (($error = fieldError($errors, 'system_requirements')) !== null): ?><span class="field-error"><?= escape($error) ?></span><?php endif; ?>
        </div>
        <fieldset class="genre-checklist">
            <legend>Genres</legend>
            <?php foreach ($genres as $genre): ?>
                <label><input type="checkbox" name="genre_ids[]" value="<?= (int) $genre['id'] ?>"
                    <?= in_array((int) $genre['id'], $selectedGenreIds, true) ? 'checked' : '' ?>> <?= escape($genre['name']) ?></label>
            <?php endforeach; ?>
            <?php if (($error = fieldError($errors, 'genre_ids')) !== null): ?><span class="field-error"><?= escape($error) ?></span><?php endif; ?>
        </fieldset>
        <button class="btn btn-primary" type="submit"><?= $gameId === null ? 'Create game' : 'Save game' ?></button>
    </form>

    <?php if ($databaseGame !== null): ?>
        <section class="media-manager" aria-labelledby="media-heading">
            <h2 id="media-heading">Game media</h2>
            <p>Files are checked from their content and dimensions, renamed by the server and stored as non-executable images.</p>
            <div class="media-admin-grid">
                <article class="media-admin-panel">
                    <h3>Cover</h3>
                    <?php if ($databaseGame['cover'] !== null && ($coverUrl = safeGameMediaUrl($databaseGame['cover']['storage_path'])) !== null): ?>
                        <img class="media-admin-preview" src="<?= escape($coverUrl) ?>" alt="<?= escape($databaseGame['cover']['alt_text']) ?>">
                        <form method="post" action="<?= escape(baseUrl('actions/admin/media-delete.php')) ?>" data-confirm-form="Delete this cover image?">
                            <?= csrfInput() ?><input type="hidden" name="game_id" value="<?= $gameId ?>">
                            <input type="hidden" name="media_id" value="<?= (int) $databaseGame['cover']['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit">Delete cover</button>
                        </form>
                    <?php else: ?>
                        <p>No cover uploaded.</p>
                    <?php endif; ?>
                    <form class="admin-form" method="post" enctype="multipart/form-data"
                        action="<?= escape(baseUrl('actions/admin/media-upload.php')) ?>">
                        <?= csrfInput() ?><input type="hidden" name="game_id" value="<?= $gameId ?>">
                        <input type="hidden" name="media_type" value="cover">
                        <div class="form-field"><label for="cover_alt">Alternative text</label>
                            <input id="cover_alt" name="alt_text" maxlength="255" required value="<?= escape($databaseGame['cover']['alt_text'] ?? $databaseGame['title'] . ' cover') ?>"></div>
                        <div class="form-field"><label for="cover_file">JPG, PNG or WebP</label>
                            <input id="cover_file" name="media_file" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required data-image-input data-preview-target="cover-preview">
                            <small>Maximum 5 MB; 320×180 to 4000×4000 pixels.</small></div>
                        <img class="media-admin-preview" id="cover-preview" alt="Selected cover preview" hidden>
                        <button class="btn btn-primary" type="submit"><?= $databaseGame['cover'] ? 'Replace cover' : 'Upload cover' ?></button>
                    </form>
                </article>
                <article class="media-admin-panel">
                    <h3>Screenshots (<?= count($databaseGame['screenshots']) ?>/<?= MAX_GAME_SCREENSHOTS ?>)</h3>
                    <div class="screenshot-admin-list">
                        <?php foreach ($databaseGame['screenshots'] as $screenshot): ?>
                            <div>
                                <?php $screenshotUrl = safeGameMediaUrl($screenshot['storage_path']); ?>
                                <?php if ($screenshotUrl !== null): ?><img src="<?= escape($screenshotUrl) ?>" alt="<?= escape($screenshot['alt_text']) ?>"><?php endif; ?>
                                <span>#<?= (int) $screenshot['sort_order'] ?> <?= escape($screenshot['alt_text']) ?></span>
                                <form method="post" action="<?= escape(baseUrl('actions/admin/media-delete.php')) ?>" data-confirm-form="Delete this screenshot?">
                                    <?= csrfInput() ?><input type="hidden" name="game_id" value="<?= $gameId ?>">
                                    <input type="hidden" name="media_id" value="<?= (int) $screenshot['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <form class="admin-form" method="post" enctype="multipart/form-data"
                        action="<?= escape(baseUrl('actions/admin/media-upload.php')) ?>">
                        <?= csrfInput() ?><input type="hidden" name="game_id" value="<?= $gameId ?>">
                        <input type="hidden" name="media_type" value="screenshot">
                        <div class="form-field"><label for="screenshot_alt">Alternative text</label>
                            <input id="screenshot_alt" name="alt_text" maxlength="255" required value="<?= escape($databaseGame['title'] . ' screenshot') ?>">
                            <small>When selecting several images, numbering is added automatically.</small></div>
                        <div class="form-field"><label for="screenshot_files">JPG, PNG or WebP</label>
                            <input id="screenshot_files" name="media_files[]" type="file" multiple accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required data-image-input data-preview-container="screenshot-previews">
                            <small>Maximum 8 MB each; 640×360 to 7680×4320 pixels; 12 screenshots total.</small></div>
                        <div class="image-preview-list" id="screenshot-previews" aria-live="polite"></div>
                        <button class="btn btn-primary" type="submit">Add screenshots</button>
                    </form>
                </article>
            </div>
        </section>
    <?php else: ?>
        <p class="form-note">Save the game before uploading its cover or screenshots.</p>
    <?php endif; ?>
</section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
