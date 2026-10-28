<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    renderErrorPage(405, 'Method not allowed', 'Reviews must be deleted from a game page.');
}
requireValidCsrfToken($_POST['csrf_token'] ?? null);

$rawGameId = $_POST['game_id'] ?? '';
if (!is_string($rawGameId) || !ctype_digit($rawGameId) || (int) $rawGameId < 1) {
    renderErrorPage(404, 'Game not found', 'The submitted game identifier is invalid.');
}

$deleted = deleteGameReview(db(), (int) currentUser()['id'], (int) $rawGameId);
addFlashMessage($deleted ? 'success' : 'info', $deleted ? 'Your review was deleted.' : 'No active review was found.');
redirectTo('game.php?id=' . (int) $rawGameId . '#reviews');
