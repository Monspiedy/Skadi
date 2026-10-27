<?php

declare(strict_types=1);

function currentUser(): ?array
{
    $user = $_SESSION['authenticated_user'] ?? null;

    return is_array($user) ? $user : null;
}

function isAuthenticated(): bool
{
    return currentUser() !== null;
}

function userHasRole(string $role): bool
{
    return isAuthenticated() && (currentUser()['role'] ?? null) === $role;
}

function requireAuthentication(): void
{
    if (!isAuthenticated()) {
        renderErrorPage(403, 'Sign in required', 'You must sign in before using this feature.');
    }
}

function requireRole(string $role): void
{
    requireAuthentication();

    if (!userHasRole($role)) {
        renderErrorPage(403, 'Access denied', 'Your account does not have permission to view this page.');
    }
}
