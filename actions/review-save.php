<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    renderErrorPage(405, 'Method not allowed', 'Reviews must be submitted from a game page.');
}
requireValidCsrfToken($_POST['csrf_token'] ?? null);

$rawGameId = $_POST['game_id'] ?? '';
$rawRating = $_POST['rating'] ?? '';
$body = $_POST['body'] ?? '';
if (!is_string($rawGameId) || !ctype_digit($rawGameId) || (int) $rawGameId < 1) {
    renderErrorPage(404, 'Game not found', 'The submitted game identifier is invalid.');
}
if (!is_string($rawRating) || !ctype_digit($rawRating) || !is_string($body)) {
    addFlashMessage('warning', 'Provide a whole-number rating from 1 to 10 and valid review text.');
    redirectTo('game.php?id=' . (int) $rawGameId . '#reviews');
}

try {
    $result = saveGameReview(db(), (int) currentUser()['id'], (int) $rawGameId, (int) $rawRating, $body);
    addFlashMessage('success', $result === 'updated' ? 'Your review was updated.' : 'Your review was published.');
} catch (LibraryException $exception) {
    addFlashMessage('warning', $exception->getMessage());
}
redirectTo('game.php?id=' . (int) $rawGameId . '#reviews');
