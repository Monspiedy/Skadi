<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    renderErrorPage(405, 'Method not allowed', 'Checkout must be started from the cart page.');
}
requireValidCsrfToken($_POST['csrf_token'] ?? null);

try {
    $result = checkoutCart(db(), (int) currentUser()['id']);
    addFlashMessage(
        'success',
        'Order ' . $result['order_number'] . ' completed for ' . commercePriceLabel($result['total']) . '.'
    );
    redirectTo('orders.php');
} catch (CommerceException | WeekendSaleException $exception) {
    addFlashMessage('warning', $exception->getMessage());
    redirectTo('cart.php');
}
