<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';
requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    renderErrorPage(405, 'Method not allowed', 'Guides must be deleted from their guide page.');
}
requireValidCsrfToken($_POST['csrf_token'] ?? null);
$guideId = filter_var($_POST['guide_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
try {
    $thumbnail = deleteGuide(db(), (int) currentUser()['id'], $guideId);
    if (is_string($thumbnail)) {
        deleteManagedGuideUpload($thumbnail);
    }
    addFlashMessage('success', 'Guide deleted.');
    redirectTo('guides.php');
} catch (GuideException $exception) {
    addFlashMessage('error', $exception->getMessage());
    redirectTo('guide.php?id=' . $guideId);
}
