<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';
requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    renderErrorPage(405, 'Method not allowed', 'Comments must be deleted from a guide page.');
}
requireValidCsrfToken($_POST['csrf_token'] ?? null);
$commentId = filter_var($_POST['comment_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
try {
    $guideId = deleteGuideComment(db(), (int) currentUser()['id'], $commentId);
    addFlashMessage('success', 'Comment deleted.');
    redirectTo('guide.php?id=' . $guideId . '#comments-heading');
} catch (GuideException $exception) {
    addFlashMessage('error', $exception->getMessage());
    redirectTo('guides.php');
}
