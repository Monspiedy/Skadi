<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/config/bootstrap.php';

requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    renderErrorPage(405, 'Method not allowed', 'Game media must be uploaded from the administration form.');
}
requireValidCsrfToken($_POST['csrf_token'] ?? null);

$rawGameId = $_POST['game_id'] ?? '';
if (!is_string($rawGameId) || !ctype_digit($rawGameId) || (int) $rawGameId < 1) {
    renderErrorPage(404, 'Game not found', 'The submitted game identifier is invalid.');
}
$gameId = (int) $rawGameId;
$game = loadAdminGame(db(), $gameId);
if ($game === null) {
    renderErrorPage(404, 'Game not found', 'The submitted game no longer exists.');
}

$mediaType = is_string($_POST['media_type'] ?? null) ? $_POST['media_type'] : '';
$altText = trim((string) ($_POST['alt_text'] ?? ''));

if ($mediaType === 'cover') {
    $uploads = [$_FILES['media_file'] ?? []];
    $context = 'game_cover';
} elseif ($mediaType === 'screenshot') {
    $uploads = normalizeMultipleUploads($_FILES['media_files'] ?? []);
    $context = 'game_screenshot';
    if (count($uploads) > MAX_GAME_SCREENSHOTS - count($game['screenshots'])) {
        addFlashMessage('error', 'A game can have at most ' . MAX_GAME_SCREENSHOTS . ' screenshots.');
        redirectTo('admin/game-form.php?id=' . $gameId);
    }
} else {
    renderErrorPage(422, 'Invalid media type', 'Only game covers and screenshots can be uploaded here.');
}

$altTextError = imageAltTextError($altText, count($uploads));
if ($altTextError !== null) {
    addFlashMessage('error', $altTextError);
    redirectTo('admin/game-form.php?id=' . $gameId);
}

$validatedUploads = [];
foreach ($uploads as $index => $upload) {
    $validation = validateUploadedImage($upload, $context);
    if (!$validation['valid']) {
        $label = $mediaType === 'screenshot' ? 'Screenshot ' . ($index + 1) . ': ' : '';
        addFlashMessage('error', $label . $validation['error']);
        redirectTo('admin/game-form.php?id=' . $gameId);
    }
    $validatedUploads[] = $validation;
}

$storedPaths = [];
try {
    foreach ($validatedUploads as $validatedUpload) {
        $storedPaths[] = storeValidatedImage($validatedUpload);
    }
} catch (Throwable $exception) {
    foreach ($storedPaths as $storedPath) {
        deleteManagedGameUpload($storedPath);
    }
    throw $exception;
}

$connection = db();
$oldCoverPath = null;
$connection->beginTransaction();
try {
    if ($mediaType === 'cover') {
        $oldCover = $connection->prepare(
            "SELECT storage_path FROM game_media
             WHERE game_id = :game_id AND media_type = 'cover' AND sort_order = 0 FOR UPDATE"
        );
        $oldCover->execute(['game_id' => $gameId]);
        $oldCoverPath = $oldCover->fetchColumn();
        $deleteCover = $connection->prepare(
            "DELETE FROM game_media WHERE game_id = :game_id AND media_type = 'cover' AND sort_order = 0"
        );
        $deleteCover->execute(['game_id' => $gameId]);
        $insert = $connection->prepare(
            "INSERT INTO game_media (game_id, media_type, storage_path, alt_text, sort_order)
             VALUES (:game_id, 'cover', :storage_path, :alt_text, 0)"
        );
        $insert->execute(['game_id' => $gameId, 'storage_path' => $storedPaths[0], 'alt_text' => $altText]);
    } else {
        $lock = $connection->prepare(
            "SELECT id FROM game_media WHERE game_id = :game_id AND media_type = 'screenshot' FOR UPDATE"
        );
        $lock->execute(['game_id' => $gameId]);
        $currentCount = count($lock->fetchAll());
        if ($currentCount + count($storedPaths) > MAX_GAME_SCREENSHOTS) {
            throw new RuntimeException('The screenshot limit was reached by another request.');
        }
        $orderStatement = $connection->prepare(
            "SELECT COALESCE(MAX(sort_order), 0) FROM game_media
             WHERE game_id = :game_id AND media_type = 'screenshot'"
        );
        $orderStatement->execute(['game_id' => $gameId]);
        $nextOrder = (int) $orderStatement->fetchColumn() + 1;
        $insert = $connection->prepare(
            "INSERT INTO game_media (game_id, media_type, storage_path, alt_text, sort_order)
             VALUES (:game_id, 'screenshot', :storage_path, :alt_text, :sort_order)"
        );
        foreach ($storedPaths as $index => $storedPath) {
            $imageAlt = count($storedPaths) === 1 ? $altText : $altText . ' ' . ($index + 1);
            $insert->execute([
                'game_id' => $gameId,
                'storage_path' => $storedPath,
                'alt_text' => $imageAlt,
                'sort_order' => $nextOrder + $index,
            ]);
        }
    }
    recordAdminAudit(
        $connection,
        (int) currentUser()['id'],
        'media.uploaded',
        'game',
        $gameId,
        ($mediaType === 'cover' ? 'Uploaded cover.' : 'Uploaded ' . count($storedPaths) . ' screenshot(s).')
    );
    $connection->commit();
} catch (Throwable $exception) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
    foreach ($storedPaths as $storedPath) {
        deleteManagedGameUpload($storedPath);
    }
    throw $exception;
}

if (is_string($oldCoverPath) && isManagedGameUploadPath($oldCoverPath) && !deleteManagedGameUpload($oldCoverPath)) {
    addFlashMessage('warning', 'The new cover is active, but the replaced file could not be removed. Check file permissions.');
}
addFlashMessage('success', $mediaType === 'cover' ? 'Cover uploaded.' : count($storedPaths) . ' screenshot(s) uploaded.');
redirectTo('admin/game-form.php?id=' . $gameId);
