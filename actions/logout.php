<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

requireAuth(true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    renderErrorPage(405, 'Method not allowed', 'Sign out using the account navigation form.');
}

requireValidCsrfToken($_POST['csrf_token'] ?? null);

clearEntireSession();
addFlashMessage('success', 'You have signed out.');
redirectTo('login.php');
