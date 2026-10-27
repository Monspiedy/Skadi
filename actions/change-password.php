<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

requireAuth(true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    renderErrorPage(405, 'Method not allowed', 'Password changes must be submitted from the account form.');
}

requireValidCsrfToken($_POST['csrf_token'] ?? null);

$currentPassword = (string) ($_POST['current_password'] ?? '');
$newPassword = (string) ($_POST['new_password'] ?? '');
$confirmation = (string) ($_POST['new_password_confirmation'] ?? '');
$errors = [];

if ($newPassword !== $confirmation) {
    $errors['new_password_confirmation'] = 'Password confirmation must match the new password.';
}

if ($errors === []) {
    $errors = changeUserPassword(db(), (int) currentUser()['id'], $currentPassword, $newPassword);
}

if ($errors !== []) {
    $_SESSION['password_change_errors'] = $errors;
    redirectTo('change-password.php');
}

$user = findAuthenticationUserById(db(), (int) currentUser()['id']);
setAuthenticatedUser($user);
addFlashMessage('success', 'Your password was changed.');
redirectTo('account.php');
