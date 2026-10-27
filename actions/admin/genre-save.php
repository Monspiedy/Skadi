<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/config/bootstrap.php';

requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    renderErrorPage(405, 'Method not allowed', 'Genres must be saved from the administration form.');
}
requireValidCsrfToken($_POST['csrf_token'] ?? null);

$rawGenreId = $_POST['genre_id'] ?? null;
$genreId = is_string($rawGenreId) && ctype_digit($rawGenreId) && (int) $rawGenreId > 0 ? (int) $rawGenreId : null;
if ($rawGenreId !== null && $genreId === null) {
    renderErrorPage(404, 'Genre not found', 'The submitted genre identifier is invalid.');
}
if ($genreId !== null) {
    $existingGenre = db()->prepare('SELECT id FROM genres WHERE id = :id LIMIT 1');
    $existingGenre->execute(['id' => $genreId]);
    if ($existingGenre->fetchColumn() === false) {
        renderErrorPage(404, 'Genre not found', 'The submitted genre no longer exists.');
    }
}

$input = normalizeGenreInput($_POST);
$errors = genreInputErrors(db(), $input, $genreId);
if ($errors !== []) {
    $_SESSION['admin_genre_form'] = $input;
    $_SESSION['admin_genre_errors'] = $errors;
    $_SESSION['admin_genre_form_id'] = $genreId;
    redirectTo('admin/genres.php' . ($genreId === null ? '' : '?edit=' . $genreId));
}

try {
    saveGenre(db(), $input, $genreId);
} catch (PDOException $exception) {
    if ($exception->getCode() === '23000') {
        $_SESSION['admin_genre_form'] = $input;
        $_SESSION['admin_genre_errors'] = [
            'name' => 'A genre with this name or slug already exists.',
            'slug' => 'A genre with this name or slug already exists.',
        ];
        $_SESSION['admin_genre_form_id'] = $genreId;
        redirectTo('admin/genres.php' . ($genreId === null ? '' : '?edit=' . $genreId));
    }
    throw $exception;
}

addFlashMessage('success', $genreId === null ? 'Genre created.' : 'Genre updated.');
redirectTo('admin/genres.php');
