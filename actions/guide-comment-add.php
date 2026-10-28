<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';
requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    renderErrorPage(405, 'Method not allowed', 'Comments require a form submission.');
}
requireValidCsrfToken($_POST['csrf_token'] ?? null);
$guideId = filter_var($_POST['guide_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$body = is_string($_POST['body'] ?? null) ? $_POST['body'] : '';
try {
    addGuideComment(db(), (int) currentUser()['id'], $guideId, $body);
    addFlashMessage('success', 'Comment posted.');
} catch (GuideException $exception) {
    addFlashMessage('error', $exception->getMessage());
}
redirectTo('guide.php?id=' . $guideId . '#comments-heading');
