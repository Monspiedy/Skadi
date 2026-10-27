<?php

declare(strict_types=1);

function requiredError(string $value, string $label): ?string
{
    return trim($value) === '' ? $label . ' is required.' : null;
}

function emailError(string $value, string $label = 'Email'): ?string
{
    return filter_var($value, FILTER_VALIDATE_EMAIL) === false
        ? $label . ' must be a valid email address.'
        : null;
}

function lengthError(string $value, string $label, int $minimum, int $maximum): ?string
{
    $length = mb_strlen($value);

    if ($length < $minimum || $length > $maximum) {
        return sprintf('%s must be between %d and %d characters.', $label, $minimum, $maximum);
    }

    return null;
}

function collectErrors(?string ...$errors): array
{
    return array_values(array_filter($errors, static fn (?string $error): bool => $error !== null));
}

function loginNameError(string $value): ?string
{
    if (preg_match('/\A[A-Za-z0-9_]{3,50}\z/', $value) !== 1) {
        return 'Login name must be 3-50 characters using only letters, numbers and underscores.';
    }

    return null;
}

function displayNameError(string $value): ?string
{
    $lengthError = lengthError($value, 'Display name', 2, 80);

    if ($lengthError !== null) {
        return $lengthError;
    }

    if (preg_match('/[\p{C}]/u', $value) === 1) {
        return 'Display name contains an unsupported control character.';
    }

    return null;
}

function passwordError(string $value, string $label = 'Password'): ?string
{
    $byteLength = strlen($value);

    if ($byteLength < 12 || $byteLength > 72) {
        return $label . ' must be between 12 and 72 bytes.';
    }

    if (
        preg_match('/[A-Z]/', $value) !== 1
        || preg_match('/[a-z]/', $value) !== 1
        || preg_match('/[0-9]/', $value) !== 1
        || preg_match('/[^A-Za-z0-9]/', $value) !== 1
    ) {
        return $label . ' must include uppercase, lowercase, number and symbol characters.';
    }

    return null;
}

function fieldError(array $errors, string $field): ?string
{
    $error = $errors[$field] ?? null;

    return is_string($error) ? $error : null;
}
