<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

requireAdmin();

$rawEditId = $_GET['edit'] ?? null;
$editId = is_string($rawEditId) && ctype_digit($rawEditId) && (int) $rawEditId > 0 ? (int) $rawEditId : null;
$genres = loadAllGenres(db());
$editingGenre = null;
foreach ($genres as $genre) {
    if ((int) $genre['id'] === $editId) {
        $editingGenre = $genre;
        break;
    }
}
if ($rawEditId !== null && $editingGenre === null) {
    renderErrorPage(404, 'Genre not found', 'The requested genre does not exist.');
}

$formState = $_SESSION['admin_genre_form'] ?? $editingGenre ?? ['name' => '', 'slug' => ''];
$errors = $_SESSION['admin_genre_errors'] ?? [];
$formEditId = $_SESSION['admin_genre_form_id'] ?? $editId;
unset($_SESSION['admin_genre_form'], $_SESSION['admin_genre_errors'], $_SESSION['admin_genre_form_id']);
if ($formEditId !== $editId) {
    $formState = $editingGenre ?? ['name' => '', 'slug' => ''];
    $errors = [];
}

$pageTitle = 'Manage genres';
$pageScripts = ['assets/js/admin-media.js'];
require SKADI_ROOT . '/includes/header.php';
?>
<section class="admin-shell" aria-labelledby="genres-heading">
    <p class="eyebrow">Catalog administration</p>
    <h1 id="genres-heading">Genres</h1>
    <nav class="admin-subnav" aria-label="Administration sections">
        <a href="<?= escape(baseUrl('admin/games.php')) ?>">Games</a>
        <a aria-current="page" href="<?= escape(baseUrl('admin/genres.php')) ?>">Genres</a>
        <a href="<?= escape(baseUrl('admin/featured.php')) ?>">Featured</a>
    </nav>
    <div class="admin-two-column">
        <form class="admin-form media-admin-panel" method="post" action="<?= escape(baseUrl('actions/admin/genre-save.php')) ?>" novalidate>
            <?= csrfInput() ?>
            <?php if ($editId !== null): ?><input type="hidden" name="genre_id" value="<?= $editId ?>"><?php endif; ?>
            <h2><?= $editId === null ? 'Add genre' : 'Edit genre' ?></h2>
            <div class="form-field"><label for="genre_name">Name</label>
                <input id="genre_name" name="name" maxlength="80" required value="<?= escape($formState['name']) ?>">
                <?php if (($error = fieldError($errors, 'name')) !== null): ?><span class="field-error"><?= escape($error) ?></span><?php endif; ?></div>
            <div class="form-field"><label for="genre_slug">Slug</label>
                <input id="genre_slug" name="slug" maxlength="80" required value="<?= escape($formState['slug']) ?>">
                <?php if (($error = fieldError($errors, 'slug')) !== null): ?><span class="field-error"><?= escape($error) ?></span><?php endif; ?></div>
            <button class="btn btn-primary" type="submit"><?= $editId === null ? 'Create genre' : 'Save genre' ?></button>
            <?php if ($editId !== null): ?><a class="btn btn-outline-secondary" href="<?= escape(baseUrl('admin/genres.php')) ?>">Cancel</a><?php endif; ?>
        </form>
        <div class="table-responsive">
            <table class="admin-table">
                <thead><tr><th scope="col">Genre</th><th scope="col">Games</th><th scope="col">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($genres as $genre): ?>
                    <tr>
                        <th scope="row"><?= escape($genre['name']) ?><small><?= escape($genre['slug']) ?></small></th>
                        <td><?= (int) $genre['game_count'] ?></td>
                        <td class="admin-actions">
                            <a class="btn btn-sm btn-outline-secondary" href="<?= escape(baseUrl('admin/genres.php?edit=' . (int) $genre['id'])) ?>">Edit</a>
                            <form method="post" action="<?= escape(baseUrl('actions/admin/genre-delete.php')) ?>" data-confirm-form="Delete this unused genre?">
                                <?= csrfInput() ?><input type="hidden" name="genre_id" value="<?= (int) $genre['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit" <?= (int) $genre['game_count'] > 0 ? 'disabled title="Remove this genre from its games first."' : '' ?>>Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <p class="form-note">A genre assigned to any game cannot be deleted. Remove its game assignments first.</p>
</section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
