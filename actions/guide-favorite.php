<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';
requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    renderErrorPage(405, 'Method not allowed', 'Favorites require a form submission.');
}
requireValidCsrfToken($_POST['csrf_token'] ?? null);
$guideId = filter_var($_POST['guide_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
try {
    $added = toggleGuideFavorite(db(), (int) currentUser()['id'], $guideId);
    addFlashMessage('success', $added ? 'Guide added to favorites.' : 'Guide removed from favorites.');
} catch (GuideException $exception) {
    addFlashMessage('error', $exception->getMessage());
}
redirectTo('guide.php?id=' . $guideId);
