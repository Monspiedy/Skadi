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
