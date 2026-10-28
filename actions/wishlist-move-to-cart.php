<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    renderErrorPage(405, 'Method not allowed', 'Wishlist items must be moved from the wishlist page.');
}
requireValidCsrfToken($_POST['csrf_token'] ?? null);

$rawGameId = $_POST['game_id'] ?? '';
if (!is_string($rawGameId) || !ctype_digit($rawGameId) || (int) $rawGameId < 1) {
    renderErrorPage(404, 'Game not found', 'The submitted game identifier is invalid.');
}

try {
    moveWishlistGameToCart(db(), (int) currentUser()['id'], (int) $rawGameId);
    addFlashMessage('success', 'The game was moved from your wishlist to your cart.');
    redirectTo('cart.php');
} catch (LibraryException $exception) {
    addFlashMessage('warning', $exception->getMessage());
    redirectTo('wishlist.php');
}
