<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

requireGuest();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    renderErrorPage(405, 'Method not allowed', 'Registration must be submitted from the account form.');
}

requireValidCsrfToken($_POST['csrf_token'] ?? null);

$input = normalizedRegistrationInput($_POST);
$safeForm = array_intersect_key($input, array_flip(['login_name', 'email', 'display_name']));
$errors = registrationErrors($input);

if ($errors === []) {
    $errors = registrationAvailabilityErrors(db(), $input);
}

if ($errors !== []) {
    $_SESSION['registration_form'] = $safeForm;
    $_SESSION['registration_errors'] = $errors;
    redirectTo('register.php');
}

try {
    $user = registerUser(db(), $input);
} catch (PDOException $exception) {
    if ($exception->getCode() === '23000') {
        $_SESSION['registration_form'] = $safeForm;
        $_SESSION['registration_errors'] = [
            'login_name' => 'The login name or email became unavailable. Please choose different details.',
            'email' => 'The login name or email became unavailable. Please choose different details.',
        ];
        redirectTo('register.php');
    }

    throw $exception;
}

setAuthenticatedUser($user);
addFlashMessage('success', 'Account created. Your EUR 100.00 demonstration balance is ready.');
redirectTo('account.php');
