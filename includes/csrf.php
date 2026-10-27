<?php

declare(strict_types=1);

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfInput(): string
{
    return '<input type="hidden" name="csrf_token" value="' . escape(csrfToken()) . '">';
}

function isValidCsrfToken(?string $submittedToken): bool
{
    $sessionToken = $_SESSION['csrf_token'] ?? null;

    return is_string($sessionToken)
        && is_string($submittedToken)
        && hash_equals($sessionToken, $submittedToken);
}

function requireValidCsrfToken(?string $submittedToken): void
{
    if (!isValidCsrfToken($submittedToken)) {
        renderErrorPage(403, 'Request rejected', 'This form expired or did not come from Skadi.');
    }
}
