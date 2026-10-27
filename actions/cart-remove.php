<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    renderErrorPage(405, 'Method not allowed', 'Cart items must be removed from the cart page.');
}
requireValidCsrfToken($_POST['csrf_token'] ?? null);

$rawGameId = $_POST['game_id'] ?? '';
if (!is_string($rawGameId) || !ctype_digit($rawGameId) || (int) $rawGameId < 1) {
    renderErrorPage(404, 'Game not found', 'The submitted game identifier is invalid.');
}

$removed = removeGameFromCart(db(), (int) currentUser()['id'], (int) $rawGameId);
addFlashMessage($removed ? 'success' : 'info', $removed ? 'The game was removed from your cart.' : 'That game was not in your cart.');
redirectTo('cart.php');
