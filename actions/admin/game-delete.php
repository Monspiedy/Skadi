<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/config/bootstrap.php';

requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    renderErrorPage(405, 'Method not allowed', 'Games must be deleted from the administration page.');
}
requireValidCsrfToken($_POST['csrf_token'] ?? null);

$rawGameId = $_POST['game_id'] ?? '';
if (!is_string($rawGameId) || !ctype_digit($rawGameId) || (int) $rawGameId < 1) {
    renderErrorPage(404, 'Game not found', 'The submitted game identifier is invalid.');
}
$gameId = (int) $rawGameId;
$game = loadAdminGame(db(), $gameId);
if ($game === null) {
    addFlashMessage('warning', 'The game no longer exists.');
    redirectTo('admin/games.php');
}

$connection = db();
$connection->beginTransaction();
try {
    $result = deleteAdminGame($connection, $gameId);
    if (!$result['deleted']) {
        $connection->rollBack();
        $details = [];
        foreach ($result['dependencies'] as $label => $count) {
            $details[] = $count . ' ' . $label;
        }
        addFlashMessage('error', 'Game not deleted. Remove or retain these references safely first: ' . implode(', ', $details) . '.');
        redirectTo('admin/games.php');
    }
    recordAdminAudit($connection, (int) currentUser()['id'], 'game.deleted', 'game', $gameId, 'Deleted game: ' . $game['title']);
    $connection->commit();
} catch (Throwable $exception) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
    throw $exception;
}

$cleanupFailed = false;
foreach ($result['media_paths'] as $mediaPath) {
    if (is_string($mediaPath) && isManagedGameUploadPath($mediaPath) && !deleteManagedGameUpload($mediaPath)) {
        $cleanupFailed = true;
    }
}
addFlashMessage('success', 'Game “' . $game['title'] . '” deleted.');
if ($cleanupFailed) {
    addFlashMessage('warning', 'The database was updated, but one uploaded image could not be removed. Check file permissions.');
}
redirectTo('admin/games.php');
