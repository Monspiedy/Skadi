<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';
requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    renderErrorPage(405, 'Method not allowed', 'Guide votes require a form submission.');
}
requireValidCsrfToken($_POST['csrf_token'] ?? null);
$guideId = filter_var($_POST['guide_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$vote = filter_var($_POST['vote'] ?? null, FILTER_VALIDATE_INT);
try {
    setGuideVote(db(), (int) currentUser()['id'], $guideId, is_int($vote) ? $vote : 0);
    addFlashMessage('success', 'Your vote was saved.');
} catch (GuideException $exception) {
    addFlashMessage('error', $exception->getMessage());
}
redirectTo('guide.php?id=' . $guideId);
