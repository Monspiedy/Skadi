<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    renderErrorPage(405, 'Method not allowed', 'Games must be added from a Skadi store form.');
}
requireValidCsrfToken($_POST['csrf_token'] ?? null);

$rawGameId = $_POST['game_id'] ?? '';
if (!is_string($rawGameId) || !ctype_digit($rawGameId) || (int) $rawGameId < 1) {
    renderErrorPage(404, 'Game not found', 'The submitted game identifier is invalid.');
}

try {
    addGameToCart(db(), (int) currentUser()['id'], (int) $rawGameId);
    addFlashMessage('success', 'The game was added to your cart at the current server price.');
} catch (CommerceException | WeekendSaleException $exception) {
    addFlashMessage('warning', $exception->getMessage());
}
redirectTo('cart.php');
