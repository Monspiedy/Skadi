<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/config/bootstrap.php';

requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    renderErrorPage(405, 'Method not allowed', 'Game media must be deleted from the administration page.');
}
requireValidCsrfToken($_POST['csrf_token'] ?? null);

$rawGameId = $_POST['game_id'] ?? '';
$rawMediaId = $_POST['media_id'] ?? '';
if (
    !is_string($rawGameId) || !ctype_digit($rawGameId) || (int) $rawGameId < 1
    || !is_string($rawMediaId) || !ctype_digit($rawMediaId) || (int) $rawMediaId < 1
) {
    renderErrorPage(404, 'Media not found', 'The submitted media identifier is invalid.');
}
$gameId = (int) $rawGameId;
$mediaId = (int) $rawMediaId;
$statement = db()->prepare(
    'SELECT id, storage_path, media_type FROM game_media WHERE id = :id AND game_id = :game_id LIMIT 1'
);
$statement->execute(['id' => $mediaId, 'game_id' => $gameId]);
$media = $statement->fetch();
if (!is_array($media)) {
    addFlashMessage('warning', 'The image no longer exists.');
    redirectTo('admin/game-form.php?id=' . $gameId);
}

$delete = db()->prepare('DELETE FROM game_media WHERE id = :id AND game_id = :game_id');
$delete->execute(['id' => $mediaId, 'game_id' => $gameId]);
if (isManagedGameUploadPath($media['storage_path']) && !deleteManagedGameUpload($media['storage_path'])) {
    addFlashMessage('warning', 'The media record was deleted, but the uploaded file could not be removed. Check file permissions.');
} else {
    addFlashMessage('success', ucfirst($media['media_type']) . ' deleted.');
}
redirectTo('admin/game-form.php?id=' . $gameId);
