<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    renderErrorPage(405, 'Method not allowed', 'Library visibility must be changed from the library page.');
}
requireValidCsrfToken($_POST['csrf_token'] ?? null);

$rawGameId = $_POST['game_id'] ?? '';
$visibility = $_POST['visibility'] ?? '';
if (!is_string($rawGameId) || !ctype_digit($rawGameId) || (int) $rawGameId < 1
    || !is_string($visibility) || !in_array($visibility, ['hidden', 'visible'], true)) {
    renderErrorPage(404, 'Library item not found', 'The submitted library change is invalid.');
}

$changed = setLibraryItemHidden(db(), (int) currentUser()['id'], (int) $rawGameId, $visibility === 'hidden');
addFlashMessage(
    $changed ? 'success' : 'warning',
    $changed ? 'Library visibility updated.' : 'That active game was not found in your library.'
);
redirectTo('library.php?visibility=all');
