<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    renderErrorPage(405, 'Method not allowed', 'Library access must be removed from the confirmation form.');
}
requireValidCsrfToken($_POST['csrf_token'] ?? null);

$rawGameId = $_POST['game_id'] ?? '';
if (!is_string($rawGameId) || !ctype_digit($rawGameId) || (int) $rawGameId < 1) {
    renderErrorPage(404, 'Library item not found', 'The submitted game identifier is invalid.');
}
if (($_POST['confirm_remove'] ?? null) !== 'yes') {
    addFlashMessage('warning', 'Confirm that you understand permanent removal does not provide a refund.');
    redirectTo('library.php?visibility=all');
}

$removed = removeLibraryAccess(db(), (int) currentUser()['id'], (int) $rawGameId);
addFlashMessage(
    $removed ? 'success' : 'warning',
    $removed
        ? 'Game access was removed. No refund was issued, and order history was preserved.'
        : 'That active game was not found in your library.'
);
redirectTo('library.php?visibility=all');
