<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';
requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    renderErrorPage(405, 'Method not allowed', 'Guides must be saved from the editor form.');
}
requireValidCsrfToken($_POST['csrf_token'] ?? null);

$userId = (int) currentUser()['id'];
$gameId = filter_var($_POST['game_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$guideId = filter_var($_POST['guide_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
$data = validateGuideInput($_POST);
if ($gameId < 1 || $data['errors'] !== []) {
    addFlashMessage('error', $gameId < 1 ? 'Choose an active game.' : implode(' ', $data['errors']));
    redirectTo('guide-edit.php' . ($guideId ? '?id=' . $guideId : ''));
}

$newThumbnail = null;
$oldThumbnail = null;
$upload = $_FILES['thumbnail'] ?? null;
if (is_array($upload) && (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $validated = validateUploadedImage($upload, 'guide_thumbnail');
    if (empty($validated['valid'])) {
        addFlashMessage('error', $validated['error']);
        redirectTo('guide-edit.php' . ($guideId ? '?id=' . $guideId : ''));
    }
    $newThumbnail = storeValidatedImage($validated);
}

$connection = db();
try {
    $connection->beginTransaction();
    if ($guideId !== null && $newThumbnail !== null) {
        $old = $connection->prepare('SELECT thumbnail_path FROM guides WHERE id = :id AND author_user_id = :user_id FOR UPDATE');
        $old->execute(['id' => $guideId, 'user_id' => $userId]);
        $oldThumbnail = $old->fetchColumn() ?: null;
    }
    $savedId = saveGuide($connection, $userId, $gameId, $guideId, $data, $newThumbnail);
    $connection->commit();
    if (is_string($oldThumbnail)) {
        deleteManagedGuideUpload($oldThumbnail);
    }
    addFlashMessage('success', $data['status'] === 'published' ? 'Guide published.' : 'Draft saved.');
    redirectTo('guide.php?id=' . $savedId);
} catch (Throwable $exception) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
    if ($newThumbnail !== null) {
        deleteManagedGuideUpload($newThumbnail);
    }
    addFlashMessage('error', $exception instanceof GuideException ? $exception->getMessage() : 'The guide could not be saved.');
    redirectTo('guide-edit.php' . ($guideId ? '?id=' . $guideId : ''));
}
