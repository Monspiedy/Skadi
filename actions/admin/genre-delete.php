<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/config/bootstrap.php';

requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    renderErrorPage(405, 'Method not allowed', 'Genres must be deleted from the administration page.');
}
requireValidCsrfToken($_POST['csrf_token'] ?? null);

$rawGenreId = $_POST['genre_id'] ?? '';
if (!is_string($rawGenreId) || !ctype_digit($rawGenreId) || (int) $rawGenreId < 1) {
    renderErrorPage(404, 'Genre not found', 'The submitted genre identifier is invalid.');
}
$result = deleteGenre(db(), (int) $rawGenreId);
if (!$result['deleted'] && $result['game_count'] > 0) {
    addFlashMessage('error', 'Genre not deleted because it is assigned to ' . $result['game_count'] . ' game(s).');
} elseif (!$result['deleted']) {
    addFlashMessage('warning', 'The genre no longer exists.');
} else {
    addFlashMessage('success', 'Genre deleted.');
}
redirectTo('admin/genres.php');
