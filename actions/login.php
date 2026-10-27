<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

requireGuest();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    renderErrorPage(405, 'Method not allowed', 'Sign-in details must be submitted from the login form.');
}

requireValidCsrfToken($_POST['csrf_token'] ?? null);

$identifier = trim((string) ($_POST['identifier'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$validInput = $identifier !== ''
    && strlen($identifier) <= 254
    && $password !== ''
    && strlen($password) <= 72;
$result = $validInput
    ? authenticateUser(db(), $identifier, $password)
    : ['status' => 'invalid'];

if ($result['status'] !== 'authenticated') {
    $_SESSION['login_form'] = [
        'identifier' => strlen($identifier) <= 254 ? $identifier : '',
    ];
    $_SESSION['login_errors'] = [
        'credentials' => $result['status'] === 'unavailable'
            ? 'This account is not available for sign-in.'
            : 'The login name/email or password is incorrect.',
    ];
    redirectTo('login.php');
}

setAuthenticatedUser($result['user']);

if (!empty($result['user']['must_change_password'])) {
    addFlashMessage('warning', 'Change the temporary password before continuing.');
    redirectTo('change-password.php');
}

addFlashMessage('success', 'Welcome back, ' . $result['user']['display_name'] . '.');
redirectTo('account.php');
