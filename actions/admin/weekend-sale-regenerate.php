<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/config/bootstrap.php';

requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    renderErrorPage(405, 'Method not allowed', 'Weekend sale regeneration must be submitted from the administration form.');
}
requireValidCsrfToken($_POST['csrf_token'] ?? null);

if (($_POST['confirm_regeneration'] ?? '') !== 'yes') {
    addFlashMessage('warning', 'Confirm the regeneration warning before replacing the active sale.');
    redirectTo('admin/weekend-sale.php');
}

try {
    $result = regenerateCurrentWeekendSale(db(), (int) currentUser()['id']);
    addFlashMessage(
        'success',
        'Weekend sale generation #' . $result['generation_number'] . ' created with '
        . $result['discount_count'] . ' shared discounts.'
    );
} catch (WeekendSaleException $exception) {
    addFlashMessage('warning', $exception->getMessage());
}

redirectTo('admin/weekend-sale.php');
