<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/config/bootstrap.php';
requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    renderErrorPage(405, 'Method not allowed', 'Wallet adjustments must be submitted from the administration form.');
}
requireValidCsrfToken($_POST['csrf_token'] ?? null);
$userId = communityPositiveInt($_POST['user_id'] ?? null);
if ($userId === null) {
    renderErrorPage(404, 'User not found', 'The submitted user identifier is invalid.');
}
try {
    $result = adjustUserWallet(db(), (int) currentUser()['id'], $userId, $_POST['amount'] ?? null, (string) ($_POST['reason'] ?? ''));
    addFlashMessage('success', 'Wallet adjusted. New balance: ' . gamePriceLabel($result['balance_after']) . '.');
} catch (AdminException $exception) {
    addFlashMessage('warning', $exception->getMessage());
}
redirectTo('admin/users.php');
