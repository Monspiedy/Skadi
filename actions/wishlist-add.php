<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    renderErrorPage(405, 'Method not allowed', 'Wishlist items must be added from a game form.');
}
requireValidCsrfToken($_POST['csrf_token'] ?? null);

$rawGameId = $_POST['game_id'] ?? '';
if (!is_string($rawGameId) || !ctype_digit($rawGameId) || (int) $rawGameId < 1) {
    renderErrorPage(404, 'Game not found', 'The submitted game identifier is invalid.');
}

try {
    addGameToWishlist(db(), (int) currentUser()['id'], (int) $rawGameId);
    addFlashMessage('success', 'The game was added to your wishlist.');
} catch (LibraryException $exception) {
    addFlashMessage('warning', $exception->getMessage());
}
redirectTo('wishlist.php');
